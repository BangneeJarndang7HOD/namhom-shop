<?php
/**
 * Order Detail - รายละเอียดคำสั่งซื้อ
 */
require_once 'config/database.php';

if (!isLoggedIn()) {
    redirect('/login.php');
}

$orderId = (int) ($_GET['id'] ?? 0);
$db = getDB();
$userId = getCurrentUserId();

// Get order
$stmt = $db->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$orderId, $userId]);
$order = $stmt->fetch();

if (!$order) {
    setFlash('danger', 'ไม่พบคำสั่งซื้อ');
    redirect('/profile.php?tab=orders');
}

// An order can be self-cancelled while it's still unshipped and, for bank
// transfers, before an admin has confirmed the payment.
function isOrderCancellable($order)
{
    if (!in_array($order['status'], ['pending', 'processing'])) {
        return false;
    }
    if ($order['payment_method'] === 'transfer' && $order['payment_status'] === 'confirmed') {
        return false;
    }
    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    requireCsrf('/order_detail.php?id=' . $orderId);

    if (isOrderCancellable($order)) {
        $db->beginTransaction();

        $stmt = $db->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
        $stmt->execute([$orderId]);
        foreach ($stmt->fetchAll() as $item) {
            $restock = $db->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
            $restock->execute([$item['quantity'], $item['product_id']]);
        }

        $stmt = $db->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?");
        $stmt->execute([$orderId]);

        $db->commit();

        setFlash('success', 'ยกเลิกคำสั่งซื้อเรียบร้อยแล้ว');
    } else {
        setFlash('danger', 'ไม่สามารถยกเลิกคำสั่งซื้อนี้ได้ เนื่องจากอยู่ระหว่างดำเนินการหรือชำระเงินแล้ว');
    }
    redirect('/order_detail.php?id=' . $orderId);
}

$pageTitle = 'คำสั่งซื้อ #' . str_pad($orderId, 6, '0', STR_PAD_LEFT) . ' - ParfumLuxe';

// Get order items
$stmt = $db->prepare("
    SELECT oi.*, p.name, p.image, p.brand
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$stmt->execute([$orderId]);
$orderItems = $stmt->fetchAll();

function getStatusText($status)
{
    return match ($status) {
        'pending' => 'รอดำเนินการ',
        'paid' => 'ชำระเงินแล้ว',
        'processing' => 'กำลังจัดเตรียม',
        'shipped' => 'จัดส่งแล้ว',
        'delivered' => 'ส่งสำเร็จ',
        'cancelled' => 'ยกเลิก',
        default => $status
    };
}

require_once 'header.php';
?>

<section class="section">
    <div class="container">
        <a href="/profile.php?tab=orders" class="btn btn-secondary mb-lg">
            <i class="fas fa-arrow-left"></i> กลับไปประวัติการสั่งซื้อ
        </a>

        <div class="grid" style="grid-template-columns: 2fr 1fr; gap: var(--space-xl);">
            <!-- Order Items -->
            <div>
                <div class="card">
                    <div class="card-body">
                        <h3 style = "color: white"; class="mb-lg">
                            คำสั่งซื้อ #
                            <?= str_pad($orderId, 6, '0', STR_PAD_LEFT) ?>
                        </h3>

                        <?php foreach ($orderItems as $item): ?>
                            <div class="d-flex gap-lg align-center mb-lg"
                                style="padding-bottom: var(--space-lg); border-bottom: 1px solid var(--gray-200);">
                                <div
                                    style="width: 80px; height: 80px; border-radius: var(--radius-md); overflow: hidden; flex-shrink: 0; background: var(--gray-200);">
                                    <?php if ($item['image']): ?>
                                        <img src="/uploads/products/<?= htmlspecialchars($item['image']) ?>" alt=""
                                            style="width: 100%; height: 100%; object-fit: cover;"
                                            onerror="this.src='/images/placeholder.jpg'">
                                    <?php endif; ?>
                                </div>
                                <div style="flex: 1;">
                                    <p class="text-muted" style="font-size: 0.875rem;">
                                        <?= htmlspecialchars($item['brand']) ?>
                                    </p>
                                    <h4 style = "color: white">
                                        <?= htmlspecialchars($item['name']) ?>
                                        <?php if ($item['variant_label']): ?>
                                            <span class="status-badge status-processing"><?= htmlspecialchars($item['variant_label']) ?></span>
                                        <?php endif; ?>
                                    </h4>
                                    <p class="text-muted">จำนวน:
                                        <?= $item['quantity'] ?>
                                    </p>
                                </div>
                                <div style="font-weight: 600; color: var(--primary); font-size: 1.125rem;">
                                    <?= formatPrice($item['price'] * $item['quantity']) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Shipping Info -->
                <div class="card mt-lg">
                    <div class="card-body">
                        <h4 style = "color: white"; class="mb-md"><i class="fas fa-truck text-gold"></i> ข้อมูลการจัดส่ง</h4>
                        <p><strong>ที่อยู่:</strong>
                            <?= nl2br(htmlspecialchars($order['shipping_address'])) ?>
                        </p>
                        <p><strong>โทรศัพท์:</strong>
                            <?= htmlspecialchars($order['phone']) ?>
                        </p>
                        <?php if ($order['notes']): ?>
                            <p><strong>หมายเหตุ:</strong>
                                <?= htmlspecialchars($order['notes']) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Order Summary -->
            <div>
                <div class="cart-summary">
                    <h3>สรุปคำสั่งซื้อ</h3>

                    <div class="cart-summary-row">
                        <span>วันที่สั่งซื้อ</span>
                        <span>
                            <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?>
                        </span>
                    </div>

                    <div class="cart-summary-row">
                        <span>วิธีชำระเงิน</span>
                        <span>
                            <?= $order['payment_method'] === 'cod' ? 'ปลายทาง' : 'โอนเงิน' ?>
                        </span>
                    </div>

                    <div class="cart-summary-row">
                        <span>สถานะการชำระเงิน</span>
                        <span class="status-badge status-<?= $order['payment_status'] ?>">
                            <?= match ($order['payment_status']) {
                                'pending' => 'รอตรวจสอบ',
                                'confirmed' => 'ยืนยันแล้ว',
                                'failed' => 'ไม่สำเร็จ',
                                default => $order['payment_status']
                            } ?>
                        </span>
                    </div>

                    <div class="cart-summary-row">
                        <span>สถานะคำสั่งซื้อ</span>
                        <span class="status-badge status-<?= $order['status'] ?>">
                            <?= getStatusText($order['status']) ?>
                        </span>
                    </div>

                    <div class="cart-summary-row cart-summary-total">
                        <span>ยอดรวมทั้งสิ้น</span>
                        <span>
                            <?= formatPrice($order['total_amount']) ?>
                        </span>
                    </div>

                    <?php if ($order['payment_method'] === 'transfer' && $order['payment_status'] === 'pending' && $order['status'] !== 'cancelled'): ?>
                        <a href="/payment.php?order=<?= $orderId ?>" class="btn btn-primary btn-block btn-lg mt-lg">
                            <i class="fas fa-qrcode"></i> ชำระเงิน
                        </a>
                    <?php endif; ?>

                    <?php if (isOrderCancellable($order)): ?>
                        <form method="POST" onsubmit="return confirm('ยืนยันการยกเลิกคำสั่งซื้อนี้?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="cancel_order" value="1">
                            <button type="submit" class="btn btn-danger btn-block mt-md">
                                <i class="fas fa-ban"></i> ยกเลิกคำสั่งซื้อ
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once 'footer.php'; ?>