<?php
/**
 * Admin User Edit - แก้ไขข้อมูลผู้ใช้
 */
require_once __DIR__ . '/../config/database.php';

if (!isAdmin()) {
    redirect('/');
}

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    setFlash('danger', 'ไม่พบรหัสผู้ใช้');
    redirect('/admin/users.php');
}

// Get user data
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    setFlash('danger', 'ไม่พบข้อมูลผู้ใช้');
    redirect('/admin/users.php');
}

$pageTitle = 'แก้ไขผู้ใช้ - Admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf('/admin/user_edit.php?id=' . $id);

    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (empty($email)) {
        $error = 'กรุณากรอกอีเมล';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'รูปแบบอีเมลไม่ถูกต้อง';
    } else {
        // Check if email already exists for other users
        $checkStmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $checkStmt->execute([$email, $id]);
        if ($checkStmt->fetch()) {
            $error = 'อีเมลนี้ถูกใช้งานแล้ว';
        } else {
            $updateStmt = $db->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?");
            if ($updateStmt->execute([$full_name, $email, $phone, $id])) {
                setFlash('success', 'อัปเดตข้อมูลผู้ใช้เรียบร้อยแล้ว');
                redirect('/admin/users.php');
            } else {
                $error = 'เกิดข้อผิดพลาดในการอัปเดตข้อมูล';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="icon" type="image/png" sizes="32x32" href="/images/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/images/favicon-16.png">
    <link rel="apple-touch-icon" href="/images/apple-touch-icon.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>
    <aside class="admin-sidebar">
        <div class="admin-sidebar-brand">
            <a href="/admin/">Parfum Luxe</a>
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
            <h1>แก้ไขผู้ใช้: <?= htmlspecialchars($user['username']) ?></h1>
            <a href="/admin/users.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> กลับไปหน้าจัดการผู้ใช้</a>
        </div>

        <div class="admin-card" style="max-width: 600px;">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <?= csrfField() ?>
                <div class="form-group">
                    <label class="form-label">ชื่อผู้ใช้ (Username) - ไม่สามารถแก้ไขได้</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" disabled>
                </div>
                
                <div class="form-group">
                    <label class="form-label">ชื่อ-นามสกุล</label>
                    <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name']) ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">อีเมล</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">เบอร์โทรศัพท์</label>
                    <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone']) ?>">
                </div>

                <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-save"></i> บันทึกข้อมูล</button>
            </form>
        </div>
    </main>
</body>

</html>
