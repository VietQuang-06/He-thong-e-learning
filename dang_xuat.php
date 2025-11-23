<?php
session_start();

// Xóa toàn bộ biến trong session
$_SESSION = [];

// Hủy session cookie (nếu tồn tại)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

// Hủy session trên server
session_destroy();

// Chuyển về trang đăng nhập
header("Location: dang_nhap.php");
exit;
?>
