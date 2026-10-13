<?php
/**
 * Stripe Webhook - รับ event การชำระเงินจาก Stripe แล้วยืนยันออเดอร์อัตโนมัติ
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/stripe.php';

$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

try {
    $event = \Stripe\Webhook::constructEvent($payload, $signature, STRIPE_WEBHOOK_SECRET);
} catch (\Exception $e) {
    http_response_code(400);
    exit;
}

if ($event->type === 'payment_intent.succeeded') {
    $intent = $event->data->object;
    $orderId = (int) ($intent->metadata->order_id ?? 0);

    if ($orderId) {
        $db = getDB();
        $stmt = $db->prepare("
            UPDATE orders
            SET payment_status = 'confirmed', status = 'processing'
            WHERE id = ? AND payment_intent_id = ? AND payment_status = 'pending' AND ROUND(total_amount * 100) = ?
        ");
        $stmt->execute([$orderId, $intent->id, $intent->amount_received]);
    }
}

http_response_code(200);
echo 'ok';
