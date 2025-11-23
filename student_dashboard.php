<?php
session_start();
require_once 'config.php';

// Chỉ cho phép Học viên truy cập
if (!isset($_SESSION['user_id']) || ($_SESSION['vai_tro'] ?? '') !== 'hoc_vien') {
    header("Location: dang_nhap.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$ho_ten  = $_SESSION['ho_ten'] ?? 'Học viên';

$so_khoa_dang_hoc   = 0;
$so_khoa_hoan_thanh = 0;
$khoa_hoc_cua_toi   = [];
$khoa_hoc_de_xuat   = [];
$error = '';

try {
    $pdo = Database::pdo();

    // Thống kê số khóa đang học & hoàn thành
    $sql = "
        SELECT trang_thai, COUNT(*) AS so_luong
        FROM dang_ky_khoa_hoc
        WHERE id_hoc_vien = :id_hoc_vien
        GROUP BY trang_thai
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id_hoc_vien' => $user_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $r) {
        if ($r['trang_thai'] === 'dang_hoc')    $so_khoa_dang_hoc   = (int)$r['so_luong'];
        if ($r['trang_thai'] === 'hoan_thanh')  $so_khoa_hoan_thanh = (int)$r['so_luong'];
    }

    // Lấy danh sách "Khóa học của tôi"
    $sql = "
        SELECT kh.id, kh.ten_khoa_hoc, kh.danh_muc, kh.ngay_bat_dau, kh.ngay_ket_thuc,
               dk.trang_thai, dk.tien_do_phan_tram, dk.diem_cuoi_ky
        FROM dang_ky_khoa_hoc dk
        JOIN khoa_hoc kh ON dk.id_khoa_hoc = kh.id
        WHERE dk.id_hoc_vien = :id_hoc_vien
        ORDER BY dk.ngay_dang_ky DESC
        LIMIT 20
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id_hoc_vien' => $user_id]);
    $khoa_hoc_cua_toi = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Gợi ý một số khóa học khác chưa đăng ký
    $sql = "
        SELECT kh.id, kh.ten_khoa_hoc, kh.danh_muc, kh.ngay_bat_dau, kh.hoc_phi
        FROM khoa_hoc kh
        WHERE kh.trang_thai = 'cong_bo'
          AND kh.id NOT IN (
                SELECT id_khoa_hoc
                FROM dang_ky_khoa_hoc
                WHERE id_hoc_vien = :id_hoc_vien
          )
        ORDER BY kh.ngay_tao DESC
        LIMIT 8
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id_hoc_vien' => $user_id]);
    $khoa_hoc_de_xuat = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Khu vực học viên - E-learning PTIT</title>

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
            <i class="bi bi-mortarboard-fill"></i>
            <a href="student_dashboard.php">Khu vực học viên</a>
        </div>
        <div>
            Xin chào, <strong><?php echo htmlspecialchars($ho_ten); ?></strong>
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
            <div style="font-size: 0.9rem; color:#555;">Khu vực học viên</div>
        </div>
    </div>
</header>

<div class="container mt-4 mb-5">

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger">Lỗi: <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- Dòng chào + tóm tắt -->
    <div class="mb-4">
        <h3 class="mb-1" style="color:#b30000;">Xin chào, <?php echo htmlspecialchars($ho_ten); ?></h3>
        <p>Chúc bạn có một buổi học hiệu quả. Bạn có thể tiếp tục các khóa đang học hoặc đăng ký khóa mới.</p>
    </div>

    <!-- Thống kê nhanh -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm p-3 text-center">
                <i class="bi bi-play-circle-fill fs-1 text-danger"></i>
                <h5 class="mt-2">Khóa đang học</h5>
                <p class="display-6 mb-0"><?php echo $so_khoa_dang_hoc; ?></p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm p-3 text-center">
                <i class="bi bi-check-circle-fill fs-1 text-danger"></i>
                <h5 class="mt-2">Khóa đã hoàn thành</h5>
                <p class="display-6 mb-0"><?php echo $so_khoa_hoan_thanh; ?></p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm p-3 text-center">
                <i class="bi bi-person-circle fs-1 text-danger"></i>
                <h5 class="mt-2">Thông tin cá nhân</h5>
                <a href="student_profile.php" class="btn btn-outline-danger btn-sm mt-2">
                    Xem / Cập nhật
                </a>
            </div>
        </div>
    </div>

    <!-- Menu chức năng chính -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <a href="student_my_courses.php" class="btn btn-light border w-100 text-start">
                <i class="bi bi-journal-text"></i> &nbsp; Khóa học của tôi
            </a>
        </div>
        <div class="col-md-3">
            <a href="student_courses_catalog.php" class="btn btn-light border w-100 text-start">
                <i class="bi bi-bag-plus"></i> &nbsp; Đăng ký khóa học mới
            </a>
        </div>
        <div class="col-md-3">
            <a href="student_exams.php" class="btn btn-light border w-100 text-start">
                <i class="bi bi-card-checklist"></i> &nbsp; Bài thi & kết quả
            </a>
        </div>
    </div>

    <!-- Khóa học của tôi -->
    <div class="mb-4">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h4 style="color:#b30000;">Khóa học của tôi</h4>
            <a href="student_my_courses.php" class="btn btn-sm btn-outline-secondary">Xem tất cả</a>
        </div>

        <?php if (empty($khoa_hoc_cua_toi)): ?>
            <div class="alert alert-info mb-0">
                Bạn chưa đăng ký khóa học nào. Hãy chọn <strong>“Đăng ký khóa học mới”</strong> để bắt đầu.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Tên khóa học</th>
                            <th>Danh mục</th>
                            <th>Thời gian</th>
                            <th>Trạng thái</th>
                            <th>Tiến độ</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($khoa_hoc_cua_toi as $kh): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($kh['ten_khoa_hoc']); ?></td>
                            <td><?php echo htmlspecialchars($kh['danh_muc'] ?? ''); ?></td>
                            <td>
                                <?php
                                $bd = $kh['ngay_bat_dau'] ? date('d/m/Y', strtotime($kh['ngay_bat_dau'])) : '';
                                $kt = $kh['ngay_ket_thuc'] ? date('d/m/Y', strtotime($kh['ngay_ket_thuc'])) : '';
                                echo $bd && $kt ? $bd . ' - ' . $kt : '—';
                                ?>
                            </td>
                            <td>
                                <?php
                                $mapTrangThai = [
                                    'cho_duyet'   => 'Chờ duyệt',
                                    'dang_hoc'    => 'Đang học',
                                    'hoan_thanh'  => 'Hoàn thành',
                                    'huy'         => 'Đã hủy'
                                ];
                                $txt = $mapTrangThai[$kh['trang_thai']] ?? $kh['trang_thai'];
                                echo htmlspecialchars($txt);
                                ?>
                            </td>
                            <td>
                                <div class="progress" style="height: 18px;">
                                    <div class="progress-bar bg-danger" role="progressbar"
                                         style="width: <?php echo (int)$kh['tien_do_phan_tram']; ?>%;">
                                        <?php echo (int)$kh['tien_do_phan_tram']; ?>%
                                    </div>
                                </div>
                            </td>
                            <td class="text-center">
                                <a href="student_course_learn.php?id=<?php echo (int)$kh['id']; ?>"
                                   class="btn btn-sm btn-danger">
                                    <i class="bi bi-play-circle"></i> Vào học
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Khóa học đề xuất -->
    <div class="mb-4">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h4 style="color:#b30000;">Khóa học đề xuất cho bạn</h4>
            <a href="student_courses_catalog.php" class="btn btn-sm btn-outline-secondary">Xem tất cả khóa học</a>
        </div>

        <?php if (empty($khoa_hoc_de_xuat)): ?>
            <p>Hiện tại chưa có khóa học nào khác để gợi ý.</p>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($khoa_hoc_de_xuat as $kh): ?>
                    <div class="col-md-3">
                        <div class="card h-100 shadow-sm">
                            <div class="card-body d-flex flex-column">
                                <h6 class="card-title">
                                    <?php echo htmlspecialchars($kh['ten_khoa_hoc']); ?>
                                </h6>
                                <p class="mb-1">
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($kh['danh_muc'] ?? 'Khóa học'); ?>
                                    </small>
                                </p>
                                <p class="mb-1">
                                    <?php
                                    if ($kh['ngay_bat_dau']) {
                                        echo 'Bắt đầu: ' . date('d/m/Y', strtotime($kh['ngay_bat_dau']));
                                    } else {
                                        echo '&nbsp;';
                                    }
                                    ?>
                                </p>
                                <p class="mb-2">&nbsp;</p>
                                <div class="mt-auto">
                                    <a href="student_course_detail.php?id=<?php echo (int)$kh['id']; ?>"
                                       class="btn btn-outline-danger btn-sm w-100">
                                        Xem chi tiết
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<footer>
    <div class="container text-center">
        © <?php echo date('Y'); ?> Hệ thống E-learning PTIT - Khu vực học viên.
    </div>
</footer>

</body>
</html>
