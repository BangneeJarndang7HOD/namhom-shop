<?php
/**
 * Product Detail - หน้ารายละเอียดสินค้า
 */
require_once 'config/database.php';

$productId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$productId) {
    setFlash('danger', 'ไม่พบสินค้าที่ต้องการ');
    redirect('/products.php');
}

$db = getDB();

// Get product
$stmt = $db->prepare("SELECT p.*, c.name as category_name 
                      FROM products p 
                      LEFT JOIN categories c ON p.category_id = c.id 
                      WHERE p.id = ?");
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    setFlash('danger', 'ไม่พบสินค้าที่ต้องการ');
    redirect('/products.php');
}

$pageTitle = $product['name'] . ' - ParfumLuxe';
$discountedPrice = getDiscountedPrice($product['price'], $product['discount_percent']);

// Get split-sale sizes (e.g. 3ml, 5ml, 10ml) for this product, if any
$stmt = $db->prepare("SELECT * FROM product_variants WHERE product_id = ? ORDER BY size_ml ASC");
$stmt->execute([$productId]);
$variants = $stmt->fetchAll();

// Get related products
$stmt = $db->prepare("SELECT * FROM products WHERE category_id = ? AND id != ? LIMIT 4");
$stmt->execute([$product['category_id'], $productId]);
$relatedProducts = $stmt->fetchAll();

// ===== Reviews & vote axes =====
$voteAxes = [
    'longevity' => ['label' => 'ความติดทน', 'icon' => 'fa-clock', 'max' => 5,
        'options' => [1 => 'อ่อนมาก', 2 => 'อ่อน', 3 => 'ปานกลาง', 4 => 'ติดทนนาน', 5 => 'ติดตลอดวัน']],
    'sillage' => ['label' => 'การกระจายกลิ่น', 'icon' => 'fa-wind', 'max' => 4,
        'options' => [1 => 'แผ่วใกล้ตัว', 2 => 'ปานกลาง', 3 => 'แผ่ไกล', 4 => 'แผ่มาก']],
    'gender' => ['label' => 'เหมาะกับเพศ', 'icon' => 'fa-venus-mars', 'max' => 5,
        'options' => [1 => 'หญิง', 2 => 'ค่อนไปทางหญิง', 3 => 'ยูนิเซ็กซ์', 4 => 'ค่อนไปทางชาย', 5 => 'ชาย']],
    'price_value' => ['label' => 'ความคุ้มค่า', 'icon' => 'fa-dollar-sign', 'max' => 5,
        'options' => [1 => 'แพงเกินไปมาก', 2 => 'แพงไป', 3 => 'โอเค', 4 => 'คุ้มค่า', 5 => 'คุ้มค่ามาก']],
];

// Distribution counts per axis for this product
$voteCounts = [];
foreach ($voteAxes as $axis => $meta) {
    $voteCounts[$axis] = array_fill(1, $meta['max'], 0);
    $stmt = $db->prepare("SELECT $axis AS v, COUNT(*) AS c FROM product_reviews WHERE product_id = ? AND $axis IS NOT NULL GROUP BY $axis");
    $stmt->execute([$productId]);
    foreach ($stmt->fetchAll() as $row) {
        $voteCounts[$axis][(int) $row['v']] = (int) $row['c'];
    }
}

// Current user's own vote (to pre-fill the sliders)
$myVote = ['longevity' => null, 'sillage' => null, 'gender' => null, 'price_value' => null];
if (isLoggedIn()) {
    $stmt = $db->prepare("SELECT longevity, sillage, gender, price_value FROM product_reviews WHERE product_id = ? AND user_id = ?");
    $stmt->execute([$productId, getCurrentUserId()]);
    if ($row = $stmt->fetch()) {
        $myVote = $row;
    }
}

// Review list (เฉพาะแถวที่มีข้อความรีวิว) + filter/search
$reviewFilter = $_GET['review_filter'] ?? 'all';
$reviewSearch = trim($_GET['review_q'] ?? '');

$reviewWhere = "r.product_id = ? AND r.comment IS NOT NULL AND r.comment != ''";
$reviewParams = [$productId];

if ($reviewSearch !== '') {
    $reviewWhere .= " AND r.comment LIKE ?";
    $reviewParams[] = '%' . $reviewSearch . '%';
}

if (in_array($reviewFilter, ['positive', 'neutral', 'negative'], true)) {
    $reviewWhere .= " AND r.sentiment = ?";
    $reviewParams[] = $reviewFilter;
}

$stmt = $db->prepare("
    SELECT r.*, u.username,
        SUM(CASE WHEN v.vote_type = 'like' THEN 1 ELSE 0 END) AS likes,
        SUM(CASE WHEN v.vote_type = 'dislike' THEN 1 ELSE 0 END) AS dislikes
    FROM product_reviews r
    JOIN users u ON r.user_id = u.id
    LEFT JOIN product_review_votes v ON v.review_id = r.id
    WHERE $reviewWhere
    GROUP BY r.id
    ORDER BY r.created_at DESC
");
$stmt->execute($reviewParams);
$reviews = $stmt->fetchAll();

$reviewCountAll = $db->prepare("SELECT COUNT(*) FROM product_reviews WHERE product_id = ? AND comment IS NOT NULL AND comment != ''");
$reviewCountAll->execute([$productId]);
$reviewCountAll = (int) $reviewCountAll->fetchColumn();

// Which of these reviews has the current user already liked/disliked?
$myReviewVotes = [];
if (isLoggedIn() && !empty($reviews)) {
    $ids = array_column($reviews, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("SELECT review_id, vote_type FROM product_review_votes WHERE user_id = ? AND review_id IN ($placeholders)");
    $stmt->execute([getCurrentUserId(), ...$ids]);
    foreach ($stmt->fetchAll() as $row) {
        $myReviewVotes[$row['review_id']] = $row['vote_type'];
    }
}

require_once 'header.php';
?>

<section class="section">
    <div class="container">
        <!-- Breadcrumb -->
        <nav style="margin-bottom: var(--space-xl); color: var(--gray-500);">
            <a href="/" style="color: var(--primary);">หน้าแรก</a>
            <span style="margin: 0 var(--space-sm);">/</span>
            <a href="/products.php" style="color: var(--primary);">สินค้า</a>
            <span style="margin: 0 var(--space-sm);">/</span>
            <span>
                <?= htmlspecialchars($product['name']) ?>
            </span>
        </nav>

        <div class="grid" style="grid-template-columns: 1fr 1fr; gap: var(--space-2xl);">
            <!-- Product Image -->
            <div>
                <div class="card-img" style="aspect-ratio: 1; border-radius: var(--radius-lg); overflow: hidden;">
                    <?php if ($product['image']): ?>
                        <img src="/uploads/products/<?= htmlspecialchars($product['image']) ?>"
                            alt="<?= htmlspecialchars($product['name']) ?>"
                            style="width: 100%; height: 100%; object-fit: cover;"
                            onerror="this.src='/images/placeholder.jpg'">
                    <?php else: ?>
                        <img src="/images/placeholder.jpg" alt="No image"
                            style="width: 100%; height: 100%; object-fit: cover;">
                    <?php endif; ?>
                </div>
            </div>

            <!-- Product Info -->
            <div>
                <span class="status-badge status-processing mb-md" style="display: inline-block;">
                    <?= htmlspecialchars($product['category_name'] ?? 'ไม่ระบุหมวดหมู่') ?>
                </span>

                <p class="card-brand" style="font-size: 1rem; margin-bottom: var(--space-sm);">
                    <?= htmlspecialchars($product['brand']) ?>
                </p>

                <h1 style="color: var(--white); margin-bottom: var(--space-md);">
                    <?= htmlspecialchars($product['name']) ?>
                </h1>

                <p class="text-muted mb-lg">
                    <?= htmlspecialchars($product['size']) ?>
                </p>

                <div class="card-price mb-lg" style="font-size: 1.25rem;">
                    <span class="current" style="font-size: 2rem;">
                        <?= formatPrice($discountedPrice) ?>
                    </span>
                    <?php if ($product['discount_percent'] > 0): ?>
                        <span class="original" style="font-size: 1.25rem;">
                            <?= formatPrice($product['price']) ?>
                        </span>
                        <span class="card-badge" style="position: static;">-
                            <?= $product['discount_percent'] ?>%
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Stock Status -->
                <?php if ($product['stock'] > 0): ?>
                    <p style="color: var(--success); margin-bottom: var(--space-lg);">
                        <i class="fas fa-check-circle"></i> มีสินค้า (
                        <?= $product['stock'] ?> ชิ้น)
                    </p>
                <?php else: ?>
                    <p style="color: var(--danger); margin-bottom: var(--space-lg);">
                        <i class="fas fa-times-circle"></i> สินค้าหมด
                    </p>
                <?php endif; ?>

                <!-- Add to Cart Form -->
                <?php if ($product['stock'] > 0): ?>
                    <form id="addToCartForm" class="mb-xl">
                        <?= csrfField() ?>
                        <input type="hidden" name="product_id" value="<?= $product['id'] ?>">

                        <div class="d-flex gap-md align-center mb-lg">
                            <label style="font-weight: 500;">จำนวน:</label>
                            <div class="cart-item-quantity">
                                <button type="button" onclick="changeQty(-1)">-</button>
                                <input type="number" name="quantity" id="qtyInput" value="1" min="1"
                                    max="<?= $product['stock'] ?>">
                                <button type="button" onclick="changeQty(1)">+</button>
                            </div>
                        </div>

                        <div class="d-flex gap-md" style="flex-wrap: wrap;">
                            <button type="submit" class="btn btn-primary btn-lg" style="flex: 1; min-width: 160px;">
                                <i class="fas fa-cart-plus"></i> เพิ่มลงตะกร้า
                            </button>
                            <button type="button" class="btn btn-secondary btn-lg" style="flex: 1; min-width: 120px;" onclick="buyNow()">
                                <i class="fas fa-bolt"></i> ซื้อเลย
                            </button>
                            <?php if (!empty($variants)): ?>
                                <button type="button" class="btn btn-secondary btn-lg" style="flex: 1; min-width: 120px;" onclick="openSizeModal()">
                                    <i class="fas fa-flask"></i> ซื้อแบ่งขาย
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                <?php elseif (!empty($variants)): ?>
                    <div class="d-flex gap-md mb-xl" style="flex-wrap: wrap;">
                        <button class="btn btn-dark btn-lg" style="flex: 1; min-width: 160px;" disabled>
                            <i class="fas fa-times"></i> ขวดเต็มหมด
                        </button>
                        <button type="button" class="btn btn-secondary btn-lg" style="flex: 1; min-width: 120px;" onclick="openSizeModal()">
                            <i class="fas fa-flask"></i> ซื้อแบ่งขาย
                        </button>
                    </div>
                <?php endif; ?>
                <?php if ($product['stock'] <= 0 && empty($variants)): ?>
                    <button class="btn btn-dark btn-lg btn-block" disabled>
                        <i class="fas fa-times"></i> สินค้าหมด
                    </button>
                <?php endif; ?>

                <!-- Description -->
                <div class="card">
                    <div class="card-body">
                        <h4 style ="color: gray"; class="mb-md">รายละเอียดสินค้า</h4>
                        <p style="line-height: 1.8;">
                            <?= nl2br(htmlspecialchars($product['description'])) ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Size Selection Modal (ซื้อแบ่งขาย) -->
        <?php if (!empty($variants)):
            $defaultVariant = null;
            foreach ($variants as $v) {
                if ($v['stock'] > 0) {
                    $defaultVariant = $v;
                    break;
                }
            }
            $defaultVariant = $defaultVariant ?? $variants[0];
            ?>
            <div class="modal-overlay" id="sizeModalOverlay" onclick="if (event.target === this) closeSizeModal()">
                <div class="card modal-box">
                    <button type="button" class="modal-close" onclick="closeSizeModal()">&times;</button>
                    <h3 style="color: var(--white); margin-bottom: var(--space-xs);">เลือกขนาดแบ่งขาย</h3>
                    <p class="text-muted mb-lg"><?= htmlspecialchars($product['name']) ?></p>

                    <div class="size-options mb-lg" id="modalSizeOptions">
                        <?php foreach ($variants as $v): ?>
                            <button type="button" class="size-option<?= $v['id'] === $defaultVariant['id'] ? ' selected' : '' ?>"
                                data-variant-id="<?= $v['id'] ?>" data-stock="<?= $v['stock'] ?>"
                                data-price-html="<?= htmlspecialchars(formatPrice($v['price'])) ?>"
                                <?= $v['stock'] <= 0 ? 'disabled' : '' ?>>
                                <?= (int) $v['size_ml'] ?>ml
                                <small><?= $v['stock'] > 0 ? formatPrice($v['price']) : 'สินค้าหมด' ?></small>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <div class="card-price mb-lg" style="font-size: 1.5rem;">
                        <span class="current" id="modalPrice">
                            <?= formatPrice($defaultVariant['price']) ?>
                        </span>
                    </div>

                    <div class="d-flex gap-md align-center mb-lg">
                        <label style="font-weight: 500;">จำนวน:</label>
                        <div class="cart-item-quantity">
                            <button type="button" onclick="changeModalQty(-1)">-</button>
                            <input type="number" id="modalQtyInput" value="1" min="1"
                                max="<?= max($defaultVariant['stock'], 1) ?>" readonly>
                            <button type="button" onclick="changeModalQty(1)">+</button>
                        </div>
                    </div>

                    <div class="d-flex gap-md">
                        <button type="button" class="btn btn-primary btn-lg" id="modalAddToCartBtn" style="flex: 1;"
                            onclick="modalAddToCart()" <?= $defaultVariant['stock'] <= 0 ? 'disabled' : '' ?>>
                            <i class="fas fa-cart-plus"></i> เพิ่มลงตะกร้า
                        </button>
                        <button type="button" class="btn btn-secondary btn-lg" id="modalBuyNowBtn" onclick="modalBuyNow()"
                            <?= $defaultVariant['stock'] <= 0 ? 'disabled' : '' ?>>
                            <i class="fas fa-bolt"></i> ซื้อเลย
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Vote Widgets -->
        <div class="mt-xl">
            <h2 class="mb-lg">ให้คะแนนน้ำหอมนี้</h2>
            <div class="grid grid-4">
                <?php foreach ($voteAxes as $axis => $meta): ?>
                    <div class="card">
                        <div class="card-body">
                            <div class="vote-widget-icon"><i class="fas <?= $meta['icon'] ?>"></i></div>
                            <div class="vote-widget-label"><?= htmlspecialchars($meta['label']) ?></div>

                            <?php if (isLoggedIn()): ?>
                                <input type="range" class="vote-slider" min="0" max="<?= $meta['max'] ?>"
                                    value="<?= $myVote[$axis] ?? 0 ?>" data-axis="<?= $axis ?>"
                                    oninput="updateSliderLabel(this)" onchange="submitVote(this)">
                                <p class="text-center text-muted mb-md" style="font-size: 0.8rem;" id="sliderLabel-<?= $axis ?>">
                                    <?= $myVote[$axis] ? htmlspecialchars($meta['options'][$myVote[$axis]]) : 'ยังไม่ได้โหวต (เลื่อนเพื่อโหวต)' ?>
                                </p>
                            <?php endif; ?>

                            <div id="voteBars-<?= $axis ?>">
                                <?php
                                $maxCount = max($voteCounts[$axis]) ?: 1;
                                foreach ($meta['options'] as $val => $label):
                                    $count = $voteCounts[$axis][$val];
                                    $pct = $count > 0 ? max(4, round($count / $maxCount * 100)) : 0;
                                    ?>
                                    <div class="vote-bar-row">
                                        <span class="vote-bar-label"><?= htmlspecialchars($label) ?></span>
                                        <span class="vote-bar-count"><?= $count ?></span>
                                        <div class="vote-bar-track">
                                            <div class="vote-bar-fill" style="width: <?= $pct ?>%;"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if (!isLoggedIn()): ?>
                <p class="text-muted mt-md"><a href="/login.php" style="color: var(--primary);">เข้าสู่ระบบ</a> เพื่อร่วมโหวต</p>
            <?php endif; ?>
        </div>

        <!-- Reviews -->
        <div class="mt-xl">
            <h2 class="mb-lg">รีวิวจากผู้ใช้งาน (<?= $reviewCountAll ?>)</h2>

            <div class="card mb-xl">
                <div class="card-body">
                    <form method="GET" class="d-flex flex-wrap gap-md align-center mb-lg">
                        <input type="hidden" name="id" value="<?= $productId ?>">
                        <input type="text" name="review_q" class="form-control" style="flex: 1; min-width: 200px;"
                            placeholder="ค้นหารีวิว..." value="<?= htmlspecialchars($reviewSearch) ?>">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> ค้นหา</button>
                        <?php if ($reviewSearch !== ''): ?>
                            <a href="?id=<?= $productId ?>&review_filter=<?= $reviewFilter ?>" class="btn btn-secondary">ล้างคำค้นหา</a>
                        <?php endif; ?>
                    </form>

                    <div class="review-tabs">
                        <a class="review-tab <?= $reviewFilter === 'all' ? 'active' : '' ?>"
                            href="?id=<?= $productId ?>&review_filter=all<?= $reviewSearch !== '' ? '&review_q=' . urlencode($reviewSearch) : '' ?>">ทั้งหมด</a>
                        <a class="review-tab <?= $reviewFilter === 'positive' ? 'active' : '' ?>"
                            href="?id=<?= $productId ?>&review_filter=positive<?= $reviewSearch !== '' ? '&review_q=' . urlencode($reviewSearch) : '' ?>">รีวิวดี</a>
                        <a class="review-tab <?= $reviewFilter === 'neutral' ? 'active' : '' ?>"
                            href="?id=<?= $productId ?>&review_filter=neutral<?= $reviewSearch !== '' ? '&review_q=' . urlencode($reviewSearch) : '' ?>">เฉยๆ</a>
                        <a class="review-tab <?= $reviewFilter === 'negative' ? 'active' : '' ?>"
                            href="?id=<?= $productId ?>&review_filter=negative<?= $reviewSearch !== '' ? '&review_q=' . urlencode($reviewSearch) : '' ?>">รีวิวไม่ดี</a>
                    </div>
                    <p class="text-muted" style="font-size: 0.8rem; margin-top: -8px;">
                        <i class="fas fa-info-circle"></i> แบ่งดี/ไม่ดีจากความรู้สึกที่ผู้เขียนเลือกเองตอนโพสต์ ไม่ใช้ยอดไลค์/ดิสไลค์ตัดสิน
                    </p>

                    <?php if (isLoggedIn()): ?>
                        <form id="reviewForm" class="mb-xl mt-lg">
                            <?= csrfField() ?>
                            <input type="hidden" name="product_id" value="<?= $productId ?>">

                            <div class="form-group">
                                <label class="form-label">ความรู้สึกโดยรวม *</label>
                                <div class="d-flex gap-md">
                                    <label class="d-flex align-center gap-sm" style="cursor: pointer;">
                                        <input type="radio" name="sentiment" value="positive" required> <i class="fas fa-smile" style="color: var(--success);"></i> ชอบ
                                    </label>
                                    <label class="d-flex align-center gap-sm" style="cursor: pointer;">
                                        <input type="radio" name="sentiment" value="neutral" required> <i class="fas fa-meh" style="color: var(--warning);"></i> เฉยๆ
                                    </label>
                                    <label class="d-flex align-center gap-sm" style="cursor: pointer;">
                                        <input type="radio" name="sentiment" value="negative" required> <i class="fas fa-frown" style="color: var(--danger);"></i> ไม่ชอบ
                                    </label>
                                </div>
                            </div>

                            <div class="form-group">
                                <textarea name="comment" class="form-control" rows="3" maxlength="2000"
                                    placeholder="เขียนรีวิวน้ำหอมนี้... (เลือกคะแนนด้านบนก่อนได้ถ้าต้องการ)" required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> โพสต์รีวิว</button>
                        </form>
                    <?php else: ?>
                        <p class="text-muted mb-xl"><a href="/login.php" style="color: var(--primary);">เข้าสู่ระบบ</a> เพื่อเขียนรีวิว</p>
                    <?php endif; ?>

                    <div id="reviewList">
                        <?php if (empty($reviews)): ?>
                            <p class="text-muted text-center p-lg">ยังไม่มีรีวิว เป็นคนแรกที่รีวิวสินค้านี้!</p>
                        <?php else: ?>
                            <?php foreach ($reviews as $r): ?>
                                <div class="review-card" id="review-<?= $r['id'] ?>">
                                    <div class="review-avatar"><?= strtoupper(substr($r['username'], 0, 1)) ?></div>
                                    <div style="flex: 1; min-width: 0;">
                                        <div class="d-flex justify-between align-center flex-wrap gap-sm">
                                            <div>
                                                <?php if ($r['sentiment'] === 'positive'): ?>
                                                    <i class="fas fa-smile" style="color: var(--success);" title="ชอบ"></i>
                                                <?php elseif ($r['sentiment'] === 'negative'): ?>
                                                    <i class="fas fa-frown" style="color: var(--danger);" title="ไม่ชอบ"></i>
                                                <?php elseif ($r['sentiment'] === 'neutral'): ?>
                                                    <i class="fas fa-meh" style="color: var(--warning);" title="เฉยๆ"></i>
                                                <?php endif; ?>
                                                <strong style="color: var(--primary);"><?= htmlspecialchars($r['username']) ?></strong>
                                                <span class="text-muted" style="font-size: 0.8rem;">
                                                    <?= date('d/m/y H:i', strtotime($r['created_at'])) ?>
                                                </span>
                                            </div>
                                            <?php if (isLoggedIn() && ((int) $r['user_id'] === (int) getCurrentUserId() || isAdmin())): ?>
                                                <button type="button" class="review-vote-btn" onclick="deleteReview(<?= $r['id'] ?>)">
                                                    <i class="fas fa-trash"></i> ลบ
                                                </button>
                                            <?php endif; ?>
                                        </div>

                                        <div class="d-flex flex-wrap gap-sm mt-sm mb-sm">
                                            <?php foreach ($voteAxes as $axis => $meta): ?>
                                                <?php if (!empty($r[$axis])): ?>
                                                    <span class="status-badge status-processing">
                                                        <i class="fas <?= $meta['icon'] ?>"></i>
                                                        <?= htmlspecialchars($meta['options'][$r[$axis]]) ?>
                                                    </span>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>

                                        <p style="line-height: 1.6;"><?= nl2br(htmlspecialchars($r['comment'])) ?></p>

                                        <?php if (isLoggedIn()): ?>
                                            <div class="d-flex gap-lg mt-sm">
                                                <button type="button"
                                                    class="review-vote-btn <?= ($myReviewVotes[$r['id']] ?? '') === 'like' ? 'active' : '' ?>"
                                                    onclick="voteReview(<?= $r['id'] ?>, 'like', this)">
                                                    <i class="fas fa-thumbs-up"></i> <span class="likeCount"><?= (int) $r['likes'] ?></span>
                                                </button>
                                                <button type="button"
                                                    class="review-vote-btn <?= ($myReviewVotes[$r['id']] ?? '') === 'dislike' ? 'active' : '' ?>"
                                                    onclick="voteReview(<?= $r['id'] ?>, 'dislike', this)">
                                                    <i class="fas fa-thumbs-down"></i> <span class="dislikeCount"><?= (int) $r['dislikes'] ?></span>
                                                </button>
                                                <button type="button" class="review-vote-btn" onclick="shareReview(<?= $r['id'] ?>)">
                                                    <i class="fas fa-share-alt"></i> แชร์
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <div class="d-flex gap-lg mt-sm text-muted" style="font-size: 0.875rem;">
                                                <span><i class="fas fa-thumbs-up"></i> <?= (int) $r['likes'] ?></span>
                                                <span><i class="fas fa-thumbs-down"></i> <?= (int) $r['dislikes'] ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Related Products -->
        <?php if (!empty($relatedProducts)): ?>
            <div class="mt-xl">
                <h2 class="mb-lg">สินค้าที่เกี่ยวข้อง</h2>
                <div class="grid grid-4">
                    <?php foreach ($relatedProducts as $related): ?>
                        <?php $relatedPrice = getDiscountedPrice($related['price'], $related['discount_percent']); ?>
                        <div class="card">
                            <div class="card-img">
                                <?php if ($related['image']): ?>
                                    <img src="/uploads/products/<?= htmlspecialchars($related['image']) ?>"
                                        alt="<?= htmlspecialchars($related['name']) ?>"
                                        onerror="this.src='/images/placeholder.jpg'">
                                <?php else: ?>
                                    <img src="/images/placeholder.jpg" alt="No image">
                                <?php endif; ?>
                                <?php if ($related['discount_percent'] > 0): ?>
                                    <span class="card-badge">-
                                        <?= $related['discount_percent'] ?>%
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <p class="card-brand">
                                    <?= htmlspecialchars($related['brand']) ?>
                                </p>
                                <h3 class="card-title">
                                    <a href="/product.php?id=<?= $related['id'] ?>">
                                        <?= htmlspecialchars($related['name']) ?>
                                    </a>
                                </h3>
                                <div class="card-price">
                                    <span class="current">
                                        <?= formatPrice($relatedPrice) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
    function changeQty(delta) {
        const input = document.getElementById('qtyInput');
        let val = parseInt(input.value) + delta;
        val = Math.max(1, Math.min(val, parseInt(input.max) || 0));
        input.value = val;
    }

    document.getElementById('addToCartForm')?.addEventListener('submit', async function (e) {
        e.preventDefault();

        const formData = new FormData(this);
        formData.append('action', 'add');

        try {
            const response = await fetch('/api/cart.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                alert('เพิ่มสินค้าลงตะกร้าแล้ว!');
                location.reload();
            } else {
                alert(data.message || 'เกิดข้อผิดพลาด');
            }
        } catch (error) {
            alert('เกิดข้อผิดพลาด กรุณาลองใหม่');
        }
    });

    function buyNow() {
        const form = document.getElementById('addToCartForm');
        const formData = new FormData(form);
        formData.append('action', 'add');

        fetch('/api/cart.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = '/cart.php';
                } else {
                    alert(data.message || 'กรุณาเข้าสู่ระบบก่อน');
                    window.location.href = '/login.php';
                }
            });
    }

    // ===== Size Selection Modal (ซื้อแบ่งขาย) =====
    const CSRF_TOKEN = <?= json_encode(csrfToken()) ?>;

    function openSizeModal() {
        document.getElementById('sizeModalOverlay')?.classList.add('open');
    }

    function closeSizeModal() {
        document.getElementById('sizeModalOverlay')?.classList.remove('open');
    }

    function selectModalSize(el) {
        document.querySelectorAll('#modalSizeOptions .size-option').forEach(o => o.classList.remove('selected'));
        el.classList.add('selected');

        const stock = parseInt(el.dataset.stock, 10) || 0;
        const qtyInput = document.getElementById('modalQtyInput');
        qtyInput.max = Math.max(stock, 1);
        qtyInput.value = stock > 0 ? 1 : 1;

        document.getElementById('modalPrice').innerHTML = el.dataset.priceHtml;

        document.getElementById('modalAddToCartBtn').disabled = stock <= 0;
        document.getElementById('modalBuyNowBtn').disabled = stock <= 0;
    }

    document.querySelectorAll('#modalSizeOptions .size-option').forEach(btn => {
        btn.addEventListener('click', () => selectModalSize(btn));
    });

    function changeModalQty(delta) {
        const input = document.getElementById('modalQtyInput');
        let val = parseInt(input.value) + delta;
        val = Math.max(1, Math.min(val, parseInt(input.max) || 1));
        input.value = val;
    }

    function getSelectedModalVariant() {
        return document.querySelector('#modalSizeOptions .size-option.selected');
    }

    async function modalSubmit(redirectToCart) {
        const selected = getSelectedModalVariant();
        if (!selected) {
            alert('กรุณาเลือกขนาด');
            return;
        }

        const formData = new FormData();
        formData.append('csrf_token', CSRF_TOKEN);
        formData.append('action', 'add');
        formData.append('product_id', <?= (int) $product['id'] ?>);
        formData.append('variant_id', selected.dataset.variantId);
        formData.append('quantity', document.getElementById('modalQtyInput').value);

        try {
            const response = await fetch('/api/cart.php', { method: 'POST', body: formData });
            const data = await response.json();

            if (data.success) {
                if (redirectToCart) {
                    window.location.href = '/cart.php';
                } else {
                    alert('เพิ่มสินค้าลงตะกร้าแล้ว!');
                    location.reload();
                }
            } else {
                alert(data.message || 'เกิดข้อผิดพลาด');
            }
        } catch (error) {
            alert('เกิดข้อผิดพลาด กรุณาลองใหม่');
        }
    }

    function modalAddToCart() {
        modalSubmit(false);
    }

    function modalBuyNow() {
        modalSubmit(true);
    }

    // ===== Vote widgets =====
    const PRODUCT_ID = <?= (int) $product['id'] ?>;
    const AXIS_OPTIONS = <?= json_encode(array_map(fn($m) => $m['options'], $voteAxes)) ?>;

    function updateSliderLabel(input) {
        const axis = input.dataset.axis;
        const val = parseInt(input.value, 10);
        const labelEl = document.getElementById('sliderLabel-' + axis);
        labelEl.textContent = val > 0 ? AXIS_OPTIONS[axis][val] : 'ยังไม่ได้โหวต (เลื่อนเพื่อโหวต)';
    }

    async function submitVote(input) {
        const axis = input.dataset.axis;
        const val = parseInt(input.value, 10);
        if (val < 1) return;

        const formData = new FormData();
        formData.append('csrf_token', CSRF_TOKEN);
        formData.append('product_id', PRODUCT_ID);
        formData.append('axis', axis);
        formData.append('value', val);

        try {
            const response = await fetch('/api/product_vote.php', { method: 'POST', body: formData });
            const data = await response.json();
            if (data.success) {
                redrawVoteBars(axis, data.counts);
            } else {
                alert(data.message || 'เกิดข้อผิดพลาด');
            }
        } catch (error) {
            alert('เกิดข้อผิดพลาด กรุณาลองใหม่');
        }
    }

    function redrawVoteBars(axis, counts) {
        const container = document.getElementById('voteBars-' + axis);
        const rows = container.querySelectorAll('.vote-bar-row');
        const values = Object.values(counts);
        const maxCount = Math.max(...values, 1);

        rows.forEach((row, i) => {
            const val = i + 1;
            const count = counts[val] || 0;
            row.querySelector('.vote-bar-count').textContent = count;
            const pct = count > 0 ? Math.max(4, Math.round(count / maxCount * 100)) : 0;
            row.querySelector('.vote-bar-fill').style.width = pct + '%';
        });
    }

    // ===== Reviews =====
    document.getElementById('reviewForm')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'submit');

        try {
            const response = await fetch('/api/review.php', { method: 'POST', body: formData });
            const data = await response.json();
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'เกิดข้อผิดพลาด');
            }
        } catch (error) {
            alert('เกิดข้อผิดพลาด กรุณาลองใหม่');
        }
    });

    async function deleteReview(reviewId) {
        if (!confirm('ต้องการลบรีวิวนี้?')) return;

        const formData = new FormData();
        formData.append('csrf_token', CSRF_TOKEN);
        formData.append('action', 'delete');
        formData.append('review_id', reviewId);

        try {
            const response = await fetch('/api/review.php', { method: 'POST', body: formData });
            const data = await response.json();
            if (data.success) {
                document.getElementById('review-' + reviewId)?.remove();
            } else {
                alert(data.message || 'เกิดข้อผิดพลาด');
            }
        } catch (error) {
            alert('เกิดข้อผิดพลาด กรุณาลองใหม่');
        }
    }

    async function voteReview(reviewId, voteType, btn) {
        const formData = new FormData();
        formData.append('csrf_token', CSRF_TOKEN);
        formData.append('review_id', reviewId);
        formData.append('vote_type', voteType);

        try {
            const response = await fetch('/api/review_vote.php', { method: 'POST', body: formData });
            const data = await response.json();
            if (data.success) {
                const card = document.getElementById('review-' + reviewId);
                card.querySelector('.likeCount').textContent = data.likes;
                card.querySelector('.dislikeCount').textContent = data.dislikes;
                card.querySelectorAll('.review-vote-btn').forEach(b => b.classList.remove('active'));
                if (data.my_vote === 'like') card.querySelectorAll('.review-vote-btn')[0].classList.add('active');
                if (data.my_vote === 'dislike') card.querySelectorAll('.review-vote-btn')[1].classList.add('active');
            } else {
                alert(data.message || 'เกิดข้อผิดพลาด');
            }
        } catch (error) {
            alert('เกิดข้อผิดพลาด กรุณาลองใหม่');
        }
    }

    function shareReview(reviewId) {
        const url = window.location.origin + window.location.pathname + '?id=' + PRODUCT_ID + '#review-' + reviewId;
        if (navigator.clipboard) {
            navigator.clipboard.writeText(url).then(() => alert('คัดลอกลิงก์รีวิวแล้ว'));
        } else {
            prompt('คัดลอกลิงก์รีวิว:', url);
        }
    }
</script>

<?php require_once 'footer.php'; ?>