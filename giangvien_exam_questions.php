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

// Lấy id bài thi từ GET: ưu tiên id_bai_thi, nếu không có thì dùng id
$exam_id = 0;
if (isset($_GET['id_bai_thi'])) {
    $exam_id = (int)$_GET['id_bai_thi'];
} elseif (isset($_GET['id'])) {
    $exam_id = (int)$_GET['id'];
}

if ($exam_id <= 0) {
    header("Location: giangvien_exams.php");
    exit;
}

$error    = '';
$success  = '';
$exam     = null;
$questions = [];

try {
    $pdo = Database::pdo();

    // 1. Lấy thông tin bài thi + khóa học + kiểm tra có thuộc giảng viên không
    $sql = "
        SELECT 
            bt.*,
            kh.ten_khoa_hoc,
            kh.id_giang_vien
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

    if (!$exam) {
        $error = "Bạn không có quyền truy cập bài thi này hoặc bài thi không tồn tại.";
    }

    // Xử lý POST (thêm / xóa câu hỏi)
    if ($exam && $_SERVER['REQUEST_METHOD'] === 'POST') {

        $action = $_POST['action'] ?? '';

        // ===== Thêm câu hỏi mới =====
        if ($action === 'add_question') {

            $noi_dung = trim($_POST['noi_dung_cau_hoi'] ?? '');
            $loai     = $_POST['loai_cau_hoi'] ?? 'mot_dap_an';
            $diem_so  = isset($_POST['diem_so']) ? (float)$_POST['diem_so'] : 1.0;
            $thu_tu   = isset($_POST['thu_tu']) ? (int)$_POST['thu_tu'] : 1;

            $option_texts      = $_POST['option_text'] ?? [];
            $correct_indexes   = $_POST['correct_index'] ?? []; // mảng các index được check

            if ($noi_dung === '') {
                $error = "Vui lòng nhập nội dung câu hỏi.";
            } else {
                // Nếu là dạng trắc nghiệm → kiểm tra đáp án
                $is_mcq = in_array($loai, ['mot_dap_an', 'nhieu_dap_an', 'dung_sai'], true);

                if ($is_mcq) {
                    // Lọc bỏ đáp án trống
                    $valid_options = [];
                    foreach ($option_texts as $idx => $txt) {
                        $txt = trim($txt);
                        if ($txt !== '') {
                            $valid_options[$idx] = $txt;
                        }
                    }

                    if (empty($valid_options)) {
                        $error = "Vui lòng nhập ít nhất một đáp án lựa chọn cho câu hỏi trắc nghiệm.";
                    } else {
                        // Đếm số đáp án đúng
                        $num_correct = 0;
                        foreach ($valid_options as $idx => $txt) {
                            if (in_array((string)$idx, $correct_indexes, true)) {
                                $num_correct++;
                            }
                        }

                        if ($num_correct === 0) {
                            $error = "Vui lòng chọn ít nhất một đáp án đúng.";
                        } elseif (($loai === 'mot_dap_an' || $loai === 'dung_sai') && $num_correct !== 1) {
                            $error = "Dạng 1 đáp án / đúng-sai chỉ được phép có đúng 1 đáp án đúng.";
                        }
                    }
                }

                // Nếu không có lỗi → ghi vào DB
                if ($error === '') {
                    try {
                        $pdo->beginTransaction();

                        // 1. Insert câu hỏi
                        $sql = "
                            INSERT INTO cau_hoi (id_bai_thi, noi_dung_cau_hoi, loai_cau_hoi, diem_so, thu_tu)
                            VALUES (:id_bt, :nd, :loai, :diem, :thu_tu)
                        ";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([
                            ':id_bt'  => $exam_id,
                            ':nd'     => $noi_dung,
                            ':loai'   => $loai,
                            ':diem'   => $diem_so,
                            ':thu_tu' => $thu_tu
                        ]);

                        $question_id = (int)$pdo->lastInsertId();

                        // 2. Nếu là trắc nghiệm → insert đáp án
                        if ($is_mcq) {
                            $order = 1;
                            $sqlOption = "
                                INSERT INTO lua_chon (id_cau_hoi, noi_dung_lua_chon, la_dap_an_dung, thu_tu)
                                VALUES (:id_ch, :nd, :dung, :thu_tu)
                            ";
                            $stmtOpt = $pdo->prepare($sqlOption);

                            foreach ($option_texts as $idx => $txt) {
                                $txt = trim($txt);
                                if ($txt === '') continue;

                                $is_correct = in_array((string)$idx, $correct_indexes, true) ? 1 : 0;

                                $stmtOpt->execute([
                                    ':id_ch'  => $question_id,
                                    ':nd'     => $txt,
                                    ':dung'   => $is_correct,
                                    ':thu_tu' => $order++
                                ]);
                            }
                        }

                        $pdo->commit();
                        $success = "Đã thêm câu hỏi mới thành công.";
                    } catch (PDOException $e) {
                        $pdo->rollBack();
                        $error = "Lỗi khi thêm câu hỏi: " . $e->getMessage();
                    }
                }
            }
        }

        // ===== Xóa câu hỏi =====
        if ($action === 'delete_question') {
            $question_id = isset($_POST['question_id']) ? (int)$_POST['question_id'] : 0;
            if ($question_id > 0) {
                try {
                    $sql = "DELETE FROM cau_hoi WHERE id = :id AND id_bai_thi = :id_bt";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ':id'   => $question_id,
                        ':id_bt'=> $exam_id
                    ]);
                    $success = "Đã xóa câu hỏi.";
                } catch (PDOException $e) {
                    $error = "Lỗi khi xóa câu hỏi: " . $e->getMessage();
                }
            }
        }
    }

    // 2. Lấy danh sách câu hỏi
    if ($exam) {
        $sql = "
            SELECT 
                ch.*,
                (
                    SELECT COUNT(*) FROM lua_chon lc WHERE lc.id_cau_hoi = ch.id
                ) AS so_dap_an
            FROM cau_hoi ch
            WHERE ch.id_bai_thi = :id_bt
            ORDER BY ch.thu_tu ASC, ch.id ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id_bt' => $exam_id]);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    $error = "Lỗi hệ thống: " . $e->getMessage();
}

// Hàm text trạng thái bài thi (tái sử dụng giống file khác)
function textTrangThaiBaiThi($code) {
    $map = [
        'nhap'     => 'Nháp',
        'dang_mo'  => 'Đang mở',
        'dong'     => 'Đã đóng'
    ];
    return $map[$code] ?? $code;
}

// Map loại câu hỏi sang tiếng Việt
function textLoaiCauHoi($code) {
    $map = [
        'mot_dap_an'    => 'Trắc nghiệm - 1 đáp án đúng',
        'nhieu_dap_an'  => 'Trắc nghiệm - nhiều đáp án đúng',
        'dung_sai'      => 'Đúng / Sai',
        'tu_luan_ngan'  => 'Tự luận ngắn',
        'tu_luan'       => 'Tự luận'
    ];
    return $map[$code] ?? $code;
}

// Gợi ý thứ tự tiếp theo
$next_order = count($questions) + 1;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý câu hỏi - Giảng viên</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Icons -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <!-- CSS dùng chung -->
    <link rel="stylesheet" href="css/style.css">

    <style>
        .question-form-card {
            border-left: 4px solid #b30000;
        }
        .answer-row .remove-answer-btn {
            font-size: 0.8rem;
        }
    </style>
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
            <a href="giangvien_exams.php?id_khoa_hoc=<?php echo (int)$exam['id_khoa_hoc']; ?>" class="text-white">
                Bài thi trong khóa
            </a>
            <span> / Câu hỏi</span>
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
            <div style="font-size: 0.9rem; color:#555;">
                Quản lý câu hỏi - 
                <?php if ($exam): ?>
                    <?php echo htmlspecialchars($exam['tieu_de']); ?>
                    (<?php echo htmlspecialchars($exam['ten_khoa_hoc']); ?>)
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>

<div class="container mt-4 mb-5">

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <?php if ($exam): ?>

        <!-- Thông tin ngắn về bài thi -->
        <div class="card mb-4">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-1" style="color:#b30000;">
                        <?php echo htmlspecialchars($exam['tieu_de']); ?>
                    </h5>
                    <div style="font-size: 0.9rem;">
                        <strong>Khóa học:</strong> <?php echo htmlspecialchars($exam['ten_khoa_hoc']); ?>
                        &nbsp;|&nbsp;
                        <strong>Thời lượng:</strong> <?php echo (int)$exam['thoi_luong_phut']; ?> phút
                    </div>
                    <div style="font-size: 0.9rem;">
                        <strong>Trạng thái:</strong>
                        <?php
                        $txt = textTrangThaiBaiThi($exam['trang_thai']);
                        if ($exam['trang_thai'] === 'dang_mo') {
                            echo '<span class="badge bg-success">' . htmlspecialchars($txt) . '</span>';
                        } elseif ($exam['trang_thai'] === 'nhap') {
                            echo '<span class="badge bg-secondary">' . htmlspecialchars($txt) . '</span>';
                        } else {
                            echo '<span class="badge bg-light text-dark">' . htmlspecialchars($txt) . '</span>';
                        }
                        ?>
                    </div>
                </div>
                <div style="font-size: 0.85rem; text-align:right;">
                    <div>
                        <strong>Bắt đầu:</strong>
                        <?php
                        echo $exam['thoi_gian_bat_dau']
                            ? date('d/m/Y H:i', strtotime($exam['thoi_gian_bat_dau']))
                            : 'Chưa đặt';
                        ?>
                    </div>
                    <div>
                        <strong>Kết thúc:</strong>
                        <?php
                        echo $exam['thoi_gian_ket_thuc']
                            ? date('d/m/Y H:i', strtotime($exam['thoi_gian_ket_thuc']))
                            : 'Chưa đặt';
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form thêm câu hỏi -->
        <div class="card mb-4 question-form-card">
            <div class="card-header">
                <strong>Thêm câu hỏi mới</strong>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add_question">

                    <div class="mb-3">
                        <label class="form-label">Nội dung câu hỏi</label>
                        <textarea name="noi_dung_cau_hoi" class="form-control" rows="3" required></textarea>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Loại câu hỏi</label>
                            <select name="loai_cau_hoi" id="loai_cau_hoi" class="form-select">
                                <option value="mot_dap_an">Trắc nghiệm - 1 đáp án đúng</option>
                                <option value="nhieu_dap_an">Trắc nghiệm - nhiều đáp án đúng</option>
                                <option value="dung_sai">Đúng / Sai</option>
                                <option value="tu_luan_ngan">Tự luận ngắn</option>
                                <option value="tu_luan">Tự luận</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Điểm số</label>
                            <input type="number" step="0.25" min="0" name="diem_so" class="form-control" value="1">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Thứ tự hiển thị</label>
                            <input type="number" name="thu_tu" class="form-control" value="<?php echo $next_order; ?>">
                        </div>
                    </div>

                    <!-- Khu vực đáp án trắc nghiệm -->
                    <div id="mcq-area">
                        <label class="form-label">Đáp án lựa chọn</label>

                        <div id="answers-container">
                            <!-- Mặc định 2 đáp án để người dùng điền -->
                            <div class="row mb-2 answer-row">
                                <div class="col-md-8">
                                    <input type="text" name="option_text[]" class="form-control"
                                           placeholder="Nội dung đáp án 1">
                                </div>
                                <div class="col-md-3 d-flex align-items-center">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox"
                                               name="correct_index[]" value="0" id="ans_correct_0">
                                        <label class="form-check-label" for="ans_correct_0">
                                            Đáp án đúng
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-1 d-flex align-items-center justify-content-end">
                                    <!-- nút xóa dòng (tùy chọn) -->
                                    <button type="button" class="btn btn-sm btn-outline-danger remove-answer-btn" style="display:none;">
                                        &times;
                                    </button>
                                </div>
                            </div>

                            <div class="row mb-2 answer-row">
                                <div class="col-md-8">
                                    <input type="text" name="option_text[]" class="form-control"
                                           placeholder="Nội dung đáp án 2">
                                </div>
                                <div class="col-md-3 d-flex align-items-center">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox"
                                               name="correct_index[]" value="1" id="ans_correct_1">
                                        <label class="form-check-label" for="ans_correct_1">
                                            Đáp án đúng
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-1 d-flex align-items-center justify-content-end">
                                    <button type="button" class="btn btn-sm btn-outline-danger remove-answer-btn" style="display:none;">
                                        &times;
                                    </button>
                                </div>
                            </div>
                        </div>

                        <button type="button" id="btn-add-answer" class="btn btn-sm btn-outline-primary mt-2">
                            <i class="bi bi-plus-circle"></i> Thêm đáp án
                        </button>
                        <div class="form-text">
                            - Với câu hỏi trắc nghiệm, bạn có thể thêm bao nhiêu đáp án tùy ý.<br>
                            - Đánh dấu một hoặc nhiều "Đáp án đúng" tùy theo loại câu hỏi.
                        </div>
                    </div>

                    <div class="mt-3">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-save"></i> Lưu câu hỏi
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Danh sách câu hỏi -->
        <div class="mb-4">
            <h4 style="color:#b30000;">Danh sách câu hỏi</h4>

            <div class="card">
                <div class="card-body p-0">
                    <?php if (empty($questions)): ?>
                        <div class="p-3">
                            <em>Chưa có câu hỏi nào trong bài thi này. Hãy thêm câu hỏi ở form phía trên.</em>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped align-middle mb-0">
                                <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Nội dung câu hỏi</th>
                                    <th>Loại</th>
                                    <th>Điểm</th>
                                    <th>Thứ tự</th>
                                    <th>Số đáp án</th>
                                    <th>Thao tác</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($questions as $idx => $q): ?>
                                    <tr>
                                        <td><?php echo $idx + 1; ?></td>
                                        <td style="max-width:400px;">
                                            <?php echo nl2br(htmlspecialchars($q['noi_dung_cau_hoi'])); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars(textLoaiCauHoi($q['loai_cau_hoi'])); ?></td>
                                        <td><?php echo (float)$q['diem_so']; ?></td>
                                        <td><?php echo (int)$q['thu_tu']; ?></td>
                                        <td><?php echo (int)$q['so_dap_an']; ?></td>
                                        <td>
                                            <!-- Có thể sau này làm trang sửa chi tiết -->
                                            <!-- <a href="giangvien_exam_question_edit.php?id=<?php echo (int)$q['id']; ?>" class="btn btn-sm btn-outline-primary">Sửa</a> -->
                                            <form method="POST" style="display:inline;"
                                                  onsubmit="return confirm('Bạn có chắc chắn muốn xóa câu hỏi này?');">
                                                <input type="hidden" name="action" value="delete_question">
                                                <input type="hidden" name="question_id" value="<?php echo (int)$q['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    Xóa
                                                </button>
                                            </form>
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

<!-- JS cho Bootstrap (tùy chọn) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// JS: ẩn/hiện khu vực đáp án theo loại câu hỏi
document.addEventListener('DOMContentLoaded', function () {
    const loaiSelect    = document.getElementById('loai_cau_hoi');
    const mcqArea       = document.getElementById('mcq-area');
    const answersParent = document.getElementById('answers-container');
    const btnAddAnswer  = document.getElementById('btn-add-answer');

    function updateMcqVisibility() {
        const val = loaiSelect.value;
        // Nếu là tự luận → ẩn khu vực đáp án
        if (val === 'tu_luan' || val === 'tu_luan_ngan') {
            mcqArea.style.display = 'none';
        } else {
            mcqArea.style.display = 'block';
        }
    }

    loaiSelect.addEventListener('change', updateMcqVisibility);
    updateMcqVisibility(); // chạy lúc đầu

    // Quản lý index cho đáp án
    let answerIndex = answersParent.querySelectorAll('.answer-row').length;

    btnAddAnswer.addEventListener('click', function () {
        const row = document.createElement('div');
        row.className = 'row mb-2 answer-row';
        row.innerHTML = `
            <div class="col-md-8">
                <input type="text" name="option_text[]" class="form-control"
                       placeholder="Nội dung đáp án ${answerIndex + 1}">
            </div>
            <div class="col-md-3 d-flex align-items-center">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox"
                           name="correct_index[]" value="${answerIndex}" id="ans_correct_${answerIndex}">
                    <label class="form-check-label" for="ans_correct_${answerIndex}">
                        Đáp án đúng
                    </label>
                </div>
            </div>
            <div class="col-md-1 d-flex align-items-center justify-content-end">
                <button type="button" class="btn btn-sm btn-outline-danger remove-answer-btn">
                    &times;
                </button>
            </div>
        `;
        answersParent.appendChild(row);
        answerIndex++;

        // Gán sự kiện xóa cho nút mới
        const removeBtn = row.querySelector('.remove-answer-btn');
        removeBtn.addEventListener('click', function () {
            row.remove();
        });

        // Hiển thị nút xóa cho tất cả các dòng (trừ khi bạn muốn giữ ít nhất 2 dòng)
        const allRows = answersParent.querySelectorAll('.answer-row');
        allRows.forEach(function(r) {
            const btn = r.querySelector('.remove-answer-btn');
            if (btn) btn.style.display = 'inline-block';
        });
    });

    // Gắn event xóa cho 2 dòng mặc định (nếu muốn cho phép xóa)
    const defaultRemoveBtns = answersParent.querySelectorAll('.answer-row .remove-answer-btn');
    defaultRemoveBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            const row = btn.closest('.answer-row');
            row.remove();
        });
    });
});
</script>

</body>
</html>
