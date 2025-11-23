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

$errors  = [];
$success = '';

// nhận tham số lọc khóa học (không bắt buộc)
$course_filter = isset($_GET['id_khoa_hoc']) ? (int)$_GET['id_khoa_hoc'] : 0;

// biến form
$exam_id          = 0;
$selected_course  = 0;
$tieu_de          = '';
$mo_ta            = '';
$thoi_gian_bd_raw = '';
$thoi_gian_kt_raw = '';
$thoi_luong_phut  = 60;
$gioi_han_so_lan  = 1;
$tron_cau_hoi     = 1;
$trang_thai       = 'nhap';

$courses = [];
$exams   = [];

try {
    $pdo = Database::pdo();

    // 1. Lấy danh sách tất cả khóa học của giảng viên
    $sql = "
        SELECT id, ten_khoa_hoc, danh_muc
        FROM khoa_hoc
        WHERE id_giang_vien = :id_gv
        ORDER BY ngay_tao DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id_gv' => $giang_vien_id]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Nếu có filter nhưng không thuộc danh sách khóa của GV thì bỏ filter
    $course_ids = array_column($courses, 'id');
    if ($course_filter > 0 && !in_array($course_filter, $course_ids, true)) {
        $course_filter = 0;
    }

    // ---------------------
    // 2. XÓA BÀI THI
    // ---------------------
    if (isset($_GET['delete'])) {
        $id_delete = (int)$_GET['delete'];
        if ($id_delete > 0) {
            // chỉ xóa bài thi thuộc khóa của chính giảng viên
            $sql = "
                DELETE bt
                FROM bai_thi bt
                JOIN khoa_hoc kh ON bt.id_khoa_hoc = kh.id
                WHERE bt.id = :id_bt
                  AND kh.id_giang_vien = :id_gv
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':id_bt' => $id_delete,
                ':id_gv' => $giang_vien_id
            ]);

            $qs = $course_filter > 0 ? '&id_khoa_hoc='.$course_filter : '';
            header("Location: giangvien_exams.php?msg=deleted{$qs}");
            exit;
        }
    }

    // ---------------------
    // 3. NẾU EDIT → NẠP FORM
    // ---------------------
    if (isset($_GET['edit'])) {
        $exam_id = (int)$_GET['edit'];
        if ($exam_id > 0) {
            $sql = "
                SELECT bt.*
                FROM bai_thi bt
                JOIN khoa_hoc kh ON bt.id_khoa_hoc = kh.id
                WHERE bt.id = :id_bt
                  AND kh.id_giang_vien = :id_gv
                LIMIT 1
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':id_bt' => $exam_id,
                ':id_gv' => $giang_vien_id
            ]);
            $exam = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($exam) {
                $selected_course  = (int)$exam['id_khoa_hoc'];
                $tieu_de          = $exam['tieu_de'];
                $mo_ta            = $exam['mo_ta'];
                $thoi_luong_phut  = (int)$exam['thoi_luong_phut'];
                $gioi_han_so_lan  = (int)$exam['gioi_han_so_lan'];
                $tron_cau_hoi     = (int)$exam['tron_cau_hoi'];
                $trang_thai       = $exam['trang_thai'];

                $thoi_gian_bd_raw = $exam['thoi_gian_bat_dau']
                    ? date('Y-m-d\TH:i', strtotime($exam['thoi_gian_bat_dau']))
                    : '';
                $thoi_gian_kt_raw = $exam['thoi_gian_ket_thuc']
                    ? date('Y-m-d\TH:i', strtotime($exam['thoi_gian_ket_thuc']))
                    : '';

                // auto set filter = khóa đang edit cho dễ nhìn
                if ($course_filter === 0) {
                    $course_filter = $selected_course;
                }
            }
        }
    }

    // ---------------------
    // 4. SUBMIT FORM (THÊM / SỬA)
    // ---------------------
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_exam'])) {

        $exam_id          = (int)($_POST['exam_id'] ?? 0);
        $selected_course  = (int)($_POST['id_khoa_hoc'] ?? 0);
        $tieu_de          = trim($_POST['tieu_de'] ?? '');
        $mo_ta            = trim($_POST['mo_ta'] ?? '');
        $thoi_gian_bd_raw = trim($_POST['thoi_gian_bat_dau'] ?? '');
        $thoi_gian_kt_raw = trim($_POST['thoi_gian_ket_thuc'] ?? '');
        $thoi_luong_phut  = (int)($_POST['thoi_luong_phut'] ?? 60);
        $gioi_han_so_lan  = (int)($_POST['gioi_han_so_lan'] ?? 1);
        $tron_cau_hoi     = isset($_POST['tron_cau_hoi']) ? 1 : 0;
        $trang_thai       = $_POST['trang_thai'] ?? 'nhap';

        if ($tieu_de === '') {
            $errors[] = "Vui lòng nhập tiêu đề bài thi.";
        }
        if ($selected_course <= 0 || !in_array($selected_course, $course_ids, true)) {
            $errors[] = "Vui lòng chọn khóa học hợp lệ.";
        }
        if ($thoi_luong_phut <= 0) {
            $errors[] = "Thời lượng phải lớn hơn 0 phút.";
        }
        if ($gioi_han_so_lan <= 0) {
            $gioi_han_so_lan = 1;
        }

        // parse datetime-local
        $thoi_gian_bd = null;
        $thoi_gian_kt = null;
        if ($thoi_gian_bd_raw !== '') {
            $ts = strtotime($thoi_gian_bd_raw);
            if ($ts !== false) $thoi_gian_bd = date('Y-m-d H:i:s', $ts);
        }
        if ($thoi_gian_kt_raw !== '') {
            $ts = strtotime($thoi_gian_kt_raw);
            if ($ts !== false) $thoi_gian_kt = date('Y-m-d H:i:s', $ts);
        }

        if (empty($errors)) {
            if ($exam_id > 0) {
                // UPDATE (chỉ update bài thi thuộc khóa của GV)
                $sql = "
                    UPDATE bai_thi
                    SET id_khoa_hoc = :id_kh,
                        tieu_de = :tieu_de,
                        mo_ta = :mo_ta,
                        thoi_gian_bat_dau = :tgbd,
                        thoi_gian_ket_thuc = :tgkt,
                        thoi_luong_phut = :tl,
                        gioi_han_so_lan = :sl,
                        tron_cau_hoi = :tron,
                        trang_thai = :tt,
                        ngay_cap_nhat = NOW()
                    WHERE id = :id_bt
                      AND id_khoa_hoc IN (
                          SELECT id FROM khoa_hoc WHERE id_giang_vien = :id_gv
                      )
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':id_kh'  => $selected_course,
                    ':tieu_de'=> $tieu_de,
                    ':mo_ta'  => $mo_ta !== '' ? $mo_ta : null,
                    ':tgbd'   => $thoi_gian_bd,
                    ':tgkt'   => $thoi_gian_kt,
                    ':tl'     => $thoi_luong_phut,
                    ':sl'     => $gioi_han_so_lan,
                    ':tron'   => $tron_cau_hoi,
                    ':tt'     => $trang_thai,
                    ':id_bt'  => $exam_id,
                    ':id_gv'  => $giang_vien_id
                ]);

                $qs = $selected_course > 0 ? '&id_khoa_hoc='.$selected_course : '';
                header("Location: giangvien_exams.php?msg=updated{$qs}");
                exit;

            } else {
                // INSERT
                $sql = "
                    INSERT INTO bai_thi
                        (id_khoa_hoc, tieu_de, mo_ta, thoi_gian_bat_dau, thoi_gian_ket_thuc,
                         thoi_luong_phut, gioi_han_so_lan, tron_cau_hoi, trang_thai,
                         ngay_tao, ngay_cap_nhat)
                    VALUES
                        (:id_kh, :tieu_de, :mo_ta, :tgbd, :tgkt,
                         :tl, :sl, :tron, :tt,
                         NOW(), NOW())
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':id_kh'  => $selected_course,
                    ':tieu_de'=> $tieu_de,
                    ':mo_ta'  => $mo_ta !== '' ? $mo_ta : null,
                    ':tgbd'   => $thoi_gian_bd,
                    ':tgkt'   => $thoi_gian_kt,
                    ':tl'     => $thoi_luong_phut,
                    ':sl'     => $gioi_han_so_lan,
                    ':tron'   => $tron_cau_hoi,
                    ':tt'     => $trang_thai
                ]);

                $qs = $selected_course > 0 ? '&id_khoa_hoc='.$selected_course : '';
                header("Location: giangvien_exams.php?msg=created{$qs}");
                exit;
            }
        }
    }

    // ---------------------
    // 5. Lấy danh sách bài thi (toàn bộ hoặc theo khóa)
    // ---------------------
    $sql = "
        SELECT bt.*, kh.ten_khoa_hoc
        FROM bai_thi bt
        JOIN khoa_hoc kh ON bt.id_khoa_hoc = kh.id
        WHERE kh.id_giang_vien = :id_gv
    ";
    $params = [':id_gv' => $giang_vien_id];

    if ($course_filter > 0) {
        $sql .= " AND kh.id = :id_kh ";
        $params[':id_kh'] = $course_filter;
    }

    $sql .= " ORDER BY kh.ten_khoa_hoc ASC, bt.thoi_gian_bat_dau IS NULL, bt.thoi_gian_bat_dau ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $errors[] = "Lỗi hệ thống: " . $e->getMessage();
}

// Thông báo msg
if (isset($_GET['msg']) && empty($errors)) {
    switch ($_GET['msg']) {
        case 'created': $success = "Đã tạo bài thi mới."; break;
        case 'updated': $success = "Đã cập nhật bài thi."; break;
        case 'deleted': $success = "Đã xóa bài thi."; break;
    }
}

function textTrangThaiBaiThi($code) {
    $map = [
        'nhap'    => 'Nháp',
        'dang_mo' => 'Đang mở',
        'dong'    => 'Đã đóng',
    ];
    return $map[$code] ?? $code;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý bài thi - Giảng viên</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
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
            <span> / Quản lý bài thi</span>
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
            <div style="font-size: 0.9rem; color:#555;">Quản lý bài thi các khóa học</div>
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

    <!-- Bộ lọc theo khóa học -->
    <form class="row g-2 mb-3" method="get">
        <div class="col-auto">
            <label class="col-form-label">Lọc theo khóa học:</label>
        </div>
        <div class="col-auto">
            <select name="id_khoa_hoc" class="form-select form-select-sm">
                <option value="0">-- Tất cả khóa học --</option>
                <?php foreach ($courses as $c): ?>
                    <option value="<?php echo (int)$c['id']; ?>"
                        <?php if ($course_filter == $c['id']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($c['ten_khoa_hoc']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <button class="btn btn-danger btn-sm" type="submit">
                <i class="bi bi-funnel"></i> Lọc
            </button>
        </div>
    </form>

    <!-- Form thêm / sửa bài thi -->
    <div class="card mb-4">
        <div class="card-header">
            <strong><?php echo $exam_id > 0 ? "Sửa bài thi" : "Tạo bài thi mới"; ?></strong>
        </div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="exam_id" value="<?php echo (int)$exam_id; ?>">

                <div class="mb-3">
                    <label class="form-label">Thuộc khóa học <span class="text-danger">*</span></label>
                    <select name="id_khoa_hoc" class="form-select" required>
                        <option value="">-- Chọn khóa học --</option>
                        <?php foreach ($courses as $c): ?>
                            <option value="<?php echo (int)$c['id']; ?>"
                                <?php
                                $selected = $selected_course ?: $course_filter;
                                if ($selected == $c['id']) echo 'selected';
                                ?>>
                                <?php echo htmlspecialchars($c['ten_khoa_hoc']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Tiêu đề bài thi <span class="text-danger">*</span></label>
                    <input type="text" name="tieu_de" class="form-control"
                           value="<?php echo htmlspecialchars($tieu_de); ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Mô tả / Hướng dẫn</label>
                    <textarea name="mo_ta" class="form-control" rows="3"
                              placeholder="Ví dụ: Bài thi giữa kỳ, gồm 30 câu trắc nghiệm..."><?php
                        echo htmlspecialchars($mo_ta);
                    ?></textarea>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Thời gian bắt đầu</label>
                        <input type="datetime-local" name="thoi_gian_bat_dau" class="form-control"
                               value="<?php echo htmlspecialchars($thoi_gian_bd_raw); ?>">
                        <div class="form-text">Có thể để trống nếu chưa mở lịch.</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Thời gian kết thúc</label>
                        <input type="datetime-local" name="thoi_gian_ket_thuc" class="form-control"
                               value="<?php echo htmlspecialchars($thoi_gian_kt_raw); ?>">
                        <div class="form-text">Sau thời điểm này sinh viên không thể làm bài.</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Thời lượng (phút) <span class="text-danger">*</span></label>
                        <input type="number" name="thoi_luong_phut" min="1" class="form-control"
                               value="<?php echo (int)$thoi_luong_phut; ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Giới hạn số lần làm</label>
                        <input type="number" name="gioi_han_so_lan" min="1" class="form-control"
                               value="<?php echo (int)$gioi_han_so_lan; ?>">
                        <div class="form-text">1 = chỉ được thi một lần.</div>
                    </div>
                    <div class="col-md-4 mb-3 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="tron_cau_hoi" id="tron_cau_hoi"
                                   <?php if ($tron_cau_hoi) echo 'checked'; ?>>
                            <label class="form-check-label" for="tron_cau_hoi">
                                Trộn thứ tự câu hỏi
                            </label>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Trạng thái</label>
                        <select name="trang_thai" class="form-select">
                            <option value="nhap"    <?php if ($trang_thai === 'nhap')    echo 'selected'; ?>>Nháp</option>
                            <option value="dang_mo" <?php if ($trang_thai === 'dang_mo') echo 'selected'; ?>>Đang mở</option>
                            <option value="dong"    <?php if ($trang_thai === 'dong')    echo 'selected'; ?>>Đã đóng</option>
                        </select>
                    </div>
                </div>

                <button type="submit" name="save_exam" class="btn btn-danger">
                    <i class="bi bi-save"></i>
                    <?php echo $exam_id > 0 ? " Cập nhật bài thi" : " Lưu bài thi"; ?>
                </button>

                <?php if ($exam_id > 0): ?>
                    <a href="giangvien_exams.php<?php echo $course_filter ? '?id_khoa_hoc='.$course_filter : ''; ?>"
                       class="btn btn-outline-secondary ms-2">
                        Hủy chỉnh sửa
                    </a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Danh sách bài thi -->
    <div class="card">
        <div class="card-header">
            <strong>Danh sách bài thi của bạn</strong>
        </div>
        <div class="card-body p-0">
            <?php if (empty($exams)): ?>
                <div class="p-3">
                    <em>Chưa có bài thi nào.</em>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>Khóa học</th>
                            <th>Tiêu đề</th>
                            <th>Bắt đầu</th>
                            <th>Kết thúc</th>
                            <th>Thời lượng</th>
                            <th>Giới hạn</th>
                            <th>Trộn</th>
                            <th>Trạng thái</th>
                            <th>Hành động</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($exams as $bt): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($bt['ten_khoa_hoc']); ?></td>
                                <td><?php echo htmlspecialchars($bt['tieu_de']); ?></td>
                                <td>
                                    <?php
                                    echo $bt['thoi_gian_bat_dau']
                                        ? date('d/m/Y H:i', strtotime($bt['thoi_gian_bat_dau']))
                                        : '—';
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    echo $bt['thoi_gian_ket_thuc']
                                        ? date('d/m/Y H:i', strtotime($bt['thoi_gian_ket_thuc']))
                                        : '—';
                                    ?>
                                </td>
                                <td><?php echo (int)$bt['thoi_luong_phut']; ?>'</td>
                                <td><?php echo (int)$bt['gioi_han_so_lan']; ?></td>
                                <td><?php echo $bt['tron_cau_hoi'] ? 'Có' : 'Không'; ?></td>
                                <td>
                                    <?php
                                    $txt = textTrangThaiBaiThi($bt['trang_thai']);
                                    if ($bt['trang_thai'] === 'dang_mo') {
                                        echo '<span class="badge bg-success">'.htmlspecialchars($txt).'</span>';
                                    } elseif ($bt['trang_thai'] === 'nhap') {
                                        echo '<span class="badge bg-secondary">'.htmlspecialchars($txt).'</span>';
                                    } else {
                                        echo '<span class="badge bg-light text-dark">'.htmlspecialchars($txt).'</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <!-- Quản lý câu hỏi -->
                                        <a href="giangvien_exam_questions.php?id=<?php echo (int)$bt['id']; ?>"
                                           class="btn btn-outline-dark">
                                            <i class="bi bi-list-check"></i> Câu hỏi
                                        </a>
                                        <!-- Sửa -->
                                        <a href="giangvien_exams.php?id_khoa_hoc=<?php echo (int)$bt['id_khoa_hoc']; ?>&edit=<?php echo (int)$bt['id']; ?>"
                                           class="btn btn-outline-primary">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <!-- Xóa -->
                                        <a href="giangvien_exams.php?id_khoa_hoc=<?php echo (int)$bt['id_khoa_hoc']; ?>&delete=<?php echo (int)$bt['id']; ?>"
                                           class="btn btn-outline-danger"
                                           onclick="return confirm('Xóa bài thi này? Toàn bộ lần thi và bài làm của sinh viên cũng sẽ bị xóa.');">
                                            <i class="bi bi-trash3"></i>
                                        </a>
                                        <!-- Kết quả -->
                                        <a href="giangvien_exam_results.php?id=<?php echo (int)$bt['id']; ?>"
                                           class="btn btn-outline-success">
                                            <i class="bi bi-bar-chart-line"></i>
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

</div>

<footer class="text-center py-3">
    © <?php echo date('Y'); ?> Hệ thống E-learning PTIT - Khu vực giảng viên
</footer>

</body>
</html>
