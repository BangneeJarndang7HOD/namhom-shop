<?php
/**
 * Admin Orders Management - จัดการคำสั่งซื้อและยืนยันการชำระเงิน
 */
require_once __DIR__ . '/../config/database.php';

if (!isAdmin()) {
    redirect('/');
}

$pageTitle = 'จัดการคำสั่งซื้อ - Admin';
$db = getDB();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf('/admin/orders.php' . (isset($_GET['filter']) ? '?filter=' . $_GET['filter'] : ''));

    $orderId = (int) ($_POST['order_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'confirm_payment':
            $stmt = $db->prepare("UPDATE orders SET payment_status = 'confirmed', status = 'processing' WHERE id = ?");
            $stmt->execute([$orderId]);
            setFlash('success', 'ยืนยันการชำระเงินสำเร็จ');
            break;

        case 'reject_payment':
            $stmt = $db->prepare("UPDATE orders SET payment_status = 'failed', status = 'cancelled' WHERE id = ?");
            $stmt->execute([$orderId]);
            setFlash('warning', 'ปฏิเสธการชำระเงินแล้ว');
            break;

        case 'update_status':
            $newStatus = $_POST['status'] ?? '';
            if (in_array($newStatus, ['pending', 'processing', 'shipped', 'delivered', 'cancelled'])) {
                $stmt = $db->prepare("UPDATE orders SET status = ? WHERE id = ?");
                $stmt->execute([$newStatus, $orderId]);
                setFlash('success', 'อัพเดทสถานะสำเร็จ');
            }
            break;
    }
    redirect('/admin/orders.php' . (isset($_GET['filter']) ? '?filter=' . $_GET['filter'] : ''));
}

// Filters
$filter = $_GET['filter'] ?? 'all';
$where = match ($filter) {
    'pending' => "WHERE payment_method = 'transfer' AND payment_status = 'pending'",
    'cod' => "WHERE payment_method = 'cod'",
    'confirmed' => "WHERE payment_status = 'confirmed'",
    'cancelled' => "WHERE status = 'cancelled'",
    default => ''
};

// Get orders
$stmt = $db->query("
    SELECT o.*, u.username, u.email
    FROM orders o
    JOIN users u ON o.user_id = u.id
    $where
    ORDER BY o.created_at DESC
");
$orders = $stmt->fetchAll();

// Count for tabs
$counts = [
    'all' => $db->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
    'pending' => $db->query("SELECT COUNT(*) FROM orders WHERE payment_method = 'transfer' AND payment_status = 'pending'")->fetchColumn(),
    'cod' => $db->query("SELECT COUNT(*) FROM orders WHERE payment_method = 'cod'")->fetchColumn(),
];
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
            <a href="/admin/">
                <i class="fas fa-chart-line"></i>
                <span>แดชบอร์ด</span>
            </a>
            <a href="/admin/products.php">
                <i class="fas fa-box"></i>
                <span>จัดการสินค้า</span>
            </a>
            <a href="/admin/orders.php" class="active">
                <i class="fas fa-shopping-cart"></i>
                <span>คำสั่งซื้อ</span>
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
            <h1>จัดการคำสั่งซื้อ</h1>
        </div>

        <!-- Filter Tabs -->
        <div class="profile-tabs mb-xl">
            <a href="?filter=all" class="profile-tab <?= $filter === 'all' ? 'active' : '' ?>">
                ทั้งหมด (
                <?= $counts['all'] ?>)
            </a>
            <a href="?filter=pending" class="profile-tab <?= $filter === 'pending' ? 'active' : '' ?>">
                <i class="fas fa-clock"></i> รอยืนยันชำระเงิน (
                <?= $counts['pending'] ?>)
            </a>
            <a href="?filter=cod" class="profile-tab <?= $filter === 'cod' ? 'active' : '' ?>">
                COD (
                <?= $counts['cod'] ?>)
            </a>
        </div>

        <?php if (empty($orders)): ?>
            <div class="admin-card text-center p-xl">
                <i class="fas fa-shopping-cart fa-3x text-muted mb-lg"></i>
                <h3>ไม่พบคำสั่งซื้อ</h3>
            </div>
        <?php else: ?>
            <?php foreach ($orders as $order): ?>
                <div class="admin-card mb-lg">
                    <div class="d-flex justify-between align-center mb-md">
                        <div>
                            <h4>
                                คำสั่งซื้อ #
                                <?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?>
                                <?php if ($order['payment_method'] === 'transfer' && $order['payment_status'] === 'pending'): ?>
                                    <span class="status-badge"
                                        style="background: var(--warning); color: var(--dark); margin-left: var(--space-sm);">
                                        ⚠️ รอยืนยันชำระเงิน
                                    </span>
                                <?php endif; ?>
                            </h4>
                            <p class="text-muted">
                                <i class="fas fa-user"></i>
                                <?= htmlspecialchars($order['username']) ?> (
                                <?= htmlspecialchars($order['email']) ?>)
                                &bull;
                                <i class="fas fa-calendar"></i>
                                <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?>
                            </p>
                        </div>
                        <div class="text-right">
                            <div style="font-size: 1.5rem; font-weight: 700; color: var(--primary);">
                                <?= formatPrice($order['total_amount']) ?>
                            </div>
                            <div class="d-flex gap-sm mt-sm">
                                <span
                                    class="status-badge status-<?= $order['payment_method'] === 'cod' ? 'processing' : $order['payment_status'] ?>">
                                    <?= $order['payment_method'] === 'cod' ? 'COD' : ($order['payment_status'] === 'pending' ? 'รอยืนยัน' : ($order['payment_status'] === 'confirmed' ? 'ชำระแล้ว' : 'ไม่สำเร็จ')) ?>
                                </span>
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
                        </div>
                    </div>

                    <div class="d-flex gap-md">
                        <div
                            style="flex: 1; background: var(--gray-100); padding: var(--space-md); border-radius: var(--radius-md);">
                            <strong>ที่อยู่จัดส่ง:</strong><br>
                            <?= nl2br(htmlspecialchars($order['shipping_address'])) ?><br>
                            <strong>โทร:</strong>
                            <?= htmlspecialchars($order['phone']) ?>
                        </div>

                        <?php if ($order['payment_method'] === 'transfer' && !empty($order['slip_image'])): ?>
                            <a href="/uploads/slips/<?= htmlspecialchars($order['slip_image']) ?>" target="_blank"
                                style="flex-shrink: 0;">
                                <img src="/uploads/slips/<?= htmlspecialchars($order['slip_image']) ?>" alt="สลิปโอนเงิน"
                                    style="width: 80px; height: 80px; object-fit: cover; border-radius: var(--radius-md); border: 1px solid var(--gray-300);">
                            </a>
                        <?php endif; ?>

                        <div class="d-flex gap-sm align-center">
                            <?php if ($order['payment_method'] === 'transfer' && $order['payment_status'] === 'pending'): ?>
                                <form method="POST" style="display: inline;">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                    <input type="hidden" name="action" value="confirm_payment">
                                    <button type="submit" class="btn btn-success"
                                        onclick="return confirm('ยืนยันว่าได้รับเงินแล้ว?')">
                                        <i class="fas fa-check"></i> ยืนยันชำระเงิน
                                    </button>
                                </form>
                                <form method="POST" style="display: inline;">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                    <input type="hidden" name="action" value="reject_payment">
                                    <button type="submit" class="btn btn-danger" onclick="return confirm('ปฏิเสธการชำระเงิน?')">
                                        <i class="fas fa-times"></i> ปฏิเสธ
                                    </button>
                                </form>
                            <?php else: ?>
                                <form method="POST" class="d-flex gap-sm">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                    <input type="hidden" name="action" value="update_status">
                                    <select name="status" class="form-control" style="width: auto;">
                                        <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>>รอดำเนินการ
                                        </option>
                                        <option value="processing" <?= $order['status'] === 'processing' ? 'selected' : '' ?>
                                            >กำลังจัดเตรียม</option>
                                        <option value="shipped" <?= $order['status'] === 'shipped' ? 'selected' : '' ?>>จัดส่งแล้ว
                                        </option>
                                        <option value="delivered" <?= $order['status'] === 'delivered' ? 'selected' : '' ?>>ส่งสำเร็จ
                                        </option>
                                        <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>ยกเลิก
                                        </option>
                                    </select>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> อัพเดท
                                    </button>
                                </form>
                            <?php endif; ?>

                            <a href="/admin/order_detail.php?id=<?= $order['id'] ?>" class="btn btn-secondary">
                                <i class="fas fa-eye"></i> ดูรายละเอียด
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>
</body>

</html>