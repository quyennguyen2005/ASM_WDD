<?php
require_once 'db.php';
require_once 'config.php';

secure_session_start();

// Kiểm tra đăng nhập và chuyển hướng
if (is_logged_in()) {
    // Nếu đã đăng nhập, chuyển đến dashboard
    header("Location: index.php");
    exit();
} else {
    // Nếu chưa đăng nhập, chuyển đến trang chào mừng
    header("Location: welcome.php");
    exit();
}
?>
