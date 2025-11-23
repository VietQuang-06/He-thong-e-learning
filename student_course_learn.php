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
$my_reg = null;
$bai_giang = [];
$luot_xem_map = [];
$tien_do_phan_tram = 0;

// Lấy id khóa học
$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($course_id <= 0) {
    $error = 'Khóa học không hợp lệ.';
} else {
    try {
        $pdo = Database::pdo();

        // 1. Lấy thông tin khóa học + giảng viên
        $sql = "
            SELECT 
                kh.id,
                kh.ten_khoa_hoc,
                kh.mo_ta,
                kh.danh_muc,
                kh.trang_thai,
                kh.ngay_bat_dau,
                kh.ngay_ket_thuc,
                nd.ho_ten AS ten_giang_vien
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
            // 2. Lấy thông tin đăng ký của học viên cho khóa này
            $sql = "
                SELECT id, trang_thai, tien_do_phan_tram, diem_cuoi_ky
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

            if (!$my_reg) {
                $error = 'Bạn chưa đăng ký khóa học này.';
            } elseif (!in_array($my_reg['trang_thai'], ['dang_hoc','hoan_thanh'], true)) {
                $error = 'Tài khoản của bạn hiện không được phép học khóa này (trạng thái: ' . $my_reg['trang_thai'] . ').';
            } else {
                // 3. Lấy danh sách bài giảng
                $sql = "
                    SELECT 
                        id,
                        tieu_de,
                        loai_noi_dung,
                        duong_dan_noi_dung,
                        thu_tu_hien_thi,
                        hien_thi
                    FROM bai_giang
                    WHERE id_khoa_hoc = :kh AND hien_thi = 1
                    ORDER BY thu_tu_hien_thi, id
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':kh' => $course_id]);
                $bai_giang = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // 4. Lấy lịch sử xem bài giảng của học viên
                if (!empty($bai_giang)) {
                    $ids = array_column($bai_giang, 'id');
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));

                    $sql = "
                        SELECT id_bai_giang, thoi_gian_xem, vi_tri_cuoi
                        FROM luot_xem_bai_giang
                        WHERE id_hoc_vien = ? 
                          AND id_bai_giang IN ($placeholders)
                    ";
                    $params = array_merge([$user_id], $ids);
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $luot_xem = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    // Map id_bai_giang => dữ liệu lượt xem
                    foreach ($luot_xem as $lx) {
                        $luot_xem_map[$lx['id_bai_giang']] = $lx;
                    }

                    // 5. Tính tiến độ: số bài có lượt xem / tổng số bài
                    $tong_bai = count($bai_giang);
                    $so_bai_da_xem = count($luot_xem_map);
                    if ($tong_bai > 0) {
                        $tien_do_phan_tram = (int)round($so_bai_da_xem * 100 / $tong_bai);
                    }
                }

                // 6. Cập nhật tiến độ vào bảng dang_ky_khoa_hoc (nếu khác cũ)
                if ($tien_do_phan_tram !== (int)$my_reg['tien_do_phan_tram']) {
                    $sql = "
                        UPDATE dang_ky_khoa_hoc
                        SET tien_do_phan_tram = :td
                        WHERE id = :id
                    ";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ':td' => $tien_do_phan_tram,
                        ':id' => $my_reg['id']
                    ]);
                    $my_reg['tien_do_phan_tram'] = $tien_do_phan_tram;
                }
            }
        }

    } catch (PDOException $e) {
        $error = 'Lỗi khi tải dữ liệu: ' . $e->getMessage();
    }
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

// Icon cho loại nội dung
function iconLoaiNoiDung($type) {
    switch ($type) {
        case 'video': return 'bi bi-camera-video-fill';
        case 'pdf':   return 'bi bi-file-earmark-pdf-fill';
        case 'html':  return 'bi bi-file-code-fill';
        case 'tep':   return 'bi bi-file-earmark-arrow-down-fill';
        case 'link':  return 'bi bi-link-45deg';
        default:      return 'bi bi-file-earmark-text';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Học khóa học - E-learning PTIT</title>

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
            <a href="student_my_courses.php">Khóa học của tôi</a> /
            <span>Học khóa học</span>
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
            <div style="font-size:0.9rem;color:#555;">Khu vực học viên - Học khóa học</div>
        </div>
    </div>
</header>

<div class="container mt-4 mb-5">

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <i class="bi bi-x-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>

        <a href="student_my_courses.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Quay lại Khóa học của tôi
        </a>
    <?php else: ?>

        <?php if ($course && $my_reg): ?>
            <!-- Thông tin khóa học + tiến độ -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <h3 style="color:#b30000;"><?php echo htmlspecialchars($course['ten_khoa_hoc']); ?></h3>
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
                        <strong>Trạng thái đăng ký:</strong>
                        <?php echo htmlspecialchars(textTrangThaiDangKy($my_reg['trang_thai'])); ?>
                    </p>
                    <p class="mb-1">
                        <strong>Điểm cuối kỳ:</strong>
                        <?php
                        if ($my_reg['diem_cuoi_ky'] !== null) {
                            echo htmlspecialchars($my_reg['diem_cuoi_ky']);
                        } else {
                            echo '—';
                        }
                        ?>
                    </p>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <p class="mb-1"><strong>Tiến độ khóa học:</strong></p>
                            <div class="progress mb-2" style="height:20px;">
                                <div class="progress-bar bg-danger"
                                     role="progressbar"
                                     style="width: <?php echo (int)$my_reg['tien_do_phan_tram']; ?>%;">
                                    <?php echo (int)$my_reg['tien_do_phan_tram']; ?>%
                                </div>
                            </div>
                            <p class="small text-muted mb-0">
                                Dựa trên số bài giảng bạn đã xem.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Danh sách bài giảng -->
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Danh sách bài giảng</strong>
                    <span class="small text-muted">
                        Tổng: <?php echo count($bai_giang); ?> bài
                    </span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($bai_giang)): ?>
                        <div class="p-3">
                            <em>Hiện tại khóa học chưa có bài giảng nào.</em>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:5%;">#</th>
                                        <th style="width:40%;">Tiêu đề</th>
                                        <th style="width:15%;">Loại nội dung</th>
                                        <th style="width:20%;">Trạng thái</th>
                                        <th style="width:20%;">Hành động</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                $stt = 1;
                                foreach ($bai_giang as $bg):
                                    $id_bg = (int)$bg['id'];
                                    $da_xem = isset($luot_xem_map[$id_bg]);
                                ?>
                                    <tr>
                                        <td><?php echo $stt++; ?></td>
                                        <td>
                                            <i class="<?php echo iconLoaiNoiDung($bg['loai_noi_dung']); ?>"></i>
                                            &nbsp;
                                            <?php echo htmlspecialchars($bg['tieu_de']); ?>
                                        </td>
                                        <td>
                                            <?php
                                            $loai_map = [
                                                'video' => 'Video',
                                                'pdf'   => 'Tài liệu PDF',
                                                'html'  => 'Nội dung HTML',
                                                'tep'   => 'Tệp tải xuống',
                                                'link'  => 'Liên kết ngoài',
                                            ];
                                            echo htmlspecialchars($loai_map[$bg['loai_noi_dung']] ?? $bg['loai_noi_dung']);
                                            ?>
                                        </td>
                                        <td>
                                            <?php if ($da_xem): ?>
                                                <span class="badge bg-success">
                                                    <i class="bi bi-check-circle"></i> Đã xem
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">
                                                    Chưa xem
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="student_lesson_view.php?id=<?php echo $id_bg; ?>&course_id=<?php echo $course_id; ?>"
                                               class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-play-circle"></i> Học bài này
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
