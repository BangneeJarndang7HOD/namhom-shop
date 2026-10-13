<?php
/**
 * Login Page - เข้าสู่ระบบ
 */
$pageTitle = 'เข้าสู่ระบบ - ParfumLuxe';
require_once 'header.php';

if (isLoggedIn()) {
    redirect('/');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf('/login.php' . (isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''));

    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $errors[] = 'กรุณากรอกข้อมูลให้ครบถ้วน';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['is_admin'] = $user['is_admin'];

            setFlash('success', 'ยินดีต้อนรับกลับมา, ' . htmlspecialchars($user['username']) . '!');

            // Redirect to intended page or home
            $redirect = $_GET['redirect'] ?? '/';
            redirect($redirect);
        } else {
            $errors[] = 'ชื่อผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง';
        }
    }
}
?>

<section class="section">
    <div class="container">
        <div style="max-width: 450px; margin: 0 auto;">
            <div class="card">
                <div class="card-body" style="padding: var(--space-2xl);">
                    <div class="text-center mb-xl">
                        <h2 class="mb-sm">เข้าสู่ระบบ</h2>
                        <p class="text-muted">ยินดีต้อนรับกลับมา!</p>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <?php foreach ($errors as $error): ?>
                                <p>
                                    <?= htmlspecialchars($error) ?>
                                </p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <?= csrfField() ?>
                        <div class="form-group">
                            <label class="form-label">ชื่อผู้ใช้งาน หรือ อีเมล</label>
                            <input type="text" name="username" class="form-control"
                                placeholder="username หรือ email@example.com" required
                                value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">รหัสผ่าน</label>
                            <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg btn-block">
                            <i class="fas fa-sign-in-alt"></i> เข้าสู่ระบบ
                        </button>
                    </form>

                    <div class="text-center mt-lg">
                        <a href="/google_login.php" class="btn btn-secondary btn-lg btn-block">
                            <i class="fab fa-google"></i> เข้าสู่ระบบด้วย Google
                        </a>
                    </div>

                    <div class="text-center mt-lg">
                        <p class="text-muted">
                            ยังไม่มีบัญชี?
                            <a href="/register.php" style="color: var(--primary); font-weight: 500;">
                                สมัครสมาชิก
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once 'footer.php'; ?>