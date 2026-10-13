<?php
/**
 * Admin Categories Management - จัดการหมวดหมู่
 */
require_once __DIR__ . '/../config/database.php';

if (!isAdmin()) {
    redirect('/');
}

$pageTitle = 'จัดการหมวดหมู่ - Admin';
$db = getDB();

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    requireCsrf('/admin/categories.php');

    $catId = (int) $_POST['delete_id'];
    $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->execute([$catId]);
    setFlash('success', 'ลบหมวดหมู่เรียบร้อยแล้ว');
    redirect('/admin/categories.php');
}

// Handle form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'])) {
    requireCsrf('/admin/categories.php');

    $catId = (int) ($_POST['category_id'] ?? 0);
    $name = sanitize($_POST['name'] ?? '');
    $description = sanitize($_POST['description'] ?? '');

    if ($name === '') {
        setFlash('danger', 'กรุณากรอกชื่อหมวดหมู่');
        redirect('/admin/categories.php' . ($catId ? '?edit=' . $catId : ''));
    }

    if ($catId) {
        $stmt = $db->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?");
        $stmt->execute([$name, $description, $catId]);
        setFlash('success', 'อัพเดทหมวดหมู่เรียบร้อย');
    } else {
        $stmt = $db->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
        $stmt->execute([$name, $description]);
        setFlash('success', 'เพิ่มหมวดหมู่เรียบร้อย');
    }
    redirect('/admin/categories.php');
}

// Get categories with count
$categories = $db->query("
    SELECT c.*, COUNT(p.id) as product_count 
    FROM categories c 
    LEFT JOIN products p ON c.id = p.category_id 
    GROUP BY c.id 
    ORDER BY c.name
")->fetchAll();

// Get category for edit
$editCategory = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([(int) $_GET['edit']]);
    $editCategory = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?= $pageTitle ?>
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
            <a href="/admin/products.php"><i class="fas fa-box"></i><span>จัดการสินค้า</span></a>
            <a href="/admin/orders.php"><i class="fas fa-shopping-cart"></i><span>คำสั่งซื้อ</span></a>
            <a href="/admin/categories.php" class="active"><i class="fas fa-tags"></i><span>หมวดหมู่</span></a>
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

        <div class="admin-header">
            <h1>จัดการหมวดหมู่</h1>
        </div>

        <div class="grid" style="grid-template-columns: 1fr 1fr; gap: var(--space-xl);">
            <!-- Form -->
            <div class="admin-card">
                <h3 class="mb-lg">
                    <?= $editCategory ? 'แก้ไขหมวดหมู่' : 'เพิ่มหมวดหมู่ใหม่' ?>
                </h3>

                <form method="POST">
                    <?= csrfField() ?>
                    <?php if ($editCategory): ?>
                        <input type="hidden" name="category_id" value="<?= $editCategory['id'] ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label class="form-label">ชื่อหมวดหมู่ *</label>
                        <input type="text" name="name" class="form-control" required
                            value="<?= htmlspecialchars($editCategory['name'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">คำอธิบาย</label>
                        <textarea name="description" class="form-control"
                            rows="3"><?= htmlspecialchars($editCategory['description'] ?? '') ?></textarea>
                    </div>

                    <div class="d-flex gap-md">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            <?= $editCategory ? 'บันทึก' : 'เพิ่มหมวดหมู่' ?>
                        </button>
                        <?php if ($editCategory): ?>
                            <a href="/admin/categories.php" class="btn btn-secondary">ยกเลิก</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Categories List -->
            <div class="admin-card">
                <h3 class="mb-lg">หมวดหมู่ทั้งหมด</h3>

                <?php if (empty($categories)): ?>
                    <p class="text-muted">ยังไม่มีหมวดหมู่</p>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ชื่อ</th>
                                <th>สินค้า</th>
                                <th>จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $cat): ?>
                                <tr>
                                    <td>
                                        <strong>
                                            <?= htmlspecialchars($cat['name']) ?>
                                        </strong>
                                        <?php if ($cat['description']): ?>
                                            <p class="text-muted" style="font-size: 0.875rem;">
                                                <?= htmlspecialchars($cat['description']) ?>
                                            </p>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= $cat['product_count'] ?>
                                    </td>
                                    <td>
                                        <a href="?edit=<?= $cat['id'] ?>" class="btn btn-sm btn-secondary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('ลบหมวดหมู่นี้?')">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="delete_id" value="<?= $cat['id'] ?>">
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