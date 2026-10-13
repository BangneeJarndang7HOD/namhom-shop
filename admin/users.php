<?php
/**
 * Admin Users Management - จัดการผู้ใช้
 */
require_once __DIR__ . '/../config/database.php';

if (!isAdmin()) {
    redirect('/');
}

$pageTitle = 'จัดการผู้ใช้ - Admin';
$db = getDB();

// Role tab: 'user' (default) shows regular members, 'admin' shows admin accounts
$role = ($_GET['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
$isAdminFilter = $role === 'admin' ? 1 : 0;

// Get users with order count
$stmt = $db->prepare("
    SELECT u.*, COUNT(o.id) as order_count, COALESCE(SUM(o.total_amount), 0) as total_spent
    FROM users u
    LEFT JOIN orders o ON u.id = o.user_id
    WHERE u.is_admin = ?
    GROUP BY u.id
    ORDER BY u.created_at DESC
");
$stmt->execute([$isAdminFilter]);
$users = $stmt->fetchAll();

$memberCount = $db->query("SELECT COUNT(*) FROM users WHERE is_admin = 0")->fetchColumn();
$adminCount = $db->query("SELECT COUNT(*) FROM users WHERE is_admin = 1")->fetchColumn();
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
            <a href="/admin/orders.php"><i class="fas fa-shopping-cart"></i><span>คำสั่งซื้อ</span></a>
            <a href="/admin/categories.php"><i class="fas fa-tags"></i><span>หมวดหมู่</span></a>
            <a href="/admin/users.php" class="active"><i class="fas fa-users"></i><span>ผู้ใช้งาน</span></a>
            <hr style="border-color: var(--gray-700); margin: var(--space-lg) 0;">
            <a href="/"><i class="fas fa-globe"></i><span>ไปยังหน้าเว็บ</span></a>
            <a href="/logout.php"><i class="fas fa-sign-out-alt"></i><span>ออกจากระบบ</span></a>
        </nav>
    </aside>

    <main class="admin-main">
        <div class="admin-header">
            <h1>จัดการผู้ใช้งาน</h1>
        </div>

        <!-- Role Tabs -->
        <div class="profile-tabs mb-xl">
            <a href="?role=user" class="profile-tab <?= $role === 'user' ? 'active' : '' ?>">
                <i class="fas fa-user"></i> สมาชิก (<?= $memberCount ?>)
            </a>
            <a href="?role=admin" class="profile-tab <?= $role === 'admin' ? 'active' : '' ?>">
                <i class="fas fa-user-shield"></i> แอดมิน (<?= $adminCount ?>)
            </a>
        </div>

        <div class="admin-card">
            <?php if (empty($users)): ?>
                <p class="text-muted text-center p-xl"><?= $role === 'admin' ? 'ยังไม่มีแอดมิน' : 'ยังไม่มีสมาชิก' ?></p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ผู้ใช้</th>
                                <th>อีเมล</th>
                                <th>โทรศัพท์</th>
                                <th>คำสั่งซื้อ</th>
                                <th>ยอดรวม</th>
                                <th>สมัครเมื่อ</th>
                                <th>จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex gap-md align-center">
                                            <div
                                                style="width: 40px; height: 40px; border-radius: 50%; background: var(--primary); display: flex; align-items: center; justify-content: center; color: var(--dark); font-weight: 600;">
                                                <?= strtoupper(substr($user['username'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <strong>
                                                    <?= htmlspecialchars($user['username']) ?>
                                                </strong>
                                                <?php if ($user['full_name']): ?>
                                                    <p class="text-muted" style="font-size: 0.875rem;">
                                                        <?= htmlspecialchars($user['full_name']) ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($user['email']) ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($user['phone'] ?: '-') ?>
                                    </td>
                                    <td>
                                        <?= $user['order_count'] ?>
                                    </td>
                                    <td style="font-weight: 600; color: var(--primary);">
                                        <?= formatPrice($user['total_spent']) ?>
                                    </td>
                                    <td>
                                        <?= date('d/m/Y', strtotime($user['created_at'])) ?>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-sm">
                                            <a href="user_edit.php?id=<?= $user['id'] ?>" class="btn btn-secondary btn-sm" title="แก้ไข">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ((int) $user['id'] === (int) getCurrentUserId()): ?>
                                                <span class="text-muted" style="align-self: center; font-size: 0.8rem;">(บัญชีของคุณ)</span>
                                            <?php elseif ($role === 'admin'): ?>
                                                <form action="user_action.php" method="POST" style="display:inline;" onsubmit="return confirm('ยืนยันการถอดสิทธิ์แอดมินของผู้ใช้นี้?');">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="action" value="demote">
                                                    <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                                    <button type="submit" class="btn btn-secondary btn-sm" title="ถอดสิทธิ์แอดมิน">
                                                        <i class="fas fa-user-minus"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form action="user_action.php" method="POST" style="display:inline;" onsubmit="return confirm('ยืนยันการเลื่อนผู้ใช้นี้เป็นแอดมิน?');">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="action" value="promote">
                                                    <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                                    <button type="submit" class="btn btn-primary btn-sm" title="เลื่อนเป็นแอดมิน">
                                                        <i class="fas fa-user-shield"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if ((int) $user['id'] !== (int) getCurrentUserId()): ?>
                                            <form action="user_action.php" method="POST" style="display:inline;" onsubmit="return confirm('ยืนยันการลบผู้ใช้นี้? (การลบจะไม่สามารถกู้คืนได้)');">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" title="ลบผู้ใช้">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </div>
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