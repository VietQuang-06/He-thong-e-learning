<?php
session_start();
require_once 'config.php';

// Chỉ admin được vào
if (!isset($_SESSION['user_id']) || ($_SESSION['vai_tro'] ?? '') !== 'quan_tri') {
    header("Location: dang_nhap.php");
    exit;
}

$pdo = Database::pdo();
$errors = [];
$ho_ten_admin = $_SESSION['ho_ten'] ?? 'Quản trị hệ thống';

/* ============================
   LẤY DANH SÁCH KHÓA HỌC
============================ */
$stmt = $pdo->query("SELECT id, ten_khoa_hoc FROM khoa_hoc ORDER BY ten_khoa_hoc");
$ds_khoa_hoc = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ============================
   XỬ LÝ THÊM BÀI THI
============================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $tieu_de = trim($_POST['tieu_de']);
    $id_khoa_hoc = (int)$_POST['id_khoa_hoc'];
    $mo_ta = trim($_POST['mo_ta']);
    $thoi_gian_bat_dau = $_POST['thoi_gian_bat_dau'] ?: null;
    $thoi_gian_ket_thuc = $_POST['thoi_gian_ket_thuc'] ?: null;
    $thoi_luong = (int)$_POST['thoi_luong_phut'];
    $gioi_han_so_lan = (int)$_POST['gioi_han_so_lan'];
    $tron_cau_hoi = isset($_POST['tron_cau_hoi']) ? 1 : 0;
    $trang_thai = $_POST['trang_thai'];

    // Validate
    if ($tieu_de === '') $errors[] = "Tiêu đề bài thi không được để trống.";
    if ($id_khoa_hoc <= 0) $errors[] = "Vui lòng chọn khóa học.";
    if (!in_array($trang_thai, ['nhap','dang_mo','dong'], true))
        $errors[] = "Trạng thái không hợp lệ.";

    if (empty($errors)) {
        try {

            $stmt = $pdo->prepare("
                INSERT INTO bai_thi
                (id_khoa_hoc, tieu_de, mo_ta, thoi_gian_bat_dau, thoi_gian_ket_thuc,
                 thoi_luong_phut, gioi_han_so_lan, tron_cau_hoi, trang_thai, ngay_tao)
                VALUES
                (:kh, :t, :m, :bd, :kt, :tl, :sl, :tron, :tt, NOW())
            ");

            $stmt->execute([
                ':kh' => $id_khoa_hoc,
                ':t'  => $tieu_de,
                ':m'  => $mo_ta,
                ':bd' => $thoi_gian_bat_dau,
                ':kt' => $thoi_gian_ket_thuc,
                ':tl' => $thoi_luong,
                ':sl' => $gioi_han_so_lan,
                ':tron' => $tron_cau_hoi,
                ':tt' => $trang_thai
            ]);

            header("Location: admin_exams.php?msg=them_thanh_cong");
            exit;

        } catch (PDOException $e) {
            $errors[] = "Lỗi khi thêm bài thi: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Thêm bài thi mới</title>

    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <link rel="stylesheet" href="css/style.css">
</head>

<body>

<!-- Thanh đỏ trên cùng -->
<div class="top-bar">
    <div class="container d-flex justify-content-between">
        <div>
            <i class="bi bi-speedometer2"></i>
            <a href="admin_dashboard.php">Trang quản trị</a>
        </div>
        <div>
            Xin chào, <strong><?= htmlspecialchars($ho_ten_admin) ?></strong>
            |
            <a href="dang_xuat.php" style="color:white;">Đăng xuất</a>
        </div>
    </div>
</div>

<header class="main-header">
    <div class="container py-2 d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center">
            <img src="image/ptit.png" style="height:55px;" class="me-3">
            <div>
                <div class="logo-text">THÊM BÀI THI MỚI</div>
                <div class="logo-subtext">Tạo bài thi cho khóa học</div>
            </div>
        </div>

        <a href="admin_exams.php" class="btn btn-outline-secondary btn-sm">
            &laquo; Quay lại quản lý bài thi
        </a>
    </div>
</header>

<div class="container mt-4 mb-5">

    <h4 class="mb-3" style="color:#b30000;">Thêm bài thi</h4>

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
        <div class="card-body">

            <form method="post" class="row g-3">

                <!-- Tiêu đề -->
                <div class="col-md-6">
                    <label class="form-label">Tiêu đề bài thi *</label>
                    <input type="text" name="tieu_de" class="form-control"
                           required value="<?= htmlspecialchars($_POST['tieu_de'] ?? '') ?>">
                </div>

                <!-- Khóa học -->
                <div class="col-md-6">
                    <label class="form-label">Thuộc khóa học *</label>
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

                <!-- Mô tả -->
                <div class="col-12">
                    <label class="form-label">Mô tả</label>
                    <textarea name="mo_ta" class="form-control" rows="3"><?= 
                        htmlspecialchars($_POST['mo_ta'] ?? '') ?></textarea>
                </div>

                <!-- Thời gian -->
                <div class="col-md-4">
                    <label class="form-label">Thời gian mở</label>
                    <input type="datetime-local" name="thoi_gian_bat_dau" class="form-control"
                           value="<?= htmlspecialchars($_POST['thoi_gian_bat_dau'] ?? '') ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Thời gian đóng</label>
                    <input type="datetime-local" name="thoi_gian_ket_thuc" class="form-control"
                           value="<?= htmlspecialchars($_POST['thoi_gian_ket_thuc'] ?? '') ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Thời lượng (phút)</label>
                    <input type="number" name="thoi_luong_phut" min="1" class="form-control"
                           value="<?= htmlspecialchars($_POST['thoi_luong_phut'] ?? 60) ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Giới hạn số lần thi</label>
                    <input type="number" name="gioi_han_so_lan" min="1" class="form-control"
                           value="<?= htmlspecialchars($_POST['gioi_han_so_lan'] ?? 1) ?>">
                </div>

                <!-- Xáo trộn -->
                <div class="col-md-4 d-flex align-items-center">
                    <label class="form-check-label">
                        <input type="checkbox" name="tron_cau_hoi" class="form-check-input"
                            <?= isset($_POST['tron_cau_hoi']) ? 'checked' : '' ?>>
                        Xáo trộn câu hỏi
                    </label>
                </div>

                <!-- Trạng thái -->
                <div class="col-md-4">
                    <label class="form-label">Trạng thái</label>
                    <select name="trang_thai" class="form-select">
                        <option value="nhap" <?= (($_POST['trang_thai'] ?? '')==='nhap')?'selected':'' ?>>Nháp</option>
                        <option value="dang_mo" <?= (($_POST['trang_thai'] ?? '')==='dang_mo')?'selected':'' ?>>Đang mở</option>
                        <option value="dong" <?= (($_POST['trang_thai'] ?? '')==='dong')?'selected':'' ?>>Đóng</option>
                    </select>
                </div>

                <div class="col-12">
                    <button class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Tạo bài thi
                    </button>

                    <a href="admin_exams.php" class="btn btn-secondary">Hủy</a>
                </div>

            </form>

        </div>
    </div>

</div>

<footer>
    <div class="container text-center">
        © <?= date('Y') ?> Hệ thống E-learning PTIT – Thêm bài thi
    </div>
</footer>

</body>
</html>
