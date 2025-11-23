<?php
session_start();
require_once 'config.php';

// Chỉ cho phép học viên
if (!isset($_SESSION['user_id']) || ($_SESSION['vai_tro'] ?? '') !== 'hoc_vien') {
    header("Location: dang_nhap.php");
    exit;
}

$user_id        = $_SESSION['user_id'];
$ho_ten_session = $_SESSION['ho_ten'] ?? '';

$error   = '';
$success = '';
$courses = [];
$topics  = [];

// =========================
// Lấy danh sách khóa học mà sinh viên đã đăng ký
// =========================
try {
    $pdo = Database::pdo();

    $sql = "
        SELECT DISTINCT
            kh.id,
            kh.ten_khoa_hoc
        FROM dang_ky_khoa_hoc dk
        JOIN khoa_hoc kh ON dk.id_khoa_hoc = kh.id
        WHERE dk.id_hoc_vien = :id_hoc_vien
          AND dk.trang_thai <> 'huy'
        ORDER BY kh.ten_khoa_hoc
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id_hoc_vien' => $user_id]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Lỗi khi lấy khóa học: " . $e->getMessage();
}

// Xác định khóa học đang chọn để lọc
$selected_course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

// Bảo vệ: nếu chọn course_id không thuộc khóa của mình thì reset về 0
if ($selected_course_id > 0 && !empty($courses)) {
    $valid_ids = array_column($courses, 'id');
    if (!in_array($selected_course_id, $valid_ids, true)) {
        $selected_course_id = 0;
    }
}

// =========================
// Xử lý tạo chủ đề mới
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_topic'])) {

    $course_id  = (int)($_POST['course_id'] ?? 0);
    $tieu_de    = trim($_POST['tieu_de'] ?? '');
    $noi_dung   = trim($_POST['noi_dung'] ?? '');

    // Kiểm tra quyền: course_id phải nằm trong danh sách khóa học của sinh viên
    $allowed_course_ids = array_column($courses, 'id');

    if ($course_id <= 0 || !in_array($course_id, $allowed_course_ids, true)) {
        $error = "Khóa học không hợp lệ.";
    } elseif ($tieu_de === '') {
        $error = "Vui lòng nhập tiêu đề chủ đề.";
    } elseif ($noi_dung === '') {
        $error = "Vui lòng nhập nội dung chủ đề.";
    }

    if ($error === '') {
        try {
            $pdo->beginTransaction();

            // Thêm vào bảng chủ đề
            $sql = "
                INSERT INTO chu_de_dien_dan (id_khoa_hoc, id_nguoi_dung, tieu_de, noi_dung, bi_khoa, ngay_tao, ngay_cap_nhat)
                VALUES (:id_khoa_hoc, :id_nguoi_dung, :tieu_de, :noi_dung, 0, NOW(), NOW())
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':id_khoa_hoc' => $course_id,
                ':id_nguoi_dung' => $user_id,
                ':tieu_de' => $tieu_de,
                ':noi_dung' => $noi_dung
            ]);

            $new_topic_id = $pdo->lastInsertId();

            $pdo->commit();

            $success = "Tạo chủ đề mới thành công!";
            // Sau khi tạo xong, chuyển sang xem chủ đề hoặc chỉ refresh trang
            header("Location: student_forum.php?course_id=" . $course_id . "&msg=created");
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Lỗi khi tạo chủ đề: " . $e->getMessage();
        }
    }
}

// Lấy thông báo từ query string (sau khi redirect)
if (isset($_GET['msg']) && $_GET['msg'] === 'created' && $success === '') {
    $success = "Tạo chủ đề mới thành công!";
}

// =========================
// Lấy danh sách chủ đề trong các khóa học của sinh viên
// =========================
if ($error === '') {
    try {
        $pdo = Database::pdo();

        $params = [':id_hoc_vien' => $user_id];

        $sql = "
            SELECT 
                cd.id,
                cd.tieu_de,
                cd.noi_dung,
                cd.ngay_tao,
                cd.ngay_cap_nhat,
                cd.bi_khoa,
                kh.ten_khoa_hoc,
                kh.id AS id_khoa_hoc,
                nd.ho_ten AS ten_nguoi_tao,
                COUNT(bd.id) AS so_phan_hoi,
                MAX(bd.ngay_tao) AS ngay_tra_loi_cuoi
            FROM chu_de_dien_dan cd
            JOIN khoa_hoc kh ON cd.id_khoa_hoc = kh.id
            JOIN nguoi_dung nd ON cd.id_nguoi_dung = nd.id
            WHERE cd.id_khoa_hoc IN (
                SELECT dk2.id_khoa_hoc
                FROM dang_ky_khoa_hoc dk2
                WHERE dk2.id_hoc_vien = :id_hoc_vien
                  AND dk2.trang_thai <> 'huy'
            )
        ";

        if ($selected_course_id > 0) {
            $sql .= " AND cd.id_khoa_hoc = :selected_course_id ";
            $params[':selected_course_id'] = $selected_course_id;
        }

        $sql .= "
            LEFT JOIN bai_dang_dien_dan bd ON bd.id_chu_de = cd.id
            GROUP BY cd.id, cd.tieu_de, cd.noi_dung, cd.ngay_tao, cd.ngay_cap_nhat,
                     cd.bi_khoa, kh.ten_khoa_hoc, kh.id, nd.ho_ten
            ORDER BY cd.ngay_cap_nhat DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $topics = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $error = "Lỗi khi lấy danh sách chủ đề: " . $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Diễn đàn khóa học - Học viên</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Icons -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<!-- Top bar -->
<div class="top-bar">
    <div class="container d-flex justify-content-between">
        <div>
            <a href="student_dashboard.php" class="text-white">
                <i class="bi bi-mortarboard-fill"></i> Khu vực học viên
            </a>
            <span> / Diễn đàn khóa học</span>
        </div>
        <div>
            Xin chào, <strong><?php echo htmlspecialchars($ho_ten_session); ?></strong> |
            <a href="dang_xuat.php" class="text-white">Đăng xuất</a>
        </div>
    </div>
</div>

<header class="main-header">
    <div class="container d-flex align-items-center py-2">
        <img src="image/ptit.png" style="height:55px;" class="me-3" alt="PTIT">
        <div>
            <h4 class="logo-text mb-0">HỆ THỐNG E-LEARNING PTIT</h4>
            <span>Diễn đàn khóa học</span>
        </div>
    </div>
</header>

<div class="container mt-4 mb-5">

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 style="color:#b30000;">Diễn đàn các khóa học của bạn</h4>
    </div>

    <?php if (empty($courses)): ?>
        <div class="alert alert-info">
            Bạn chưa đăng ký khóa học nào nên hiện chưa có diễn đàn.<br>
            Hãy vào <strong>"Đăng ký khóa học mới"</strong> để tham gia khóa học trước.
        </div>
    <?php else: ?>

        <!-- Bộ lọc theo khóa học -->
        <form class="row g-2 mb-4" method="get">
            <div class="col-md-4">
                <label class="form-label">Chọn khóa học:</label>
                <select name="course_id" class="form-select" onchange="this.form.submit()">
                    <option value="0">-- Tất cả khóa học của tôi --</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?php echo (int)$c['id']; ?>"
                            <?php if ($selected_course_id == $c['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($c['ten_khoa_hoc']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <!-- Form tạo chủ đề mới -->
        <div class="card mb-4">
            <div class="card-header">
                <strong>Tạo chủ đề mới</strong>
            </div>
            <div class="card-body">
                <form method="post">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Thuộc khóa học</label>
                            <select name="course_id" class="form-select" required>
                                <?php foreach ($courses as $c): ?>
                                    <option value="<?php echo (int)$c['id']; ?>"
                                        <?php
                                        // Nếu đã chọn filter theo khóa nào thì mặc định form cũng chọn khóa đó
                                        if ($selected_course_id == $c['id']) echo 'selected';
                                        ?>>
                                        <?php echo htmlspecialchars($c['ten_khoa_hoc']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Tiêu đề chủ đề</label>
                            <input type="text" name="tieu_de" class="form-control" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Nội dung</label>
                        <textarea name="noi_dung" class="form-control" rows="4" required></textarea>
                    </div>

                    <button type="submit" name="create_topic" class="btn btn-danger">
                        <i class="bi bi-chat-dots"></i> Đăng chủ đề
                    </button>
                </form>
            </div>
        </div>

        <!-- Danh sách chủ đề -->
        <div class="card">
            <div class="card-header">
                <strong>Danh sách chủ đề</strong>
            </div>
            <div class="card-body p-0">
                <?php if (empty($topics)): ?>
                    <div class="p-3">
                        <em>Chưa có chủ đề nào trong các khóa học của bạn. Hãy là người tạo chủ đề đầu tiên!</em>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Chủ đề</th>
                                    <th>Khóa học</th>
                                    <th>Người tạo</th>
                                    <th>Phản hồi</th>
                                    <th>Cập nhật cuối</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($topics as $t): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($t['tieu_de']); ?></strong>
                                        <?php if (!empty($t['noi_dung'])): ?>
                                            <div class="small text-muted">
                                                <?php
                                                echo htmlspecialchars(mb_strimwidth($t['noi_dung'], 0, 90, '...', 'UTF-8'));
                                                ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ((int)$t['bi_khoa'] === 1): ?>
                                            <span class="badge bg-secondary mt-1">Chủ đề đã khóa</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($t['ten_khoa_hoc']); ?></td>
                                    <td><?php echo htmlspecialchars($t['ten_nguoi_tao']); ?></td>
                                    <td><?php echo (int)$t['so_phan_hoi']; ?></td>
                                    <td>
                                        <?php
                                        $time = $t['ngay_tra_loi_cuoi'] ?: $t['ngay_cap_nhat'];
                                        echo $time ? date('d/m/Y H:i', strtotime($time)) : '—';
                                        ?>
                                    </td>
                                    <td class="text-center">
                                        <a href="student_forum_topic.php?id=<?php echo (int)$t['id']; ?>"
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-arrow-right-circle"></i> Xem
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

</div>

<footer class="text-center py-3">
    © <?php echo date('Y'); ?> Hệ thống E-learning PTIT
</footer>

</body>
</html>
