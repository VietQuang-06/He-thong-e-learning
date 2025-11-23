<?php
session_start();
require_once 'config.php';

// Chỉ admin mới được vào
if (!isset($_SESSION['user_id']) || ($_SESSION['vai_tro'] ?? '') !== 'quan_tri') {
    header("Location: dang_nhap.php");
    exit;
}

$ho_ten_admin = $_SESSION['ho_ten'] ?? 'Quản trị hệ thống';

// Mảng lỗi
$errors = [];

// Giá trị giữ lại trên form khi có lỗi
$ho_ten        = '';
$ma_sinh_vien  = '';
$lop_hoc       = '';
$gioi_tinh     = 'nam';
$email         = '';
$mat_khau      = '';
$vai_tro       = 'hoc_vien';
$so_dien_thoai = '';
$trang_thai    = 'hoat_dong';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Lấy dữ liệu từ form
    $ho_ten       = trim($_POST['ho_ten'] ?? '');
    $ma_sinh_vien = trim($_POST['ma_sinh_vien'] ?? '');
    $lop_hoc      = trim($_POST['lop_hoc'] ?? '');
    $gioi_tinh    = $_POST['gioi_tinh'] ?? 'nam';
    $email        = trim($_POST['email'] ?? '');
    $mat_khau     = trim($_POST['mat_khau'] ?? '');
    $vai_tro      = $_POST['vai_tro'] ?? 'hoc_vien';
    $so_dien_thoai= trim($_POST['so_dien_thoai'] ?? '');
    $trang_thai   = $_POST['trang_thai'] ?? 'hoat_dong';

    // Validate
    if ($ho_ten === '') {
        $errors[] = "Vui lòng nhập họ tên.";
    }
    if ($email === '') {
        $errors[] = "Vui lòng nhập email.";
    }
    if ($mat_khau === '') {
        $errors[] = "Vui lòng nhập mật khẩu.";
    }
    if (!in_array($gioi_tinh, ['nam','nu'], true)) {
        $errors[] = "Giới tính không hợp lệ.";
    }

    // Nếu là học viên thì bắt buộc phải có mã SV & lớp
    if ($vai_tro === 'hoc_vien') {
        if ($ma_sinh_vien === '') {
            $errors[] = "Học viên phải có mã sinh viên.";
        }
        if ($lop_hoc === '') {
            $errors[] = "Học viên phải có lớp học.";
        }
    }

    if (empty($errors)) {
        try {
            $pdo = Database::pdo();

            // Kiểm tra email đã tồn tại chưa
            $check = $pdo->prepare("SELECT COUNT(*) FROM nguoi_dung WHERE email = :email");
            $check->execute([':email' => $email]);
            if ($check->fetchColumn() > 0) {
                $errors[] = "Email này đã được sử dụng.";
            } else {
                // Vì ma_sinh_vien, lop_hoc trong DB là NOT NULL, nên nếu trống thì lưu ''
                if ($ma_sinh_vien === '') {
                    $ma_sinh_vien = '';
                }
                if ($lop_hoc === '') {
                    $lop_hoc = '';
                }

                $sql = "INSERT INTO nguoi_dung
                        (ho_ten, ma_sinh_vien, lop_hoc, gioi_tinh, email, mat_khau, vai_tro, so_dien_thoai, trang_thai, ngay_tao, ngay_cap_nhat)
                        VALUES
                        (:ho_ten, :ma_sinh_vien, :lop_hoc, :gioi_tinh, :email, :mat_khau, :vai_tro, :so_dien_thoai, :trang_thai, NOW(), NOW())";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':ho_ten'       => $ho_ten,
                    ':ma_sinh_vien' => $ma_sinh_vien,
                    ':lop_hoc'      => $lop_hoc,
                    ':gioi_tinh'    => $gioi_tinh,
                    ':email'        => $email,
                    ':mat_khau'     => $mat_khau, // Đang để plain text theo yêu cầu
                    ':vai_tro'      => $vai_tro,
                    ':so_dien_thoai'=> $so_dien_thoai !== '' ? $so_dien_thoai : null,
                    ':trang_thai'   => $trang_thai
                ]);

                // Thêm xong quay lại danh sách
                header("Location: admin_users.php?msg=them_thanh_cong");
                exit;
            }

        } catch (PDOException $e) {
            $errors[] = "Lỗi hệ thống: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Thêm tài khoản - Quản trị hệ thống</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<!-- Thanh đỏ trên cùng -->
<div class="top-bar">
    <div class="container d-flex justify-content-between align-items-center">
        <div>
            <i class="bi bi-house-door-fill"></i>
            <a href="admin_dashboard.php">Trang quản trị</a>
        </div>
        <div>
            Xin chào, <strong><?php echo htmlspecialchars($ho_ten_admin, ENT_QUOTES, 'UTF-8'); ?></strong>
            &nbsp;|&nbsp;
            <a href="dang_xuat.php" style="color:#fff;">Đăng xuất</a>
        </div>
    </div>
</div>

<!-- Header -->
<header class="main-header">
    <div class="container py-2 d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center">
            <img src="image/ptit.png" alt="Logo PTIT" style="height:55px;" class="me-3">
            <div>
                <div class="logo-text">THÊM TÀI KHOẢN NGƯỜI DÙNG</div>
                <div class="logo-subtext">Sinh viên, giảng viên, quản trị viên</div>
            </div>
        </div>

        <nav>
            <a href="admin_users.php" class="btn btn-sm btn-outline-secondary">
                &laquo; Quay lại danh sách
            </a>
        </nav>
    </div>
</header>

<div class="container mt-4 mb-5" style="max-width: 750px;">
    <h4 class="mb-3" style="color:#b30000;">Thông tin tài khoản</h4>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $er): ?>
                    <li><?php echo htmlspecialchars($er, ENT_QUOTES, 'UTF-8'); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
        <div class="mb-3">
            <label class="form-label">Họ tên <span class="text-danger">*</span></label>
            <input type="text" name="ho_ten" class="form-control"
                   value="<?php echo htmlspecialchars($ho_ten, ENT_QUOTES, 'UTF-8'); ?>" required>
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">Giới tính <span class="text-danger">*</span></label>
                <select name="gioi_tinh" class="form-select">
                    <option value="nam" <?php if ($gioi_tinh === 'nam') echo 'selected'; ?>>Nam</option>
                    <option value="nu"  <?php if ($gioi_tinh === 'nu')  echo 'selected'; ?>>Nữ</option>
                </select>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Mã sinh viên / Mã GV</label>
                <input type="text" name="ma_sinh_vien" class="form-control"
                       value="<?php echo htmlspecialchars($ma_sinh_vien, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Lớp học</label>
                <input type="text" name="lop_hoc" class="form-control"
                       value="<?php echo htmlspecialchars($lop_hoc, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Số điện thoại</label>
            <input type="text" name="so_dien_thoai" class="form-control"
                   value="<?php echo htmlspecialchars($so_dien_thoai, ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Email <span class="text-danger">*</span></label>
            <input type="email" name="email" class="form-control"
                   value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Mật khẩu <span class="text-danger">*</span></label>
            <input type="text" name="mat_khau" class="form-control"
                   value="<?php echo htmlspecialchars($mat_khau, ENT_QUOTES, 'UTF-8'); ?>"
                   placeholder="Có thể đặt bằng mã sinh viên, rồi yêu cầu sinh viên đổi sau" required>
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">Vai trò</label>
                <select name="vai_tro" class="form-select">
                    <option value="hoc_vien"   <?php if ($vai_tro === 'hoc_vien')   echo 'selected'; ?>>Học viên</option>
                    <option value="giang_vien" <?php if ($vai_tro === 'giang_vien') echo 'selected'; ?>>Giảng viên</option>
                    <option value="quan_tri"   <?php if ($vai_tro === 'quan_tri')   echo 'selected'; ?>>Quản trị viên</option>
                </select>
            </div>

            <div class="col-md-4 mb-3">
                <label class="form-label">Trạng thái</label>
                <select name="trang_thai" class="form-select">
                    <option value="hoat_dong"        <?php if ($trang_thai === 'hoat_dong')        echo 'selected'; ?>>Hoạt động</option>
                    <option value="khong_hoat_dong"  <?php if ($trang_thai === 'khong_hoat_dong')  echo 'selected'; ?>>Không hoạt động</option>
                    <option value="chan"             <?php if ($trang_thai === 'chan')             echo 'selected'; ?>>Chặn</option>
                </select>
            </div>
        </div>

        <button class="btn btn-login mt-3" type="submit">
            <i class="bi bi-save"></i> Lưu tài khoản
        </button>
    </form>
</div>

<footer>
    <div class="container text-center">
        © <?php echo date('Y'); ?> Hệ thống E-learning PTIT - Thêm tài khoản.
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
