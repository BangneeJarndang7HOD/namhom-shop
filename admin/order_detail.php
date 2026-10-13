<?php
/**
 * Admin Order Detail - รายละเอียดคำสั่งซื้อ (Admin)
 */
require_once __DIR__ . '/../config/database.php';

if (!isAdmin()) {
    redirect('/');
}

$orderId = (int) ($_GET['id'] ?? 0);
$db = getDB();

// Get order
$stmt = $db->prepare("
    SELECT o.*, u.username, u.email, u.full_name
    FROM orders o
    JOIN users u ON o.user_id = u.id
    WHERE o.id = ?
");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    setFlash('danger', 'ไม่พบคำสั่งซื้อ');
    redirect('/admin/orders.php');
}

// Get order items
$stmt = $db->prepare("
    SELECT oi.*, p.name, p.image, p.brand
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$stmt->execute([$orderId]);
$orderItems = $stmt->fetchAll();

$pageTitle = 'คำสั่งซื้อ #' . str_pad($orderId, 6, '0', STR_PAD_LEFT) . ' - Admin';
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?= $pageTitle ?>
    </title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="icon" type="image/png" sizes="32x32" href="/images/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/images/favicon-16.png">
    <link rel="apple-touch-icon" href="/images/apple-touch-icon.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>
    <aside class="admin-sidebar">
        <div class="admin-sidebar-brand">
            <a href="/admin/">ParfumLuxe</a>
            <small style="display: block; color: var(--gray-500); font-size: 0.75rem;">Admin Panel</small>
        </div>

        <nav class="admin-nav">
            <a href="/admin/"><i class="fas fa-chart-line"></i><span>แดชบอร์ด</span></a>
            <a href="/admin/products.php"><i class="fas fa-box"></i><span>จัดการสินค้า</span></a>
            <a href="/admin/orders.php" class="active"><i
                    class="fas fa-shopping-cart"></i><span>คำสั่งซื้อ</span></a>
            <a href="/admin/categories.php"><i class="fas fa-tags"></i><span>หมวดหมู่</span></a>
            <hr style="border-color: var(--gray-700); margin: var(--space-lg) 0;">
            <a href="/"><i class="fas fa-globe"></i><span>ไปยังหน้าเว็บ</span></a>
            <a href="/logout.php"><i class="fas fa-sign-out-alt"></i><span>ออกจากระบบ</span></a>
        </nav>
    </aside>

    <main class="admin-main">
        <a href="/admin/orders.php" class="btn btn-secondary mb-lg">
            <i class="fas fa-arrow-left"></i> กลับ
        </a>

        <div class="admin-header">
            <h1>คำสั่งซื้อ #
                <?= str_pad($orderId, 6, '0', STR_PAD_LEFT) ?>
            </h1>
        </div>

        <div class="grid" style="grid-template-columns: 2fr 1fr; gap: var(--space-xl);">
            <!-- Order Items -->
            <div>
                <div class="admin-card">
                    <h3 class="mb-lg">รายการสินค้า</h3>

                    <?php foreach ($orderItems as $item): ?>
                        <div class="d-flex gap-lg align-center mb-lg pb-lg"
                            style="border-bottom: 1px solid var(--gray-200);">
                            <div
                                style="width: 80px; height: 80px; border-radius: var(--radius-md); overflow: hidden; background: var(--gray-200);">
                                <?php if ($item['image']): ?>
                                    <img src="/uploads/products/<?= htmlspecialchars($item['image']) ?>" alt=""
                                        style="width: 100%; height: 100%; object-fit: cover;"
                                        onerror="this.src='/images/placeholder.jpg'">
                                <?php endif; ?>
                            </div>
                            <div style="flex: 1;">
                                <p class="text-muted">
                                    <?= htmlspecialchars($item['brand']) ?>
                                </p>
                                <h4>
                                    <?= htmlspecialchars($item['name']) ?>
                                    <?php if ($item['variant_label']): ?>
                                        <span class="status-badge status-processing"><?= htmlspecialchars($item['variant_label']) ?></span>
                                    <?php endif; ?>
                                </h4>
                                <p class="text-muted">จำนวน:
                                    <?= $item['quantity'] ?> ×
                                    <?= formatPrice($item['price']) ?>
                                </p>
                            </div>
                            <div style="font-size: 1.25rem; font-weight: 600; color: var(--primary);">
                                <?= formatPrice($item['price'] * $item['quantity']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Customer Info -->
                <div class="admin-card mt-lg">
                    <h3 class="mb-lg">ข้อมูลลูกค้า</h3>
                    <p><strong>ชื่อผู้ใช้:</strong>
                        <?= htmlspecialchars($order['username']) ?>
                    </p>
                    <p><strong>อีเมล:</strong>
                        <?= htmlspecialchars($order['email']) ?>
                    </p>
                    <p><strong>ชื่อ:</strong>
                        <?= htmlspecialchars($order['full_name'] ?: '-') ?>
                    </p>
                </div>

                <!-- Shipping Info -->
                <div class="admin-card mt-lg">
                    <h3 class="mb-lg">ข้อมูลการจัดส่ง</h3>
                    <p><strong>ที่อยู่:</strong><br>
                        <?= nl2br(htmlspecialchars($order['shipping_address'])) ?>
                    </p>
                    <p><strong>โทรศัพท์:</strong>
                        <?= htmlspecialchars($order['phone']) ?>
                    </p>
                    <?php if ($order['notes']): ?>
                        <p><strong>หมายเหตุ:</strong>
                            <?= htmlspecialchars($order['notes']) ?>
                        </p>
                    <?php endif; ?>
                </div>

                <!-- Payment Slip -->
                <?php if ($order['payment_method'] === 'transfer' && !empty($order['slip_image'])): ?>
                    <div class="admin-card mt-lg">
                        <h3 class="mb-lg">สลิปการโอนเงิน</h3>
                        <a href="/uploads/slips/<?= htmlspecialchars($order['slip_image']) ?>" target="_blank">
                            <img src="/uploads/slips/<?= htmlspecialchars($order['slip_image']) ?>" alt="สลิปโอนเงิน"
                                style="max-width: 100%; width: 300px; border-radius: var(--radius-md); border: 1px solid var(--gray-300);">
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Order Summary -->
            <div>
                <div class="cart-summary">
                    <h3>สรุปคำสั่งซื้อ</h3>

                    <div class="cart-summary-row">
                        <span>วันที่สั่งซื้อ</span>
                        <span>
                            <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?>
                        </span>
                    </div>

                    <div class="cart-summary-row">
                        <span>วิธีชำระเงิน</span>
                        <span>
                            <?= $order['payment_method'] === 'cod' ? 'ปลายทาง' : 'โอนเงิน' ?>
                        </span>
                    </div>

                    <div class="cart-summary-row">
                        <span>สถานะชำระเงิน</span>
                        <span class="status-badge status-<?= $order['payment_status'] ?>">
                            <?= match ($order['payment_status']) {
                                'pending' => 'รอตรวจสอบ',
                                'confirmed' => 'ยืนยันแล้ว',
                                'failed' => 'ไม่สำเร็จ',
                                default => $order['payment_status']
                            } ?>
                        </span>
                    </div>

                    <div class="cart-summary-row">
                        <span>สถานะคำสั่งซื้อ</span>
                        <span class="status-badge status-<?= $order['status'] ?>">
                            <?= match ($order['status']) {
                                'pending' => 'รอดำเนินการ',
                                'processing' => 'กำลังจัดเตรียม',
                                'shipped' => 'จัดส่งแล้ว',
                                'delivered' => 'ส่งสำเร็จ',
                                'cancelled' => 'ยกเลิก',
                                default => $order['status']
                            } ?>
                        </span>
                    </div>

                    <div class="cart-summary-row cart-summary-total">
                        <span>ยอดรวมทั้งสิ้น</span>
                        <span>
                            <?= formatPrice($order['total_amount']) ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>

</html>