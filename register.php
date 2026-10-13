<?php
/**
 * Register Page - สมัครสมาชิก
 */
$pageTitle = 'สมัครสมาชิก - ParfumLuxe';
require_once 'header.php';

if (isLoggedIn()) {
    redirect('/');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf('/register.php');

    $username = sanitize($_POST['username'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $fullName = sanitize($_POST['full_name'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');

    // Validation
    if (empty($username))
        $errors[] = 'กรุณากรอกชื่อผู้ใช้งาน';
    if (strlen($username) < 3)
        $errors[] = 'ชื่อผู้ใช้งานต้องมีอย่างน้อย 3 ตัวอักษร';
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username))
        $errors[] = 'ชื่อผู้ใช้งานใช้ได้เฉพาะ a-z, 0-9 และ _';

    if (empty($email))
        $errors[] = 'กรุณากรอกอีเมล';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = 'รูปแบบอีเมลไม่ถูกต้อง';

    if (empty($password))
        $errors[] = 'กรุณากรอกรหัสผ่าน';
    if (strlen($password) < 6)
        $errors[] = 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร';
    if ($password !== $confirmPassword)
        $errors[] = 'รหัสผ่านไม่ตรงกัน';

    if (empty($errors)) {
        $db = getDB();

        // Check if username or email already exists
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);

        if ($stmt->fetch()) {
            $errors[] = 'ชื่อผู้ใช้งานหรืออีเมลนี้ถูกใช้งานแล้ว';
        } else {
            // Create user
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $db->prepare("
                INSERT INTO users (username, email, password, full_name, phone)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$username, $email, $hashedPassword, $fullName, $phone]);

            $userId = $db->lastInsertId();

            // Auto login
            session_regenerate_id(true);
            $_SESSION['user_id'] = $userId;
            $_SESSION['username'] = $username;
            $_SESSION['is_admin'] = 0;

            setFlash('success', 'สมัครสมาชิกสำเร็จ! ยินดีต้อนรับสู่ ParfumLuxe');
            redirect('/');
        }
    }
}
?>

<section class="section">
    <div class="container">
        <div style="max-width: 500px; margin: 0 auto;">
            <div class="card">
                <div class="card-body" style="padding: var(--space-2xl);">
                    <div class="text-center mb-xl">
                        <h2 class="mb-sm">สมัครสมาชิก</h2>
                        <p class="text-muted">เข้าร่วมครอบครัว ParfumLuxe วันนี้!</p>
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

                    <form method="POST">
                        <?= csrfField() ?>
                        <div class="form-group">
                            <label class="form-label">ชื่อผู้ใช้งาน *</label>
                            <input type="text" name="username" class="form-control" placeholder="เช่น john_doe" required
                                value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">อีเมล *</label>
                            <input type="email" name="email" class="form-control" placeholder="email@example.com"
                                required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        </div>

                        <div class="grid grid-2">
                            <div class="form-group">
                                <label class="form-label">รหัสผ่าน *</label>
                                <input type="password" name="password" class="form-control"
                                    placeholder="อย่างน้อย 6 ตัวอักษร" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">ยืนยันรหัสผ่าน *</label>
                                <input type="password" name="confirm_password" class="form-control"
                                    placeholder="กรอกรหัสผ่านอีกครั้ง" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">ชื่อ-นามสกุล</label>
                            <input type="text" name="full_name" class="form-control" placeholder="ชื่อจริง นามสกุล"
                                value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">เบอร์โทรศัพท์</label>
                            <input type="tel" name="phone" class="form-control" placeholder="08X-XXX-XXXX"
                                value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg btn-block">
                            <i class="fas fa-user-plus"></i> สมัครสมาชิก
                        </button>
                    </form>

                    <div class="text-center mt-lg">
                        <p class="text-muted">
                            มีบัญชีอยู่แล้ว?
                            <a href="/login.php" style="color: var(--primary); font-weight: 500;">
                                เข้าสู่ระบบ
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once 'footer.php'; ?>