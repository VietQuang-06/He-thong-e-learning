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

$error = '';
$course = null;
$my_reg  = null;

// Lấy id khóa học
$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($course_id <= 0) {
    $error = 'Khóa học không hợp lệ.';
} else {
    try {
        $pdo = Database::pdo();

        // Lấy thông tin khóa học + giảng viên + số bài giảng + số học viên đã đăng ký
        $sql = "
            SELECT 
                kh.id,
                kh.ten_khoa_hoc,
                kh.mo_ta,
                kh.duong_dan_tom_tat,
                kh.danh_muc,
                kh.trang_thai,
                kh.ngay_bat_dau,
                kh.ngay_ket_thuc,
                nd.ho_ten AS ten_giang_vien,
                (
                    SELECT COUNT(*) 
                    FROM bai_giang bg 
                    WHERE bg.id_khoa_hoc = kh.id
                ) AS so_bai_giang,
                (
                    SELECT COUNT(*) 
                    FROM dang_ky_khoa_hoc dk 
                    WHERE dk.id_khoa_hoc = kh.id
                ) AS so_hoc_vien
            FROM khoa_hoc kh
            JOIN nguoi_dung nd ON kh.id_giang_vien = nd.id
            WHERE kh.id = :id
            LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $course_id]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$course) {
            $error = 'Không tìm thấy thông tin khóa học.';
        } else {
            // Lấy thông tin đăng ký của chính học viên cho khóa này (nếu có)
            $sql = "
                SELECT trang_thai, tien_do_phan_tram, diem_cuoi_ky
                FROM dang_ky_khoa_hoc
                WHERE id_hoc_vien = :hv AND id_khoa_hoc = :kh
                LIMIT 1
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':hv' => $user_id,
                ':kh' => $course_id
            ]);
            $my_reg = $stmt->fetch(PDO::FETCH_ASSOC);
        }

    } catch (PDOException $e) {
        $error = 'Lỗi khi tải dữ liệu: ' . $e->getMessage();
    }
}

// Map trạng thái khóa học
function textTrangThaiKhoa($code) {
    $map = [
        'nhap'      => 'Bản nháp',
        'cong_bo'   => 'Đang mở đăng ký',
        'luu_tru'   => 'Đã lưu trữ',
    ];
    return $map[$code] ?? $code;
}

// Map trạng thái đăng ký
function textTrangThaiDangKy($code) {
    $map = [
        'cho_duyet'   => 'Chờ duyệt',
        'dang_hoc'    => 'Đang học',
        'hoan_thanh'  => 'Hoàn thành',
        'huy'         => 'Đã hủy',
    ];
    return $map[$code] ?? $code;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Chi tiết khóa học - E-learning PTIT</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<!-- Thanh trên -->
<div class="top-bar">
    <div class="container d-flex justify-content-between align-items-center">
        <div>
            <i class="bi bi-mortarboard-fill"></i>
            <a href="student_dashboard.php">Khu vực học viên</a> /
            <a href="student_courses_catalog.php">Danh sách khóa học</a> /
            <span>Chi tiết khóa học</span>
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
        <img src="image/ptit.png" style="height:55px;" class="me-3" alt="PTIT Logo">
        <div>
            <div class="logo-text">HỆ THỐNG E-LEARNING PTIT</div>
            <div style="font-size:0.9rem;color:#555;">Chi tiết khóa học</div>
        </div>
    </div>
</header>

<div class="container mt-4 mb-5">

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <i class="bi bi-x-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>

        <a href="student_courses_catalog.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Quay lại danh sách khóa học
        </a>
    <?php else: ?>

        <?php if ($course): ?>
            <div class="row mb-4">
                <div class="col-md-8">
                    <h3 style="color:#b30000;">
                        <?php echo htmlspecialchars($course['ten_khoa_hoc']); ?>
                    </h3>
                    <p class="mb-1">
                        <strong>Giảng viên:</strong>
                        <?php echo htmlspecialchars($course['ten_giang_vien']); ?>
                    </p>
                    <p class="mb-1">
                        <strong>Danh mục:</strong>
                        <?php echo htmlspecialchars($course['danh_muc'] ?? ''); ?>
                    </p>
                    <p class="mb-1">
                        <strong>Thời gian học:</strong>
                        <?php
                        $bd = $course['ngay_bat_dau'] ? date('d/m/Y', strtotime($course['ngay_bat_dau'])) : '';
                        $kt = $course['ngay_ket_thuc'] ? date('d/m/Y', strtotime($course['ngay_ket_thuc'])) : '';
                        echo ($bd && $kt) ? ($bd . ' - ' . $kt) : '—';
                        ?>
                    </p>
                    <p class="mb-1">
                        <strong>Trạng thái khóa học:</strong>
                        <?php echo htmlspecialchars(textTrangThaiKhoa($course['trang_thai'])); ?>
                    </p>
                </div>

                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <p class="mb-1">
                                <strong>Số bài giảng:</strong>
                                <?php echo (int)($course['so_bai_giang'] ?? 0); ?>
                            </p>
                            <p class="mb-1">
                                <strong>Số học viên đã đăng ký:</strong>
                                <?php echo (int)($course['so_hoc_vien'] ?? 0); ?>
                            </p>

                            <hr class="my-2">

                            <?php if ($my_reg): ?>
                                <p class="mb-1">
                                    <strong>Trạng thái của bạn:</strong>
                                    <?php echo htmlspecialchars(textTrangThaiDangKy($my_reg['trang_thai'])); ?>
                                </p>

                                <p class="mb-1">
                                    <strong>Tiến độ:</strong>
                                </p>
                                <div class="progress mb-2" style="height:18px;">
                                    <div class="progress-bar bg-danger"
                                         role="progressbar"
                                         style="width: <?php echo (int)$my_reg['tien_do_phan_tram']; ?>%;">
                                        <?php echo (int)$my_reg['tien_do_phan_tram']; ?>%
                                    </div>
                                </div>

                                <p class="mb-2">
                                    <strong>Điểm cuối kỳ:</strong>
                                    <?php
                                    if ($my_reg['diem_cuoi_ky'] !== null) {
                                        echo htmlspecialchars($my_reg['diem_cuoi_ky']);
                                    } else {
                                        echo '—';
                                    }
                                    ?>
                                </p>

                                <?php if ($my_reg['trang_thai'] === 'dang_hoc' || $my_reg['trang_thai'] === 'hoan_thanh'): ?>
                                    <a href="student_course_learn.php?id=<?php echo (int)$course['id']; ?>"
                                       class="btn btn-danger w-100 mb-1">
                                        <i class="bi bi-play-circle"></i> Vào học ngay
                                    </a>
                                <?php endif; ?>

                            <?php else: ?>
                                <p class="mb-2">
                                    Bạn <strong>chưa đăng ký</strong> khóa học này.
                                </p>
                                <?php if ($course['trang_thai'] === 'cong_bo'): ?>
                                    <a href="student_register_course.php?id=<?php echo (int)$course['id']; ?>"
                                       class="btn btn-danger w-100 mb-1">
                                        <i class="bi bi-plus-circle"></i> Đăng ký khóa học này
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-secondary w-100 mb-1" disabled>
                                        Khóa học hiện không mở đăng ký
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>

                            <a href="student_my_courses.php" class="btn btn-outline-secondary btn-sm w-100 mt-1">
                                <i class="bi bi-journal-text"></i> Khóa học của tôi
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mô tả khóa học -->
            <div class="card shadow-sm">
                <div class="card-header">
                    <strong>Giới thiệu khóa học</strong>
                </div>
                <div class="card-body">
                    <?php
                    if (!empty($course['mo_ta'])) {
                        echo nl2br(htmlspecialchars($course['mo_ta']));
                    } else {
                        echo '<em>Chưa có mô tả chi tiết cho khóa học này.</em>';
                    }
                    ?>
                </div>
            </div>

        <?php endif; ?>

    <?php endif; ?>

</div>

<footer>
    <div class="container text-center">
        © <?php echo date('Y'); ?> Hệ thống E-learning PTIT - Khu vực học viên.
    </div>
</footer>

</body>
</html>
