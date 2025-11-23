<?php
session_start();
require_once 'config.php';

// Chỉ cho phép Admin truy cập
if (!isset($_SESSION['user_id']) || ($_SESSION['vai_tro'] ?? '') !== 'quan_tri') {
    header("Location: dang_nhap.php");
    exit;
}

$ho_ten_admin = $_SESSION['ho_ten'] ?? 'Quản trị hệ thống';

$pdo = Database::pdo();
$errors = [];
$thong_bao = '';

/* ============================
    THÔNG BÁO
============================ */
if (isset($_GET['msg'])) {
    $map = [
        'them_thanh_cong' => "Thêm bài giảng thành công.",
        'cap_nhat_thanh_cong' => "Cập nhật bài giảng thành công.",
        'xoa_thanh_cong' => "Xóa bài giảng thành công.",
        'loi_he_thong' => "Có lỗi hệ thống.",
        'loi_id' => "ID không hợp lệ.",
    ];
    $thong_bao = $map[$_GET['msg']] ?? '';
}

/* ============================
    XÓA BÀI GIẢNG (GET)
============================ */
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    if ($id <= 0) {
        header("Location: admin_lessons.php?msg=loi_id");
        exit;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM bai_giang WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);

        header("Location: admin_lessons.php?msg=xoa_thanh_cong");
        exit;

    } catch (PDOException $e) {
        header("Location: admin_lessons.php?msg=loi_he_thong");
        exit;
    }
}

/* =====================================
    LẤY DANH SÁCH KHÓA HỌC (CHO LỌC)
===================================== */
$stmt = $pdo->query("SELECT id, ten_khoa_hoc FROM khoa_hoc ORDER BY ten_khoa_hoc");
$ds_khoa_hoc = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================================
    XỬ LÝ CẬP NHẬT BÀI GIẢNG (POST)
===================================== */
$editing_id = 0;
$editing_row = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['mode'] ?? '') === 'update') {

    $editing_id = (int)($_POST['id'] ?? 0);
    $tieu_de = trim($_POST['tieu_de'] ?? '');
    $id_khoa_hoc = (int)($_POST['id_khoa_hoc'] ?? 0);
    $loai_noi_dung = $_POST['loai_noi_dung'] ?? 'video';
    $duong_dan_noi_dung = trim($_POST['duong_dan_noi_dung'] ?? '');
    $noi_dung_html = $_POST['noi_dung_html'] ?? '';
    $thu_tu_hien_thi = (int)($_POST['thu_tu_hien_thi'] ?? 1);
    $hien_thi = isset($_POST['hien_thi']) ? 1 : 0;

    // Validate
    if ($editing_id <= 0) $errors[] = "ID bài giảng không hợp lệ.";
    if ($tieu_de === '') $errors[] = "Tiêu đề không được để trống.";
    if ($id_khoa_hoc <= 0) $errors[] = "Phải chọn khóa học.";

    if (empty($errors)) {
        try {
            $sql = "UPDATE bai_giang SET 
                        tieu_de = :tieu_de,
                        id_khoa_hoc = :id_khoa_hoc,
                        loai_noi_dung = :loai_noi_dung,
                        duong_dan_noi_dung = :duong_dan,
                        noi_dung_html = :html,
                        thu_tu_hien_thi = :thu_tu,
                        hien_thi = :ht,
                        ngay_cap_nhat = NOW()
                    WHERE id = :id";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':tieu_de' => $tieu_de,
                ':id_khoa_hoc' => $id_khoa_hoc,
                ':loai_noi_dung' => $loai_noi_dung,
                ':duong_dan' => $duong_dan_noi_dung,
                ':html' => $noi_dung_html,
                ':thu_tu' => $thu_tu_hien_thi,
                ':ht' => $hien_thi,
                ':id' => $editing_id
            ]);

            header("Location: admin_lessons.php?msg=cap_nhat_thanh_cong");
            exit;

        } catch (PDOException $e) {
            $errors[] = "Lỗi hệ thống khi cập nhật: " . $e->getMessage();
        }
    } else {
        // Giữ dữ liệu lỗi
        $editing_row = [
            'id' => $editing_id,
            'tieu_de' => $tieu_de,
            'id_khoa_hoc' => $id_khoa_hoc,
            'loai_noi_dung' => $loai_noi_dung,
            'duong_dan_noi_dung' => $duong_dan_noi_dung,
            'noi_dung_html' => $noi_dung_html,
            'thu_tu_hien_thi' => $thu_tu_hien_thi,
            'hien_thi' => $hien_thi
        ];
    }
}

/* ======================================
    NẾU NHẤN EDIT THÌ LẤY DỮ LIỆU BÀI GIẢNG
====================================== */
if ($editing_row === null && isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];

    if ($eid > 0) {
        $stmt = $pdo->prepare("SELECT * FROM bai_giang WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $eid]);
        $editing_row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($editing_row) $editing_id = $eid;
    }
}

/* =======================================
    LỌC + TÌM KIẾM
======================================= */
$id_kh_filter = $_GET['khoa_hoc'] ?? 'tat_ca';
$tu_khoa = trim($_GET['q'] ?? '');

$sql = "SELECT bg.*, kh.ten_khoa_hoc
        FROM bai_giang bg 
        JOIN khoa_hoc kh ON bg.id_khoa_hoc = kh.id 
        WHERE 1=1";

$params = [];

if ($id_kh_filter !== 'tat_ca') {
    $sql .= " AND bg.id_khoa_hoc = :id_kh";
    $params[':id_kh'] = $id_kh_filter;
}

if ($tu_khoa !== '') {
    $sql .= " AND bg.tieu_de LIKE :kw";
    $params[':kw'] = "%" . $tu_khoa . "%";
}

$sql .= " ORDER BY bg.thu_tu_hien_thi ASC, bg.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ds_bai_giang = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý bài giảng</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
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
                <div class="logo-text">QUẢN LÝ BÀI GIẢNG</div>
                <div class="logo-subtext">Danh sách, sửa, xóa bài giảng</div>
            </div>
        </div>

        <a href="admin_dashboard.php" class="btn btn-outline-secondary btn-sm">
            &laquo; Về trang tổng quan
        </a>
    </div>
</header>

<div class="container mt-4">

    <h4 class="mb-3" style="color:#b30000;">Danh sách bài giảng</h4>

    <!-- THÔNG BÁO -->
    <?php if ($thong_bao): ?>
        <div class="alert alert-info"><?= htmlspecialchars($thong_bao) ?></div>
    <?php endif ?>

    <!-- LỖI -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach ?>
            </ul>
        </div>
    <?php endif ?>

    <!-- FORM SỬA BÀI GIẢNG -->
    <?php if ($editing_id > 0 && $editing_row): ?>
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between bg-light">
                <strong>Sửa bài giảng: <?= htmlspecialchars($editing_row['tieu_de']) ?></strong>
                <a href="admin_lessons.php" class="btn btn-sm btn-outline-secondary">Đóng</a>
            </div>

            <div class="card-body">
                <form method="post" class="row g-3">
                    <input type="hidden" name="mode" value="update">
                    <input type="hidden" name="id" value="<?= $editing_row['id'] ?>">

                    <div class="col-md-6">
                        <label class="form-label">Tiêu đề <span class="text-danger">*</span></label>
                        <input type="text" name="tieu_de" class="form-control"
                               value="<?= htmlspecialchars($editing_row['tieu_de']) ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Thuộc khóa học</label>
                        <select name="id_khoa_hoc" class="form-select" required>
                            <option value="">-- Chọn khóa học --</option>
                            <?php foreach ($ds_khoa_hoc as $kh): ?>
                                <option value="<?= $kh['id'] ?>"
                                    <?= ($editing_row['id_khoa_hoc'] == $kh['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($kh['ten_khoa_hoc']) ?>
                                </option>
                            <?php endforeach ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Loại nội dung</label>
                        <select name="loai_noi_dung" class="form-select">
                            <?php
                            $loai_arr = ['video','pdf','html','tep','link'];
                            foreach ($loai_arr as $loai):
                            ?>
                                <option value="<?= $loai ?>"
                                    <?= ($editing_row['loai_noi_dung'] == $loai) ? 'selected' : '' ?>>
                                    <?= strtoupper($loai) ?>
                                </option>
                            <?php endforeach ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Đường dẫn nội dung</label>
                        <input type="text" name="duong_dan_noi_dung" class="form-control"
                               value="<?= htmlspecialchars($editing_row['duong_dan_noi_dung']) ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Thứ tự</label>
                        <input type="number" name="thu_tu_hien_thi" class="form-control"
                               value="<?= $editing_row['thu_tu_hien_thi'] ?>">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Nội dung HTML (nếu có)</label>
                        <textarea name="noi_dung_html" class="form-control" rows="4"><?= 
                            htmlspecialchars($editing_row['noi_dung_html']) ?></textarea>
                    </div>

                    <div class="col-md-12">
                        <label class="form-check-label">
                            <input type="checkbox" name="hien_thi" class="form-check-input"
                                <?= $editing_row['hien_thi'] ? 'checked' : '' ?>>
                            Hiển thị bài giảng
                        </label>
                    </div>

                    <div class="col-12">
                        <button class="btn btn-primary"><i class="bi bi-save"></i> Lưu thay đổi</button>
                        <a href="admin_lessons.php" class="btn btn-secondary">Hủy</a>
                    </div>

                </form>
            </div>
        </div>
    <?php endif ?>

    <!-- BỘ LỌC -->
    <form class="row g-2 mb-3" method="get">

        <div class="col-md-3">
            <select name="khoa_hoc" class="form-select">
                <option value="tat_ca">Tất cả khóa học</option>
                <?php foreach ($ds_khoa_hoc as $kh): ?>
                    <option value="<?= $kh['id'] ?>"
                        <?= ($id_kh_filter == $kh['id'] ? 'selected' : '') ?>>
                        <?= htmlspecialchars($kh['ten_khoa_hoc']) ?>
                    </option>
                <?php endforeach ?>
            </select>
        </div>

        <div class="col-md-3">
            <input type="text" name="q" class="form-control"
                   placeholder="Tìm theo tiêu đề..."
                   value="<?= htmlspecialchars($tu_khoa) ?>">
        </div>

        <div class="col-md-2 d-grid">
            <button class="btn btn-primary"><i class="bi bi-search"></i></button>
        </div>

        <div class="col-md-2 d-grid">
            <a href="lesson_add.php" class="btn btn-success">
                <i class="bi bi-plus-circle"></i> Thêm bài giảng
            </a>
        </div>

    </form>

    <!-- DANH SÁCH BÀI GIẢNG -->
    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Tiêu đề</th>
                    <th>Khóa học</th>
                    <th>Loại</th>
                    <th>Hiển thị</th>
                    <th>Thứ tự</th>
                    <th>Ngày tạo</th>
                    <th>Hành động</th>
                </tr>
            </thead>

            <tbody>
            <?php if (empty($ds_bai_giang)): ?>
                <tr>
                    <td colspan="8" class="text-center text-muted">
                        Chưa có bài giảng nào.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($ds_bai_giang as $index => $bg): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>

                        <td>
                            <strong><?= htmlspecialchars($bg['tieu_de']) ?></strong>
                        </td>

                        <td><?= htmlspecialchars($bg['ten_khoa_hoc']) ?></td>

                        <td><span class="badge bg-secondary"><?= $bg['loai_noi_dung'] ?></span></td>

                        <td>
                            <?= $bg['hien_thi']
                                ? '<span class="badge bg-success">Hiển thị</span>'
                                : '<span class="badge bg-danger">Ẩn</span>' ?>
                        </td>

                        <td><?= $bg['thu_tu_hien_thi'] ?></td>

                        <td><?= $bg['ngay_tao'] ?></td>

                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="admin_lessons.php?edit=<?= $bg['id'] ?>"
                                   class="btn btn-outline-primary">
                                    <i class="bi bi-pencil-square"></i>
                                </a>
                                <a href="admin_lessons.php?delete=<?= $bg['id'] ?>"
                                   onclick="return confirm('Xóa bài giảng này?')"
                                   class="btn btn-outline-danger">
                                    <i class="bi bi-trash3"></i>
                                </a>
                            </div>
                        </td>

                    </tr>
                <?php endforeach ?>
            <?php endif ?>
            </tbody>
        </table>
    </div>

</div>

<footer>
    <div class="container text-center">
        © <?= date('Y') ?> Hệ thống E-learning PTIT - Quản lý bài giảng.
    </div>
</footer>

</body>
</html>
