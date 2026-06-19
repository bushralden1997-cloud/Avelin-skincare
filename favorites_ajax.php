<?php
require_once '../includes/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek']);
    exit;
}

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Giriş yapmanız gerekiyor', 'redirect' => 'login.php']);
    exit;
}

$user_id    = (int)$_SESSION['user_id'];
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$action     = isset($_POST['action']) ? trim($_POST['action']) : '';

if (!$product_id || !in_array($action, ['add', 'remove'])) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz parametre']);
    exit;
}

if ($action === 'add') {
    $stmt = $conn->prepare("INSERT IGNORE INTO favorites (user_id, product_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $user_id, $product_id);
    $stmt->execute();
    $msg = 'Favorilere eklendi!';
} else {
    $stmt = $conn->prepare("DELETE FROM favorites WHERE user_id = ? AND product_id = ?");
    $stmt->bind_param("ii", $user_id, $product_id);
    $stmt->execute();
    $msg = 'Favorilerden kaldırıldı.';
}

$count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM favorites WHERE user_id = ?");
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$fav_count = (int)$count_stmt->get_result()->fetch_assoc()['total'];

echo json_encode(['success' => true, 'message' => $msg, 'fav_count' => $fav_count]);
