<?php
session_start();
require_once 'config.php';

// Chỉ cho phép Admin truy cập
if (!isset($_SESSION['user_id']) || ($_SESSION['vai_tro'] ?? '') !== 'quan_tri') {
    header("Location: dang_nhap.php");
    exit;
}

$ho_ten_admin = $_SESSION['ho_ten'] ?? 'Quản trị hệ thống';

$pdo   = Database::pdo();
$error = '';

// =========================
// 1. LẤY THỐNG KÊ LỚP HỌC
// =========================
try {
    // Đếm số sinh viên theo lớp
    $sql_lop = "
        SELECT lop_hoc, COUNT(*) AS so_sinh_vien
        FROM nguoi_dung
        WHERE vai_tro = 'hoc_vien'
          AND lop_hoc IS NOT NULL
          AND lop_hoc <> ''
        GROUP BY lop_hoc
        ORDER BY lop_hoc
    ";
    $stmt_lop = $pdo->query($sql_lop);
    $ds_lop   = $stmt_lop->fetchAll(PDO::FETCH_ASSOC);

    // Đếm số khóa học mà từng lớp đang học (dựa vào đăng ký khóa học)
    $sql_lop_khoa = "
        SELECT nd.lop_hoc,
               COUNT(DISTINCT dk.id_khoa_hoc) AS so_khoa_hoc
        FROM dang_ky_khoa_hoc dk
        JOIN nguoi_dung nd ON dk.id_hoc_vien = nd.id
        WHERE nd.vai_tro = 'hoc_vien'
          AND nd.lop_hoc IS NOT NULL
          AND nd.lop_hoc <> ''
        GROUP BY nd.lop_hoc
    ";
    $stmt_lop_khoa = $pdo->query($sql_lop_khoa);
    $map_lop_khoa  = [];
    foreach ($stmt_lop_khoa->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $map_lop_khoa[$row['lop_hoc']] = (int)$row['so_khoa_hoc'];
    }

} catch (PDOException $e) {
    $error = $e->getMessage();
}

// =========================
// 2. CHI TIẾT 1 LỚP (NẾU CHỌN)
// =========================
$lop_chon            = $_GET['lop'] ?? '';
$ds_sinh_vien_lop    = [];
$ds_khoa_hoc_cua_lop = [];

if ($lop_chon !== '') {
    try {
        // Danh sách sinh viên của lớp
        $sql_sv = "
            SELECT ho_ten, ma_sinh_vien, email, so_dien_thoai, trang_thai
            FROM nguoi_dung
            WHERE vai_tro = 'hoc_vien'
              AND lop_hoc = :lop_hoc
            ORDER BY ho_ten
        ";
        $stmt_sv = $pdo->prepare($sql_sv);
        $stmt_sv->execute([':lop_hoc' => $lop_chon]);
        $ds_sinh_vien_lop = $stmt_sv->fetchAll(PDO::FETCH_ASSOC);

        // Danh sách khóa học mà lớp đang học (dựa trên đăng ký)
        $sql_kh = "
            SELECT DISTINCT kh.id, kh.ten_khoa_hoc, kh.ngay_bat_dau, kh.ngay_ket_thuc, kh.trang_thai
            FROM dang_ky_khoa_hoc dk
            JOIN nguoi_dung nd ON dk.id_hoc_vien = nd.id
            JOIN khoa_hoc kh   ON dk.id_khoa_hoc = kh.id
            WHERE nd.vai_tro = 'hoc_vien'
              AND nd.lop_hoc = :lop_hoc
            ORDER BY kh.ten_khoa_hoc
        ";
        $stmt_kh = $pdo->prepare($sql_kh);
        $stmt_kh->execute([':lop_hoc' => $lop_chon]);
        $ds_khoa_hoc_cua_lop = $stmt_kh->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $error = $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý lớp học - Admin</title>

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

<!-- Header -->
<header class="main-header">
    <div class="container py-2 d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center">
            <img src="image/ptit.png" alt="PTIT Logo" style="height:55px;" class="me-3">
            <div>
                <div class="logo-text">QUẢN LÝ LỚP HỌC</div>
                <div class="logo-subtext">Thống kê số sinh viên, khóa học theo lớp</div>
            </div>
        </div>

        <nav>
            <a href="admin_dashboard.php" class="btn btn-sm btn-outline-secondary">
                &laquo; Về trang tổng quan
            </a>
        </nav>
    </div>
</header>

<div class="container mt-4 mb-5">

    <h4 class="mb-3" style="color:#b30000;">Tổng quan lớp học</h4>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            Lỗi: <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <!-- BẢNG THỐNG KÊ CÁC LỚP -->
    <div class="table-responsive mb-4">
        <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
            <tr>
                <th>#</th>
                <th>Lớp học</th>
                <th>Số sinh viên</th>
                <th>Số khóa học đang học</th>
                <th>Hành động</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($ds_lop)): ?>
                <tr>
                    <td colspan="5" class="text-center text-muted">
                        Chưa có dữ liệu lớp học (chưa có sinh viên nào được gán lớp).
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($ds_lop as $index => $lop): ?>
                    <?php
                    $lop_hoc      = $lop['lop_hoc'];
                    $so_sv        = (int)$lop['so_sinh_vien'];
                    $so_khoa_hoc  = $map_lop_khoa[$lop_hoc] ?? 0;
                    ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td><strong><?php echo htmlspecialchars($lop_hoc); ?></strong></td>
                        <td><?php echo $so_sv; ?></td>
                        <td><?php echo $so_khoa_hoc; ?></td>
                        <td>
                            <a href="admin_classes.php?lop=<?php echo urlencode($lop_hoc); ?>"
                               class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i> Xem chi tiết
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- CHI TIẾT 1 LỚP -->
    <?php if ($lop_chon !== ''): ?>
        <div class="card mb-4">
            <div class="card-header bg-light">
                <strong>Chi tiết lớp: <?php echo htmlspecialchars($lop_chon); ?></strong>
                <a href="admin_classes.php" class="btn btn-sm btn-outline-secondary float-end">
                    Đóng chi tiết
                </a>
            </div>
            <div class="card-body">

                <div class="row">
                    <!-- Danh sách sinh viên -->
                    <div class="col-md-6">
                        <h5>Sinh viên trong lớp</h5>
                        <?php if (empty($ds_sinh_vien_lop)): ?>
                            <p class="text-muted">Chưa có sinh viên nào trong lớp này.</p>
                        <?php else: ?>
                            <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                                <table class="table table-sm table-hover">
                                    <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Mã SV</th>
                                        <th>Họ tên</th>
                                        <th>Email</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($ds_sinh_vien_lop as $i => $sv): ?>
                                        <tr>
                                            <td><?php echo $i + 1; ?></td>
                                            <td><?php echo htmlspecialchars($sv['ma_sinh_vien']); ?></td>
                                            <td><?php echo htmlspecialchars($sv['ho_ten']); ?></td>
                                            <td><?php echo htmlspecialchars($sv['email']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Danh sách khóa học của lớp -->
                    <div class="col-md-6">
                        <h5>Các khóa học lớp đang học</h5>
                        <?php if (empty($ds_khoa_hoc_cua_lop)): ?>
                            <p class="text-muted">Chưa có khóa học nào được đăng ký cho lớp này.</p>
                        <?php else: ?>
                            <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                                <table class="table table-sm table-hover">
                                    <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Tên khóa học</th>
                                        <th>Trạng thái</th>
                                        <th>Bắt đầu</th>
                                        <th>Kết thúc</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($ds_khoa_hoc_cua_lop as $i => $kh): ?>
                                        <tr>
                                            <td><?php echo $i + 1; ?></td>
                                            <td><?php echo htmlspecialchars($kh['ten_khoa_hoc']); ?></td>
                                            <td><?php echo htmlspecialchars($kh['trang_thai']); ?></td>
                                            <td><?php echo htmlspecialchars($kh['ngay_bat_dau']); ?></td>
                                            <td><?php echo htmlspecialchars($kh['ngay_ket_thuc']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    <?php endif; ?>

</div>

<footer>
    <div class="container text-center">
        © <?php echo date('Y'); ?> Hệ thống E-learning PTIT - Quản lý lớp học.
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
