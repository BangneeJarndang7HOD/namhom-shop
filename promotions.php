<?php
/**
 * Promotions Page - หน้าโปรโมชั่น
 */
$pageTitle = 'โปรโมชั่น - ParfumLuxe';
require_once 'header.php';

$db = getDB();

// Get promotion products
$stmt = $db->query("SELECT * FROM products WHERE is_promotion = 1 AND discount_percent > 0 ORDER BY discount_percent DESC");
$products = $stmt->fetchAll();
?>

<section class="section" style="background: var(--dark);">
    <div class="container">
        <div class="section-header">
            <h2 style="color: var(--white);">🔥 โปรโมชั่นพิเศษ</h2>
            <p style="color: var(--gray-400);">ลดราคาสูงสุด 20% เฉพาะช่วงนี้เท่านั้น!</p>
            <div class="gold-line"></div>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <?php if (empty($products)): ?>
            <div class="card text-center p-xl">
                <i class="fas fa-tags fa-3x" style="color: var(--gray-400); margin-bottom: var(--space-lg);"></i>
                <h3>ยังไม่มีโปรโมชั่นในขณะนี้</h3>
                <p class="text-muted mb-lg">กลับมาตรวจสอบอีกครั้งในภายหลัง</p>
                <a href="/products.php" class="btn btn-primary">
                    <i class="fas fa-shopping-bag"></i> ดูสินค้าทั้งหมด
                </a>
            </div>
        <?php else: ?>
            <div class="grid grid-4">
                <?php foreach ($products as $product): ?>
                    <?php $discountedPrice = getDiscountedPrice($product['price'], $product['discount_percent']); ?>
                    <div class="card">
                        <div class="card-img">
                            <?php if ($product['image']): ?>
                                <img src="/uploads/products/<?= htmlspecialchars($product['image']) ?>"
                                    alt="<?= htmlspecialchars($product['name']) ?>"
                                    onerror="this.src='/images/placeholder.jpg'">
                            <?php else: ?>
                                <img src="/images/placeholder.jpg" alt="No image">
                            <?php endif; ?>
                            <span class="card-badge">-
                                <?= $product['discount_percent'] ?>%
                            </span>
                        </div>
                        <div class="card-body">
                            <p class="card-brand">
                                <?= htmlspecialchars($product['brand']) ?>
                            </p>
                            <h3 class="card-title">
                                <a href="/product.php?id=<?= $product['id'] ?>">
                                    <?= htmlspecialchars($product['name']) ?>
                                </a>
                            </h3>
                            <p class="text-muted mb-md" style="font-size: 0.875rem;">
                                <?= htmlspecialchars($product['size']) ?>
                            </p>
                            <div class="card-price">
                                <span class="current">
                                    <?= formatPrice($discountedPrice) ?>
                                </span>
                                <span class="original">
                                    <?= formatPrice($product['price']) ?>
                                </span>
                            </div>
                            <p class="text-muted mb-md" style="font-size: 0.875rem;">
                                ประหยัด
                                <?= formatPrice($product['price'] - $discountedPrice) ?>
                            </p>
                            <a href="/product.php?id=<?= $product['id'] ?>" class="btn btn-primary btn-block">
                                <i class="fas fa-eye"></i> ดูรายละเอียด
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once 'footer.php'; ?>