<?php
session_start();
require_once 'config.php';

// Chỉ cho phép Admin truy cập
if (!isset($_SESSION['user_id']) || ($_SESSION['vai_tro'] ?? '') !== 'quan_tri') {
    header("Location: dang_nhap.php");
    exit;
}

$pdo = Database::pdo();
$errors = [];
$thong_bao = '';

$ho_ten_admin = $_SESSION['ho_ten'] ?? 'Quản trị hệ thống';

/* ============================
   LẤY DANH SÁCH KHÓA HỌC
============================ */
$stmt = $pdo->query("SELECT id, ten_khoa_hoc FROM khoa_hoc ORDER BY ten_khoa_hoc");
$ds_khoa_hoc = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ============================
   XỬ LÝ THÊM BÀI GIẢNG
============================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $tieu_de = trim($_POST['tieu_de'] ?? '');
    $id_khoa_hoc = (int)($_POST['id_khoa_hoc'] ?? 0);
    $loai_noi_dung = $_POST['loai_noi_dung'] ?? 'video';
    $duong_dan_noi_dung = trim($_POST['duong_dan_noi_dung'] ?? '');
    $noi_dung_html = trim($_POST['noi_dung_html'] ?? '');
    $thu_tu_hien_thi = (int)($_POST['thu_tu_hien_thi'] ?? 1);
    $hien_thi = isset($_POST['hien_thi']) ? 1 : 0;

    // Validate
    if ($tieu_de === '') {
        $errors[] = "Tiêu đề không được để trống.";
    }
    if ($id_khoa_hoc <= 0) {
        $errors[] = "Vui lòng chọn khóa học.";
    }
    if (!in_array($loai_noi_dung, ['video','pdf','html','tep','link'], true)) {
        $errors[] = "Loại nội dung không hợp lệ.";
    }

    // Nếu không lỗi → thêm mới
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO bai_giang (
                    id_khoa_hoc, tieu_de, loai_noi_dung, duong_dan_noi_dung,
                    noi_dung_html, thu_tu_hien_thi, hien_thi, ngay_tao, ngay_cap_nhat
                )
                VALUES (
                    :id_khoa_hoc, :tieu_de, :loai, :duongdan,
                    :html, :thutu, :hienthi, NOW(), NOW()
                )
            ");

            $stmt->execute([
                ':id_khoa_hoc' => $id_khoa_hoc,
                ':tieu_de' => $tieu_de,
                ':loai' => $loai_noi_dung,
                ':duongdan' => $duong_dan_noi_dung,
                ':html' => $noi_dung_html,
                ':thutu' => $thu_tu_hien_thi,
                ':hienthi' => $hien_thi
            ]);

            header("Location: admin_lessons.php?msg=them_thanh_cong");
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
    <title>Thêm bài giảng mới</title>

    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<!-- TOP BAR -->
<div class="top-bar">
    <div class="container d-flex justify-content-between">
        <div>
            <i class="bi bi-house-door"></i>
            <a href="admin_dashboard.php">Trang quản trị</a>
        </div>
        <div>
            Xin chào, <strong><?= htmlspecialchars($ho_ten_admin) ?></strong>
            &nbsp;|&nbsp;
            <a href="dang_xuat.php" style="color:white;">Đăng xuất</a>
        </div>
    </div>
</div>

<!-- HEADER -->
<header class="main-header">
    <div class="container py-2 d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center">
            <img src="image/ptit.png" style="height:55px;" class="me-3">
            <div>
                <div class="logo-text">THÊM BÀI GIẢNG MỚI</div>
                <div class="logo-subtext">Tạo mới nội dung bài giảng cho khóa học</div>
            </div>
        </div>

        <a href="admin_lessons.php" class="btn btn-outline-secondary btn-sm">
            &laquo; Quay lại danh sách
        </a>
    </div>
</header>

<div class="container mt-4 mb-5">

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach ?>
            </ul>
        </div>
    <?php endif ?>

    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <strong>Nhập thông tin bài giảng</strong>
        </div>

        <div class="card-body">

            <form method="post" class="row g-3">

                <!-- Tiêu đề -->
                <div class="col-md-6">
                    <label class="form-label">Tiêu đề bài giảng <span class="text-danger">*</span></label>
                    <input type="text" name="tieu_de" class="form-control"
                           value="<?= htmlspecialchars($_POST['tieu_de'] ?? '') ?>" required>
                </div>

                <!-- Khóa học -->
                <div class="col-md-6">
                    <label class="form-label">Thuộc khóa học <span class="text-danger">*</span></label>
                    <select name="id_khoa_hoc" class="form-select" required>
                        <option value="">-- Chọn khóa học --</option>
                        <?php foreach ($ds_khoa_hoc as $kh): ?>
                            <option value="<?= $kh['id'] ?>"
                                <?= (($_POST['id_khoa_hoc'] ?? '') == $kh['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($kh['ten_khoa_hoc']) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                </div>

                <!-- Loại nội dung -->
                <div class="col-md-4">
                    <label class="form-label">Loại nội dung</label>
                    <select name="loai_noi_dung" class="form-select">
                        <?php
                        $list = ['video','pdf','html','tep','link'];
                        foreach ($list as $l):
                        ?>
                            <option value="<?= $l ?>"
                                <?= (($_POST['loai_noi_dung'] ?? '') == $l) ? 'selected' : '' ?>>
                                <?= strtoupper($l) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                </div>

                <!-- Đường dẫn nội dung -->
                <div class="col-md-4">
                    <label class="form-label">Đường dẫn nội dung (URL / file)</label>
                    <input type="text" name="duong_dan_noi_dung" class="form-control"
                           value="<?= htmlspecialchars($_POST['duong_dan_noi_dung'] ?? '') ?>">
                </div>

                <!-- Thứ tự -->
                <div class="col-md-4">
                    <label class="form-label">Thứ tự hiển thị</label>
                    <input type="number" name="thu_tu_hien_thi" class="form-control"
                           value="<?= htmlspecialchars($_POST['thu_tu_hien_thi'] ?? 1) ?>">
                </div>

                <!-- Nội dung HTML -->
                <div class="col-12">
                    <label class="form-label">Nội dung HTML (tuỳ chọn)</label>
                    <textarea name="noi_dung_html" class="form-control" rows="4"><?= 
                        htmlspecialchars($_POST['noi_dung_html'] ?? '') ?></textarea>
                </div>

                <!-- Hiển thị -->
                <div class="col-12">
                    <label class="form-check-label">
                        <input type="checkbox" name="hien_thi" class="form-check-input"
                            <?= isset($_POST['hien_thi']) ? 'checked' : '' ?>>
                        Hiển thị bài giảng
                    </label>
                </div>

                <!-- Nút -->
                <div class="col-12">
                    <button class="btn btn-primary">
                        <i class="bi bi-save"></i> Lưu bài giảng
                    </button>

                    <a href="admin_lessons.php" class="btn btn-secondary">Hủy</a>
                </div>

            </form>

        </div>
    </div>

</div>

<footer>
    <div class="container text-center">
        © <?= date('Y') ?> Hệ thống E-learning PTIT - Thêm bài giảng.
    </div>
</footer>

</body>
</html>
