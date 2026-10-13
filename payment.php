<?php
/**
 * Payment Page - หน้าชำระเงินสำหรับคำสั่งซื้อที่โอนเงิน (ชำระผ่าน Stripe Checkout หรือแจ้งสลิป)
 */
require_once 'config/database.php';

if (!isLoggedIn()) {
    redirect('/login.php');
}

$orderId = (int) ($_GET['order'] ?? 0);

if (!$orderId) {
    setFlash('danger', 'ไม่พบคำสั่งซื้อ');
    redirect('/profile.php');
}

$db = getDB();
$userId = getCurrentUserId();

$stmt = $db->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$orderId, $userId]);
$order = $stmt->fetch();

if (!$order) {
    setFlash('danger', 'ไม่พบคำสั่งซื้อ');
    redirect('/profile.php');
}

if ($order['payment_method'] !== 'transfer') {
    redirect('/profile.php');
}

$pageTitle = 'ชำระเงิน - คำสั่งซื้อ #' . $orderId;

require_once 'header.php';
?>

<section class="section">
    <div class="container">
        <div style="max-width: 500px; margin: 0 auto;">
            <div class="qr-code-container">
                <h2 class="mb-lg">ชำระเงิน</h2>

                <div class="qr-code-amount">
                    <?= formatPrice($order['total_amount']) ?>
                </div>

                <p class="text-muted mb-lg">คำสั่งซื้อ #
                    <?= str_pad($orderId, 6, '0', STR_PAD_LEFT) ?>
                </p>

                <?php if ($order['status'] === 'cancelled'): ?>
                    <div class="alert alert-danger">คำสั่งซื้อนี้ถูกยกเลิกแล้ว ไม่สามารถชำระเงินได้</div>
                <?php elseif ($order['payment_status'] !== 'pending'): ?>
                    <div class="alert alert-info">คำสั่งซื้อนี้ไม่ต้องชำระเงินเพิ่มแล้ว</div>
                <?php else: ?>
                    <a href="/stripe_checkout.php?order=<?= $orderId ?>" class="btn btn-primary btn-lg btn-block mb-lg">
                        <i class="fas fa-qrcode"></i> ชำระเงินด้วย PromptPay / บัตร
                    </a>
                    <p class="text-muted mb-lg" style="font-size: 0.875rem;">
                        ระบบจะพาไปหน้าชำระเงินที่ปลอดภัยของ Stripe และยืนยันการชำระให้อัตโนมัติ
                    </p>
                <?php endif; ?>
                <a href="/profile.php?tab=orders" class="btn btn-secondary btn-block mt-md">
                    <i class="fas fa-history"></i> ดูประวัติการสั่งซื้อ
                </a>
            </div>
        </div>
    </div>
</section>

<?php require_once 'footer.php'; ?>
