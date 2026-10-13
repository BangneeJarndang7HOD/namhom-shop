<?php
/**
 * Cart API - จัดการตะกร้าสินค้า (รองรับขนาดแบ่งขาย/variant)
 */
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบก่อน']);
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'เซสชันหมดอายุ กรุณารีเฟรชหน้าแล้วลองใหม่']);
    exit;
}

$db = getDB();
$userId = getCurrentUserId();
$action = $_POST['action'] ?? '';
$productId = (int) ($_POST['product_id'] ?? 0);
$variantId = isset($_POST['variant_id']) && $_POST['variant_id'] !== '' ? (int) $_POST['variant_id'] : null;

// Resolve the stock available for this product/variant combination
function resolveStock($db, $productId, $variantId)
{
    if ($variantId === null) {
        $stmt = $db->prepare("SELECT stock FROM products WHERE id = ?");
        $stmt->execute([$productId]);
    } else {
        $stmt = $db->prepare("SELECT stock FROM product_variants WHERE id = ? AND product_id = ?");
        $stmt->execute([$variantId, $productId]);
    }
    $row = $stmt->fetch();
    return $row === false ? null : (int) $row['stock'];
}

switch ($action) {
    case 'add':
        $quantity = max(1, (int) ($_POST['quantity'] ?? 1));
        $stock = resolveStock($db, $productId, $variantId);

        if ($stock === null) {
            echo json_encode(['success' => false, 'message' => 'ไม่พบสินค้า']);
            exit;
        }

        if ($stock < $quantity) {
            echo json_encode(['success' => false, 'message' => 'สินค้าไม่เพียงพอ']);
            exit;
        }

        $stmt = $db->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ? AND variant_id <=> ?");
        $stmt->execute([$userId, $productId, $variantId]);
        $existing = $stmt->fetch();

        if ($existing) {
            $newQty = min($existing['quantity'] + $quantity, $stock);
            $stmt = $db->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
            $stmt->execute([$newQty, $existing['id']]);
        } else {
            $stmt = $db->prepare("INSERT INTO cart (user_id, product_id, variant_id, quantity) VALUES (?, ?, ?, ?)");
            $stmt->execute([$userId, $productId, $variantId, $quantity]);
        }

        echo json_encode(['success' => true, 'message' => 'เพิ่มลงตะกร้าแล้ว']);
        break;

    case 'update':
        $quantity = max(1, (int) ($_POST['quantity'] ?? 1));
        $stock = resolveStock($db, $productId, $variantId);

        $stmt = $db->prepare("SELECT id FROM cart WHERE user_id = ? AND product_id = ? AND variant_id <=> ?");
        $stmt->execute([$userId, $productId, $variantId]);
        $item = $stmt->fetch();

        if (!$item || $stock === null) {
            echo json_encode(['success' => false, 'message' => 'ไม่พบสินค้าในตะกร้า']);
            exit;
        }

        $quantity = min($quantity, $stock);

        $stmt = $db->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
        $stmt->execute([$quantity, $item['id']]);

        echo json_encode(['success' => true, 'message' => 'อัพเดทจำนวนแล้ว']);
        break;

    case 'remove':
        $stmt = $db->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ? AND variant_id <=> ?");
        $stmt->execute([$userId, $productId, $variantId]);

        echo json_encode(['success' => true, 'message' => 'ลบสินค้าแล้ว']);
        break;

    case 'clear':
        $stmt = $db->prepare("DELETE FROM cart WHERE user_id = ?");
        $stmt->execute([$userId]);

        echo json_encode(['success' => true, 'message' => 'ล้างตะกร้าแล้ว']);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
