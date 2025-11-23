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
$khoa_hoc = [];
$da_dang_ky = [];

try {
    $pdo = Database::pdo();

    // Lấy danh sách các khóa học đã đăng ký (để hiển thị nút tương ứng)
    $sql = "SELECT id_khoa_hoc, trang_thai FROM dang_ky_khoa_hoc WHERE id_hoc_vien = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $user_id]);
    $dk = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($dk as $d) {
        // mảng [id_khoa_hoc => trang_thai]
        $da_dang_ky[$d['id_khoa_hoc']] = $d['trang_thai'];
    }

    // Lấy danh sách khóa học có trạng thái công bố
    $sql = "
        SELECT 
            kh.id,
            kh.ten_khoa_hoc,
            kh.mo_ta,
            kh.duong_dan_tom_tat,
            kh.danh_muc,
            kh.ngay_bat_dau,
            kh.ngay_ket_thuc,
            nd.ho_ten AS ten_giang_vien
        FROM khoa_hoc kh
        JOIN nguoi_dung nd ON kh.id_giang_vien = nd.id
        WHERE kh.trang_thai = 'cong_bo'
        ORDER BY kh.ngay_tao DESC
    ";
    $stmt = $pdo->query($sql);
    $khoa_hoc = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = $e->getMessage();
}

// Map trạng thái để hiển thị text đẹp hơn
function textTrangThaiDangKy($code) {
    $map = [
        'cho_duyet'   => 'Chờ giảng viên duyệt',
        'dang_hoc'    => 'Đang học',
        'hoan_thanh'  => 'Đã hoàn thành',
        'huy'         => 'Đã hủy',
    ];
    return $map[$code] ?? $code;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Danh sách khóa học - E-learning PTIT</title>

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
            <span>Danh sách khóa học</span>
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
        <img src="image/ptit.png" style="height:55px;" class="me-3">
        <div>
            <div class="logo-text">HỆ THỐNG E-LEARNING PTIT</div>
            <div style="font-size:0.9rem;color:#555;">Danh sách khóa học</div>
        </div>
    </div>
</header>

<div class="container mt-4 mb-5">

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger">Lỗi: <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 style="color:#b30000;">Tất cả khóa học</h3>

        <a href="student_my_courses.php" class="btn btn-outline-danger btn-sm">
            <i class="bi bi-journal-text"></i> Khóa học của tôi
        </a>
    </div>

    <?php if (empty($khoa_hoc)): ?>
        <div class="alert alert-warning">
            Hiện chưa có khóa học nào được công bố.
        </div>
    <?php else: ?>

        <div class="alert alert-info py-2">
            Khi bấm <strong>"Gửi yêu cầu đăng ký"</strong>, yêu cầu sẽ được gửi tới giảng viên.
            Sau khi được duyệt, khóa học sẽ xuất hiện trong mục <strong>"Khóa học của tôi"</strong>.
        </div>

        <div class="row g-3">
            <?php foreach ($khoa_hoc as $kh): ?>
                <div class="col-md-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body d-flex flex-column">

                            <h5 class="card-title" style="color:#b30000;">
                                <?php echo htmlspecialchars($kh['ten_khoa_hoc']); ?>
                            </h5>

                            <p class="text-muted small mb-1">
                                Giảng viên:
                                <strong><?php echo htmlspecialchars($kh['ten_giang_vien']); ?></strong>
                            </p>

                            <p class="small mb-2">
                                Danh mục:
                                <strong><?php echo htmlspecialchars($kh['danh_muc'] ?? ''); ?></strong>
                            </p>

                            <p class="card-text small">
                                <?php
                                echo htmlspecialchars(
                                    mb_strimwidth($kh['mo_ta'], 0, 120, '...', 'UTF-8')
                                );
                                ?>
                            </p>

                            <div class="mt-auto">

                                <?php if (isset($da_dang_ky[$kh['id']])): ?>

                                    <?php $tr = $da_dang_ky[$kh['id']]; ?>
                                    <?php $text = textTrangThaiDangKy($tr); ?>

                                    <button class="btn btn-secondary w-100" disabled>
                                        <?php if ($tr === 'cho_duyet'): ?>
                                            <i class="bi bi-hourglass-split"></i>
                                            Đã gửi yêu cầu (<?php echo $text; ?>)
                                        <?php elseif ($tr === 'dang_hoc'): ?>
                                            <i class="bi bi-check-circle-fill"></i>
                                            Đã được duyệt (<?php echo $text; ?>)
                                        <?php elseif ($tr === 'hoan_thanh'): ?>
                                            <i class="bi bi-award-fill"></i>
                                            Đã hoàn thành
                                        <?php else: ?>
                                            <i class="bi bi-x-circle"></i>
                                            <?php echo $text; ?>
                                        <?php endif; ?>
                                    </button>

                                <?php else: ?>

                                    <!-- Gửi yêu cầu đăng ký (tạo bản ghi trạng thái cho_duyet) -->
                                    <a href="student_register_course.php?id=<?php echo (int)$kh['id']; ?>"
                                       class="btn btn-danger w-100">
                                        <i class="bi bi-send"></i> Gửi yêu cầu đăng ký
                                    </a>

                                <?php endif; ?>

                                <a href="student_course_detail.php?id=<?php echo (int)$kh['id']; ?>"
                                   class="btn btn-outline-dark btn-sm mt-2 w-100">
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

<footer>
    <div class="container text-center">
        © <?php echo date('Y'); ?> Hệ thống E-learning PTIT - Khu vực học viên.
    </div>
</footer>

</body>
</html>
