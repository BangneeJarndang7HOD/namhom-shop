<?php
/**
 * Profile Page - หน้าโปรไฟล์
 */
$pageTitle = 'โปรไฟล์ - ParfumLuxe';
require_once 'header.php';

if (!isLoggedIn()) {
    setFlash('warning', 'กรุณาเข้าสู่ระบบก่อน');
    redirect('/login.php');
}

$db = getDB();
$userId = getCurrentUserId();
$user = getCurrentUser();

$activeTab = $_GET['tab'] ?? 'profile';
$errors = [];
$success = false;

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    requireCsrf('/profile.php');

    $fullName = sanitize($_POST['full_name'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $address = sanitize($_POST['address'] ?? '');

    $stmt = $db->prepare("UPDATE users SET full_name = ?, phone = ?, address = ? WHERE id = ?");
    $stmt->execute([$fullName, $phone, $address, $userId]);

    setFlash('success', 'อัพเดทข้อมูลเรียบร้อยแล้ว');
    redirect('/profile.php');
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    requireCsrf('/profile.php?tab=password');

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!password_verify($currentPassword, $user['password'])) {
        $errors[] = 'รหัสผ่านปัจจุบันไม่ถูกต้อง';
    } elseif (strlen($newPassword) < 6) {
        $errors[] = 'รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร';
    } elseif ($newPassword !== $confirmPassword) {
        $errors[] = 'รหัสผ่านใหม่ไม่ตรงกัน';
    } else {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashedPassword, $userId]);

        setFlash('success', 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว');
        redirect('/profile.php');
    }
    $activeTab = 'password';
}

// Get orders
$stmt = $db->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$userId]);
$orders = $stmt->fetchAll();

// Function to get status text
function getStatusText($status)
{
    return match ($status) {
        'pending' => 'รอดำเนินการ',
        'paid' => 'ชำระเงินแล้ว',
        'processing' => 'กำลังจัดเตรียม',
        'shipped' => 'จัดส่งแล้ว',
        'delivered' => 'ส่งสำเร็จ',
        'cancelled' => 'ยกเลิก',
        default => $status
    };
}

function getPaymentStatusText($status)
{
    return match ($status) {
        'pending' => 'รอตรวจสอบ',
        'confirmed' => 'ยืนยันแล้ว',
        'failed' => 'ไม่สำเร็จ',
        default => $status
    };
}
?>

<!-- Profile Header -->
<div class="profile-header">
    <div class="container">
        <div class="profile-avatar">
            <?= strtoupper(substr($user['username'], 0, 1)) ?>
        </div>
        <h2 class="profile-name">
            <?= htmlspecialchars($user['full_name'] ?: $user['username']) ?>
        </h2>
        <p class="profile-email">
            <?= htmlspecialchars($user['email']) ?>
        </p>
    </div>
</div>

<section class="section" style="padding-top: 0;">
    <div class="container">
        <!-- Profile Tabs -->
        <div class="profile-tabs">
            <a href="?tab=profile" class="profile-tab <?= $activeTab === 'profile' ? 'active' : '' ?>">
                <i class="fas fa-user"></i> ข้อมูลส่วนตัว
            </a>
            <a href="?tab=orders" class="profile-tab <?= $activeTab === 'orders' ? 'active' : '' ?>">
                <i class="fas fa-shopping-bag"></i> ประวัติการสั่งซื้อ
            </a>
            <a href="?tab=password" class="profile-tab <?= $activeTab === 'password' ? 'active' : '' ?>">
                <i class="fas fa-lock"></i> เปลี่ยนรหัสผ่าน
            </a>
            <a href="/logout.php" class="profile-tab" style="margin-left: auto; color: var(--danger);">
                <i class="fas fa-sign-out-alt"></i> ออกจากระบบ
            </a>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul style="margin: 0; padding-left: var(--space-lg);">
                    <?php foreach ($errors as $error): ?>
                        <li>
                            <?= htmlspecialchars($error) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Profile Tab -->
        <?php if ($activeTab === 'profile'): ?>
            <div class="grid" style="grid-template-columns: 2fr 1fr; gap: var(--space-xl);">
                <div class="card">
                    <div class="card-body">
                        <h3 class="mb-lg">ข้อมูลส่วนตัว</h3>

                        <form method="POST">
                            <?= csrfField() ?>
                            <input type="hidden" name="update_profile" value="1">

                            <div class="form-group">
                                <label class="form-label">ชื่อผู้ใช้งาน</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($user['username']) ?>"
                                    disabled>
                            </div>

                            <div class="form-group">
                                <label class="form-label">อีเมล</label>
                                <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>"
                                    disabled>
                            </div>

                            <div class="form-group">
                                <label class="form-label">ชื่อ-นามสกุล</label>
                                <input type="text" name="full_name" class="form-control"
                                    value="<?= htmlspecialchars($user['full_name'] ?? '') ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label">เบอร์โทรศัพท์</label>
                                <input type="tel" name="phone" class="form-control"
                                    value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label">ที่อยู่</label>
                                <textarea name="address" class="form-control"
                                    rows="3"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> บันทึกข้อมูล
                            </button>
                        </form>
                    </div>
                </div>

                <div>
                    <div class="card">
                        <div class="card-body text-center">
                            <div style="font-size: 3rem; color: var(--primary); margin-bottom: var(--space-md);">
                                <i class="fas fa-shopping-bag"></i>
                            </div>
                            <h4>
                                <?= count($orders) ?>
                            </h4>
                            <p class="text-muted">คำสั่งซื้อทั้งหมด</p>
                        </div>
                    </div>

                    <div class="card mt-lg">
                        <div class="card-body text-center">
                            <div style="font-size: 3rem; color: var(--success); margin-bottom: var(--space-md);">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <h4>
                                <?= count(array_filter($orders, fn($o) => $o['status'] === 'delivered')) ?>
                            </h4>
                            <p class="text-muted">สั่งซื้อสำเร็จ</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Orders Tab -->
        <?php elseif ($activeTab === 'orders'): ?>
            <?php if (empty($orders)): ?>
                <div class="card text-center p-xl">
                    <i class="fas fa-shopping-bag fa-3x" style="color: var(--gray-400); margin-bottom: var(--space-lg);"></i>
                    <h3>ยังไม่มีประวัติการสั่งซื้อ</h3>
                    <p class="text-muted mb-lg">เริ่มช้อปปิ้งกับเราวันนี้!</p>
                    <a href="/products.php" class="btn btn-primary">
                        <i class="fas fa-shopping-cart"></i> เลือกซื้อสินค้า
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>วันที่</th>
                                <th>ยอดรวม</th>
                                <th>การชำระเงิน</th>
                                <th>สถานะ</th>
                                <th>จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td>
                                        <?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?>
                                    </td>
                                    <td>
                                        <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?>
                                    </td>
                                    <td style="font-weight: 600; color: var(--primary);">
                                        <?= formatPrice($order['total_amount']) ?>
                                    </td>
                                    <td>
                                        <?php if ($order['payment_method'] === 'cod'): ?>
                                            <span class="status-badge status-processing">COD</span>
                                        <?php else: ?>
                                            <span class="status-badge status-<?= $order['payment_status'] ?>">
                                                <?= getPaymentStatusText($order['payment_status']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= $order['status'] ?>">
                                            <?= getStatusText($order['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="/order_detail.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-secondary">
                                            <i class="fas fa-eye"></i> ดู
                                        </a>
                                        <?php if ($order['payment_method'] === 'transfer' && $order['payment_status'] === 'pending' && $order['status'] !== 'cancelled'): ?>
                                            <a href="/payment.php?order=<?= $order['id'] ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-qrcode"></i> ชำระเงิน
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- Password Tab -->
        <?php elseif ($activeTab === 'password'): ?>
            <div style="max-width: 500px;">
                <div class="card">
                    <div class="card-body">
                        <h3 class="mb-lg">เปลี่ยนรหัสผ่าน</h3>

                        <form method="POST">
                            <?= csrfField() ?>
                            <input type="hidden" name="change_password" value="1">

                            <div class="form-group">
                                <label class="form-label">รหัสผ่านปัจจุบัน</label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">รหัสผ่านใหม่</label>
                                <input type="password" name="new_password" class="form-control" required
                                    placeholder="อย่างน้อย 6 ตัวอักษร">
                            </div>

                            <div class="form-group">
                                <label class="form-label">ยืนยันรหัสผ่านใหม่</label>
                                <input type="password" name="confirm_password" class="form-control" required>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-key"></i> เปลี่ยนรหัสผ่าน
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once 'footer.php'; ?>