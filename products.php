<?php
/**
 * Products Listing - หน้าสินค้าทั้งหมด
 */
$pageTitle = 'สินค้าทั้งหมด - ParfumLuxe';
require_once 'header.php';

$db = getDB();

// Filters
$categoryId = isset($_GET['category']) ? (int) $_GET['category'] : null;
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Build query
$where = [];
$params = [];

if ($categoryId) {
    $where[] = "category_id = ?";
    $params[] = $categoryId;
}

if ($search) {
    $where[] = "(name LIKE ? OR brand LIKE ? OR description LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Sort
$orderBy = match ($sort) {
    'price_low' => 'price ASC',
    'price_high' => 'price DESC',
    'name' => 'name ASC',
    'discount' => 'discount_percent DESC',
    default => 'created_at DESC'
};

$stmt = $db->prepare("SELECT * FROM products $whereClause ORDER BY $orderBy");
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get categories for filter
$stmt = $db->query("SELECT * FROM categories");
$categories = $stmt->fetchAll();
?>

<section class="section">
    <div class="container">
        <div class="section-header">
            <h2 style = "color: var(--white);">
                🛍️ สินค้าทั้งหมด
            </h2>
            <p>น้ำหอมแบรนด์เนมคุณภาพดี ราคาถูก</p>
            <div class="gold-line"></div>
        </div>

        <!-- Filters -->
        <div class="card mb-xl">
            <div class="card-body">
                <form method="GET" class="d-flex flex-wrap gap-lg align-center">
                    <div style="flex: 1; min-width: 200px;">
                        <input type="text" name="search" class="form-control" placeholder="ค้นหาสินค้า..."
                            value="<?= htmlspecialchars($search) ?>">
                    </div>

                    <div>
                        <select name="category" class="form-control">
                            <option value="">ทุกหมวดหมู่</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $categoryId == $cat['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <select name="sort" class="form-control">
                            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>ใหม่ล่าสุด</option>
                            <option value="price_low" <?= $sort === 'price_low' ? 'selected' : '' ?>>ราคาต่ำ-สูง</option>
                            <option value="price_high" <?= $sort === 'price_high' ? 'selected' : '' ?>>ราคาสูง-ต่ำ</option>
                            <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>ชื่อ A-Z</option>
                            <option value="discount" <?= $sort === 'discount' ? 'selected' : '' ?>>ลดราคามากสุด</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> ค้นหา
                    </button>

                    <?php if ($search || $categoryId || $sort !== 'newest'): ?>
                        <a href="/products.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> ล้างตัวกรอง
                        </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Results -->
        <p class="mb-lg text-muted">พบ
            <?= count($products) ?> รายการ
        </p>

        <?php if (empty($products)): ?>
            <div class="card text-center p-xl">
                <i class="fas fa-box-open fa-3x" style="color: var(--gray-400); margin-bottom: var(--space-lg);"></i>
                <h3>ไม่พบสินค้า</h3>
                <p class="text-muted">ลองเปลี่ยนคำค้นหาหรือตัวกรองใหม่</p>
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
                            <?php if ($product['discount_percent'] > 0): ?>
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
                            <p class="text-muted mb-md" style="font-size: 0.875rem;">
                                <?= htmlspecialchars($product['size']) ?>
                            </p>
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
        <?php endif; ?>
    </div>
</section>

<?php require_once 'footer.php'; ?>