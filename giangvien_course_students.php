<?php
session_start();
require_once 'config.php';

// Chỉ cho phép giảng viên
if (!isset($_SESSION['user_id']) || ($_SESSION['vai_tro'] ?? '') !== 'giang_vien') {
    header("Location: dang_nhap.php");
    exit;
}

$gv_id  = $_SESSION['user_id'];
$ho_ten_gv = $_SESSION['ho_ten'] ?? 'Giảng viên';

$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$error = '';
$message = '';
$course = null;
$students = [];
$status_filter = $_GET['status'] ?? 'tat_ca'; // tat_ca | cho_duyet | dang_hoc | hoan_thanh | huy

// ======================
// 1. Kiểm tra khóa học
// ======================
if ($course_id <= 0) {
    $error = 'Khóa học không hợp lệ.';
} else {
    try {
        $pdo = Database::pdo();

        // Lấy thông tin khóa học, chắc chắn thuộc về giảng viên đang đăng nhập
        $sql = "
            SELECT 
                kh.id,
                kh.ten_khoa_hoc,
                kh.danh_muc,
                kh.trang_thai,
                kh.ngay_bat_dau,
                kh.ngay_ket_thuc
            FROM khoa_hoc kh
            WHERE kh.id = :id
              AND kh.id_giang_vien = :gv
            LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id' => $course_id,
            ':gv' => $gv_id
        ]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$course) {
            $error = 'Bạn không có quyền xem khóa học này hoặc khóa học không tồn tại.';
        }

    } catch (PDOException $e) {
        $error = 'Lỗi hệ thống: ' . $e->getMessage();
    }
}

// ======================
// 2. Xử lý xóa sinh viên
// ======================
if (!$error && isset($_GET['delete'])) {
    $dk_id = (int)$_GET['delete'];
    if ($dk_id > 0) {
        try {
            // Kiểm tra đăng ký này có thuộc khóa của giảng viên không
            $sql = "
                SELECT dk.id
                FROM dang_ky_khoa_hoc dk
                JOIN khoa_hoc kh ON dk.id_khoa_hoc = kh.id
                WHERE dk.id = :dk
                  AND dk.id_khoa_hoc = :kh
                  AND kh.id_giang_vien = :gv
                LIMIT 1
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':dk' => $dk_id,
                ':kh' => $course_id,
                ':gv' => $gv_id
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                // Xóa bản ghi đăng ký
                $sql = "DELETE FROM dang_ky_khoa_hoc WHERE id = :id LIMIT 1";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':id' => $dk_id]);

                $message = 'Đã xóa sinh viên khỏi khóa học.';
            } else {
                $error = 'Không tìm thấy đăng ký hợp lệ để xóa.';
            }

        } catch (PDOException $e) {
            $error = 'Lỗi khi xóa sinh viên: ' . $e->getMessage();
        }
    }
}

// ======================
// 3. Lấy danh sách sinh viên
// ======================
if (!$error) {
    try {
        $pdo = Database::pdo();

        $sql = "
            SELECT
                dk.id AS dk_id,
                dk.trang_thai,
                dk.tien_do_phan_tram,
                dk.ngay_dang_ky,
                nd.id AS hoc_vien_id,
                nd.ho_ten,
                nd.ma_sinh_vien,
                nd.lop_hoc,
                nd.gioi_tinh,
                nd.email,
                nd.so_dien_thoai
            FROM dang_ky_khoa_hoc dk
            JOIN nguoi_dung nd ON dk.id_hoc_vien = nd.id
            WHERE dk.id_khoa_hoc = :kh
        ";

        $params = [':kh' => $course_id];

        if ($status_filter !== 'tat_ca') {
            $sql .= " AND dk.trang_thai = :st";
            $params[':st'] = $status_filter;
        }

        $sql .= " ORDER BY dk.ngay_dang_ky DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $error = 'Lỗi khi tải danh sách sinh viên: ' . $e->getMessage();
    }
}

// Hàm map trạng thái đăng ký
function textTrangThaiDK($code) {
    $map = [
        'cho_duyet'   => 'Chờ duyệt',
        'dang_hoc'    => 'Đang học',
        'hoan_thanh'  => 'Hoàn thành',
        'huy'         => 'Đã hủy',
    ];
    return $map[$code] ?? $code;
}

// Hàm map giới tính
function textGioiTinh($gt) {
    if ($gt === 'nam') return 'Nam';
    if ($gt === 'nu')  return 'Nữ';
    return '—';
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Danh sách sinh viên khóa học - E-learning PTIT</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Icons -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <!-- CSS chung -->
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<!-- Thanh đỏ trên cùng -->
<div class="top-bar">
    <div class="container d-flex justify-content-between align-items-center">
        <div>
            <i class="bi bi-person-video3"></i>
            <a href="giangvien_dashboard.php">Khu vực giảng viên</a> /
            <a href="giangvien_courses.php">Khóa học của tôi</a> /
            <span>Sinh viên trong khóa</span>
        </div>
        <div>
            Xin chào, <strong><?php echo htmlspecialchars($ho_ten_gv); ?></strong>
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
            <div style="font-size:0.9rem;color:#555;">Danh sách sinh viên trong khóa học</div>
        </div>
    </div>
</header>

<div class="container mt-4 mb-5">

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="bi bi-x-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if ($message): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($course): ?>
        <!-- Thông tin khóa học -->
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <h4 class="mb-1" style="color:#b30000;">
                    <?php echo htmlspecialchars($course['ten_khoa_hoc']); ?>
                </h4>
                <p class="mb-1">
                    <strong>Danh mục:</strong>
                    <?php echo htmlspecialchars($course['danh_muc'] ?? ''); ?>
                </p>
                <p class="mb-1">
                    <strong>Thời gian học:</strong>
                    <?php
                    $bd = $course['ngay_bat_dau'] ? date('d/m/Y', strtotime($course['ngay_bat_dau'])) : '';
                    $kt = $course['ngay_ket_thuc'] ? date('d/m/Y', strtotime($course['ngay_ket_thuc'])) : '';
                    echo ($bd && $kt) ? "$bd - $kt" : '—';
                    ?>
                </p>
                <p class="mb-0">
                    <strong>Trạng thái khóa:</strong>
                    <?php
                    $mapCourseStatus = [
                        'nhap'     => 'Nháp',
                        'cong_bo' => 'Đang công bố',
                        'luu_tru' => 'Lưu trữ'
                    ];
                    echo htmlspecialchars($mapCourseStatus[$course['trang_thai']] ?? $course['trang_thai']);
                    ?>
                </p>
            </div>
        </div>

        <!-- Bộ lọc trạng thái đăng ký -->
        <form class="row g-2 mb-3" method="get">
            <input type="hidden" name="id" value="<?php echo (int)$course_id; ?>">
            <div class="col-auto">
                <label for="status" class="col-form-label">Lọc theo trạng thái đăng ký:</label>
            </div>
            <div class="col-auto">
                <select name="status" id="status" class="form-select form-select-sm">
                    <option value="tat_ca"     <?php if ($status_filter === 'tat_ca') echo 'selected'; ?>>Tất cả</option>
                    <option value="cho_duyet"  <?php if ($status_filter === 'cho_duyet') echo 'selected'; ?>>Chờ duyệt</option>
                    <option value="dang_hoc"   <?php if ($status_filter === 'dang_hoc') echo 'selected'; ?>>Đang học</option>
                    <option value="hoan_thanh" <?php if ($status_filter === 'hoan_thanh') echo 'selected'; ?>>Hoàn thành</option>
                    <option value="huy"        <?php if ($status_filter === 'huy') echo 'selected'; ?>>Đã hủy</option>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-danger btn-sm">
                    <i class="bi bi-funnel"></i> Lọc
                </button>
            </div>
            <div class="col-auto">
                <a href="giangvien_course_detail.php?id=<?php echo (int)$course_id; ?>"
                   class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i> Quay lại chi tiết khóa
                </a>
            </div>
        </form>

        <!-- Bảng danh sách sinh viên -->
        <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Họ tên</th>
                        <th>Mã SV</th>
                        <th>Lớp</th>
                        <th>Giới tính</th>
                        <th>Email</th>
                        <th>SĐT</th>
                        <th>Trạng thái</th>
                        <th>Tiến độ</th>
                        <th>Ngày đăng ký</th>
                        <th>Hành động</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($students)): ?>
                    <tr>
                        <td colspan="11" class="text-center text-muted">
                            Hiện chưa có sinh viên nào (hoặc không có sinh viên phù hợp bộ lọc).
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($students as $index => $st): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><?php echo htmlspecialchars($st['ho_ten']); ?></td>
                            <td><?php echo htmlspecialchars($st['ma_sinh_vien']); ?></td>
                            <td><?php echo htmlspecialchars($st['lop_hoc']); ?></td>
                            <td><?php echo htmlspecialchars(textGioiTinh($st['gioi_tinh'])); ?></td>
                            <td><?php echo htmlspecialchars($st['email']); ?></td>
                            <td><?php echo htmlspecialchars($st['so_dien_thoai']); ?></td>
                            <td><?php echo htmlspecialchars(textTrangThaiDK($st['trang_thai'])); ?></td>
                            <td>
                                <div class="progress" style="height:18px;">
                                    <div class="progress-bar bg-danger"
                                         role="progressbar"
                                         style="width: <?php echo (int)$st['tien_do_phan_tram']; ?>%;">
                                        <?php echo (int)$st['tien_do_phan_tram']; ?>%
                                    </div>
                                </div>
                            </td>
                            <td><?php echo $st['ngay_dang_ky'] ? date('d/m/Y H:i', strtotime($st['ngay_dang_ky'])) : '—'; ?></td>
                            <td class="text-center">
                                <a href="giangvien_course_students.php?id=<?php echo (int)$course_id; ?>&delete=<?php echo (int)$st['dk_id']; ?>"
                                   class="btn btn-sm btn-outline-danger"
                                   onclick="return confirm('Bạn có chắc chắn muốn xóa sinh viên này khỏi khóa học?');">
                                    <i class="bi bi-trash3"></i> Xóa
                                </a>
                                <!-- Nếu muốn sau này có chức năng xem chi tiết tiến độ, có thể thêm nút ở đây -->
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>

</div>

<footer>
    <div class="container text-center">
        © <?php echo date('Y'); ?> Hệ thống E-learning PTIT - Khu vực giảng viên.
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
