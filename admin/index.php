<?php
/**
 * Admin Dashboard - แดชบอร์ดผู้ดูแลระบบ
 */
require_once __DIR__ . '/../config/database.php';

if (!isAdmin()) {
    setFlash('danger', 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้');
    redirect('/');
}

$pageTitle = 'Admin Dashboard - ParfumLuxe';
$db = getDB();

// Get statistics
$stats = [
    'products' => $db->query("SELECT COUNT(*) FROM products")->fetchColumn(),
    'orders' => $db->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
    'users' => $db->query("SELECT COUNT(*) FROM users WHERE is_admin = 0")->fetchColumn(),
    'pending_payments' => $db->query("SELECT COUNT(*) FROM orders WHERE payment_method = 'transfer' AND payment_status = 'pending'")->fetchColumn(),
    'revenue' => $db->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE payment_status = 'confirmed' OR payment_method = 'cod'")->fetchColumn(),
];

// Get recent orders
$stmt = $db->query("
    SELECT o.*, u.username 
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    ORDER BY o.created_at DESC 
    LIMIT 10
");
$recentOrders = $stmt->fetchAll();
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
    <!-- Admin Sidebar -->
    <aside class="admin-sidebar">
        <div class="admin-sidebar-brand">
            <a href="/admin/">ParfumLuxe</a>
            <small style="display: block; color: var(--gray-500); font-size: 0.75rem;">Admin Panel</small>
        </div>

        <nav class="admin-nav">
            <a href="/admin/" class="active">
                <i class="fas fa-chart-line"></i>
                <span>แดชบอร์ด</span>
            </a>
            <a href="/admin/products.php">
                <i class="fas fa-box"></i>
                <span>จัดการสินค้า</span>
            </a>
            <a href="/admin/orders.php">
                <i class="fas fa-shopping-cart"></i>
                <span>คำสั่งซื้อ</span>
                <?php if ($stats['pending_payments'] > 0): ?>
                    <span
                        style="background: var(--danger); color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.75rem; margin-left: auto;">
                        <?= $stats['pending_payments'] ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="/admin/categories.php">
                <i class="fas fa-tags"></i>
                <span>หมวดหมู่</span>
            </a>
            <a href="/admin/users.php">
                <i class="fas fa-users"></i>
                <span>ผู้ใช้งาน</span>
            </a>
            <hr style="border-color: var(--gray-700); margin: var(--space-lg) 0;">
            <a href="/">
                <i class="fas fa-globe"></i>
                <span>ไปยังหน้าเว็บ</span>
            </a>
            <a href="/logout.php">
                <i class="fas fa-sign-out-alt"></i>
                <span>ออกจากระบบ</span>
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="admin-main">
        <?php $flash = getFlash();
        if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] ?> mb-lg">
                <?= htmlspecialchars($flash['message']) ?>
            </div>
        <?php endif; ?>

        <div class="admin-header">
            <h1>แดชบอร์ด</h1>
            <p class="text-muted">ภาพรวมร้านค้า</p>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-4 mb-xl">
            <div class="admin-card admin-stat">
                <i class="fas fa-box fa-2x text-gold mb-md"></i>
                <div class="admin-stat-value">
                    <?= number_format($stats['products']) ?>
                </div>
                <div class="admin-stat-label">สินค้าทั้งหมด</div>
            </div>

            <div class="admin-card admin-stat">
                <i class="fas fa-shopping-cart fa-2x text-gold mb-md"></i>
                <div class="admin-stat-value">
                    <?= number_format($stats['orders']) ?>
                </div>
                <div class="admin-stat-label">คำสั่งซื้อ</div>
            </div>

            <div class="admin-card admin-stat">
                <i class="fas fa-users fa-2x text-gold mb-md"></i>
                <div class="admin-stat-value">
                    <?= number_format($stats['users']) ?>
                </div>
                <div class="admin-stat-label">สมาชิก</div>
            </div>

            <div class="admin-card admin-stat">
                <i class="fas fa-coins fa-2x text-gold mb-md"></i>
                <div class="admin-stat-value">
                    <?= number_format($stats['revenue'], 0) ?>฿
                </div>
                <div class="admin-stat-label">ยอดขายรวม</div>
            </div>
        </div>

        <!-- Pending Payments Alert -->
        <?php if ($stats['pending_payments'] > 0): ?>
            <div class="alert alert-warning mb-xl">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>มีคำสั่งซื้อรอยืนยันการชำระเงิน
                        <?= $stats['pending_payments'] ?> รายการ
                    </strong>
                    <a href="/admin/orders.php?filter=pending"
                        style="color: var(--warning); margin-left: var(--space-md);">
                        ดูรายละเอียด →
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Recent Orders -->
        <div class="admin-card">
            <div class="d-flex justify-between align-center mb-lg">
                <h3>คำสั่งซื้อล่าสุด</h3>
                <a href="/admin/orders.php" class="btn btn-sm btn-secondary">ดูทั้งหมด</a>
            </div>

            <?php if (empty($recentOrders)): ?>
                <p class="text-muted text-center p-xl">ยังไม่มีคำสั่งซื้อ</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>ลูกค้า</th>
                                <th>ยอดรวม</th>
                                <th>การชำระเงิน</th>
                                <th>สถานะ</th>
                                <th>วันที่</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentOrders as $order): ?>
                                <tr>
                                    <td>
                                        <?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($order['username']) ?>
                                    </td>
                                    <td style="font-weight: 600;">
                                        <?= formatPrice($order['total_amount']) ?>
                                    </td>
                                    <td>
                                        <?php if ($order['payment_method'] === 'cod'): ?>
                                            <span class="status-badge status-processing">COD</span>
                                        <?php else: ?>
                                            <span class="status-badge status-<?= $order['payment_status'] ?>">
                                                <?= $order['payment_status'] === 'pending' ? 'รอยืนยัน' : ($order['payment_status'] === 'confirmed' ? 'ยืนยันแล้ว' : 'ไม่สำเร็จ') ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= $order['status'] ?>">
                                            <?= match ($order['status']) {
                                                'pending' => 'รอดำเนินการ',
                                                'paid' => 'ชำระแล้ว',
                                                'processing' => 'กำลังจัดเตรียม',
                                                'shipped' => 'จัดส่งแล้ว',
                                                'delivered' => 'ส่งสำเร็จ',
                                                'cancelled' => 'ยกเลิก',
                                                default => $order['status']
                                            } ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= date('d/m H:i', strtotime($order['created_at'])) ?>
                                    </td>
                                    <td>
                                        <a href="/admin/order_detail.php?id=<?= $order['id'] ?>"
                                            class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>

</html>