<?php
/**
 * Google OAuth callback: แลก code เป็น token แล้วล็อกอิน/สร้างบัญชีผู้ใช้
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/google_keys.php';

function googleHttp($url, $postFields = null, $bearer = null)
{
    $ch = curl_init($url);
    $headers = [];
    if ($bearer) {
        $headers[] = 'Authorization: Bearer ' . $bearer;
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POST => $postFields !== null,
        CURLOPT_POSTFIELDS => $postFields,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, json_decode((string) $body, true)];
}

function uniqueUsername($db, $email)
{
    $base = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', explode('@', $email)[0]));
    if (strlen($base) < 3) {
        $base = 'user' . $base;
    }
    $base = substr($base, 0, 40);
    $candidate = $base;
    $n = 1;
    $check = $db->prepare("SELECT id FROM users WHERE username = ?");
    while (true) {
        $check->execute([$candidate]);
        if (!$check->fetch()) {
            return $candidate;
        }
        $candidate = $base . $n++;
    }
}

if (!empty($_GET['error']) || empty($_GET['code']) || empty($_GET['state'])) {
    setFlash('danger', 'การเข้าสู่ระบบด้วย Google ถูกยกเลิก');
    redirect('/login.php');
}

$expectedState = $_SESSION['google_oauth_state'] ?? '';
unset($_SESSION['google_oauth_state']);
if ($expectedState === '' || !hash_equals($expectedState, $_GET['state'])) {
    setFlash('danger', 'เซสชันหมดอายุ กรุณาลองเข้าสู่ระบบด้วย Google อีกครั้ง');
    redirect('/login.php');
}

$baseUrl = 'http' . (!empty($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] ;

[$tokenCode, $token] = googleHttp('https://oauth2.googleapis.com/token', http_build_query([
    'code' => $_GET['code'],
    'client_id' => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri' => $baseUrl . '/google_callback.php',
    'grant_type' => 'authorization_code',
]));

if ($tokenCode !== 200 || empty($token['access_token'])) {
    error_log('Google token exchange failed: ' . $tokenCode);
    setFlash('danger', 'ไม่สามารถเข้าสู่ระบบด้วย Google ได้ในขณะนี้');
    redirect('/login.php');
}

[$infoCode, $info] = googleHttp('https://openidconnect.googleapis.com/v1/userinfo', null, $token['access_token']);

if ($infoCode !== 200 || empty($info['sub']) || empty($info['email']) || empty($info['email_verified'])) {
    setFlash('danger', 'บัญชี Google นี้ยังไม่ได้ยืนยันอีเมล จึงใช้เข้าสู่ระบบไม่ได้');
    redirect('/login.php');
}

$db = getDB();
$googleId = (string) $info['sub'];
$email = (string) $info['email'];

$stmt = $db->prepare("SELECT * FROM users WHERE google_id = ?");
$stmt->execute([$googleId]);
$user = $stmt->fetch();

if (!$user) {
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $db->prepare("UPDATE users SET google_id = ? WHERE id = ?")->execute([$googleId, $user['id']]);
    } else {
        $username = uniqueUsername($db, $email);
        $randomPassword = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (username, email, password, google_id, full_name) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$username, $email, $randomPassword, $googleId, (string) ($info['name'] ?? '')]);
        $user = [
            'id' => $db->lastInsertId(),
            'username' => $username,
            'is_admin' => 0,
        ];
    }
}

session_regenerate_id(true);
$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['is_admin'] = (int) $user['is_admin'];

setFlash('success', 'ยินดีต้อนรับ, ' . htmlspecialchars($user['username']) . '!');

$next = $_SESSION['google_redirect_after'] ?? '/';
unset($_SESSION['google_redirect_after']);
if (!is_string($next) || !str_starts_with($next, '/') || str_starts_with($next, '//')) {
    $next = '/';
}
redirect($next);
