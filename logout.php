<?php
/**
 * Logout - ออกจากระบบ
 */
require_once 'config/database.php';

session_destroy();
session_start();

setFlash('success', 'ออกจากระบบเรียบร้อยแล้ว');
redirect('/');
?>