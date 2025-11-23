<?php
session_start();
require_once 'config.php';

// Chỉ cho phép Học viên truy cập
if (!isset($_SESSION['user_id']) || ($_SESSION['vai_tro'] ?? '') !== 'hoc_vien') {
    header("Location: dang_nhap.php");
    exit;
}

$user_id   = $_SESSION['user_id'];
$ho_ten    = $_SESSION['ho_ten'] ?? 'Học viên';

$error     = '';
$lesson    = null;
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

// Lấy id bài giảng
$lesson_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($lesson_id <= 0) {
    $error = 'Bài giảng không hợp lệ.';
} else {
    try {
        $pdo = Database::pdo();

        // 1. Lấy thông tin bài giảng + khóa học + giảng viên
        $sql = "
            SELECT 
                bg.id,
                bg.id_khoa_hoc,
                bg.tieu_de,
                bg.loai_noi_dung,
                bg.duong_dan_noi_dung,
                bg.noi_dung_html,
                bg.thu_tu_hien_thi,
                kh.ten_khoa_hoc,
                kh.danh_muc,
                nd.ho_ten AS ten_giang_vien
            FROM bai_giang bg
            JOIN khoa_hoc kh ON bg.id_khoa_hoc = kh.id
            JOIN nguoi_dung nd ON kh.id_giang_vien = nd.id
            WHERE bg.id = :id
              AND bg.hien_thi = 1
            LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $lesson_id]);
        $lesson = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$lesson) {
            $error = 'Không tìm thấy thông tin bài giảng.';
        } else {
            // Nếu URL có course_id nhưng khác với id_khoa_hoc của bài, báo lỗi
            if ($course_id > 0 && $course_id !== (int)$lesson['id_khoa_hoc']) {
                $error = 'Bài giảng không thuộc khóa học bạn yêu cầu.';
            } else {
                // Đồng bộ course_id theo bài giảng
                $course_id = (int)$lesson['id_khoa_hoc'];

                // 2. Kiểm tra học viên có đăng ký khóa học này không
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

                if (!$dk) {
                    $error = 'Bạn chưa đăng ký khóa học này, không thể xem bài giảng.';
                } elseif (!in_array($dk['trang_thai'], ['dang_hoc','hoan_thanh'], true)) {
                    $error = 'Tài khoản của bạn hiện không được phép học khóa này (trạng thái: ' . $dk['trang_thai'] . ').';
                } else {
                    // 3. Ghi nhận lượt xem bài giảng (insert hoặc update)
                    $sql = "
                        SELECT id
                        FROM luot_xem_bai_giang
                        WHERE id_bai_giang = :bg AND id_hoc_vien = :hv
                        LIMIT 1
                    ";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ':bg' => $lesson_id,
                        ':hv' => $user_id
                    ]);
                    $view = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($view) {
                        // Cập nhật thời gian xem gần nhất
                        $sql = "
                            UPDATE luot_xem_bai_giang
                            SET thoi_gian_xem = NOW()
                            WHERE id = :id
                        ";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([':id' => $view['id']]);
                    } else {
                        // Ghi lượt xem mới
                        $sql = "
                            INSERT INTO luot_xem_bai_giang (id_bai_giang, id_hoc_vien, vi_tri_cuoi)
                            VALUES (:bg, :hv, 0)
                        ";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([
                            ':bg' => $lesson_id,
                            ':hv' => $user_id
                        ]);
                    }
                }
            }
        }

    } catch (PDOException $e) {
        $error = 'Lỗi khi tải dữ liệu bài giảng: ' . $e->getMessage();
    }
}

// Hàm icon cho loại nội dung
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

// Map loại nội dung → text
function textLoaiNoiDung($type) {
    $map = [
        'video' => 'Video bài giảng',
        'pdf'   => 'Tài liệu PDF',
        'html'  => 'Nội dung HTML',
        'tep'   => 'Tệp tài liệu',
        'link'  => 'Liên kết ngoài',
    ];
    return $map[$type] ?? $type;
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Xem bài giảng - E-learning PTIT</title>

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
            <a href="student_my_courses.php">Khóa học của tôi</a>
            <?php if ($course_id): ?>
                / <a href="student_course_learn.php?id=<?php echo (int)$course_id; ?>">Học khóa học</a>
            <?php endif; ?>
            / <span>Bài giảng</span>
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
            <div style="font-size:0.9rem;color:#555;">Xem bài giảng</div>
        </div>
    </div>
</header>

<div class="container mt-4 mb-5">

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <i class="bi bi-x-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>

        <?php if ($course_id): ?>
            <a href="student_course_learn.php?id=<?php echo (int)$course_id; ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Quay lại danh sách bài giảng
            </a>
        <?php else: ?>
            <a href="student_my_courses.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Quay lại Khóa học của tôi
            </a>
        <?php endif; ?>

    <?php elseif ($lesson): ?>

        <!-- Thông tin bài giảng -->
        <div class="mb-3">
            <h3 style="color:#b30000;">
                <i class="<?php echo iconLoaiNoiDung($lesson['loai_noi_dung']); ?>"></i>
                &nbsp;<?php echo htmlspecialchars($lesson['tieu_de']); ?>
            </h3>
            <p class="mb-1">
                <strong>Khóa học:</strong>
                <?php echo htmlspecialchars($lesson['ten_khoa_hoc']); ?>
            </p>
            <p class="mb-1">
                <strong>Giảng viên:</strong>
                <?php echo htmlspecialchars($lesson['ten_giang_vien']); ?>
            </p>
            <p class="mb-1">
                <strong>Loại nội dung:</strong>
                <?php echo htmlspecialchars(textLoaiNoiDung($lesson['loai_noi_dung'])); ?>
            </p>
        </div>

        <!-- Nội dung bài giảng -->
        <div class="card shadow-sm">
            <div class="card-body">
                <?php
                $type = $lesson['loai_noi_dung'];
                $path = trim((string)$lesson['duong_dan_noi_dung']);

                // ==========================
                // 1. VIDEO (mp4 + YouTube)
                // ==========================
                if ($type === 'video') {
                    if ($path === '') {
                        echo '<em>Chưa cấu hình đường dẫn video cho bài giảng này.</em>';
                    } else {
                        $lower = strtolower($path);

                        // Nếu là YouTube → chuyển sang dạng embed
                        if (strpos($lower, 'youtube.com') !== false || strpos($lower, 'youtu.be') !== false) {

                            $videoId = '';

                            // link kiểu https://youtu.be/xxxxx
                            if (preg_match('~youtu\.be/([^?&/]+)~', $path, $m)) {
                                $videoId = $m[1];
                            }
                            // link kiểu https://www.youtube.com/watch?v=xxxxx
                            elseif (preg_match('~v=([^?&]+)~', $path, $m)) {
                                $videoId = $m[1];
                            }

                            if ($videoId !== '') {
                                $embedUrl = 'https://www.youtube.com/embed/' . $videoId;
                            } else {
                                // fallback: nếu không bắt được id thì vẫn dùng trực tiếp
                                $embedUrl = $path;
                            }

                            echo '<div class="ratio ratio-16x9 mb-3">';
                            echo '<iframe src="' . htmlspecialchars($embedUrl) . '" 
                                         title="Video bài giảng" 
                                         frameborder="0"
                                         allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                         allowfullscreen></iframe>';
                            echo '</div>';
                        }
                        // Nếu là file mp4 / webm / ogg → dùng <video>
                        elseif (preg_match('/\.(mp4|webm|ogg)$/i', $lower)) {
                            echo '<video controls style="width:100%;max-height:600px;" class="mb-3">';
                            echo '<source src="' . htmlspecialchars($path) . '">';
                            echo 'Trình duyệt của bạn không hỗ trợ video.';
                            echo '</video>';
                        }
                        // Còn lại vẫn thử dùng <video> (ví dụ link .m3u8... thì có thể không chạy)
                        else {
                            echo '<video controls style="width:100%;max-height:600px;" class="mb-3">';
                            echo '<source src="' . htmlspecialchars($path) . '">';
                            echo 'Trình duyệt của bạn không hỗ trợ video hoặc định dạng này.';
                            echo '</video>';
                        }

                        echo '<p><a href="' . htmlspecialchars($path) . '" target="_blank" rel="noopener">';
                        echo '<i class="bi bi-box-arrow-up-right"></i> Mở video trong tab mới</a></p>';
                    }

                // ==========================
                // 2. PDF
                // ==========================
                } elseif ($type === 'pdf') {
                    if ($path === '') {
                        echo '<em>Chưa cấu hình file PDF cho bài giảng này.</em>';
                    } else {
                        echo '<div class="mb-3" style="height:600px;">';
                        echo '<iframe src="' . htmlspecialchars($path) . '" '
                           . 'style="width:100%;height:100%;" frameborder="0"></iframe>';
                        echo '</div>';
                        echo '<p><a href="' . htmlspecialchars($path) . '" target="_blank" rel="noopener">';
                        echo '<i class="bi bi-download"></i> Tải tài liệu PDF</a></p>';
                    }

                // ==========================
                // 3. HTML
                // ==========================
                } elseif ($type === 'html') {
                    if (!empty($lesson['noi_dung_html'])) {
                        echo $lesson['noi_dung_html'];
                    } else {
                        echo '<em>Chưa có nội dung HTML cho bài giảng này.</em>';
                    }

                // ==========================
                // 4. TỆP
                // ==========================
                } elseif ($type === 'tep') {
                    if ($path === '') {
                        echo '<em>Chưa cấu hình tệp tài liệu cho bài giảng này.</em>';
                    } else {
                        echo '<p>Tải tài liệu đính kèm:</p>';
                        echo '<p><a href="' . htmlspecialchars($path) . '" target="_blank" rel="noopener" class="btn btn-outline-danger">';
                        echo '<i class="bi bi-file-earmark-arrow-down"></i> Tải tệp</a></p>';
                    }

                // ==========================
                // 5. LINK NGOÀI
                // ==========================
                } elseif ($type === 'link') {
                    if ($path === '') {
                        echo '<em>Chưa cấu hình liên kết cho bài giảng này.</em>';
                    } else {
                        echo '<p>Nhấp vào liên kết dưới đây để mở nội dung bài giảng:</p>';
                        echo '<p><a href="' . htmlspecialchars($path) . '" target="_blank" rel="noopener" class="btn btn-outline-primary">';
                        echo '<i class="bi bi-box-arrow-up-right"></i> Mở liên kết</a></p>';
                    }

                } else {
                    echo '<em>Loại nội dung chưa được hỗ trợ.</em>';
                }
                ?>
            </div>
        </div>

        <div class="mt-3">
            <a href="student_course_learn.php?id=<?php echo (int)$course_id; ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Quay lại danh sách bài giảng
            </a>
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
