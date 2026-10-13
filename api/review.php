<?php
/**
 * Product Review API - เขียน/ลบรีวิวสินค้า
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

$db = getDB();
$userId = getCurrentUserId();
$action = $_POST['action'] ?? '';

if ($action === 'submit') {
    $productId = (int) ($_POST['product_id'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');

    $stmt = $db->prepare("SELECT id FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'ไม่พบสินค้า']);
        exit;
    }

    if ($comment === '') {
        echo json_encode(['success' => false, 'message' => 'กรุณากรอกข้อความรีวิว']);
        exit;
    }
    if (mb_strlen($comment) > 2000) {
        echo json_encode(['success' => false, 'message' => 'ข้อความรีวิวยาวเกินไป']);
        exit;
    }

    $sentiment = $_POST['sentiment'] ?? '';
    if (!in_array($sentiment, ['positive', 'neutral', 'negative'], true)) {
        echo json_encode(['success' => false, 'message' => 'กรุณาเลือกความรู้สึกโดยรวม']);
        exit;
    }

    $axes = ['longevity' => 5, 'sillage' => 4, 'gender' => 5, 'price_value' => 5];
    $axisValues = [];
    foreach ($axes as $axis => $max) {
        $raw = $_POST[$axis] ?? '';
        $axisValues[$axis] = ($raw !== '' && (int) $raw >= 1 && (int) $raw <= $max) ? (int) $raw : null;
    }

    $stmt = $db->prepare("
        INSERT INTO product_reviews (product_id, user_id, comment, sentiment, longevity, sillage, gender, price_value)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            comment = VALUES(comment),
            sentiment = VALUES(sentiment),
            longevity = COALESCE(VALUES(longevity), longevity),
            sillage = COALESCE(VALUES(sillage), sillage),
            gender = COALESCE(VALUES(gender), gender),
            price_value = COALESCE(VALUES(price_value), price_value)
    ");
    $stmt->execute([
        $productId, $userId, $comment, $sentiment,
        $axisValues['longevity'], $axisValues['sillage'], $axisValues['gender'], $axisValues['price_value'],
    ]);

    echo json_encode(['success' => true, 'message' => 'บันทึกรีวิวเรียบร้อยแล้ว']);
    exit;
}

if ($action === 'delete') {
    $reviewId = (int) ($_POST['review_id'] ?? 0);

    $stmt = $db->prepare("SELECT * FROM product_reviews WHERE id = ?");
    $stmt->execute([$reviewId]);
    $review = $stmt->fetch();

    if (!$review || ($review['user_id'] != $userId && !isAdmin())) {
        echo json_encode(['success' => false, 'message' => 'ไม่มีสิทธิ์ลบรีวิวนี้']);
        exit;
    }

    $hasAnyVote = $review['longevity'] || $review['sillage'] || $review['gender'] || $review['price_value'];

    if ($hasAnyVote) {
        // เก็บคะแนนโหวต 4 แกนไว้ ลบแค่ข้อความรีวิวกับความรู้สึก
        $stmt = $db->prepare("UPDATE product_reviews SET comment = NULL, sentiment = NULL WHERE id = ?");
        $stmt->execute([$reviewId]);
    } else {
        $stmt = $db->prepare("DELETE FROM product_reviews WHERE id = ?");
        $stmt->execute([$reviewId]);
    }

    echo json_encode(['success' => true, 'message' => 'ลบรีวิวเรียบร้อยแล้ว']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
