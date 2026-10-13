<?php
/**
 * Admin Product Variants - จัดการขนาดแบ่งขาย (เช่น 3ml, 5ml, 10ml) ของสินค้า
 */
require_once __DIR__ . '/../config/database.php';

if (!isAdmin()) {
    redirect('/');
}

$db = getDB();
$productId = (int) ($_GET['product_id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    setFlash('danger', 'ไม่พบสินค้า');
    redirect('/admin/products.php');
}

$pageTitle = 'ขนาดแบ่งขาย - ' . $product['name'];

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    requireCsrf('/admin/product_variants.php?product_id=' . $productId);

    $stmt = $db->prepare("DELETE FROM product_variants WHERE id = ? AND product_id = ?");
    $stmt->execute([(int) $_POST['delete_id'], $productId]);
    setFlash('success', 'ลบขนาดนี้เรียบร้อยแล้ว');
    redirect('/admin/product_variants.php?product_id=' . $productId);
}

// Handle add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['size_ml'])) {
    requireCsrf('/admin/product_variants.php?product_id=' . $productId);

    $variantId = (int) ($_POST['variant_id'] ?? 0);
    $sizeMl = (int) $_POST['size_ml'];
    $price = (float) ($_POST['price'] ?? 0);
    $stock = (int) ($_POST['stock'] ?? 0);

    $errors = [];
    if ($sizeMl <= 0)
        $errors[] = 'กรุณากรอกขนาด (ml) ให้ถูกต้อง';
    if ($price < 0)
        $errors[] = 'ราคาต้องไม่ติดลบ';
    if ($stock < 0)
        $errors[] = 'จำนวนสต็อกต้องไม่ติดลบ';

    if (empty($errors)) {
        try {
            if ($variantId) {
                $stmt = $db->prepare("UPDATE product_variants SET size_ml = ?, price = ?, stock = ? WHERE id = ? AND product_id = ?");
                $stmt->execute([$sizeMl, $price, $stock, $variantId, $productId]);
                setFlash('success', 'บันทึกขนาดเรียบร้อยแล้ว');
            } else {
                $stmt = $db->prepare("INSERT INTO product_variants (product_id, size_ml, price, stock) VALUES (?, ?, ?, ?)");
                $stmt->execute([$productId, $sizeMl, $price, $stock]);
                setFlash('success', 'เพิ่มขนาดเรียบร้อยแล้ว');
            }
            redirect('/admin/product_variants.php?product_id=' . $productId);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                setFlash('danger', 'มีขนาดนี้อยู่แล้วสำหรับสินค้านี้');
            } else {
                throw $e;
            }
        }
    } else {
        setFlash('danger', implode(' / ', $errors));
    }
}

$variants = $db->prepare("SELECT * FROM product_variants WHERE product_id = ? ORDER BY size_ml ASC");
$variants->execute([$productId]);
$variants = $variants->fetchAll();

$editVariant = null;
if (isset($_GET['edit'])) {
    foreach ($variants as $v) {
        if ($v['id'] === (int) $_GET['edit']) {
            $editVariant = $v;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?= htmlspecialchars($pageTitle) ?>
    </title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="icon" type="image/png" sizes="32x32" href="/images/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/images/favicon-16.png">
    <link rel="apple-touch-icon" href="/images/apple-touch-icon.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>
    <aside class="admin-sidebar">
        <div class="admin-sidebar-brand">
            <a href="/admin/">ParfumLuxe</a>
            <small style="display: block; color: var(--gray-500); font-size: 0.75rem;">Admin Panel</small>
        </div>

        <nav class="admin-nav">
            <a href="/admin/"><i class="fas fa-chart-line"></i><span>แดชบอร์ด</span></a>
            <a href="/admin/products.php" class="active"><i class="fas fa-box"></i><span>จัดการสินค้า</span></a>
            <a href="/admin/orders.php"><i class="fas fa-shopping-cart"></i><span>คำสั่งซื้อ</span></a>
            <a href="/admin/categories.php"><i class="fas fa-tags"></i><span>หมวดหมู่</span></a>
            <a href="/admin/users.php"><i class="fas fa-users"></i><span>ผู้ใช้งาน</span></a>
            <hr style="border-color: var(--gray-700); margin: var(--space-lg) 0;">
            <a href="/"><i class="fas fa-globe"></i><span>ไปยังหน้าเว็บ</span></a>
            <a href="/logout.php"><i class="fas fa-sign-out-alt"></i><span>ออกจากระบบ</span></a>
        </nav>
    </aside>

    <main class="admin-main">
        <?php $flash = getFlash();
        if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] ?> mb-lg">
                <?= htmlspecialchars($flash['message']) ?>
            </div>
        <?php endif; ?>

        <a href="/admin/products.php" class="btn btn-secondary mb-lg">
            <i class="fas fa-arrow-left"></i> กลับไปจัดการสินค้า
        </a>

        <div class="admin-header">
            <h1>ขนาดแบ่งขาย: <?= htmlspecialchars($product['name']) ?></h1>
            <p class="text-muted">ขวดเต็ม (<?= htmlspecialchars($product['size']) ?>) ยังขายตามปกติเสมอ ส่วนนี้ใช้เพิ่มตัวเลือกแบ่งขายขนาดเล็ก เช่น 3ml, 5ml, 10ml</p>
        </div>

        <div class="grid" style="grid-template-columns: 1fr 1fr; gap: var(--space-xl);">
            <!-- Form -->
            <div class="admin-card">
                <h3 class="mb-lg"><?= $editVariant ? 'แก้ไขขนาด' : 'เพิ่มขนาดใหม่' ?></h3>

                <form method="POST">
                    <?= csrfField() ?>
                    <?php if ($editVariant): ?>
                        <input type="hidden" name="variant_id" value="<?= $editVariant['id'] ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label class="form-label">ขนาด (ml) *</label>
                        <input type="number" name="size_ml" class="form-control" required min="1"
                            value="<?= $editVariant['size_ml'] ?? '' ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">ราคา (บาท) *</label>
                        <input type="number" name="price" class="form-control" required step="0.01" min="0"
                            value="<?= $editVariant['price'] ?? '' ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">จำนวนในสต็อก</label>
                        <input type="number" name="stock" class="form-control" min="0"
                            value="<?= $editVariant['stock'] ?? 0 ?>">
                    </div>

                    <div class="d-flex gap-md">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?= $editVariant ? 'บันทึก' : 'เพิ่มขนาด' ?>
                        </button>
                        <?php if ($editVariant): ?>
                            <a href="/admin/product_variants.php?product_id=<?= $productId ?>" class="btn btn-secondary">ยกเลิก</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- List -->
            <div class="admin-card">
                <h3 class="mb-lg">ขนาดที่มีอยู่</h3>

                <?php if (empty($variants)): ?>
                    <p class="text-muted">ยังไม่มีขนาดแบ่งขาย สินค้านี้จะขายเฉพาะขวดเต็มเท่านั้น</p>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ขนาด</th>
                                <th>ราคา</th>
                                <th>สต็อก</th>
                                <th>จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($variants as $v): ?>
                                <tr>
                                    <td><?= (int) $v['size_ml'] ?>ml</td>
                                    <td><?= formatPrice($v['price']) ?></td>
                                    <td><?= (int) $v['stock'] ?></td>
                                    <td>
                                        <a href="?product_id=<?= $productId ?>&edit=<?= $v['id'] ?>" class="btn btn-sm btn-secondary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('ลบขนาดนี้?')">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="delete_id" value="<?= $v['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>

</html>
