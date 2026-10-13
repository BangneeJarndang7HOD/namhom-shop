<?php
/**
 * Header Component
 * ส่วนหัวของเว็บไซต์
 */
require_once __DIR__ . '/config/database.php';

$cartCount = getCartCount();
$currentUser = getCurrentUser();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Parfum Luxe - ร้านน้ำหอมแบรนด์เนมของแท้ 100% ราคาดีที่สุด">
    <title>
        <?= $pageTitle ?? 'Parfum Luxe - Premium Perfume Store' ?>
    </title>
    <link rel="stylesheet" href="/css/style.css?v=<?= @filemtime(__DIR__ . '/css/style.css') ?: time() ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="/images/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/images/favicon-16.png">
    <link rel="apple-touch-icon" href="/images/apple-touch-icon.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="container">
            <a href="/" class="navbar-brand">
                Parfum<span>Luxe</span>
            </a>

            <button class="mobile-menu-btn" onclick="toggleMobileMenu()">
                <i class="fas fa-bars"></i>
            </button>

            <ul class="navbar-menu" id="navbarMenu">
                <li><a href="/" class="<?= $currentPage === 'index' ? 'active' : '' ?>">หน้าแรก</a></li>
                <li><a href="/products.php"
                        class="<?= $currentPage === 'products' ? 'active' : '' ?>">สินค้าทั้งหมด</a></li>
                <li><a href="/promotions.php"
                        class="<?= $currentPage === 'promotions' ? 'active' : '' ?>">โปรโมชั่น</a></li>
            </ul>

            <div class="navbar-actions">
                <a href="/cart.php" class="cart-icon">
                    <i class="fas fa-shopping-cart"></i>
                    <?php if ($cartCount > 0): ?>
                        <span class="cart-badge">
                            <?= $cartCount ?>
                        </span>
                    <?php endif; ?>
                </a>

                <?php if (isLoggedIn()): ?>
                    <a href="/profile.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-user"></i>
                        <span class="navbar-username"><?= htmlspecialchars($currentUser['username']) ?></span>
                    </a>
                    <?php if (isAdmin()): ?>
                        <a href="/admin/" class="btn btn-primary btn-sm">
                            <i class="fas fa-cog"></i> <span class="navbar-username">Admin</span>
                        </a>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="/login.php" class="btn btn-secondary btn-sm">เข้าสู่ระบบ</a>
                    <a href="/register.php" class="btn btn-primary btn-sm">สมัครสมาชิก</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <?php
    // Display flash messages
    $flash = getFlash();
    if ($flash):
        ?>
        <div class="container mt-lg">
            <div class="alert alert-<?= $flash['type'] ?>">
                <i
                    class="fas fa-<?= $flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'danger' ? 'exclamation-circle' : 'info-circle') ?>"></i>
                <?= htmlspecialchars($flash['message']) ?>
            </div>
        </div>
    <?php endif; ?>

    <main>