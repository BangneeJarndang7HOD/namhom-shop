<?php
/**
 * เริ่มการเข้าสู่ระบบด้วย Google (OAuth 2.0)
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/google_keys.php';

if (isLoggedIn()) {
    redirect('/');
}

$state = bin2hex(random_bytes(16));
$_SESSION['google_oauth_state'] = $state;

$baseUrl = 'http' . (!empty($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] ;
$_SESSION['google_redirect_after'] = $_GET['redirect'] ?? '/';

$params = http_build_query([
    'client_id' => GOOGLE_CLIENT_ID,
    'redirect_uri' => $baseUrl . '/google_callback.php',
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'state' => $state,
    'prompt' => 'select_account',
]);

header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params);
exit;
