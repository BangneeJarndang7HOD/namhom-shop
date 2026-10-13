<?php
/**
 * เริ่มหน้าชำระเงินของ Stripe Checkout สำหรับออเดอร์ที่โอนเงิน (PromptPay)
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/stripe.php';

if (!isLoggedIn()) {
    redirect('/login.php');
}

$orderId = (int) ($_GET['order'] ?? 0);
$db = getDB();

$stmt = $db->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$orderId, getCurrentUserId()]);
$order = $stmt->fetch();

if (!$order || $order['payment_method'] !== 'transfer' || $order['payment_status'] !== 'pending' || $order['status'] === 'cancelled') {
    setFlash('danger', 'ไม่พบคำสั่งซื้อที่ต้องชำระเงิน');
    redirect('/profile.php?tab=orders');
}

$amountSatang = (int) round($order['total_amount'] * 100);
$baseUrl = 'http' . (!empty($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] ;

try {
    $session = null;
    if (!empty($order['stripe_session_id'])) {
        $existing = \Stripe\Checkout\Session::retrieve($order['stripe_session_id']);
        if ($existing->status === 'open' && $existing->amount_total === $amountSatang) {
            $session = $existing;
        }
    }

    if ($session === null) {
        $session = \Stripe\Checkout\Session::create([
            'mode' => 'payment',
            'payment_method_types' => ['card', 'promptpay'],
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => 'thb',
                    'unit_amount' => $amountSatang,
                    'product_data' => ['name' => 'ParfumLuxe คำสั่งซื้อ #' . str_pad($orderId, 6, '0', STR_PAD_LEFT)],
                ],
            ]],
            'client_reference_id' => (string) $orderId,
            'metadata' => ['order_id' => (string) $orderId],
            'success_url' => $baseUrl . '/payment_return.php?order=' . $orderId . '&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $baseUrl . '/payment.php?order=' . $orderId,
        ]);

        $stmt = $db->prepare("UPDATE orders SET stripe_session_id = ? WHERE id = ?");
        $stmt->execute([$session->id, $orderId]);
    }

    header('Location: ' . $session->url);
    exit;
} catch (\Stripe\Exception\ApiErrorException $e) {
    error_log('Stripe checkout error (order ' . $orderId . '): ' . $e->getMessage());
    setFlash('danger', 'ไม่สามารถเปิดหน้าชำระเงินได้ในขณะนี้ กรุณาลองใหม่อีกครั้ง');
    redirect('/payment.php?order=' . $orderId);
}
