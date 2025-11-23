<?php

session_start();
require_once 'config.php';

$loi   = "";
$email = "";

// Nếu người dùng nhấn nút Đăng nhập
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $mat_khau = trim($_POST['mat_khau'] ?? '');

    if ($email === '' || $mat_khau === '') {
        $loi = "Vui lòng nhập đầy đủ email và mật khẩu.";
    } else {
        try {
            $pdo = Database::pdo();

            // Kiểm tra tài khoản
            $sql = "SELECT * FROM nguoi_dung
                    WHERE email = :email
                      AND mat_khau = :mat_khau
                      AND trang_thai = 'hoat_dong'
                    LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':email'    => $email,
                ':mat_khau' => $mat_khau
            ]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Lưu session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['ho_ten']  = $user['ho_ten'];
                $_SESSION['vai_tro'] = $user['vai_tro'];
                $_SESSION['email']   = $user['email'];

                // Điều hướng theo vai trò
                switch ($user['vai_tro']) {
                    case 'quan_tri':
                        header("Location: admin_dashboard.php");
                        break;

                    case 'giang_vien':
                        header("Location: giangvien_dashboard.php");
                        break;

                    default: // hoc_vien
                        header("Location: student_dashboard.php");
                        break;
                }
                exit;
            } else {
                $loi = "Email hoặc mật khẩu không đúng.";
            }

        } catch (PDOException $e) {
            $loi = "Lỗi hệ thống: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đăng nhập - Hệ thống học tập trực tuyến</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- CSS chung -->
    <link rel="stylesheet" href="css/style.css">

    <!-- Icons -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>
<body class="bg-light">

<!-- Thanh đỏ trên cùng -->
<div class="top-bar">
    <div class="container d-flex justify-content-between align-items-center">
        <div>
            <i class="bi bi-house-door-fill"></i>
            <a href="index.php">Cổng học trực tuyến</a>
        </div>
        <div>
            <span class="me-3"><i class="bi bi-telephone-fill"></i> (023) 1456789</span>
            <span><i class="bi bi-envelope-fill"></i> elearning@ptit.edu.vn</span>
        </div>
    </div>
</div>

<!-- Header -->
<header class="main-header">
    <div class="container py-2 d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center">
            <img src="image/ptit.png" alt="Logo PTIT" style="height:55px;" class="me-3">
        </div>

        <nav class="d-flex align-items-center">
            <a href="dang_nhap.php" class="btn btn-login">Đăng nhập</a>
        </nav>
    </div>
</header>

<!-- Hero -->
<section class="hero">
    <div class="container text-center">
        <h1 class="hero-title">ĐĂNG NHẬP HỆ THỐNG</h1>
    </div>
</section>

<!-- Form đăng nhập -->
<div class="container" style="max-width: 480px; margin-top: 40px; margin-bottom: 50px;">
    <div class="card shadow-sm">
        <div class="card-body p-4">

            <h4 class="text-center mb-3" style="color:#b30000;">Thông tin đăng nhập</h4>

            <?php if ($loi): ?>
                <div class="alert alert-danger py-2">
                    <?= htmlspecialchars($loi, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email"
                           name="email"
                           class="form-control"
                           value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
                           required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Mật khẩu</label>
                    <input type="password" name="mat_khau" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-login w-100 mt-2">
                    Đăng nhập
                </button>
            </form>

        </div>
    </div>

    <div class="text-center mt-3">
        <a href="index.php" class="small">&laquo; Quay lại trang chủ</a>
    </div>
</div>

<footer>
    <div class="container text-center">
        © <?= date('Y') ?> Hệ thống học tập trực tuyến - PTIT.
    </div>
</footer>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
