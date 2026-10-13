<?php
/**
 * หน้ารับกลับจาก Stripe Checkout: ตรวจสถานะการจ่ายกับ Stripe แล้วอัปเดตออเดอร์
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/stripe.php';

if (!isLoggedIn()) {
    redirect('/login.php');
}

$orderId = (int) ($_GET['order'] ?? 0);
$sessionId = (string) ($_GET['session_id'] ?? '');
$db = getDB();

$stmt = $db->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$orderId, getCurrentUserId()]);
$order = $stmt->fetch();

if (!$order || $sessionId === '' || $sessionId !== $order['stripe_session_id']) {
    setFlash('danger', 'ไม่พบข้อมูลการชำระเงินของคำสั่งซื้อนี้');
    redirect('/profile.php?tab=orders');
}

try {
    $session = \Stripe\Checkout\Session::retrieve($sessionId);
    $amountSatang = (int) round($order['total_amount'] * 100);

    if ($session->payment_status === 'paid'
        && (int) $session->amount_total === $amountSatang
        && (string) ($session->metadata->order_id ?? '') === (string) $orderId) {

        $stmt = $db->prepare("
            UPDATE orders SET payment_status = 'confirmed', status = 'processing'
            WHERE id = ? AND payment_status = 'pending' AND status != 'cancelled'
        ");
        $stmt->execute([$orderId]);
        setFlash('success', 'ชำระเงินเรียบร้อยแล้ว ขอบคุณที่ใช้บริการ');
    } else {
        setFlash('warning', 'ยังไม่พบการชำระเงิน หากชำระแล้วกรุณารอสักครู่ แล้วรีเฟรชหน้านี้');
    }
} catch (\Stripe\Exception\ApiErrorException $e) {
    error_log('Stripe return check error (order ' . $orderId . '): ' . $e->getMessage());
    setFlash('danger', 'ไม่สามารถตรวจสอบการชำระเงินได้ในขณะนี้ กรุณาลองใหม่อีกครั้ง');
}

redirect('/payment_pending.php?order=' . $orderId);
