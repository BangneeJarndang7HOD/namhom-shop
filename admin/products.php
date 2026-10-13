<?php
/**
 * Admin Products Management - จัดการสินค้า
 */
require_once __DIR__ . '/../config/database.php';

if (!isAdmin()) {
    redirect('/');
}

$pageTitle = 'จัดการสินค้า - Admin';
$db = getDB();

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    requireCsrf('/admin/products.php');

    $productId = (int) $_POST['delete_id'];

    $stmt = $db->prepare("SELECT COUNT(*) FROM order_items WHERE product_id = ?");
    $stmt->execute([$productId]);
    $hasOrders = $stmt->fetchColumn() > 0;

    if ($hasOrders) {
        // Deleting would cascade-delete historical order items; hide the product instead.
        $stmt = $db->prepare("UPDATE products SET stock = 0, is_featured = 0, is_promotion = 0 WHERE id = ?");
        $stmt->execute([$productId]);
        setFlash('warning', 'สินค้านี้มีอยู่ในคำสั่งซื้อเดิมแล้ว จึงไม่สามารถลบได้ ระบบได้ตั้งสต็อกเป็น 0 และซ่อนออกจากหน้าแนะนำ/โปรโมชั่นแทน');
    } else {
        $stmt = $db->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        setFlash('success', 'ลบสินค้าเรียบร้อยแล้ว');
    }
    redirect('/admin/products.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'])) {
    requireCsrf('/admin/products.php');

    $productId = (int)($_POST['product_id'] ?? 0);
    $name = sanitize($_POST['name'] ?? '');
    $brand = sanitize($_POST['brand'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $discountPercent = (int)($_POST['discount_percent'] ?? 0);
    $stock = (int)($_POST['stock'] ?? 0);
    $size = sanitize($_POST['size'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
    $isPromotion = isset($_POST['is_promotion']) ? 1 : 0;

    $formErrors = [];
    if ($name === '')
        $formErrors[] = 'กรุณากรอกชื่อสินค้า';
    if ($brand === '')
        $formErrors[] = 'กรุณากรอกแบรนด์';
    if ($price < 0)
        $formErrors[] = 'ราคาต้องไม่ติดลบ';
    if ($discountPercent < 0 || $discountPercent > 100)
        $formErrors[] = 'ส่วนลดต้องอยู่ระหว่าง 0-100%';
    if ($stock < 0)
        $formErrors[] = 'จำนวนสต็อกต้องไม่ติดลบ';

    // Handle image upload
    $imageName = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'];
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $imageInfo = @getimagesize($_FILES['image']['tmp_name']);
        $maxSize = 5 * 1024 * 1024; // 5MB

        if ($_FILES['image']['size'] > $maxSize) {
            $formErrors[] = 'ไฟล์รูปภาพต้องมีขนาดไม่เกิน 5MB';
        } elseif (!isset($allowed[$ext]) || $imageInfo === false || !in_array($imageInfo['mime'], $allowed, true)) {
            $formErrors[] = 'ไฟล์รูปภาพต้องเป็น JPG, PNG, WEBP หรือ GIF เท่านั้น';
        } else {
            $uploadDir = __DIR__ . '/../uploads/products/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $imageName = uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $imageName);
        }
    }

    if (empty($formErrors)) {
        if ($productId) {
            // Update
            if ($imageName) {
                $stmt = $db->prepare("UPDATE products SET name=?, brand=?, category_id=?, price=?, discount_percent=?, stock=?, size=?, description=?, is_featured=?, is_promotion=?, image=? WHERE id=?");
                $stmt->execute([$name, $brand, $categoryId, $price, $discountPercent, $stock, $size, $description, $isFeatured, $isPromotion, $imageName, $productId]);
            } else {
                $stmt = $db->prepare("UPDATE products SET name=?, brand=?, category_id=?, price=?, discount_percent=?, stock=?, size=?, description=?, is_featured=?, is_promotion=? WHERE id=?");
                $stmt->execute([$name, $brand, $categoryId, $price, $discountPercent, $stock, $size, $description, $isFeatured, $isPromotion, $productId]);
            }
            setFlash('success', 'อัพเดทสินค้าเรียบร้อยแล้ว');
        } else {
            // Insert
            $stmt = $db->prepare("INSERT INTO products (name, brand, category_id, price, discount_percent, stock, size, description, is_featured, is_promotion, image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $brand, $categoryId, $price, $discountPercent, $stock, $size, $description, $isFeatured, $isPromotion, $imageName]);
            setFlash('success', 'เพิ่มสินค้าเรียบร้อยแล้ว');
        }
        redirect('/admin/products.php');
    } else {
        setFlash('danger', implode(' / ', $formErrors));
        redirect($productId ? '/admin/products.php?edit=' . $productId : '/admin/products.php?add=1');
    }
}

// Get categories
$categories = $db->query("SELECT * FROM categories")->fetchAll();

// Get products
$products = $db->query("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    ORDER BY p.created_at DESC
")->fetchAll();

// Get product for editing
$editProduct = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editProduct = $stmt->fetch();
}

$showForm = isset($_GET['add']) || $editProduct;
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
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
            <hr style="border-color: var(--gray-700); margin: var(--space-lg) 0;">
            <a href="/"><i class="fas fa-globe"></i><span>ไปยังหน้าเว็บ</span></a>
            <a href="/logout.php"><i class="fas fa-sign-out-alt"></i><span>ออกจากระบบ</span></a>
        </nav>
    </aside>
    
    <main class="admin-main">
        <?php $flash = getFlash(); if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] ?> mb-lg">
                <?= htmlspecialchars($flash['message']) ?>
            </div>
        <?php endif; ?>
        
        <div class="admin-header">
            <div>
                <h1>จัดการสินค้า</h1>
            </div>
            <?php if (!$showForm): ?>
                <a href="?add=1" class="btn btn-primary">
                    <i class="fas fa-plus"></i> เพิ่มสินค้าใหม่
                </a>
            <?php endif; ?>
        </div>
        
        <?php if ($showForm): ?>
            <!-- Product Form -->
            <div class="admin-card">
                <h3 class="mb-lg"><?= $editProduct ? 'แก้ไขสินค้า' : 'เพิ่มสินค้าใหม่' ?></h3>
                
                <form method="POST" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <?php if ($editProduct): ?>
                        <input type="hidden" name="product_id" value="<?= $editProduct['id'] ?>">
                    <?php endif; ?>
                    
                    <div class="grid grid-2 gap-lg">
                        <div class="form-group">
                            <label class="form-label">ชื่อสินค้า *</label>
                            <input type="text" name="name" class="form-control" required
                                   value="<?= htmlspecialchars($editProduct['name'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">แบรนด์ *</label>
                            <input type="text" name="brand" class="form-control" required
                                   value="<?= htmlspecialchars($editProduct['brand'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">หมวดหมู่</label>
                            <select name="category_id" class="form-control">
                                <option value="">-- เลือกหมวดหมู่ --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= ($editProduct['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">ขนาด</label>
                            <input type="text" name="size" class="form-control" placeholder="เช่น 100ml"
                                   value="<?= htmlspecialchars($editProduct['size'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">ราคา (บาท) *</label>
                            <input type="number" name="price" class="form-control" required step="0.01" min="0"
                                   value="<?= $editProduct['price'] ?? '' ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">ส่วนลด (%)</label>
                            <input type="number" name="discount_percent" class="form-control" min="0" max="100"
                                   value="<?= $editProduct['discount_percent'] ?? 0 ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">จำนวนในสต็อก</label>
                            <input type="number" name="stock" class="form-control" min="0"
                                   value="<?= $editProduct['stock'] ?? 0 ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">รูปภาพ</label>
                            <input type="file" name="image" class="form-control" accept="image/*">
                            <?php if ($editProduct && $editProduct['image']): ?>
                                <p class="text-muted mt-sm">รูปปัจจุบัน: <?= htmlspecialchars($editProduct['image']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">รายละเอียด</label>
                        <textarea name="description" class="form-control" rows="4"><?= htmlspecialchars($editProduct['description'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="d-flex gap-lg mb-lg">
                        <label style="display: flex; align-items: center; gap: var(--space-sm); cursor: pointer;">
                            <input type="checkbox" name="is_featured" value="1" <?= ($editProduct['is_featured'] ?? 0) ? 'checked' : '' ?>>
                            สินค้าแนะนำ
                        </label>
                        <label style="display: flex; align-items: center; gap: var(--space-sm); cursor: pointer;">
                            <input type="checkbox" name="is_promotion" value="1" <?= ($editProduct['is_promotion'] ?? 0) ? 'checked' : '' ?>>
                            โปรโมชั่น
                        </label>
                    </div>
                    
                    <div class="d-flex gap-md">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?= $editProduct ? 'บันทึก' : 'เพิ่มสินค้า' ?>
                        </button>
                        <a href="/admin/products.php" class="btn btn-secondary">ยกเลิก</a>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <!-- Products List -->
            <div class="admin-card">
                <?php if (empty($products)): ?>
                    <p class="text-muted text-center p-xl">ยังไม่มีสินค้า</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th style="width: 80px;">รูป</th>
                                    <th>ชื่อสินค้า</th>
                                    <th>แบรนด์</th>
                                    <th>หมวดหมู่</th>
                                    <th>ราคา</th>
                                    <th>สต็อก</th>
                                    <th>จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td>
                                            <div style="width: 60px; height: 60px; border-radius: var(--radius-sm); overflow: hidden; background: var(--gray-200);">
                                                <?php if ($product['image']): ?>
                                                    <img src="/uploads/products/<?= htmlspecialchars($product['image']) ?>" 
                                                         alt="" style="width: 100%; height: 100%; object-fit: cover;"
                                                         onerror="this.src='/images/placeholder.jpg'">
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($product['name']) ?></strong>
                                            <?php if ($product['is_featured']): ?><span class="status-badge status-processing">แนะนำ</span><?php endif; ?>
                                            <?php if ($product['is_promotion']): ?><span class="status-badge status-pending">โปรโมชั่น</span><?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($product['brand']) ?></td>
                                        <td><?= htmlspecialchars($product['category_name'] ?? '-') ?></td>
                                        <td>
                                            <?php if ($product['discount_percent'] > 0): ?>
                                                <span style="text-decoration: line-through; color: var(--gray-400);"><?= formatPrice($product['price']) ?></span><br>
                                                <span style="color: var(--primary); font-weight: 600;"><?= formatPrice(getDiscountedPrice($product['price'], $product['discount_percent'])) ?></span>
                                            <?php else: ?>
                                                <?= formatPrice($product['price']) ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $product['stock'] ?></td>
                                        <td>
                                            <a href="?edit=<?= $product['id'] ?>" class="btn btn-sm btn-secondary" title="แก้ไข">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="product_variants.php?product_id=<?= $product['id'] ?>" class="btn btn-sm btn-secondary" title="ขนาดแบ่งขาย">
                                                <i class="fas fa-flask"></i>
                                            </a>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('ลบสินค้านี้?')">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="delete_id" value="<?= $product['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
