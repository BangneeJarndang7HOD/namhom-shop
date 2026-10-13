<?php
/**
 * Payment Pending - หน้ารอยืนยันการชำระเงิน
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

// Get order
$stmt = $db->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$orderId, $userId]);
$order = $stmt->fetch();

if (!$order) {
    setFlash('danger', 'ไม่พบคำสั่งซื้อ');
    redirect('/profile.php');
}

$pageTitle = 'รอยืนยันการชำระเงิน - ParfumLuxe';

// Get order items
$stmt = $db->prepare("
    SELECT oi.*, p.name, p.image
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$stmt->execute([$orderId]);
$orderItems = $stmt->fetchAll();

require_once 'header.php';
?>

<section class="pending-container section">
    <div class="container">
        <div class="pending-box fade-in">
            <?php if ($order['payment_status'] === 'pending'): ?>
                <div class="pending-icon" style="background: rgba(245, 158, 11, 0.2);">
                    <i class="fas fa-clock" style="color: var(--warning);"></i>
                </div>

                <h2 class="pending-title">รอยืนยันการชำระเงิน</h2>

                <p class="pending-description">
                    ขอบคุณสำหรับการสั่งซื้อ! ทางเราได้รับคำสั่งซื้อของคุณแล้ว<br>
                    กรุณารอสักครู่ขณะที่เราตรวจสอบการชำระเงินของคุณ
                </p>

            <?php elseif ($order['payment_status'] === 'confirmed'): ?>
                <div class="pending-icon" style="background: rgba(16, 185, 129, 0.2); animation: none;">
                    <i class="fas fa-check-circle" style="color: var(--success);"></i>
                </div>

                <h2 class="pending-title" style="color: var(--success);">ยืนยันการชำระเงินแล้ว!</h2>

                <p class="pending-description">
                    การชำระเงินของคุณได้รับการยืนยันเรียบร้อยแล้ว<br>
                    คำสั่งซื้อของคุณกำลังดำเนินการจัดส่ง
                </p>

            <?php else: ?>
                <div class="pending-icon" style="background: rgba(239, 68, 68, 0.2); animation: none;">
                    <i class="fas fa-times-circle" style="color: var(--danger);"></i>
                </div>

                <h2 class="pending-title" style="color: var(--danger);">การชำระเงินล้มเหลว</h2>

                <p class="pending-description">
                    ขออภัย การชำระเงินของคุณไม่สำเร็จ<br>
                    กรุณาติดต่อเจ้าหน้าที่หรือลองชำระเงินใหม่อีกครั้ง
                </p>
            <?php endif; ?>

            <div class="pending-order-id">
                หมายเลขคำสั่งซื้อ: #
                <?= str_pad($orderId, 6, '0', STR_PAD_LEFT) ?>
            </div>

            <!-- Order Summary Card -->
            <div class="card text-left mb-lg">
                <div class="card-body">
                    <h4 class="mb-md">รายการสินค้า</h4>
                    <?php foreach ($orderItems as $item): ?>
                        <div class="d-flex gap-md align-center mb-md">
                            <div
                                style="width: 50px; height: 50px; border-radius: var(--radius-sm); overflow: hidden; flex-shrink: 0; background: var(--gray-200);">
                                <?php if ($item['image']): ?>
                                    <img src="/uploads/products/<?= htmlspecialchars($item['image']) ?>" alt=""
                                        style="width: 100%; height: 100%; object-fit: cover;"
                                        onerror="this.src='/images/placeholder.jpg'">
                                <?php endif; ?>
                            </div>
                            <div style="flex: 1;">
                                <p style="font-weight: 500;">
                                    <?= htmlspecialchars($item['name']) ?>
                                    <?php if ($item['variant_label']): ?> (<?= htmlspecialchars($item['variant_label']) ?>)<?php endif; ?>
                                </p>
                                <p class="text-muted" style="font-size: 0.875rem;">x
                                    <?= $item['quantity'] ?>
                                </p>
                            </div>
                            <div style="font-weight: 600; color: var(--primary);">
                                <?= formatPrice($item['price'] * $item['quantity']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <hr style="margin: var(--space-md) 0;">

                    <div class="d-flex justify-between">
                        <strong>ยอดรวมทั้งสิ้น</strong>
                        <strong style="color: var(--primary); font-size: 1.25rem;">
                            <?= formatPrice($order['total_amount']) ?>
                        </strong>
                    </div>
                </div>
            </div>

            <div class="alert alert-info" style="text-align: left;">
                <i class="fas fa-info-circle"></i>
                <div>
                    <strong>หมายเหตุ:</strong><br>
                    ทางเราจะตรวจสอบการชำระเงินและอัพเดทสถานะภายใน 1-2 ชั่วโมง
                    (ในเวลาทำการ 09:00 - 21:00 น.)<br>
                    <strong>ไม่ต้องแนบสลิปโอนเงิน</strong> - ระบบจะตรวจสอบยอดเงินเข้าอัตโนมัติ
                </div>
            </div>

            <div class="d-flex gap-md mt-lg">
                <a href="/profile.php?tab=orders" class="btn btn-primary" style="flex: 1;">
                    <i class="fas fa-history"></i> ดูประวัติการสั่งซื้อ
                </a>
                <a href="/" class="btn btn-secondary" style="flex: 1;">
                    <i class="fas fa-home"></i> กลับหน้าแรก
                </a>
            </div>
        </div>
    </div>
</section>

<?php if ($order['payment_status'] === 'pending'): ?>
    <script>
        // Auto refresh page every 30 seconds to check status
        setTimeout(() => {
            location.reload();
        }, 30000);
    </script>
<?php endif; ?>

<?php require_once 'footer.php'; ?>