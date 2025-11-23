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
$message = '';
$course = null;

// Lấy id khóa học
$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($course_id <= 0) {
    $error = 'Khóa học không hợp lệ.';
} else {
    try {
        $pdo = Database::pdo();

        // 1. Kiểm tra khóa học hợp lệ và đã công bố
        $sql = "
            SELECT kh.id, kh.ten_khoa_hoc, kh.danh_muc, kh.mo_ta,
                   kh.hoc_phi, kh.ngay_bat_dau, kh.ngay_ket_thuc,
                   nd.ho_ten AS ten_giang_vien
            FROM khoa_hoc kh
            JOIN nguoi_dung nd ON kh.id_giang_vien = nd.id
            WHERE kh.id = :id
              AND kh.trang_thai = 'cong_bo'
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $course_id]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$course) {
            $error = 'Không tìm thấy khóa học hoặc khóa học chưa được công bố.';
        } else {
            // 2. Kiểm tra trạng thái đăng ký hiện tại
            $sql = "
                SELECT id, trang_thai
                FROM dang_ky_khoa_hoc
                WHERE id_hoc_vien = :hv AND id_khoa_hoc = :kh
                LIMIT 1
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':hv' => $user_id,
                ':kh' => $course_id
            ]);
            $dk = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($dk) {
                // Xử lý khi đã tồn tại đăng ký
                $map = [
                    'cho_duyet'   => 'Chờ giảng viên duyệt',
                    'dang_hoc'    => 'Đang học',
                    'hoan_thanh'  => 'Hoàn thành',
                    'huy'         => 'Đã hủy',
                ];
                $txt = $map[$dk['trang_thai']] ?? $dk['trang_thai'];

                $error = 'Bạn đã đăng ký khóa học này (trạng thái: '.$txt.').';

            } else {
                // 3. Thêm bản ghi mới → CHỜ DUYỆT
                $sql = "
                    INSERT INTO dang_ky_khoa_hoc 
                        (id_hoc_vien, id_khoa_hoc, trang_thai, tien_do_phan_tram, ngay_dang_ky)
                    VALUES 
                        (:hv, :kh, 'cho_duyet', 0, NOW())
                ";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':hv' => $user_id,
                    ':kh' => $course_id
                ]);

                $message = 'Yêu cầu đăng ký đã được gửi. Vui lòng chờ giảng viên duyệt.';
            }
        }

    } catch (PDOException $e) {
        $error = 'Có lỗi khi đăng ký: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đăng ký khóa học - E-learning PTIT</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/style.css">
</head>

<body>

<!-- Thanh đỏ -->
<div class="top-bar">
    <div class="container d-flex justify-content-between align-items-center">
        <div>
            <i class="bi bi-mortarboard-fill"></i>
            <a href="student_dashboard.php">Khu vực học viên</a> /
            <span>Đăng ký khóa học</span>
        </div>
        <div>
            Xin chào, <strong><?php echo htmlspecialchars($ho_ten); ?></strong> |
            <a href="dang_xuat.php" class="text-white">Đăng xuất</a>
        </div>
    </div>
</div>

<!-- Header -->
<header class="main-header">
    <div class="container py-2 d-flex align-items-center">
        <img src="image/ptit.png" style="height:55px;" class="me-3">
        <div>
            <div class="logo-text">HỆ THỐNG E-LEARNING PTIT</div>
            <div style="font-size: 0.9rem; color:#555;">Đăng ký khóa học</div>
        </div>
    </div>
</header>

<div class="container mt-4 mb-5">

    <?php if ($error): ?>
        <div class="alert alert-danger"><i class="bi bi-x-circle"></i> <?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($message): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?php echo $message; ?></div>
    <?php endif; ?>

    <?php if ($course): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h4 class="card-title" style="color:#b30000;"><?php echo htmlspecialchars($course['ten_khoa_hoc']); ?></h4>

                <p><strong>Giảng viên:</strong> <?php echo htmlspecialchars($course['ten_giang_vien']); ?></p>
                <p><strong>Danh mục:</strong> <?php echo htmlspecialchars($course['danh_muc']); ?></p>
                <p><strong>Học phí:</strong> <?php echo number_format($course['hoc_phi']); ?> đ</p>
                <p><strong>Thời gian:</strong>
                    <?php
                    $bd = $course['ngay_bat_dau'] ? date('d/m/Y', strtotime($course['ngay_bat_dau'])) : '';
                    $kt = $course['ngay_ket_thuc'] ? date('d/m/Y', strtotime($course['ngay_ket_thuc'])) : '';
                    echo ($bd && $kt) ? "$bd - $kt" : '—';
                    ?>
                </p>

                <hr>

                <p><?php echo nl2br(htmlspecialchars($course['mo_ta'])); ?></p>
            </div>
        </div>
    <?php endif; ?>

    <div class="d-flex gap-2">
        <a href="student_courses_catalog.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Quay lại danh sách
        </a>

        <a href="student_my_courses.php" class="btn btn-outline-danger">
            <i class="bi bi-journal-text"></i> Khóa học của tôi
        </a>
    </div>

</div>

<footer class="text-center py-3">
    © <?php echo date('Y'); ?> Hệ thống E-learning PTIT
</footer>

</body>
</html>
