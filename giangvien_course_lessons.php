<?php
session_start();
require_once 'config.php';

// Chỉ cho phép Giảng viên
if (!isset($_SESSION['user_id']) || ($_SESSION['vai_tro'] ?? '') !== 'giang_vien') {
    header("Location: dang_nhap.php");
    exit;
}

$giang_vien_id     = $_SESSION['user_id'];
$ho_ten_giang_vien = $_SESSION['ho_ten'] ?? 'Giảng viên';

// Lấy id khóa học
$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($course_id <= 0) {
    header("Location: giangvien_courses.php");
    exit;
}

$errors  = [];
$success = '';
$course  = null;
$lessons = [];

// Biến cho form bài giảng
$editing      = false;
$lesson_id    = 0;
$tieu_de      = '';
$loai_noi_dung= 'video';
$duong_dan    = '';
$noi_dung_html= '';
$thu_tu       = 1;
$hien_thi     = 1;

try {
    $pdo = Database::pdo();

    // 1. Kiểm tra khóa học có thuộc giảng viên không
    $sql = "
        SELECT kh.*
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
        $errors[] = "Bạn không có quyền truy cập khóa học này hoặc khóa học không tồn tại.";
    }

    // Nếu không lỗi khóa học thì xử lý tiếp
    if (empty($errors)) {

        // 2. Xử lý xóa bài giảng (nếu có ?delete=...)
        if (isset($_GET['delete'])) {
            $id_delete = (int)$_GET['delete'];
            if ($id_delete > 0) {
                $sql = "DELETE FROM bai_giang WHERE id = :id AND id_khoa_hoc = :id_kh";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':id'   => $id_delete,
                    ':id_kh'=> $course_id
                ]);
                // Quay lại chính trang này với thông báo
                header("Location: giangvien_course_lessons.php?id=" . $course_id . "&msg=deleted");
                exit;
            }
        }

        // 3. Nếu có tham số edit => lấy dữ liệu bài giảng để sửa
        if (isset($_GET['edit'])) {
            $lesson_id = (int)$_GET['edit'];
            if ($lesson_id > 0) {
                $sql = "
                    SELECT *
                    FROM bai_giang
                    WHERE id = :id
                      AND id_khoa_hoc = :id_kh
                    LIMIT 1
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':id'   => $lesson_id,
                    ':id_kh'=> $course_id
                ]);
                $lesson = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($lesson) {
                    $editing       = true;
                    $tieu_de       = $lesson['tieu_de'];
                    $loai_noi_dung = $lesson['loai_noi_dung'];
                    $duong_dan     = $lesson['duong_dan_noi_dung'];
                    $noi_dung_html = $lesson['noi_dung_html'];
                    $thu_tu        = (int)$lesson['thu_tu_hien_thi'];
                    $hien_thi      = (int)$lesson['hien_thi'];
                }
            }
        }

        // 4. Xử lý submit form thêm / sửa bài giảng
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_lesson'])) {
            $lesson_id     = (int)($_POST['lesson_id'] ?? 0);
            $tieu_de       = trim($_POST['tieu_de'] ?? '');
            $loai_noi_dung = $_POST['loai_noi_dung'] ?? 'video';
            $duong_dan     = trim($_POST['duong_dan_noi_dung'] ?? '');
            $noi_dung_html = trim($_POST['noi_dung_html'] ?? '');
            $thu_tu        = (int)($_POST['thu_tu_hien_thi'] ?? 1);
            $hien_thi      = isset($_POST['hien_thi']) ? 1 : 0;

            // Validate
            if ($tieu_de === '') {
                $errors[] = "Vui lòng nhập tiêu đề bài giảng.";
            }
            if ($thu_tu <= 0) {
                $thu_tu = 1;
            }
            // Nếu là video / link / pdf / file, khuyến khích có đường dẫn
            if (in_array($loai_noi_dung, ['video','pdf','tep','link']) && $duong_dan === '') {
                $errors[] = "Vui lòng nhập đường dẫn nội dung (video/tài liệu/liên kết).";
            }

            if (empty($errors)) {
                if ($lesson_id > 0) {
                    // UPDATE bài giảng
                    $sql = "
                        UPDATE bai_giang
                        SET tieu_de = :tieu_de,
                            loai_noi_dung = :loai,
                            duong_dan_noi_dung = :duong_dan,
                            noi_dung_html = :noi_dung,
                            thu_tu_hien_thi = :thu_tu,
                            hien_thi = :hien_thi,
                            ngay_cap_nhat = NOW()
                        WHERE id = :id
                          AND id_khoa_hoc = :id_kh
                    ";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ':tieu_de'   => $tieu_de,
                        ':loai'      => $loai_noi_dung,
                        ':duong_dan' => $duong_dan !== '' ? $duong_dan : null,
                        ':noi_dung'  => $noi_dung_html !== '' ? $noi_dung_html : null,
                        ':thu_tu'    => $thu_tu,
                        ':hien_thi'  => $hien_thi,
                        ':id'        => $lesson_id,
                        ':id_kh'     => $course_id
                    ]);

                    header("Location: giangvien_course_lessons.php?id={$course_id}&msg=updated");
                    exit;
                } else {
                    // INSERT bài giảng mới
                    $sql = "
                        INSERT INTO bai_giang
                            (id_khoa_hoc, tieu_de, loai_noi_dung, duong_dan_noi_dung,
                             noi_dung_html, thu_tu_hien_thi, hien_thi, ngay_tao, ngay_cap_nhat)
                        VALUES
                            (:id_kh, :tieu_de, :loai, :duong_dan,
                             :noi_dung, :thu_tu, :hien_thi, NOW(), NOW())
                    ";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ':id_kh'     => $course_id,
                        ':tieu_de'   => $tieu_de,
                        ':loai'      => $loai_noi_dung,
                        ':duong_dan' => $duong_dan !== '' ? $duong_dan : null,
                        ':noi_dung'  => $noi_dung_html !== '' ? $noi_dung_html : null,
                        ':thu_tu'    => $thu_tu,
                        ':hien_thi'  => $hien_thi
                    ]);

                    header("Location: giangvien_course_lessons.php?id={$course_id}&msg=created");
                    exit;
                }
            }
        }

        // 5. Lấy danh sách bài giảng để hiển thị
        $sql = "
            SELECT *
            FROM bai_giang
            WHERE id_khoa_hoc = :id_kh
            ORDER BY thu_tu_hien_thi ASC, ngay_tao ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id_kh' => $course_id]);
        $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    $errors[] = "Lỗi hệ thống: " . $e->getMessage();
}

// Thông báo theo msg
if (isset($_GET['msg']) && empty($errors)) {
    switch ($_GET['msg']) {
        case 'created':
            $success = "Đã thêm bài giảng mới.";
            break;
        case 'updated':
            $success = "Đã cập nhật bài giảng.";
            break;
        case 'deleted':
            $success = "Đã xóa bài giảng.";
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý bài giảng - Giảng viên</title>

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
            <span> / Quản lý bài giảng</span>
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
            <div style="font-size: 0.9rem; color:#555;">Quản lý bài giảng khóa học</div>
        </div>
    </div>
</header>

<div class="container mt-4 mb-5">

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $er): ?>
                    <li><?php echo htmlspecialchars($er); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <?php if ($course): ?>
        <!-- Tiêu đề khóa học -->
        <div class="mb-3 d-flex justify-content-between align-items-center">
            <div>
                <h3 style="color:#b30000;">
                    Bài giảng - <?php echo htmlspecialchars($course['ten_khoa_hoc']); ?>
                </h3>
                <div class="text-muted">
                    Mã khóa: #<?php echo (int)$course['id']; ?> |
                    Danh mục: <?php echo htmlspecialchars($course['danh_muc'] ?? ''); ?>
                </div>
            </div>
            <div>
                <a href="giangvien_course_detail.php?id=<?php echo (int)$course['id']; ?>"
                   class="btn btn-outline-secondary btn-sm">
                    &laquo; Quay lại chi tiết khóa học
                </a>
            </div>
        </div>

        <!-- Form thêm / sửa bài giảng -->
        <div class="card mb-4">
            <div class="card-header">
                <strong><?php echo $lesson_id > 0 ? "Sửa bài giảng" : "Thêm bài giảng mới"; ?></strong>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="lesson_id" value="<?php echo (int)$lesson_id; ?>">

                    <div class="mb-3">
                        <label class="form-label">Tiêu đề bài giảng <span class="text-danger">*</span></label>
                        <input type="text" name="tieu_de" class="form-control"
                               value="<?php echo htmlspecialchars($tieu_de); ?>" required>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Loại nội dung</label>
                            <select name="loai_noi_dung" class="form-select">
                                <option value="video" <?php if ($loai_noi_dung === 'video') echo 'selected'; ?>>
                                    Video (YouTube / file mp4...)
                                </option>
                                <option value="pdf" <?php if ($loai_noi_dung === 'pdf') echo 'selected'; ?>>
                                    Tài liệu PDF
                                </option>
                                <option value="html" <?php if ($loai_noi_dung === 'html') echo 'selected'; ?>>
                                    Nội dung HTML / mô tả
                                </option>
                                <option value="tep" <?php if ($loai_noi_dung === 'tep') echo 'selected'; ?>>
                                    Tệp đính kèm
                                </option>
                                <option value="link" <?php if ($loai_noi_dung === 'link') echo 'selected'; ?>>
                                    Liên kết ngoài
                                </option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Thứ tự hiển thị</label>
                            <input type="number" name="thu_tu_hien_thi" class="form-control"
                                   value="<?php echo (int)$thu_tu; ?>" min="1">
                        </div>
                        <div class="col-md-4 mb-3 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="hien_thi" id="hien_thi"
                                       <?php if ($hien_thi == 1) echo 'checked'; ?>>
                                <label class="form-check-label" for="hien_thi">
                                    Hiển thị cho học viên
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            Đường dẫn nội dung (URL video, link tài liệu, đường dẫn file...) <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="duong_dan_noi_dung" class="form-control"
                               placeholder="Ví dụ: https://www.youtube.com/watch?v=..."
                               value="<?php echo htmlspecialchars($duong_dan); ?>">
                        <div class="form-text">
                            Dùng cho loại: Video, PDF, Tệp, Liên kết. Có thể để trống nếu bạn chỉ dùng nội dung HTML bên dưới.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Nội dung HTML / Mô tả chi tiết</label>
                        <textarea name="noi_dung_html" class="form-control" rows="5"
                                  placeholder="Có thể nhập mô tả bài học, nội dung tóm tắt, ghi chú..."><?php 
                            echo htmlspecialchars($noi_dung_html); ?></textarea>
                        <div class="form-text">
                            Dùng cho loại <strong>HTML</strong> hoặc để mô tả thêm nội dung cho bài giảng.
                        </div>
                    </div>

                    <button type="submit" name="save_lesson" class="btn btn-danger">
                        <i class="bi bi-save"></i>
                        <?php echo $lesson_id > 0 ? " Cập nhật bài giảng" : " Lưu bài giảng"; ?>
                    </button>

                    <?php if ($lesson_id > 0): ?>
                        <a href="giangvien_course_lessons.php?id=<?php echo (int)$course_id; ?>"
                           class="btn btn-outline-secondary ms-2">
                            Hủy chỉnh sửa
                        </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Danh sách bài giảng -->
        <div class="card">
            <div class="card-header">
                <strong>Danh sách bài giảng</strong>
            </div>
            <div class="card-body p-0">
                <?php if (empty($lessons)): ?>
                    <div class="p-3">
                        <em>Chưa có bài giảng nào cho khóa học này. Hãy thêm bài giảng đầu tiên ở form bên trên.</em>
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
                                    <th>Hành động</th>
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
                                            'html'  => 'Nội dung HTML',
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
                                        echo $bg['ngay_tao']
                                            ? date('d/m/Y H:i', strtotime($bg['ngay_tao']))
                                            : '—';
                                        ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="giangvien_course_lessons.php?id=<?php echo (int)$course_id; ?>&edit=<?php echo (int)$bg['id']; ?>"
                                               class="btn btn-outline-primary">
                                                <i class="bi bi-pencil-square"></i> Sửa
                                            </a>
                                            <a href="giangvien_course_lessons.php?id=<?php echo (int)$course_id; ?>&delete=<?php echo (int)$bg['id']; ?>"
                                               class="btn btn-outline-danger"
                                               onclick="return confirm('Bạn có chắc chắn muốn xóa bài giảng này?');">
                                                <i class="bi bi-trash3"></i> Xóa
                                            </a>
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

    <?php endif; ?>

</div>

<footer class="text-center py-3">
    © <?php echo date('Y'); ?> Hệ thống E-learning PTIT - Khu vực giảng viên
</footer>

</body>
</html>
