<?php
/**
 * Homepage - หน้าแรก
 */
$pageTitle = 'Parfum Luxe - Premium Perfume Store';
require_once 'header.php';

$db = getDB();

// Get featured products
$stmt = $db->query("SELECT * FROM products WHERE is_featured = 1 ORDER BY created_at DESC LIMIT 8");
$featuredProducts = $stmt->fetchAll();

// Get promotion products
$stmt = $db->query("SELECT * FROM products WHERE is_promotion = 1 AND discount_percent > 0 ORDER BY discount_percent DESC LIMIT 4");
$promotionProducts = $stmt->fetchAll();

// Get categories
$stmt = $db->query("SELECT * FROM categories");
$categories = $stmt->fetchAll();
?>

<!-- Hero Section -->
<section class="hero">
    <div class="container">
        <div class="hero-content fade-in">
            <p class="hero-subtitle">✨ Premium Perfume Collection</p>
            <h1 class="hero-title">
                ค้นพบกลิ่นหอม<br>
                <span>ที่เป็นตัวคุณ</span>
            </h1>
            <p class="hero-description">
                น้ำหอมแบรนด์เนมของแท้ 100% จากทั่วโลก
                คัดสรรมาเพื่อคุณโดยเฉพาะ พร้อมส่งฟรีทั่วประเทศ
            </p>
            <div class="hero-buttons">
                <a href="/products.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-shopping-bag"></i> เลือกซื้อสินค้า
                </a>
                <a href="/promotions.php" class="btn btn-secondary btn-lg">
                    <i class="fas fa-tags"></i> โปรโมชั่น
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Promotion Banner -->
<?php if (!empty($promotionProducts)): ?>
    <section class="section" style="background: var(--dark);">
        <div class="container">
            <div class="section-header">
                <h2 style="color: var(--white);">🔥 โปรโมชั่นพิเศษ</h2>
                <p style="color: var(--gray-400);">ลดราคาสูงสุด 20% เฉพาะช่วงนี้เท่านั้น!</p>
                <div class="gold-line"></div>
            </div>

            <div class="grid grid-4">
                <?php foreach ($promotionProducts as $product): ?>
                    <?php $discountedPrice = getDiscountedPrice($product['price'], $product['discount_percent']); ?>
                    <div class="card slide-up">
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
                            <div class="card-price">
                                <span class="current">
                                    <?= formatPrice($discountedPrice) ?>
                                </span>
                                <span class="original">
                                    <?= formatPrice($product['price']) ?>
                                </span>
                            </div>
                            <a href="/product.php?id=<?= $product['id'] ?>" class="btn btn-primary btn-block">
                                <i class="fas fa-eye"></i> ดูรายละเอียด
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<!-- Categories -->
<section class="section">
    <div class="container">
        <div class="section-header">
            <h2 style="color: var(--white);">หมวดหมู่สินค้า</h2>
            <p>เลือกดูน้ำหอมตามประเภทที่คุณต้องการ</p>
            <div class="gold-line"></div>
        </div>

        <div class="grid grid-4">
            <?php
            $categoryIcons = [
                'น้ำหอมผู้ชาย' => 'fas fa-mars',
                'น้ำหอมผู้หญิง' => 'fas fa-venus',
                'น้ำหอม Unisex' => 'fas fa-venus-mars',
                'Mini Size' => 'fas fa-flask'
            ];
            foreach ($categories as $category):
                $icon = $categoryIcons[$category['name']] ?? 'fas fa-spray-can';
                ?>
                <a href="/products.php?category=<?= $category['id'] ?>" class="card" style="text-align: center;">
                    <div class="card-body">
                        <div style="font-size: 3rem; color: var(--primary); margin-bottom: var(--space-md);">
                            <i class="<?= $icon ?>"></i>
                        </div>
                        <h4>
                            <?= htmlspecialchars($category['name']) ?>
                        </h4>
                        <p style="color: var(--gray-400); font-size: 0.875rem;">
                            <?= htmlspecialchars($category['description']) ?>
                        </p>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Featured Products -->
<section class="section" style="background: var(--white);">
    <div class="container">
        <div class="section-header">
            <h2>✨ สินค้าแนะนำ</h2>
            <p>น้ำหอมยอดนิยมที่คุณไม่ควรพลาด</p>
            <div class="gold-line"></div>
        </div>

        <div class="grid grid-4">
            <?php foreach ($featuredProducts as $product): ?>
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
                        <?php if ($product['is_promotion'] && $product['discount_percent'] > 0): ?>
                            <span class="card-badge">-
                                <?= $product['discount_percent'] ?>%
                            </span>
                        <?php endif; ?>
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
                        <div class="card-price">
                            <span class="current">
                                <?= formatPrice($discountedPrice) ?>
                            </span>
                            <?php if ($product['discount_percent'] > 0): ?>
                                <span class="original">
                                    <?= formatPrice($product['price']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <a href="/product.php?id=<?= $product['id'] ?>" class="btn btn-primary btn-block">
                            <i class="fas fa-eye"></i> ดูรายละเอียด
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="text-center mt-xl">
            <a href="/products.php" class="btn btn-secondary btn-lg">
                ดูสินค้าทั้งหมด <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </div>
</section>

<!-- Features -->
<section class="section">
    <div class="container">
        <div class="grid grid-4">
            <div class="card" style="text-align: center;">
                <div class="card-body">
                    <div style="font-size: 2.5rem; color: var(--primary); margin-bottom: var(--space-md);">
                        <i class="fas fa-certificate"></i>
                    </div>
                    <h4>ของแท้ 100%</h4>
                    <p style="color: var(--gray-400);">รับประกันสินค้าแท้ทุกชิ้น</p>
                </div>
            </div>
            <div class="card" style="text-align: center;">
                <div class="card-body">
                    <div style="font-size: 2.5rem; color: var(--primary); margin-bottom: var(--space-md);">
                        <i class="fas fa-truck"></i>
                    </div>
                    <h4>ส่งฟรีทั่วไทย</h4>
                    <p style="color: var(--gray-400);">เมื่อซื้อครบ 1,500 บาท</p>
                </div>
            </div>
            <div class="card" style="text-align: center;">
                <div class="card-body">
                    <div style="font-size: 2.5rem; color: var(--primary); margin-bottom: var(--space-md);">
                        <i class="fas fa-headset"></i>
                    </div>
                    <h4>บริการ 24/7</h4>
                    <p style="color: var(--gray-400);">พร้อมตอบคำถามตลอดเวลา</p>
                </div>
            </div>
            <div class="card" style="text-align: center;">
                <div class="card-body">
                    <div style="font-size: 2.5rem; color: var(--primary); margin-bottom: var(--space-md);">
                        <i class="fas fa-undo"></i>
                    </div>
                    <h4>คืนเงินได้</h4>
                    <p style="color: var(--gray-400);">ภายใน 7 วัน หากไม่พอใจ</p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once 'footer.php'; ?>