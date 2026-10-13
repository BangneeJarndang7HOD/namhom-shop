<?php
/**
 * Admin User Action Handler
 */
require_once __DIR__ . '/../config/database.php';

if (!isAdmin()) {
    redirect('/');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf('/admin/users.php');

    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    
    if (!$id) {
        setFlash('danger', 'ไม่พบรหัสผู้ใช้');
        redirect('/admin/users.php');
    }
    
    $db = getDB();
    
    // Prevent actions on the main admin or self if applicable
    if ($id === getCurrentUserId()) {
        setFlash('danger', 'ไม่สามารถจัดการบัญชีของคุณเองผ่านหน้านี้ได้');
        redirect('/admin/users.php');
    }

    // Fetch the target user so we know whether they're currently an admin
    $stmt = $db->prepare("SELECT is_admin FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $target = $stmt->fetch();

    if (!$target) {
        setFlash('danger', 'ไม่พบผู้ใช้งาน');
        redirect('/admin/users.php');
    }

    // Refuse to remove the last remaining admin, otherwise no one could manage the site
    $adminCount = $db->query("SELECT COUNT(*) FROM users WHERE is_admin = 1")->fetchColumn();
    $wouldRemoveLastAdmin = $target['is_admin'] && $adminCount <= 1 && in_array($action, ['delete', 'demote']);

    if ($wouldRemoveLastAdmin) {
        setFlash('danger', 'ไม่สามารถดำเนินการนี้ได้ เนื่องจากเป็นแอดมินคนสุดท้ายของระบบ');
        redirect('/admin/users.php');
    }

    if ($action === 'delete') {
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        if ($stmt->execute([$id])) {
            setFlash('success', 'ลบผู้ใช้เรียบร้อยแล้ว');
        } else {
            setFlash('danger', 'เกิดข้อผิดพลาดในการลบผู้ใช้');
        }
    } elseif ($action === 'promote') {
        $stmt = $db->prepare("UPDATE users SET is_admin = 1 WHERE id = ?");
        if ($stmt->execute([$id])) {
            setFlash('success', 'เลื่อนสถานะเป็นแอดมินเรียบร้อยแล้ว');
        } else {
            setFlash('danger', 'เกิดข้อผิดพลาดในการเลื่อนสถานะ');
        }
    } elseif ($action === 'demote') {
        $stmt = $db->prepare("UPDATE users SET is_admin = 0 WHERE id = ?");
        if ($stmt->execute([$id])) {
            setFlash('success', 'ถอดสถานะแอดมินเรียบร้อยแล้ว');
        } else {
            setFlash('danger', 'เกิดข้อผิดพลาดในการถอดสถานะแอดมิน');
        }
    }
}

redirect('/admin/users.php');
