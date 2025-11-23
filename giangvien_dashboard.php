<?php
session_start();
require_once 'config.php';

// Chỉ cho phép Giảng viên
if (!isset($_SESSION['user_id']) || ($_SESSION['vai_tro'] ?? '') !== 'giang_vien') {
    header("Location: dang_nhap.php");
    exit;
}

$giang_vien_id   = $_SESSION['user_id'];
$ho_ten_giang_vien = $_SESSION['ho_ten'] ?? 'Giảng viên';

$error = '';

// Thống kê
$so_khoa_hoc          = 0;
$so_khoa_cong_bo      = 0;
$so_luot_dang_ky      = 0;

// Danh sách khóa học
$khoa_hoc_cua_toi     = [];

// Đăng ký chờ duyệt
$dang_ky_cho_duyet    = [];

// Bài thi sắp diễn ra
$bai_thi_sap_dien_ra  = [];

try {
    $pdo = Database::pdo();

    // ==========================
    // 1. Thống kê nhanh
    // ==========================

    // Tổng số khóa học của giảng viên (không đếm lưu trữ nếu bạn muốn)
    $sql = "
        SELECT 
            COUNT(*) AS tong,
            SUM(CASE WHEN trang_thai = 'cong_bo' THEN 1 ELSE 0 END) AS cong_bo
        FROM khoa_hoc
        WHERE id_giang_vien = :id_gv
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id_gv' => $giang_vien_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $so_khoa_hoc     = (int)$row['tong'];
        $so_khoa_cong_bo = (int)$row['cong_bo'];
    }

    // Tổng số lượt đăng ký (không tính trạng_thai = 'huy')
    $sql = "
        SELECT COUNT(*) AS tong
        FROM dang_ky_khoa_hoc dk
        JOIN khoa_hoc kh ON dk.id_khoa_hoc = kh.id
        WHERE kh.id_giang_vien = :id_gv
          AND dk.trang_thai <> 'huy'
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id_gv' => $giang_vien_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $so_luot_dang_ky = (int)$row['tong'];
    }

    // ==========================
    // 2. Danh sách khóa học của tôi (top 10)
    // ==========================
    $sql = "
        SELECT 
            kh.id,
            kh.ten_khoa_hoc,
            kh.trang_thai,
            kh.ngay_bat_dau,
            kh.ngay_ket_thuc,
            (
                SELECT COUNT(*) 
                FROM dang_ky_khoa_hoc dk 
                WHERE dk.id_khoa_hoc = kh.id AND dk.trang_thai <> 'huy'
            ) AS so_hoc_vien
        FROM khoa_hoc kh
        WHERE kh.id_giang_vien = :id_gv
        ORDER BY kh.ngay_tao DESC
        LIMIT 10
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id_gv' => $giang_vien_id]);
    $khoa_hoc_cua_toi = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ==========================
    // 3. Các đăng ký chờ duyệt
    // ==========================
    $sql = "
        SELECT 
            dk.id,
            dk.ngay_dang_ky,
            dk.trang_thai,
            nd.ho_ten AS ten_hoc_vien,
            nd.ma_sinh_vien,
            nd.lop_hoc,
            kh.ten_khoa_hoc
        FROM dang_ky_khoa_hoc dk
        JOIN nguoi_dung nd ON dk.id_hoc_vien = nd.id
        JOIN khoa_hoc kh ON dk.id_khoa_hoc = kh.id
        WHERE kh.id_giang_vien = :id_gv
          AND dk.trang_thai = 'cho_duyet'
        ORDER BY dk.ngay_dang_ky DESC
        LIMIT 10
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id_gv' => $giang_vien_id]);
    $dang_ky_cho_duyet = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ==========================
    // 4. Bài thi sắp diễn ra
    // ==========================
    $sql = "
        SELECT 
            bt.id,
            bt.tieu_de,
            bt.thoi_gian_bat_dau,
            bt.thoi_gian_ket_thuc,
            bt.thoi_luong_phut,
            kh.ten_khoa_hoc
        FROM bai_thi bt
        JOIN khoa_hoc kh ON bt.id_khoa_hoc = kh.id
        WHERE kh.id_giang_vien = :id_gv
          AND bt.trang_thai <> 'dong'
          AND bt.thoi_gian_bat_dau IS NOT NULL
          AND bt.thoi_gian_bat_dau >= NOW()
        ORDER BY bt.thoi_gian_bat_dau ASC
        LIMIT 5
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id_gv' => $giang_vien_id]);
    $bai_thi_sap_dien_ra = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Lỗi hệ thống: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Khu vực giảng viên - E-learning PTIT</title>

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
            <i class="bi bi-easel2-fill"></i>
            <a href="giangvien_dashboard.php">Khu vực giảng viên</a>
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
            <div style="font-size: 0.9rem; color:#555;">Khu vực giảng viên</div>
        </div>
    </div>
</header>

<div class="container mt-4 mb-5">

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- Thống kê nhanh -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm p-3 text-center">
                <i class="bi bi-journal-bookmark-fill fs-1 text-danger"></i>
                <h5 class="mt-2">Khóa học phụ trách</h5>
                <p class="display-6 mb-0"><?php echo $so_khoa_hoc; ?></p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm p-3 text-center">
                <i class="bi bi-broadcast-pin fs-1 text-danger"></i>
                <h5 class="mt-2">Khóa đã công bố</h5>
                <p class="display-6 mb-0"><?php echo $so_khoa_cong_bo; ?></p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm p-3 text-center">
                <i class="bi bi-people-fill fs-1 text-danger"></i>
                <h5 class="mt-2">Tổng lượt đăng ký</h5>
                <p class="display-6 mb-0"><?php echo $so_luot_dang_ky; ?></p>
            </div>
        </div>
    </div>

    <!-- Menu chức năng chính -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <a href="giangvien_courses.php" class="btn btn-light border w-100 text-start">
                <i class="bi bi-journal-text"></i> &nbsp; Khóa học của tôi
            </a>
        </div>
        <div class="col-md-3">
            <a href="giangvien_exams.php" class="btn btn-light border w-100 text-start">
                <i class="bi bi-card-checklist"></i> &nbsp; Bài thi & ngân hàng câu hỏi
            </a>
        </div>
        <div class="col-md-3">
            <a href="giangvien_enrollments.php" class="btn btn-light border w-100 text-start">
                <i class="bi bi-person-plus"></i> &nbsp; Duyệt đăng ký học
            </a>
        </div>
        <div class="col-md-3">
            <a href="giangvien_profile.php" class="btn btn-light border w-100 text-start">
                <i class="bi bi-person-circle"></i> &nbsp; Thông tin cá nhân
            </a>
        </div>
    </div>

    <!-- Khóa học của tôi -->
    <div class="mb-4">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h4 style="color:#b30000;">Khóa học của tôi</h4>
            <a href="giangvien_courses.php" class="btn btn-sm btn-outline-secondary">Xem tất cả</a>
        </div>

        <?php if (empty($khoa_hoc_cua_toi)): ?>
            <div class="alert alert-info mb-0">
                Bạn chưa có khóa học nào. Hãy liên hệ quản trị viên để được phân công khóa học.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Tên khóa học</th>
                            <th>Trạng thái</th>
                            <th>Thời gian</th>
                            <th>Số học viên</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($khoa_hoc_cua_toi as $kh): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($kh['ten_khoa_hoc']); ?></td>
                            <td>
                                <?php
                                $txtTrangThai = [
                                    'nhap'     => 'Nháp',
                                    'cong_bo'  => 'Đang công bố',
                                    'luu_tru'  => 'Đã lưu trữ'
                                ];
                                $lbl = $txtTrangThai[$kh['trang_thai']] ?? $kh['trang_thai'];

                                if ($kh['trang_thai'] === 'cong_bo') {
                                    echo '<span class="badge bg-success">' . htmlspecialchars($lbl) . '</span>';
                                } elseif ($kh['trang_thai'] === 'nhap') {
                                    echo '<span class="badge bg-secondary">' . htmlspecialchars($lbl) . '</span>';
                                } else {
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

    <!-- Đăng ký chờ duyệt -->
    <div class="mb-4">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h4 style="color:#b30000;">Đăng ký chờ duyệt</h4>
            <a href="giangvien_enrollments.php" class="btn btn-sm btn-outline-secondary">Xem chi tiết</a>
        </div>

        <?php if (empty($dang_ky_cho_duyet)): ?>
            <div class="alert alert-light">
                Hiện chưa có đăng ký nào đang chờ duyệt.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Học viên</th>
                            <th>Mã SV</th>
                            <th>Lớp</th>
                            <th>Khóa học</th>
                            <th>Ngày đăng ký</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($dang_ky_cho_duyet as $dk): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($dk['ten_hoc_vien']); ?></td>
                            <td><?php echo htmlspecialchars($dk['ma_sinh_vien']); ?></td>
                            <td><?php echo htmlspecialchars($dk['lop_hoc']); ?></td>
                            <td><?php echo htmlspecialchars($dk['ten_khoa_hoc']); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($dk['ngay_dang_ky'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bài thi sắp diễn ra -->
    <div class="mb-4">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h4 style="color:#b30000;">Bài thi sắp diễn ra</h4>
            <a href="giangvien_exams.php" class="btn btn-sm btn-outline-secondary">Quản lý bài thi</a>
        </div>

        <?php if (empty($bai_thi_sap_dien_ra)): ?>
            <div class="alert alert-light">
                Hiện chưa có bài thi sắp tới.
            </div>
        <?php else: ?>
            <ul class="list-group">
                <?php foreach ($bai_thi_sap_dien_ra as $bt): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <strong><?php echo htmlspecialchars($bt['tieu_de']); ?></strong><br>
                            <small class="text-muted">
                                Khóa: <?php echo htmlspecialchars($bt['ten_khoa_hoc']); ?>
                                &nbsp; | &nbsp;
                                Thời gian: 
                                <?php echo date('d/m/Y H:i', strtotime($bt['thoi_gian_bat_dau'])); ?>
                            </small>
                        </div>
                        <a href="giangvien_exams.php" class="btn btn-sm btn-outline-primary">
                            Chi tiết
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

</div>

<footer class="text-center py-3">
    © <?php echo date('Y'); ?> Hệ thống E-learning PTIT - Khu vực giảng viên
</footer>

</body>
</html>
