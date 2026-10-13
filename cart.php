<?php
/**
 * Shopping Cart - ตะกร้าสินค้า
 */
$pageTitle = 'ตะกร้าสินค้า - ParfumLuxe';
require_once 'header.php';

if (!isLoggedIn()) {
    setFlash('warning', 'กรุณาเข้าสู่ระบบก่อนใช้งานตะกร้าสินค้า');
    redirect('/login.php');
}

$db = getDB();
$userId = getCurrentUserId();

// Get cart items (รวมขนาดแบ่งขาย/variant ถ้ามี)
$stmt = $db->prepare("
    SELECT c.*, p.name, p.brand, p.image, p.size AS product_size,
           p.price AS product_price, p.discount_percent, p.stock AS product_stock,
           v.size_ml AS variant_size_ml, v.price AS variant_price, v.stock AS variant_stock
    FROM cart c
    JOIN products p ON c.product_id = p.id
    LEFT JOIN product_variants v ON c.variant_id = v.id
    WHERE c.user_id = ?
    ORDER BY c.created_at DESC
");
$stmt->execute([$userId]);
$cartItems = $stmt->fetchAll();

// คำนวณราคา/สต็อก/ป้ายขนาดที่ใช้จริงต่อแถว (เต็มขวดใช้ราคาส่วนลด, แบ่งขายใช้ราคา variant ตรงๆ)
foreach ($cartItems as &$item) {
    if ($item['variant_id']) {
        $item['effective_price'] = (float) $item['variant_price'];
        $item['effective_stock'] = (int) $item['variant_stock'];
        $item['size_label'] = $item['variant_size_ml'] . 'ml';
    } else {
        $item['effective_price'] = getDiscountedPrice($item['product_price'], $item['discount_percent']);
        $item['effective_stock'] = (int) $item['product_stock'];
        $item['size_label'] = $item['product_size'];
    }
}
unset($item);

// Calculate totals
$subtotal = 0;
foreach ($cartItems as $item) {
    $subtotal += $item['effective_price'] * $item['quantity'];
}
$shipping = $subtotal >= 1500 ? 0 : 50;
$total = $subtotal + $shipping;
?>

<section class="section">
    <div class="container">
        <h1 class="mb-xl"><i class="fas fa-shopping-cart text-gold"></i> ตะกร้าสินค้า</h1>

        <?php if (empty($cartItems)): ?>
            <div class="card text-center p-xl">
                <i class="fas fa-shopping-cart fa-4x" style="color: var(--gray-400); margin-bottom: var(--space-xl);"></i>
                <h3>ตะกร้าสินค้าว่างเปล่า</h3>
                <p class="text-muted mb-lg">ยังไม่มีสินค้าในตะกร้า เริ่มช้อปปิ้งกันเลย!</p>
                <a href="/products.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-shopping-bag"></i> เลือกซื้อสินค้า
                </a>
            </div>
        <?php else: ?>
            <div class="grid" style="grid-template-columns: 1fr 350px; gap: var(--space-xl); align-items: start;">
                <!-- Cart Items -->
                <div>
                    <?php foreach ($cartItems as $item): ?>
                        <?php $rowKey = $item['product_id'] . '-' . ($item['variant_id'] ?? 0); ?>
                        <div class="cart-item" id="cart-item-<?= $rowKey ?>">
                            <div class="cart-item-img">
                                <?php if ($item['image']): ?>
                                    <img src="/uploads/products/<?= htmlspecialchars($item['image']) ?>"
                                        alt="<?= htmlspecialchars($item['name']) ?>"
                                        onerror="this.src='/images/placeholder.jpg'">
                                <?php else: ?>
                                    <img src="/images/placeholder.jpg" alt="No image">
                                <?php endif; ?>
                            </div>

                            <div class="cart-item-info">
                                <p class="cart-item-brand text-muted">
                                    <?= htmlspecialchars($item['brand']) ?>
                                </p>
                                <h4 class="cart-item-name">
                                    <a href="/product.php?id=<?= $item['product_id'] ?>">
                                        <?= htmlspecialchars($item['name']) ?>
                                    </a>
                                </h4>
                                <p class="text-muted" style="font-size: 0.875rem;">
                                    <?= htmlspecialchars($item['size_label']) ?>
                                    <?= $item['variant_id'] ? ' <span class="status-badge status-processing">แบ่งขาย</span>' : '' ?>
                                </p>
                            </div>

                            <div class="cart-item-quantity">
                                <button onclick="updateCartQty(<?= $item['product_id'] ?>, <?= $item['variant_id'] ?: 0 ?>, -1)">-</button>
                                <input type="number" value="<?= $item['quantity'] ?>" min="1" max="<?= $item['effective_stock'] ?>"
                                    id="qty-<?= $rowKey ?>" readonly>
                                <button onclick="updateCartQty(<?= $item['product_id'] ?>, <?= $item['variant_id'] ?: 0 ?>, 1)">+</button>
                            </div>

                            <div class="cart-item-price">
                                <?= formatPrice($item['effective_price'] * $item['quantity']) ?>
                            </div>

                            <button class="cart-item-remove" onclick="removeFromCart(<?= $item['product_id'] ?>, <?= $item['variant_id'] ?: 0 ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Cart Summary -->
                <div class="cart-summary">
                    <h3>สรุปคำสั่งซื้อ</h3>

                    <div class="cart-summary-row">
                        <span>ราคาสินค้า</span>
                        <span id="subtotal">
                            <?= formatPrice($subtotal) ?>
                        </span>
                    </div>

                    <div class="cart-summary-row">
                        <span>ค่าจัดส่ง</span>
                        <span id="shipping">
                            <?php if ($shipping == 0): ?>
                                <span style="color: var(--success);">ฟรี!</span>
                            <?php else: ?>
                                <?= formatPrice($shipping) ?>
                            <?php endif; ?>
                        </span>
                    </div>

                    <?php if ($subtotal < 1500): ?>
                        <p style="font-size: 0.875rem; color: var(--gray-400); margin-bottom: var(--space-md);">
                            <i class="fas fa-info-circle"></i>
                            ซื้อเพิ่มอีก
                            <?= formatPrice(1500 - $subtotal) ?> ส่งฟรี!
                        </p>
                    <?php endif; ?>

                    <div class="cart-summary-row cart-summary-total">
                        <span>รวมทั้งสิ้น</span>
                        <span id="total">
                            <?= formatPrice($total) ?>
                        </span>
                    </div>

                    <a href="/checkout.php" class="btn btn-primary btn-block btn-lg mt-lg">
                        <i class="fas fa-credit-card"></i> ดำเนินการชำระเงิน
                    </a>

                    <a href="/products.php" class="btn btn-secondary btn-block mt-md">
                        <i class="fas fa-arrow-left"></i> เลือกซื้อสินค้าเพิ่ม
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
    const CSRF_TOKEN = <?= json_encode(csrfToken()) ?>;

    async function updateCartQty(productId, variantId, delta) {
        const rowKey = productId + '-' + variantId;
        const input = document.getElementById('qty-' + rowKey);
        let newQty = parseInt(input.value) + delta;
        newQty = Math.max(1, newQty);

        const formData = new FormData();
        formData.append('action', 'update');
        formData.append('product_id', productId);
        if (variantId) formData.append('variant_id', variantId);
        formData.append('quantity', newQty);
        formData.append('csrf_token', CSRF_TOKEN);

        try {
            const response = await fetch('/api/cart.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'เกิดข้อผิดพลาด');
            }
        } catch (error) {
            console.error(error);
        }
    }

    async function removeFromCart(productId, variantId) {
        if (!confirm('ต้องการลบสินค้านี้ออกจากตะกร้า?')) return;

        const formData = new FormData();
        formData.append('action', 'remove');
        formData.append('product_id', productId);
        if (variantId) formData.append('variant_id', variantId);
        formData.append('csrf_token', CSRF_TOKEN);

        try {
            const response = await fetch('/api/cart.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'เกิดข้อผิดพลาด');
            }
        } catch (error) {
            console.error(error);
        }
    }
</script>

<?php require_once 'footer.php'; ?>
