<?php
session_start();
require_once 'config.php';

// Chỉ cho phép Giảng viên
if (!isset($_SESSION['user_id']) || ($_SESSION['vai_tro'] ?? '') !== 'giang_vien') {
    header("Location: dang_nhap.php");
    exit;
}

$giang_vien_id      = $_SESSION['user_id'];
$ho_ten_giang_vien  = $_SESSION['ho_ten'] ?? 'Giảng viên';

$error = '';
$courses = [];

// Lọc theo trạng thái
$allowed_status = ['nhap', 'cong_bo', 'luu_tru'];
$status_filter = (isset($_GET['status']) && in_array($_GET['status'], $allowed_status, true))
    ? $_GET['status']
    : '';

// Từ khóa tìm kiếm
$tu_khoa = trim($_GET['q'] ?? '');

try {
    $pdo = Database::pdo();

    $sql = "
        SELECT 
            kh.id,
            kh.ten_khoa_hoc,
            kh.danh_muc,
            kh.trang_thai,
            kh.ngay_bat_dau,
            kh.ngay_ket_thuc,
            kh.hoc_phi,
            kh.ngay_tao,
            (
                SELECT COUNT(*) 
                FROM dang_ky_khoa_hoc dk 
                WHERE dk.id_khoa_hoc = kh.id AND dk.trang_thai <> 'huy'
            ) AS so_hoc_vien
        FROM khoa_hoc kh
        WHERE kh.id_giang_vien = :id_gv
    ";

    $params = [':id_gv' => $giang_vien_id];

    // Lọc trạng thái
    if ($status_filter !== '') {
        $sql .= " AND kh.trang_thai = :trang_thai ";
        $params[':trang_thai'] = $status_filter;
    }

    // Tìm kiếm theo tên / danh mục
    if ($tu_khoa !== '') {
        $sql .= " AND (kh.ten_khoa_hoc LIKE :kw OR kh.danh_muc LIKE :kw) ";
        $params[':kw'] = '%' . $tu_khoa . '%';
    }

    $sql .= " ORDER BY kh.ngay_tao DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Lỗi hệ thống: " . $e->getMessage();
}

function textTrangThaiKhoaHoc($code) {
    $map = [
        'nhap'     => 'Nháp',
        'cong_bo'  => 'Đang công bố',
        'luu_tru'  => 'Đã lưu trữ'
    ];
    return $map[$code] ?? $code;
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Khóa học của tôi - Giảng viên</title>

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
            <a href="giangvien_dashboard.php" class="text-white">
                <i class="bi bi-easel2-fill"></i> Khu vực giảng viên
            </a>
            <span> / Khóa học của tôi</span>
        </div>
        <div>
            Xin chào, <strong><?php echo htmlspecialchars($ho_ten_giang_vien); ?></strong>
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
            <div class="logo-text">HỆ THỐNG E-LEARNING PTIT</div>
            <div style="font-size: 0.9rem; color:#555;">Khóa học của tôi</div>
        </div>
    </div>
</header>

<div class="container mt-4 mb-5">

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 style="color:#b30000;">Danh sách khóa học bạn phụ trách</h3>
        <!-- Sau này có thể trỏ đến trang tạo / chỉnh sửa khóa học -->
        <!-- <a href="giangvien_course_edit.php" class="btn btn-danger btn-sm">
            <i class="bi bi-plus-circle"></i> Tạo khóa học mới
        </a> -->
    </div>

    <!-- Bộ lọc và tìm kiếm -->
    <form class="row g-2 mb-4" method="get">
        <div class="col-md-3">
            <select name="status" class="form-select form-select-sm">
                <option value="">-- Tất cả trạng thái --</option>
                <option value="nhap"     <?php if ($status_filter === 'nhap') echo 'selected'; ?>>Nháp</option>
                <option value="cong_bo"  <?php if ($status_filter === 'cong_bo') echo 'selected'; ?>>Đang công bố</option>
                <option value="luu_tru"  <?php if ($status_filter === 'luu_tru') echo 'selected'; ?>>Đã lưu trữ</option>
            </select>
        </div>
        <div class="col-md-5">
            <input type="text"
                   name="q"
                   class="form-control form-control-sm"
                   placeholder="Tìm theo tên khóa học hoặc danh mục..."
                   value="<?php echo htmlspecialchars($tu_khoa, ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="col-md-2">
            <button class="btn btn-danger btn-sm w-100" type="submit">
                <i class="bi bi-search"></i> Lọc / Tìm
            </button>
        </div>
        <div class="col-md-2">
            <a href="giangvien_courses.php" class="btn btn-outline-secondary btn-sm w-100">
                Xóa lọc
            </a>
        </div>
    </form>

    <!-- Bảng danh sách khóa học -->
    <div class="card">
        <div class="card-body p-0">
            <?php if (empty($courses)): ?>
                <div class="p-3">
                    <em>Hiện bạn chưa có khóa học nào (hoặc không có khóa phù hợp với bộ lọc).</em>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 30%;">Tên khóa học</th>
                                <th>Danh mục</th>
                                <th>Trạng thái</th>
                                <th>Thời gian học</th>
                                <th>Học phí</th>
                                <th>Số học viên</th>
                                <th style="width: 15%;">Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($courses as $kh): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($kh['ten_khoa_hoc']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($kh['danh_muc'] ?? ''); ?></td>
                                <td>
                                    <?php
                                    $lbl = textTrangThaiKhoaHoc($kh['trang_thai']);
                                    if ($kh['trang_thai'] === 'cong_bo') {
                                        echo '<span class="badge bg-success">' . htmlspecialchars($lbl) . '</span>';
                                    } elseif ($kh['trang_thai'] === 'nhap') {
                                        echo '<span class="badge bg-secondary">' . htmlspecialchars($lbl) . '</span>';
                                    } else { // lưu_tru
                                        echo '<span class="badge bg-light text-dark">' . htmlspecialchars($lbl) . '</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    $bd = $kh['ngay_bat_dau'] ? date('d/m/Y', strtotime($kh['ngay_bat_dau'])) : '';
                                    $kt = $kh['ngay_ket_thuc'] ? date('d/m/Y', strtotime($kh['ngay_ket_thuc'])) : '';
                                    echo ($bd && $kt) ? $bd . ' - ' . $kt : '—';
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    if ($kh['hoc_phi'] != 0) {
                                        // định dạng tiền cho đẹp, tùy bạn sửa lại
                                        echo number_format($kh['hoc_phi'], 0, ',', '.') . ' đ';
                                    } else {
                                        echo 'Miễn phí';
                                    }
                                    ?>
                                </td>
                                <td><?php echo (int)$kh['so_hoc_vien']; ?></td>
                                <td class="text-center">
                                    <a href="giangvien_course_detail.php?id=<?php echo (int)$kh['id']; ?>"
                                       class="btn btn-sm btn-danger">
                                        <i class="bi bi-gear"></i> Quản lý
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<footer class="text-center py-3">
    © <?php echo date('Y'); ?> Hệ thống E-learning PTIT - Khu vực giảng viên
</footer>

</body>
</html>
