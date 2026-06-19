<?php
require_once '../includes/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek']);
    exit;
}

$action     = isset($_POST['action']) ? trim($_POST['action']) : '';
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$quantity   = isset($_POST['quantity']) ? max(1, (int)$_POST['quantity']) : 1;

if (!$product_id) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz ürün']);
    exit;
}

// Ürün kontrolü
$prod_stmt = $conn->prepare("SELECT product_id, stock_quantity FROM products WHERE product_id = ?");
$prod_stmt->bind_param("i", $product_id);
$prod_stmt->execute();
$product = $prod_stmt->get_result()->fetch_assoc();
if (!$product) {
    echo json_encode(['success' => false, 'message' => 'Ürün bulunamadı']);
    exit;
}

if ($action === 'add') {
    if (is_logged_in()) {
        $user_id = (int)$_SESSION['user_id'];
        // Sepet var mı?
        $cart_stmt = $conn->prepare("SELECT cart_id FROM cart WHERE user_id = ?");
        $cart_stmt->bind_param("i", $user_id);
        $cart_stmt->execute();
        $cart = $cart_stmt->get_result()->fetch_assoc();
        if (!$cart) {
            $nc = $conn->prepare("INSERT INTO cart (user_id) VALUES (?)");
            $nc->bind_param("i", $user_id);
            $nc->execute();
            $cart_id = $conn->insert_id;
        } else {
            $cart_id = $cart['cart_id'];
        }
        // Ürün zaten sepette mi?
        $ci = $conn->prepare("SELECT cart_item_id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ?");
        $ci->bind_param("ii", $cart_id, $product_id);
        $ci->execute();
        $item = $ci->get_result()->fetch_assoc();
        if ($item) {
            $new_qty = min($item['quantity'] + $quantity, $product['stock_quantity']);
            $upd = $conn->prepare("UPDATE cart_items SET quantity = ? WHERE cart_item_id = ?");
            $upd->bind_param("ii", $new_qty, $item['cart_item_id']);
            $upd->execute();
        } else {
            $ins = $conn->prepare("INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (?, ?, ?)");
            $ins->bind_param("iii", $cart_id, $product_id, $quantity);
            $ins->execute();
        }
        // Sepet sayısı
        $cc = $conn->prepare("SELECT COALESCE(SUM(ci.quantity),0) as total FROM cart c JOIN cart_items ci ON c.cart_id=ci.cart_id WHERE c.user_id=?");
        $cc->bind_param("i", $user_id);
        $cc->execute();
        $cart_count = (int)$cc->get_result()->fetch_assoc()['total'];
    } else {
        if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
        $cur = $_SESSION['cart'][$product_id] ?? 0;
        $_SESSION['cart'][$product_id] = min($cur + $quantity, $product['stock_quantity']);
        $cart_count = array_sum($_SESSION['cart']);
    }
    echo json_encode(['success' => true, 'message' => 'Sepete eklendi!', 'cart_count' => $cart_count]);
}
