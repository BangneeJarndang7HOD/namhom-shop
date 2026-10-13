<?php
/**
 * Product Vote API - โหวต 4 แกน (ความติดทน, การกระจายกลิ่น, เพศ, ความคุ้มค่า)
 */
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบก่อน']);
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'เซสชันหมดอายุ กรุณารีเฟรชหน้าแล้วลองใหม่']);
    exit;
}

$allowedAxes = ['longevity' => 5, 'sillage' => 4, 'gender' => 5, 'price_value' => 5];

$productId = (int) ($_POST['product_id'] ?? 0);
$axis = $_POST['axis'] ?? '';
$value = (int) ($_POST['value'] ?? 0);

if (!isset($allowedAxes[$axis]) || $value < 1 || $value > $allowedAxes[$axis]) {
    echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ถูกต้อง']);
    exit;
}

$db = getDB();
$stmt = $db->prepare("SELECT id FROM products WHERE id = ?");
$stmt->execute([$productId]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'ไม่พบสินค้า']);
    exit;
}

// $axis is validated against a whitelist above, safe to interpolate as a column name
$stmt = $db->prepare("
    INSERT INTO product_reviews (product_id, user_id, $axis) VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE $axis = VALUES($axis)
");
$stmt->execute([$productId, getCurrentUserId(), $value]);

// Return updated distribution for this axis so the UI can redraw the bars
$stmt = $db->prepare("SELECT $axis AS v, COUNT(*) AS c FROM product_reviews WHERE product_id = ? AND $axis IS NOT NULL GROUP BY $axis");
$stmt->execute([$productId]);
$counts = array_fill(1, $allowedAxes[$axis], 0);
foreach ($stmt->fetchAll() as $row) {
    $counts[(int) $row['v']] = (int) $row['c'];
}

echo json_encode(['success' => true, 'counts' => $counts]);
