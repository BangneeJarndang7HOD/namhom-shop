<?php
/**
 * Checkout - หน้าชำระเงิน
 */
$pageTitle = 'ชำระเงิน - ParfumLuxe';
require_once 'header.php';

if (!isLoggedIn()) {
    setFlash('warning', 'กรุณาเข้าสู่ระบบก่อนทำการสั่งซื้อ');
    redirect('/login.php');
}

$db = getDB();
$userId = getCurrentUserId();
$user = getCurrentUser();

// Get cart items (รวมขนาดแบ่งขาย/variant ถ้ามี)
$stmt = $db->prepare("
    SELECT c.*, p.name, p.brand, p.image, p.size AS product_size,
           p.price AS product_price, p.discount_percent, p.stock AS product_stock,
           v.size_ml AS variant_size_ml, v.price AS variant_price, v.stock AS variant_stock
    FROM cart c
    JOIN products p ON c.product_id = p.id
    LEFT JOIN product_variants v ON c.variant_id = v.id
    WHERE c.user_id = ?
");
$stmt->execute([$userId]);
$cartItems = $stmt->fetchAll();

if (empty($cartItems)) {
    setFlash('warning', 'ตะกร้าสินค้าว่างเปล่า');
    redirect('/cart.php');
}

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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf('/checkout.php');

    $address = sanitize($_POST['address'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $paymentMethod = $_POST['payment_method'] ?? 'cod';
    $notes = sanitize($_POST['notes'] ?? '');

    $errors = [];
    if (empty($address))
        $errors[] = 'กรุณากรอกที่อยู่จัดส่ง';
    if (empty($phone))
        $errors[] = 'กรุณากรอกเบอร์โทรศัพท์';
    if (!in_array($paymentMethod, ['cod', 'transfer']))
        $errors[] = 'วิธีชำระเงินไม่ถูกต้อง';

    if (empty($errors)) {
        try {
            $db->beginTransaction();

            // Re-check stock under lock to prevent overselling from concurrent orders
            foreach ($cartItems as $item) {
                if ($item['variant_id']) {
                    $stmt = $db->prepare("SELECT stock FROM product_variants WHERE id = ? FOR UPDATE");
                    $stmt->execute([$item['variant_id']]);
                } else {
                    $stmt = $db->prepare("SELECT stock FROM products WHERE id = ? FOR UPDATE");
                    $stmt->execute([$item['product_id']]);
                }
                $currentStock = $stmt->fetchColumn();

                if ($currentStock === false || $currentStock < $item['quantity']) {
                    throw new Exception('สินค้า "' . $item['name'] . ($item['variant_id'] ? ' (' . $item['size_label'] . ')' : '') . '" มีไม่เพียงพอ กรุณาแก้ไขตะกร้าสินค้า');
                }
            }

            // Create order
            $status = $paymentMethod === 'cod' ? 'processing' : 'pending';
            $paymentStatus = $paymentMethod === 'cod' ? 'pending' : 'pending';

            $stmt = $db->prepare("
                INSERT INTO orders (user_id, total_amount, shipping_address, phone, payment_method, status, payment_status, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $total, $address, $phone, $paymentMethod, $status, $paymentStatus, $notes]);
            $orderId = $db->lastInsertId();

            // Create order items
            foreach ($cartItems as $item) {
                $stmt = $db->prepare("
                    INSERT INTO order_items (order_id, product_id, variant_id, variant_label, quantity, price)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $orderId,
                    $item['product_id'],
                    $item['variant_id'],
                    $item['variant_id'] ? $item['size_label'] : null,
                    $item['quantity'],
                    $item['effective_price'],
                ]);

                // Reduce stock
                if ($item['variant_id']) {
                    $stmt = $db->prepare("UPDATE product_variants SET stock = stock - ? WHERE id = ?");
                    $stmt->execute([$item['quantity'], $item['variant_id']]);
                } else {
                    $stmt = $db->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
                    $stmt->execute([$item['quantity'], $item['product_id']]);
                }
            }

            // Clear cart
            $stmt = $db->prepare("DELETE FROM cart WHERE user_id = ?");
            $stmt->execute([$userId]);

            $db->commit();

            // Redirect based on payment method
            if ($paymentMethod === 'transfer') {
                redirect('/payment.php?order=' . $orderId);
            } else {
                setFlash('success', 'สั่งซื้อสำเร็จ! คำสั่งซื้อ #' . $orderId . ' - ชำระเงินปลายทาง');
                redirect('/profile.php?tab=orders');
            }

        } catch (PDOException $e) {
            $db->rollBack();
            error_log('Checkout failed: ' . $e->getMessage());
            $errors[] = 'เกิดข้อผิดพลาดในระบบ กรุณาลองใหม่อีกครั้ง';
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = $e->getMessage();
        }
    }
}
?>

<section class="section">
    <div class="container">
        <h1 class="mb-xl"><i class="fas fa-credit-card text-gold"></i> ชำระเงิน</h1>

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
            <div class="grid" style="grid-template-columns: 1fr 400px; gap: var(--space-xl); align-items: start;">
                <!-- Checkout Form -->
                <div class="card">
                    <div class="card-body">
                        <h3 style="color: var(--white); margin-bottom: var(--space-lg);">ข้อมูลการจัดส่ง</h3>

                        <div class="form-group">
                            <label class="form-label">ที่อยู่จัดส่ง *</label>
                            <textarea name="address" class="form-control" rows="3" required
                                placeholder="บ้านเลขที่ ซอย ถนน แขวง/ตำบล เขต/อำเภอ จังหวัด รหัสไปรษณีย์"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label">เบอร์โทรศัพท์ *</label>
                            <input type="tel" name="phone" class="form-control" required placeholder="08X-XXX-XXXX"
                                value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">หมายเหตุ (ถ้ามี)</label>
                            <textarea name="notes" class="form-control" rows="2"
                                placeholder="เช่น เวลาที่สะดวกรับของ, ชื่อผู้รับ"></textarea>
                        </div>

                        <hr style="margin: var(--space-xl) 0; border: none; border-top: 1px solid var(--gray-200);">

                        <h3 style="color: var(--white); margin-bottom: var(--space-lg);">วิธีชำระเงิน</h3>

                        <div class="payment-methods">
                            <label class="payment-option selected">
                                <input type="radio" name="payment_method" value="cod" checked>
                                <div class="payment-option-icon">
                                    <i class="fas fa-money-bill-wave"></i>
                                </div>
                                <div class="payment-option-info">
                                    <h4>ชำระเงินปลายทาง (COD)</h4>
                                    <p>ชำระเงินเมื่อได้รับสินค้า</p>
                                </div>
                            </label>

                            <label class="payment-option">
                                <input type="radio" name="payment_method" value="transfer">
                                <div class="payment-option-icon">
                                    <i class="fas fa-qrcode"></i>
                                </div>
                                <div class="payment-option-info">
                                    <h4>โอนเงิน / PromptPay</h4>
                                    <p>สแกน QR Code เพื่อชำระเงิน</p>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Order Summary -->
                <div class="cart-summary">
                    <h3 style="color: var(--white); margin-bottom: var(--space-lg);">สรุปคำสั่งซื้อ</h3>

                    <div style="max-height: 300px; overflow-y: auto; margin-bottom: var(--space-lg);">
                        <?php foreach ($cartItems as $item): ?>
                            <div class="d-flex gap-md align-center mb-md"
                                style="padding: var(--space-sm) 0; border-bottom: 1px solid var(--gray-700);">
                                <div
                                    style="width: 50px; height: 50px; border-radius: var(--radius-sm); overflow: hidden; flex-shrink: 0;">
                                    <?php if ($item['image']): ?>
                                        <img src="/uploads/products/<?= htmlspecialchars($item['image']) ?>" alt=""
                                            style="width: 100%; height: 100%; object-fit: cover;"
                                            onerror="this.src='/images/placeholder.jpg'">
                                    <?php else: ?>
                                        <img src="/images/placeholder.jpg" alt=""
                                            style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php endif; ?>
                                </div>
                                <div style="flex: 1; min-width: 0;">
                                    <p
                                        style="font-size: 0.875rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                        <?= htmlspecialchars($item['name']) ?>
                                    </p>
                                    <p style="font-size: 0.75rem; color: var(--gray-400);">
                                        <?= $item['variant_id'] ? htmlspecialchars($item['size_label']) . ' &bull; ' : '' ?>x
                                        <?= $item['quantity'] ?>
                                    </p>
                                </div>
                                <div style="font-weight: 600; color: var(--primary);">
                                    <?= formatPrice($item['effective_price'] * $item['quantity']) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="cart-summary-row">
                        <span>ราคาสินค้า</span>
                        <span>
                            <?= formatPrice($subtotal) ?>
                        </span>
                    </div>

                    <div class="cart-summary-row">
                        <span>ค่าจัดส่ง</span>
                        <span>
                            <?php if ($shipping == 0): ?>
                                <span style="color: var(--success);">ฟรี!</span>
                            <?php else: ?>
                                <?= formatPrice($shipping) ?>
                            <?php endif; ?>
                        </span>
                    </div>

                    <div class="cart-summary-row cart-summary-total">
                        <span>รวมทั้งสิ้น</span>
                        <span>
                            <?= formatPrice($total) ?>
                        </span>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block btn-lg mt-lg">
                        <i class="fas fa-check"></i> ยืนยันสั่งซื้อ
                    </button>

                    <a href="/cart.php" class="btn btn-secondary btn-block mt-md">
                        <i class="fas fa-arrow-left"></i> กลับไปแก้ไขตะกร้า
                    </a>
                </div>
            </div>
        </form>
    </div>
</section>

<script>
    // Payment option selection
    document.querySelectorAll('.payment-option').forEach(option => {
        option.addEventListener('click', function () {
            document.querySelectorAll('.payment-option').forEach(o => o.classList.remove('selected'));
            this.classList.add('selected');
        });
    });
</script>

<?php require_once 'footer.php'; ?>