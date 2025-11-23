<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['vai_tro'] ?? '') !== 'quan_tri') {
    header("Location: dang_nhap.php");
    exit;
}

$ho_ten_admin = $_SESSION['ho_ten'] ?? 'Quản trị hệ thống';

$pdo    = Database::pdo();
$errors = [];
$success = '';

$ten_khoa_hoc      = '';
$duong_dan_tom_tat = '';
$mo_ta             = '';
$id_giang_vien     = '';
$danh_muc          = '';
$hoc_phi           = '0';
$ngay_bat_dau      = '';
$ngay_ket_thuc     = '';
$trang_thai        = 'nhap';

// Lấy danh sách giảng viên
$ds_giang_vien = [];
try {
    $stmt = $pdo->query("SELECT id, ho_ten FROM nguoi_dung WHERE vai_tro = 'giang_vien' ORDER BY ho_ten");
    $ds_giang_vien = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Lỗi hệ thống: " . $e->getMessage();
}

// Xử lý submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ten_khoa_hoc      = trim($_POST['ten_khoa_hoc'] ?? '');
    $duong_dan_tom_tat = trim($_POST['duong_dan_tom_tat'] ?? '');
    $mo_ta             = trim($_POST['mo_ta'] ?? '');
    $id_giang_vien     = (int)($_POST['id_giang_vien'] ?? 0);
    $danh_muc          = trim($_POST['danh_muc'] ?? '');
    $hoc_phi           = trim($_POST['hoc_phi'] ?? '0');
    $ngay_bat_dau      = $_POST['ngay_bat_dau'] ?: null;
    $ngay_ket_thuc     = $_POST['ngay_ket_thuc'] ?: null;
    $trang_thai        = $_POST['trang_thai'] ?? 'nhap';

    if ($ten_khoa_hoc === '') {
        $errors[] = "Tên khóa học không được để trống.";
    }
    if ($duong_dan_tom_tat === '') {
        $errors[] = "Slug (đường dẫn tóm tắt) không được để trống.";
    }
    if ($id_giang_vien <= 0) {
        $errors[] = "Vui lòng chọn giảng viên phụ trách.";
    }
    if (!in_array($trang_thai, ['nhap','cong_bo','luu_tru'], true)) {
        $errors[] = "Trạng thái không hợp lệ.";
    }
    if ($hoc_phi === '' || !is_numeric($hoc_phi) || (float)$hoc_phi < 0) {
        $errors[] = "Học phí phải là số >= 0.";
    }

    // kiểm tra slug trùng
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM khoa_hoc WHERE duong_dan_tom_tat = :slug LIMIT 1");
        $stmt->execute([':slug' => $duong_dan_tom_tat]);
        if ($stmt->fetch()) {
            $errors[] = "Slug đã tồn tại, vui lòng chọn slug khác.";
        }
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO khoa_hoc
                (ten_khoa_hoc, duong_dan_tom_tat, mo_ta, id_giang_vien, danh_muc,
                 hoc_phi, ngay_bat_dau, ngay_ket_thuc, trang_thai, ngay_tao, ngay_cap_nhat)
                VALUES
                (:ten, :slug, :mota, :gv, :dm, :hp, :nbd, :nkt, :tt, NOW(), NOW())
            ");
            $stmt->execute([
                ':ten'  => $ten_khoa_hoc,
                ':slug' => $duong_dan_tom_tat,
                ':mota' => $mo_ta,
                ':gv'   => $id_giang_vien,
                ':dm'   => $danh_muc,
                ':hp'   => (float)$hoc_phi,
                ':nbd'  => $ngay_bat_dau,
                ':nkt'  => $ngay_ket_thuc,
                ':tt'   => $trang_thai,
            ]);

            header("Location: admin_courses.php?msg=them_thanh_cong");
            exit;
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
    <title>Thêm khóa học - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<div class="top-bar">
    <div class="container d-flex justify-content-between align-items-center">
        <div>
            <i class="bi bi-house-door-fill"></i>
            <a href="admin_dashboard.php">Trang quản trị</a>
        </div>
        <div>
            Xin chào, <strong><?php echo htmlspecialchars($ho_ten_admin); ?></strong>
            &nbsp;|&nbsp;
            <a href="dang_xuat.php" style="color:#fff;">Đăng xuất</a>
        </div>
    </div>
</div>

<header class="main-header">
    <div class="container py-2 d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center">
            <img src="image/ptit.png" alt="PTIT Logo" style="height:55px;" class="me-3">
            <div>
                <div class="logo-text">THÊM KHÓA HỌC MỚI</div>
                <div class="logo-subtext">Nhập thông tin khóa học e-learning</div>
            </div>
        </div>

        <nav>
            <a href="admin_courses.php" class="btn btn-sm btn-outline-secondary">
                &laquo; Quay lại danh sách khóa học
            </a>
        </nav>
    </div>
</header>

<div class="container mt-4 mb-5">

    <h4 class="mb-3" style="color:#b30000;">Thêm khóa học</h4>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $e): ?>
                    <li><?php echo htmlspecialchars($e, ENT_QUOTES, 'UTF-8'); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Tên khóa học <span class="text-danger">*</span></label>
            <input type="text" name="ten_khoa_hoc" class="form-control"
                   value="<?php echo htmlspecialchars($ten_khoa_hoc); ?>" required>
        </div>

        <div class="col-md-6">
            <label class="form-label">Slug (đường dẫn tóm tắt) <span class="text-danger">*</span></label>
            <input type="text" name="duong_dan_tom_tat" class="form-control"
                   placeholder="vd: lap-trinh-c-co-ban"
                   value="<?php echo htmlspecialchars($duong_dan_tom_tat); ?>" required>
        </div>

        <div class="col-md-4">
            <label class="form-label">Giảng viên phụ trách <span class="text-danger">*</span></label>
            <select name="id_giang_vien" class="form-select" required>
                <option value="">-- Chọn giảng viên --</option>
                <?php foreach ($ds_giang_vien as $gv): ?>
                    <option value="<?php echo $gv['id']; ?>"
                        <?php if ($id_giang_vien == $gv['id']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($gv['ho_ten']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-4">
            <label class="form-label">Danh mục</label>
            <input type="text" name="danh_muc" class="form-control"
                   placeholder="Ví dụ: Lập trình, Mạng máy tính..."
                   value="<?php echo htmlspecialchars($danh_muc); ?>">
        </div>

        <div class="col-md-4">
            <label class="form-label">Học phí (VND)</label>
            <input type="number" name="hoc_phi" min="0" step="1000"
                   class="form-control"
                   value="<?php echo htmlspecialchars($hoc_phi); ?>">
        </div>

        <div class="col-md-3">
            <label class="form-label">Ngày bắt đầu</label>
            <input type="date" name="ngay_bat_dau" class="form-control"
                   value="<?php echo htmlspecialchars($ngay_bat_dau); ?>">
        </div>

        <div class="col-md-3">
            <label class="form-label">Ngày kết thúc</label>
            <input type="date" name="ngay_ket_thuc" class="form-control"
                   value="<?php echo htmlspecialchars($ngay_ket_thuc); ?>">
        </div>

        <div class="col-md-3">
            <label class="form-label">Trạng thái</label>
            <select name="trang_thai" class="form-select">
                <option value="nhap"    <?php if ($trang_thai === 'nhap')    echo 'selected'; ?>>Nháp</option>
                <option value="cong_bo" <?php if ($trang_thai === 'cong_bo') echo 'selected'; ?>>Công bố</option>
                <option value="luu_tru" <?php if ($trang_thai === 'luu_tru') echo 'selected'; ?>>Lưu trữ</option>
            </select>
        </div>

        <div class="col-12">
            <label class="form-label">Mô tả</label>
            <textarea name="mo_ta" class="form-control" rows="4"><?php echo htmlspecialchars($mo_ta); ?></textarea>
        </div>

        <div class="col-12">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save"></i> Lưu khóa học
            </button>
            <a href="admin_courses.php" class="btn btn-secondary">
                Hủy
            </a>
        </div>
    </form>
</div>

<footer>
    <div class="container text-center">
        © <?php echo date('Y'); ?> Hệ thống E-learning PTIT - Thêm khóa học.
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
