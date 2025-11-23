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
$khoa_hoc_cua_toi = [];

// Lọc theo trạng thái (tùy chọn): ?status=dang_hoc / hoan_thanh / cho_duyet / huy
$allowed_status = ['cho_duyet','dang_hoc','hoan_thanh','huy'];
$status_filter = isset($_GET['status']) && in_array($_GET['status'], $allowed_status, true)
    ? $_GET['status']
    : '';

try {
    $pdo = Database::pdo();

    $sql = "
        SELECT 
            kh.id,
            kh.ten_khoa_hoc,
            kh.danh_muc,
            kh.mo_ta,
            kh.ngay_bat_dau,
            kh.ngay_ket_thuc,
            dk.trang_thai,
            dk.tien_do_phan_tram,
            dk.diem_cuoi_ky,
            dk.ngay_dang_ky
        FROM dang_ky_khoa_hoc dk
        JOIN khoa_hoc kh ON dk.id_khoa_hoc = kh.id
        WHERE dk.id_hoc_vien = :id_hoc_vien
    ";

    if ($status_filter !== '') {
        $sql .= " AND dk.trang_thai = :status ";
    }

    $sql .= " ORDER BY dk.ngay_dang_ky DESC";

    $stmt = $pdo->prepare($sql);
    $params = [':id_hoc_vien' => $user_id];
    if ($status_filter !== '') {
        $params[':status'] = $status_filter;
    }
    $stmt->execute($params);

    $khoa_hoc_cua_toi = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = $e->getMessage();
}

// Hàm map trạng thái
function textTrangThai($code) {
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
    <title>Khóa học của tôi - E-learning PTIT</title>

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
            <a href="student_dashboard.php">Khu vực học viên</a> /
            <span>Khóa học của tôi</span>
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
            <div style="font-size: 0.9rem; color:#555;">Khóa học của tôi</div>
        </div>
    </div>
</header>

<div class="container mt-4 mb-5">

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger">Lỗi: <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 style="color:#b30000;">Danh sách khóa học của bạn</h3>
        <a href="student_courses_catalog.php" class="btn btn-outline-danger btn-sm">
            <i class="bi bi-bag-plus"></i> Đăng ký khóa học mới
        </a>
    </div>

    <!-- Bộ lọc trạng thái -->
    <form class="row g-2 mb-3" method="get">
        <div class="col-auto">
            <label for="status" class="col-form-label">Lọc theo trạng thái:</label>
        </div>
        <div class="col-auto">
            <select name="status" id="status" class="form-select form-select-sm">
                <option value="">-- Tất cả --</option>
                <option value="cho_duyet"   <?php if ($status_filter==='cho_duyet')   echo 'selected'; ?>>Chờ duyệt</option>
                <option value="dang_hoc"    <?php if ($status_filter==='dang_hoc')    echo 'selected'; ?>>Đang học</option>
                <option value="hoan_thanh"  <?php if ($status_filter==='hoan_thanh')  echo 'selected'; ?>>Hoàn thành</option>
                <option value="huy"         <?php if ($status_filter==='huy')         echo 'selected'; ?>>Đã hủy</option>
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-danger btn-sm">
                <i class="bi bi-funnel"></i> Lọc
            </button>
        </div>
    </form>

    <?php if (empty($khoa_hoc_cua_toi)): ?>
        <div class="alert alert-info">
            Bạn chưa đăng ký khóa học nào.
            Hãy chọn <strong>“Đăng ký khóa học mới”</strong> để bắt đầu.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 30%;">Tên khóa học</th>
                        <th>Danh mục</th>
                        <th>Ngày đăng ký</th>
                        <th>Thời gian học</th>
                        <th>Trạng thái</th>
                        <th>Tiến độ</th>
                        <th style="width: 20%;">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($khoa_hoc_cua_toi as $kh): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($kh['ten_khoa_hoc']); ?></strong>
                            <?php if (!empty($kh['mo_ta'])): ?>
                                <div class="small text-muted">
                                    <?php
                                    // Cắt ngắn mô tả cho gọn
                                    echo htmlspecialchars(mb_strimwidth($kh['mo_ta'], 0, 80, '...', 'UTF-8'));
                                    ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($kh['danh_muc'] ?? ''); ?></td>
                        <td>
                            <?php echo date('d/m/Y', strtotime($kh['ngay_dang_ky'])); ?>
                        </td>
                        <td>
                            <?php
                            $bd = $kh['ngay_bat_dau'] ? date('d/m/Y', strtotime($kh['ngay_bat_dau'])) : '';
                            $kt = $kh['ngay_ket_thuc'] ? date('d/m/Y', strtotime($kh['ngay_ket_thuc'])) : '';

                            if ($bd && $kt) {
                                echo $bd . " - " . $kt;
                            } else {
                                echo "—";
                            }
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars(textTrangThai($kh['trang_thai'])); ?></td>
                        <td>
                            <div class="progress" style="height: 18px;">
                                <div class="progress-bar bg-danger"
                                     role="progressbar"
                                     style="width: <?php echo (int)$kh['tien_do_phan_tram']; ?>%;">
                                    <?php echo (int)$kh['tien_do_phan_tram']; ?>%
                                </div>
                            </div>
                        </td>
                        <td class="text-center">

                            <?php if ($kh['trang_thai'] === 'dang_hoc' || $kh['trang_thai'] === 'hoan_thanh'): ?>
                                <a href="student_course_learn.php?id=<?php echo (int)$kh['id']; ?>"
                                   class="btn btn-sm btn-danger mb-1">
                                    <i class="bi bi-play-circle"></i> Vào học
                                </a>
                            <?php else: ?>
                                <button class="btn btn-sm btn-secondary mb-1" disabled>Không khả dụng</button>
                            <?php endif; ?>

                            <?php if ($kh['trang_thai'] === 'hoan_thanh' && $kh['diem_cuoi_ky'] !== null): ?>
                                <a href="student_exam_result.php?course_id=<?php echo (int)$kh['id']; ?>"
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-file-check"></i> Xem điểm
                                </a>
                            <?php endif; ?>

                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
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
