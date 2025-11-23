<?php
session_start();
require_once 'config.php';

// Chỉ cho phép Giảng viên
if (!isset($_SESSION['user_id']) || ($_SESSION['vai_tro'] ?? '') !== 'giang_vien') {
    header("Location: dang_nhap.php");
    exit;
}

$giang_vien_id       = $_SESSION['user_id'];
$ho_ten_giang_vien   = $_SESSION['ho_ten'] ?? 'Giảng viên';

$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($course_id <= 0) {
    header("Location: giangvien_courses.php");
    exit;
}

$error = '';
$course = null;
$students = [];
$lessons  = [];
$exams    = [];

try {
    $pdo = Database::pdo();

    // 1. Thông tin khóa học (thuộc giảng viên này)
    $sql = "
        SELECT 
            kh.*,
            (
                SELECT COUNT(*) 
                FROM dang_ky_khoa_hoc dk 
                WHERE dk.id_khoa_hoc = kh.id AND dk.trang_thai <> 'huy'
            ) AS so_hoc_vien,
            (
                SELECT COUNT(*) 
                FROM bai_giang bg 
                WHERE bg.id_khoa_hoc = kh.id
            ) AS so_bai_giang,
            (
                SELECT COUNT(*) 
                FROM bai_thi bt 
                WHERE bt.id_khoa_hoc = kh.id
            ) AS so_bai_thi
        FROM khoa_hoc kh
        WHERE kh.id = :id
          AND kh.id_giang_vien = :id_gv
        LIMIT 1
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':id'    => $course_id,
        ':id_gv' => $giang_vien_id
    ]);
    $course = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$course) {
        $error = "Bạn không có quyền truy cập khóa học này hoặc khóa học không tồn tại.";
    } else {
        // 2. Danh sách học viên (có thêm gioi_tinh, KHÔNG dùng diem_cuoi_ky)
        $sql = "
            SELECT 
                dk.id_hoc_vien,
                dk.trang_thai,
                dk.tien_do_phan_tram,
                dk.ngay_dang_ky,
                nd.ho_ten,
                nd.ma_sinh_vien,
                nd.lop_hoc,
                nd.email,
                nd.gioi_tinh
            FROM dang_ky_khoa_hoc dk
            JOIN nguoi_dung nd ON dk.id_hoc_vien = nd.id
            WHERE dk.id_khoa_hoc = :id_kh
            ORDER BY nd.ho_ten ASC
            LIMIT 20
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id_kh' => $course_id]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 3. Bài giảng
        $sql = "
            SELECT
                id,
                tieu_de,
                loai_noi_dung,
                thu_tu_hien_thi,
                hien_thi,
                ngay_tao
            FROM bai_giang
            WHERE id_khoa_hoc = :id_kh
            ORDER BY thu_tu_hien_thi ASC, ngay_tao ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id_kh' => $course_id]);
        $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 4. Bài thi
        $sql = "
            SELECT
                id,
                tieu_de,
                thoi_gian_bat_dau,
                thoi_gian_ket_thuc,
                thoi_luong_phut,
                trang_thai
            FROM bai_thi
            WHERE id_khoa_hoc = :id_kh
            ORDER BY thoi_gian_bat_dau IS NULL, thoi_gian_bat_dau ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id_kh' => $course_id]);
        $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    $error = "Lỗi hệ thống: " . $e->getMessage();
}

// Hàm hiển thị trạng thái khóa học
function textTrangThaiKhoaHoc($code) {
    $map = [
        'nhap'     => 'Nháp',
        'cong_bo'  => 'Đang công bố',
        'luu_tru'  => 'Đã lưu trữ'
    ];
    return $map[$code] ?? $code;
}

// Trạng thái đăng ký học
function textTrangThaiDangKy($code) {
    $map = [
        'cho_duyet'  => 'Chờ duyệt',
        'dang_hoc'   => 'Đang học',
        'hoan_thanh' => 'Hoàn thành',
        'huy'        => 'Đã hủy'
    ];
    return $map[$code] ?? $code;
}

// Trạng thái bài thi
function textTrangThaiBaiThi($code) {
    $map = [
        'nhap'     => 'Nháp',
        'dang_mo'  => 'Đang mở',
        'dong'     => 'Đã đóng'
    ];
    return $map[$code] ?? $code;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Chi tiết khóa học - Giảng viên</title>

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
            <span> / </span>
            <a href="giangvien_courses.php" class="text-white">
                Khóa học của tôi
            </a>
            <span> / Chi tiết khóa học</span>
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
            <div style="font-size: 0.9rem; color:#555;">Chi tiết khóa học</div>
        </div>
    </div>
</header>

<div class="container mt-4 mb-5">

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php elseif ($course): ?>

        <!-- Tên khóa học + trạng thái -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 style="color:#b30000;">
                <?php echo htmlspecialchars($course['ten_khoa_hoc']); ?>
            </h3>
            <div>
                <span class="me-2">
                    Trạng thái:
                    <?php
                    $lbl = textTrangThaiKhoaHoc($course['trang_thai']);
                    if ($course['trang_thai'] === 'cong_bo') {
                        echo '<span class="badge bg-success">' . htmlspecialchars($lbl) . '</span>';
                    } elseif ($course['trang_thai'] === 'nhap') {
                        echo '<span class="badge bg-secondary">' . htmlspecialchars($lbl) . '</span>';
                    } else {
                        echo '<span class="badge bg-light text-dark">' . htmlspecialchars($lbl) . '</span>';
                    }
                    ?>
                </span>
            </div>
        </div>

        <!-- Thống kê nhanh -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card shadow-sm p-3 text-center">
                    <i class="bi bi-people-fill fs-1 text-danger"></i>
                    <h5 class="mt-2">Số học viên</h5>
                    <p class="display-6 mb-0"><?php echo (int)$course['so_hoc_vien']; ?></p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm p-3 text-center">
                    <i class="bi bi-collection-play-fill fs-1 text-danger"></i>
                    <h5 class="mt-2">Số bài giảng</h5>
                    <p class="display-6 mb-0"><?php echo (int)$course['so_bai_giang']; ?></p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm p-3 text-center">
                    <i class="bi bi-card-checklist fs-1 text-danger"></i>
                    <h5 class="mt-2">Số bài thi</h5>
                    <p class="display-6 mb-0"><?php echo (int)$course['so_bai_thi']; ?></p>
                </div>
            </div>
        </div>

        <!-- Thông tin khóa học -->
        <div class="card mb-4">
            <div class="card-header">
                <strong>Thông tin khóa học</strong>
            </div>
            <div class="card-body">
                <div class="row mb-2">
                    <div class="col-md-6">
                        <p><strong>Danh mục:</strong>
                            <?php echo htmlspecialchars($course['danh_muc'] ?? ''); ?>
                        </p>
                        <p><strong>Học phí:</strong>
                            <?php
                            if ($course['hoc_phi'] != 0) {
                                echo number_format($course['hoc_phi'], 0, ',', '.') . ' đ';
                            } else {
                                echo 'Miễn phí';
                            }
                            ?>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Thời gian học:</strong>
                            <?php
                            $bd = $course['ngay_bat_dau'] ? date('d/m/Y', strtotime($course['ngay_bat_dau'])) : '';
                            $kt = $course['ngay_ket_thuc'] ? date('d/m/Y', strtotime($course['ngay_ket_thuc'])) : '';
                            echo ($bd && $kt) ? $bd . ' - ' . $kt : '—';
                            ?>
                        </p>
                        <p><strong>Ngày tạo:</strong>
                            <?php
                            echo $course['ngay_tao'] ? date('d/m/Y H:i', strtotime($course['ngay_tao'])) : '—';
                            ?>
                        </p>
                    </div>
                </div>

                <?php if (!empty($course['mo_ta'])): ?>
                    <div class="mb-0">
                        <strong>Mô tả:</strong>
                        <div class="border rounded p-2" style="background:#fafafa;">
                            <?php echo nl2br(htmlspecialchars($course['mo_ta'])); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Học viên trong khóa -->
        <div class="mb-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h4 style="color:#b30000;">Học viên trong khóa</h4>
                <a href="giangvien_course_students.php?id=<?php echo (int)$course_id; ?>"
                   class="btn btn-sm btn-outline-secondary">
                    Xem tất cả
                </a>
            </div>

            <div class="card">
                <div class="card-body p-0">
                    <?php if (empty($students)): ?>
                        <div class="p-3">
                            <em>Chưa có học viên đăng ký khóa học này.</em>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Học viên</th>
                                        <th>Mã SV</th>
                                        <th>Lớp</th>
                                        <th>Giới tính</th>
                                        <th>Email</th>
                                        <th>Trạng thái</th>
                                        <th>Tiến độ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($students as $sv): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($sv['ho_ten']); ?></td>
                                        <td><?php echo htmlspecialchars($sv['ma_sinh_vien']); ?></td>
                                        <td><?php echo htmlspecialchars($sv['lop_hoc']); ?></td>
                                        <td>
                                            <?php
                                            if ($sv['gioi_tinh'] === 'nam') {
                                                echo 'Nam';
                                            } elseif ($sv['gioi_tinh'] === 'nu') {
                                                echo 'Nữ';
                                            } else {
                                                echo '—';
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($sv['email']); ?></td>
                                        <td>
                                            <?php
                                            $txt = textTrangThaiDangKy($sv['trang_thai']);
                                            if ($sv['trang_thai'] === 'dang_hoc') {
                                                echo '<span class="badge bg-primary">' . htmlspecialchars($txt) . '</span>';
                                            } elseif ($sv['trang_thai'] === 'hoan_thanh') {
                                                echo '<span class="badge bg-success">' . htmlspecialchars($txt) . '</span>';
                                            } elseif ($sv['trang_thai'] === 'cho_duyet') {
                                                echo '<span class="badge bg-warning text-dark">' . htmlspecialchars($txt) . '</span>';
                                            } else {
                                                echo '<span class="badge bg-light text-dark">' . htmlspecialchars($txt) . '</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <div class="progress" style="height: 16px;">
                                                <div class="progress-bar bg-danger"
                                                     role="progressbar"
                                                     style="width: <?php echo (int)$sv['tien_do_phan_tram']; ?>%;">
                                                    <?php echo (int)$sv['tien_do_phan_tram']; ?>%
                                                </div>
                                            </div>
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

        <!-- Bài giảng -->
        <div class="mb-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h4 style="color:#b30000;">Bài giảng</h4>
                <a href="giangvien_course_lessons.php?id=<?php echo (int)$course_id; ?>"
                   class="btn btn-sm btn-outline-secondary">
                    Quản lý bài giảng
                </a>
            </div>

            <div class="card">
                <div class="card-body p-0">
                    <?php if (empty($lessons)): ?>
                        <div class="p-3">
                            <em>Chưa có bài giảng nào. Hãy vào phần "Quản lý bài giảng" để thêm mới.</em>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Tiêu đề</th>
                                        <th>Loại nội dung</th>
                                        <th>Thứ tự</th>
                                        <th>Hiển thị</th>
                                        <th>Ngày tạo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($lessons as $idx => $bg): ?>
                                    <tr>
                                        <td><?php echo $idx + 1; ?></td>
                                        <td><?php echo htmlspecialchars($bg['tieu_de']); ?></td>
                                        <td>
                                            <?php
                                            $mapLoai = [
                                                'video' => 'Video',
                                                'pdf'   => 'Tài liệu PDF',
                                                'html'  => 'Trang nội dung',
                                                'tep'   => 'Tệp đính kèm',
                                                'link'  => 'Liên kết ngoài'
                                            ];
                                            echo htmlspecialchars($mapLoai[$bg['loai_noi_dung']] ?? $bg['loai_noi_dung']);
                                            ?>
                                        </td>
                                        <td><?php echo (int)$bg['thu_tu_hien_thi']; ?></td>
                                        <td>
                                            <?php if ((int)$bg['hien_thi'] === 1): ?>
                                                <span class="badge bg-success">Hiển thị</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Ẩn</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            echo $bg['ngay_tao'] ? date('d/m/Y H:i', strtotime($bg['ngay_tao'])) : '—';
                                            ?>
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

        <!-- Bài thi -->
        <div class="mb-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h4 style="color:#b30000;">Bài thi</h4>
                <a href="giangvien_exams.php?id_khoa_hoc=<?php echo (int)$course_id; ?>"
                   class="btn btn-sm btn-outline-secondary">
                    Quản lý bài thi
                </a>
            </div>

            <div class="card">
                <div class="card-body p-0">
                    <?php if (empty($exams)): ?>
                        <div class="p-3">
                            <em>Chưa có bài thi nào. Hãy vào phần "Quản lý bài thi" để tạo bài thi cho khóa học.</em>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Tiêu đề</th>
                                        <th>Thời gian bắt đầu</th>
                                        <th>Thời gian kết thúc</th>
                                        <th>Thời lượng (phút)</th>
                                        <th>Trạng thái</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($exams as $bt): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($bt['tieu_de']); ?></td>
                                        <td>
                                            <?php
                                            echo $bt['thoi_gian_bat_dau']
                                                ? date('d/m/Y H:i', strtotime($bt['thoi_gian_bat_dau']))
                                                : 'Chưa đặt lịch';
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            echo $bt['thoi_gian_ket_thuc']
                                                ? date('d/m/Y H:i', strtotime($bt['thoi_gian_ket_thuc']))
                                                : '—';
                                            ?>
                                        </td>
                                        <td><?php echo (int)$bt['thoi_luong_phut']; ?></td>
                                        <td>
                                            <?php
                                            $txt = textTrangThaiBaiThi($bt['trang_thai']);
                                            if ($bt['trang_thai'] === 'dang_mo') {
                                                echo '<span class="badge bg-success">' . htmlspecialchars($txt) . '</span>';
                                            } elseif ($bt['trang_thai'] === 'nhap') {
                                                echo '<span class="badge bg-secondary">' . htmlspecialchars($txt) . '</span>';
                                            } else {
                                                echo '<span class="badge bg-light text-dark">' . htmlspecialchars($txt) . '</span>';
                                            }
                                            ?>
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

    <?php endif; ?>

</div>

<footer class="text-center py-3">
    © <?php echo date('Y'); ?> Hệ thống E-learning PTIT - Khu vực giảng viên
</footer>

</body>
</html>
