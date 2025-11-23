<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Hệ thống học tập trực tuyến</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<!-- Thanh đỏ trên cùng -->
<div class="top-bar">
    <div class="container d-flex justify-content-between align-items-center">
        <div>
            <i class="bi bi-house-door-fill"></i>
            <a href="dang_nhap.php">Cổng học trực tuyến</a>
        </div>
        <div>
            <span class="me-3"><i class="bi bi-telephone-fill"></i> (023) 1456789</span>
            <span><i class="bi bi-envelope-fill"></i> elearning@ptit.edu.vn</span>
        </div>
    </div>
</div>

<!-- Header chính -->
<header class="main-header">
    <div class="container py-2 d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center">
            <!-- LOGO: sửa lại src cho đúng file bạn lưu -->
            <img src="image/ptit.png" alt="Logo PTIT" style="height: 55px;" class="me-3">
        </div>

        <nav class="d-flex align-items-center">
            <a href="dang_nhap.php" class="btn btn-login">Đăng nhập</a>
        </nav>
    </div>
</header>

<!-- Hero đỏ + ô tìm kiếm -->
<section class="hero">
    <div class="container text-center">
        <h1 class="hero-title">HỆ THỐNG HỌC TẬP TRỰC TUYẾN</h1>
        <form class="search-box" method="get" action="tim_kiem_khoa_hoc.php">
            <div class="input-group">
                <input type="text" name="q" class="form-control" placeholder="Tìm khóa học">
                <button type="submit" class="btn">
                    🔍
                </button>
            </div>
        </form>
    </div>
</section>

<!-- Khóa học/Học phần của tôi -->
<section class="container">
    <h2 class="section-title">Khóa học/Học phần của tôi</h2>

    <div class="alert alert-light text-center mt-4">
        Bạn cần <strong>đăng nhập</strong> để xem danh sách khóa học của mình.
        <a href="dang_nhap.php" class="btn btn-sm btn-login ms-2">Đăng nhập</a>
    </div>

    <!-- Sau này khi đã đăng nhập, bạn load dữ liệu từ database
         và hiển thị danh sách khóa học ở đây -->
</section>

<footer>
    <div class="container text-center">
        © <?php echo date('Y'); ?> Hệ thống học tập trực tuyến - PTIT. 
    </div>
</footer>

<!-- Bootstrap JS (tùy chọn, cho dropdown, modal...) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Icon Bootstrap (cho mấy icon điện thoại, email) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</body>
</html>
