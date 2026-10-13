<?php
/**
 * Review Like/Dislike API
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
$reviewId = (int) ($_POST['review_id'] ?? 0);
$voteType = $_POST['vote_type'] ?? '';

if (!in_array($voteType, ['like', 'dislike'], true)) {
    echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ถูกต้อง']);
    exit;
}

$stmt = $db->prepare("SELECT id FROM product_reviews WHERE id = ?");
$stmt->execute([$reviewId]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'ไม่พบรีวิว']);
    exit;
}

$stmt = $db->prepare("SELECT id, vote_type FROM product_review_votes WHERE review_id = ? AND user_id = ?");
$stmt->execute([$reviewId, $userId]);
$existing = $stmt->fetch();

if ($existing && $existing['vote_type'] === $voteType) {
    // Click the same button again to undo the vote
    $db->prepare("DELETE FROM product_review_votes WHERE id = ?")->execute([$existing['id']]);
    $myVote = null;
} elseif ($existing) {
    $db->prepare("UPDATE product_review_votes SET vote_type = ? WHERE id = ?")->execute([$voteType, $existing['id']]);
    $myVote = $voteType;
} else {
    $db->prepare("INSERT INTO product_review_votes (review_id, user_id, vote_type) VALUES (?, ?, ?)")->execute([$reviewId, $userId, $voteType]);
    $myVote = $voteType;
}

$counts = $db->prepare("
    SELECT
        SUM(vote_type = 'like') AS likes,
        SUM(vote_type = 'dislike') AS dislikes
    FROM product_review_votes WHERE review_id = ?
");
$counts->execute([$reviewId]);
$row = $counts->fetch();

echo json_encode([
    'success' => true,
    'likes' => (int) $row['likes'],
    'dislikes' => (int) $row['dislikes'],
    'my_vote' => $myVote,
]);
