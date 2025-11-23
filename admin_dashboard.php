<?php
session_start();
require_once 'config.php';

// Chỉ cho phép Admin truy cập
if (!isset($_SESSION['user_id']) || ($_SESSION['vai_tro'] ?? '') !== 'quan_tri') {
    header("Location: dang_nhap.php");
    exit;
}

$ho_ten = $_SESSION['ho_ten'];

// Lấy thống kê
$so_hoc_vien = 0;
$so_giang_vien = 0;
$so_admin = 0;
$so_khoa_hoc = 0;

try {
    $pdo = Database::pdo();

    // Đếm số lượng người dùng theo vai trò
    $sql = "SELECT vai_tro, COUNT(*) AS so_luong 
            FROM nguoi_dung 
            GROUP BY vai_tro";
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $r) {
        if ($r['vai_tro'] === 'hoc_vien')   $so_hoc_vien   = $r['so_luong'];
        if ($r['vai_tro'] === 'giang_vien') $so_giang_vien = $r['so_luong'];
        if ($r['vai_tro'] === 'quan_tri')   $so_admin      = $r['so_luong'];
    }

    // Đếm số khóa học
    $so_khoa_hoc = (int)$pdo->query("SELECT COUNT(*) FROM khoa_hoc")->fetchColumn();

} catch (PDOException $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản trị hệ thống - Admin</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Icons -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <!-- CSS dùng chung -->
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<!-- Thanh đỏ trên cùng -->
<div class="top-bar">
    <div class="container d-flex justify-content-between align-items-center">
        <div>
            <i class="bi bi-speedometer2"></i>
            <a href="admin_dashboard.php">Trang quản trị</a>
        </div>
        <div>
            Xin chào, <strong><?php echo htmlspecialchars($ho_ten); ?></strong>
            &nbsp;|&nbsp;
            <a href="dang_xuat.php" style="color:#fff;">Đăng xuất</a>
        </div>
    </div>
</div>

<!-- Header -->
<header class="main-header">
    <div class="container py-2 d-flex align-items-center">
        <img src="image/ptit.png" alt="PTIT Logo" style="height:55px;" class="me-3">
        <div>
            <div class="logo-text">QUẢN TRỊ HỆ THỐNG E-LEARNING</div>
        </div>
    </div>
</header>

<div class="container mt-4 mb-5">

    <h3 class="mb-4" style="color:#b30000;">Tổng quan hệ thống</h3>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger">Lỗi: <?php echo $error; ?></div>
    <?php endif; ?>

    <div class="row g-3">

        <!-- Học viên -->
        <div class="col-md-3">
            <div class="card shadow-sm text-center p-3">
                <i class="bi bi-people-fill fs-1 text-danger"></i>
                <h5 class="mt-2">Học viên</h5>
                <p class="display-6"><?php echo $so_hoc_vien; ?></p>
            </div>
        </div>

        <!-- Giảng viên -->
        <div class="col-md-3">
            <div class="card shadow-sm text-center p-3">
                <i class="bi bi-person-badge-fill fs-1 text-danger"></i>
                <h5 class="mt-2">Giảng viên</h5>
                <p class="display-6"><?php echo $so_giang_vien; ?></p>
            </div>
        </div>

        <!-- Admin -->
        <div class="col-md-3">
            <div class="card shadow-sm text-center p-3">
                <i class="bi bi-shield-lock-fill fs-1 text-danger"></i>
                <h5 class="mt-2">Quản trị viên</h5>
                <p class="display-6"><?php echo $so_admin; ?></p>
            </div>
        </div>

        <!-- Khóa học -->
        <div class="col-md-3">
            <div class="card shadow-sm text-center p-3">
                <i class="bi bi-journal-bookmark-fill fs-1 text-danger"></i>
                <h5 class="mt-2">Khóa học</h5>
                <p class="display-6"><?php echo $so_khoa_hoc; ?></p>
            </div>
        </div>

    </div>

    <h4 class="mt-5 mb-3">Chức năng quản trị</h4>

    <div class="list-group">
        <a href="admin_users.php" class="list-group-item list-group-item-action">
            <i class="bi bi-people"></i> Quản lý tài khoản (Sinh viên, giảng viên, admin)
        </a>
        <a href="admin_courses.php" class="list-group-item list-group-item-action">
            <i class="bi bi-journal"></i> Quản lý khóa học
        </a>
        <a href="admin_lessons.php" class="list-group-item list-group-item-action">
            <i class="bi bi-file-earmark-text"></i> Quản lý bài giảng
        </a>
        <a href="admin_exams.php" class="list-group-item list-group-item-action">
            <i class="bi bi-card-checklist"></i> Quản lý bài thi & câu hỏi
        </a>
        <a href="admin_settings.php" class="list-group-item list-group-item-action">
            <i class="bi bi-gear"></i> Cấu hình hệ thống
        </a>
        <a href="admin_classes.php" class="list-group-item list-group-item-action">
    <i class="bi bi-people-fill"></i> Quản lý lớp học
</a>

    </div>

</div>

<footer>
    <div class="container text-center">
        © <?php echo date('Y'); ?> Hệ thống E-learning PTIT - Trang quản trị.
    </div>
</footer>

</body>
</html>
