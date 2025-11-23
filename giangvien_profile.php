<?php
session_start();
require_once 'config.php';

// Chỉ cho phép GIẢNG VIÊN
if (!isset($_SESSION['user_id']) || ($_SESSION['vai_tro'] ?? '') !== 'giang_vien') {
    header("Location: dang_nhap.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$ho_ten_session = $_SESSION['ho_ten'] ?? '';

$error = '';
$success = '';
$user  = null;

try {
    $pdo = Database::pdo();

    // Lấy thông tin người dùng
    $sql = "SELECT * FROM nguoi_dung WHERE id = :id LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $error = "Không tìm thấy tài khoản.";
    }

} catch (PDOException $e) {
    $error = "Lỗi hệ thống: " . $e->getMessage();
}

// Xử lý cập nhật thông tin
if ($user && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {

    $ho_ten        = trim($_POST['ho_ten']);
    $so_dien_thoai = trim($_POST['so_dien_thoai']);
    $mat_khau_cu   = $_POST['mat_khau_cu'] ?? '';
    $mat_khau_moi  = $_POST['mat_khau_moi'] ?? '';
    $xac_nhan_mk   = $_POST['xac_nhan_mk'] ?? '';

    $anh_dai_dien  = $user['anh_dai_dien'];

    // Upload ảnh đại diện (nếu có)
    if (!empty($_FILES['anh_dai_dien']['name'])) {
        $file = $_FILES['anh_dai_dien'];
        $allowed = ['jpg','jpeg','png','gif'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            $error = "Ảnh đại diện chỉ hỗ trợ JPG, PNG, GIF.";
        } else {
            $new_name = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
            $target = "uploads/" . $new_name;

            if (move_uploaded_file($file['tmp_name'], $target)) {
                $anh_dai_dien = $target;
            } else {
                $error = "Không thể tải ảnh đại diện.";
            }
        }
    }

    // Đổi mật khẩu nếu có nhập
    $update_mk = false;

    if ($mat_khau_moi !== '') {

        if ($mat_khau_moi !== $xac_nhan_mk) {
            $error = "Mật khẩu xác nhận không trùng khớp.";
        } elseif ($mat_khau_cu !== $user['mat_khau']) {  // không mã hóa (theo yêu cầu DB)
            $error = "Mật khẩu cũ không chính xác.";
        } else {
            $update_mk = true;
        }
    }

    // Nếu không có lỗi → cập nhật DB
    if ($error === '') {
        try {
            $sql = "
                UPDATE nguoi_dung
                SET ho_ten = :ho_ten,
                    so_dien_thoai = :sdt,
                    anh_dai_dien = :anh
                    " . ($update_mk ? ", mat_khau = :mk " : "") . "
                WHERE id = :id
            ";

            $stmt = $pdo->prepare($sql);

            $params = [
                ':ho_ten' => $ho_ten,
                ':sdt'    => $so_dien_thoai,
                ':anh'    => $anh_dai_dien,
                ':id'     => $user_id
            ];

            if ($update_mk) $params[':mk'] = $mat_khau_moi;

            $stmt->execute($params);

            // Cập nhật session + biến $user để hiển thị lại
            $_SESSION['ho_ten'] = $ho_ten;
            $user['ho_ten'] = $ho_ten;
            $user['so_dien_thoai'] = $so_dien_thoai;
            $user['anh_dai_dien'] = $anh_dai_dien;
            if ($update_mk) {
                $user['mat_khau'] = $mat_khau_moi;
            }

            $success = "Cập nhật thông tin thành công!";
        } catch (PDOException $e) {
            $error = "Lỗi cập nhật: " . $e->getMessage();
        }
    }
}

// Chuẩn bị avatar theo giới tính (nếu chưa có ảnh)
$avatar_src = '';
$gioi_tinh  = $user['gioi_tinh'] ?? 'nam'; // enum: 'nam','nu'
$first_char = $user ? mb_strtoupper(mb_substr($user['ho_ten'], 0, 1, 'UTF-8'), 'UTF-8') : 'A';

// avatar_src chỉ dùng nếu có ảnh upload; còn không thì dùng div "tự vẽ"
if (!empty($user['anh_dai_dien'])) {
    $avatar_src = $user['anh_dai_dien'];
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Thông tin giảng viên</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <link rel="stylesheet" href="css/style.css">

    <style>
        /* Avatar tự vẽ theo giới tính */
        .avatar-generated {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 72px;
            font-weight: 600;
            color: #fff;
            margin-bottom: 1rem;
        }
        .avatar-male {
            background: linear-gradient(135deg, #1976d2, #42a5f5);
        }
        .avatar-female {
            background: linear-gradient(135deg, #d81b60, #f48fb1);
        }
    </style>
</head>
<body>

<!-- Top bar -->
<div class="top-bar">
    <div class="container d-flex justify-content-between">
        <div>
            <a href="giangvien_dashboard.php" class="text-white">
                <i class="bi bi-mortarboard-fill"></i> Khu vực giảng viên
            </a>
        </div>
        <div>
            Xin chào, <strong><?php echo htmlspecialchars($ho_ten_session); ?></strong> |
            <a href="dang_xuat.php" class="text-white">Đăng xuất</a>
        </div>
    </div>
</div>

<header class="main-header">
    <div class="container d-flex align-items-center py-2">
        <img src="image/ptit.png" style="height:55px;" class="me-3" alt="PTIT">
        <div>
            <h4 class="logo-text mb-0">HỆ THỐNG E-LEARNING PTIT</h4>
            <span>Thông tin tài khoản giảng viên</span>
        </div>
    </div>
</header>

<div class="container mt-4 mb-5">

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if ($user): ?>
    <div class="card shadow-sm">
        <div class="card-body">

            <h4 class="mb-3" style="color:#b30000;">Thông tin giảng viên</h4>

            <form method="POST" enctype="multipart/form-data">

                <div class="row">
                    <div class="col-md-4 text-center">

                        <?php if ($avatar_src): ?>
                            <!-- Nếu đã có ảnh upload -->
                            <img src="<?php echo htmlspecialchars($avatar_src); ?>"
                                 class="img-thumbnail rounded-circle mb-3"
                                 style="width:180px;height:180px;object-fit:cover;"
                                 alt="Avatar">
                        <?php else: ?>
                            <!-- Avatar tự vẽ theo giới tính -->
                            <div class="avatar-generated <?php echo ($gioi_tinh === 'nu') ? 'avatar-female' : 'avatar-male'; ?>">
                                <?php echo htmlspecialchars($first_char); ?>
                            </div>
                        <?php endif; ?>

                        <input type="file" name="anh_dai_dien" class="form-control">

                    </div>

                    <div class="col-md-8">

                        <div class="mb-3">
                            <label class="form-label">Họ tên</label>
                            <input type="text" name="ho_ten" class="form-control"
                                   value="<?php echo htmlspecialchars($user['ho_ten']); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Giới tính</label>
                            <input type="text" class="form-control"
                                   value="<?php echo ($gioi_tinh === 'nu') ? 'Nữ' : 'Nam'; ?>" disabled>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Email (không thể sửa)</label>
                            <input type="text" class="form-control"
                                   value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                        </div>

                        <!-- Nếu muốn ẩn hẳn mã sinh viên / lớp học cho giảng viên thì có thể bỏ 2 block này -->
                        <div class="mb-3">
                            <label class="form-label">Mã sinh viên / Mã cán bộ</label>
                            <input type="text" class="form-control"
                                   value="<?php echo htmlspecialchars($user['ma_sinh_vien']); ?>" disabled>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Lớp / Bộ môn</label>
                            <input type="text" class="form-control"
                                   value="<?php echo htmlspecialchars($user['lop_hoc']); ?>" disabled>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Số điện thoại</label>
                            <input type="text" name="so_dien_thoai" class="form-control"
                                   value="<?php echo htmlspecialchars($user['so_dien_thoai']); ?>">
                        </div>

                        <hr>

                        <h5>Đổi mật khẩu</h5>

                        <div class="mb-3">
                            <label class="form-label">Mật khẩu cũ</label>
                            <input type="password" name="mat_khau_cu" class="form-control">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Mật khẩu mới</label>
                            <input type="password" name="mat_khau_moi" class="form-control">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Nhập lại mật khẩu mới</label>
                            <input type="password" name="xac_nhan_mk" class="form-control">
                        </div>

                        <button type="submit" name="update_profile" class="btn btn-danger">
                            <i class="bi bi-save"></i> Lưu thay đổi
                        </button>

                    </div>
                </div>

            </form>

        </div>
    </div>
    <?php endif; ?>

</div>

<footer class="text-center py-3">
    © <?php echo date('Y'); ?> Hệ thống E-learning PTIT
</footer>

</body>
</html>
