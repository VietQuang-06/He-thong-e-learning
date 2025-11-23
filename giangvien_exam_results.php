<?php
session_start();
require_once 'config.php';

// Chỉ cho phép Học viên
if (!isset($_SESSION['user_id']) || ($_SESSION['vai_tro'] ?? '') !== 'hoc_vien') {
    header("Location: dang_nhap.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$ho_ten  = $_SESSION['ho_ten'] ?? 'Học viên';

$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
$lan_id  = isset($_GET['lan']) ? (int)$_GET['lan'] : 0;

$error = '';
$exam  = null;
$lan_thi_list = [];
$lan_chi_tiet = null;   // lần thi được chọn để xem
$questions = [];
$answers   = [];        // chi tiết bai_lam_chi_tiet
$choices   = [];        // đáp án lua_chon

$pdo = Database::pdo();

// -------------------------
// Hàm text trạng thái lần thi
// -------------------------
function textTrangThaiLanThi($code) {
    $map = [
        'dang_lam' => 'Đang làm',
        'da_nop'   => 'Đã nộp',
        'da_cham'  => 'Đã chấm',
        'huy'      => 'Đã hủy'
    ];
    return $map[$code] ?? $code;
}

try {
    if ($exam_id <= 0) {
        $error = "Bài thi không hợp lệ.";
    }

    // 1. Thông tin bài thi + khóa học
    if (!$error) {
        $sql = "
            SELECT 
                bt.*,
                kh.ten_khoa_hoc,
                kh.id AS id_khoa_hoc
            FROM bai_thi bt
            JOIN khoa_hoc kh ON bt.id_khoa_hoc = kh.id
            WHERE bt.id = :id
            LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $exam_id]);
        $exam = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$exam) {
            $error = "Không tìm thấy thông tin bài thi.";
        }
    }

    // 2. Kiểm tra học viên có thuộc khóa học không
    if (!$error) {
        $sql = "
            SELECT 1
            FROM dang_ky_khoa_hoc
            WHERE id_hoc_vien = :hv
              AND id_khoa_hoc = :kh
              AND trang_thai IN ('dang_hoc','hoan_thanh')
            LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':hv' => $user_id,
            ':kh' => $exam['id_khoa_hoc']
        ]);

        if (!$stmt->fetchColumn()) {
            $error = "Bạn không có quyền xem kết quả bài thi này.";
        }
    }

    // 3. Lấy tất cả các lần thi của học viên trong bài thi này
    if (!$error) {
        $sql = "
            SELECT *
            FROM lan_thi
            WHERE id_bai_thi = :bt AND id_hoc_vien = :hv
            ORDER BY thoi_gian_bat_dau ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':bt' => $exam_id,
            ':hv' => $user_id
        ]);
        $lan_thi_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($lan_thi_list)) {
            $error = "Bạn chưa có lần thi nào cho bài thi này.";
        }
    }

    // 4. Nếu có tham số lan -> lấy chi tiết lần thi, câu hỏi, câu trả lời
    if (!$error && $lan_id > 0) {

        // 4.1 Lấy lần thi cụ thể (phải thuộc học viên hiện tại)
        $sql = "
            SELECT *
            FROM lan_thi
            WHERE id = :lan
              AND id_bai_thi = :bt
              AND id_hoc_vien = :hv
            LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':lan' => $lan_id,
            ':bt'  => $exam_id,
            ':hv'  => $user_id
        ]);
        $lan_chi_tiet = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($lan_chi_tiet) {
            // 4.2 Lấy chi tiết bài làm (bai_lam_chi_tiet + cau_hoi)
            $sql = "
                SELECT 
                    bl.*,
                    ch.noi_dung_cau_hoi,
                    ch.loai_cau_hoi,
                    ch.diem_so,
                    ch.thu_tu,
                    ch.id AS id_cau_hoi
                FROM bai_lam_chi_tiet bl
                JOIN cau_hoi ch ON bl.id_cau_hoi = ch.id
                WHERE bl.id_lan_thi = :lan
                ORDER BY ch.thu_tu ASC, ch.id ASC
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':lan' => $lan_id]);
            $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Lấy danh sách id_cau_hoi để lấy đáp án lựa chọn
            $question_ids = array_column($answers, 'id_cau_hoi');
            $question_ids = array_unique($question_ids);

            if ($question_ids) {
                $placeholders = implode(',', array_fill(0, count($question_ids), '?'));
                $sql = "
                    SELECT *
                    FROM lua_chon
                    WHERE id_cau_hoi IN ($placeholders)
                    ORDER BY thu_tu ASC, id ASC
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($question_ids);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($rows as $r) {
                    $choices[$r['id_cau_hoi']][] = $r;
                }
            }
        }
    }

} catch (PDOException $e) {
    $error = "Lỗi hệ thống: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Kết quả bài thi - E-learning PTIT</title>

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
    <div class="container d-flex justify-content-between align-items-center">
        <div>
            <a href="student_exams.php" class="text-white">
                <i class="bi bi-mortarboard-fill"></i> Bài thi & kết quả
            </a> /
            <span>Kết quả bài thi</span>
        </div>
        <div>
            Xin chào, <strong><?php echo htmlspecialchars($ho_ten); ?></strong> |
            <a href="dang_xuat.php" class="text-white">Đăng xuất</a>
        </div>
    </div>
</div>

<header class="main-header">
    <div class="container py-2 d-flex align-items-center">
        <img src="image/ptit.png" alt="PTIT" style="height:55px;" class="me-3">
        <div>
            <div class="logo-text">HỆ THỐNG E-LEARNING PTIT</div>
            <div style="font-size:14px;color:#555;">Kết quả bài thi</div>
        </div>
    </div>
</header>

<div class="container mt-4 mb-5">

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <a href="student_exams.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Quay lại danh sách bài thi
    </a>

<?php else: ?>

    <!-- Thông tin bài thi -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <h3 style="color:#b30000;"><?php echo htmlspecialchars($exam['tieu_de']); ?></h3>
            <p class="mb-1">
                Khóa học: <strong><?php echo htmlspecialchars($exam['ten_khoa_hoc']); ?></strong>
            </p>
            <p class="mb-1">
                Thời lượng: <strong><?php echo (int)$exam['thoi_luong_phut']; ?> phút</strong>
            </p>
            <p class="mb-0">
                Giới hạn số lần thi: 
                <strong><?php echo (int)$exam['gioi_han_so_lan']; ?> lần</strong>
            </p>
        </div>
    </div>

    <!-- Danh sách các lần thi -->
    <div class="card mb-4">
        <div class="card-header">
            <strong>Các lần thi của bạn</strong>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Lần</th>
                            <th>Thời gian bắt đầu</th>
                            <th>Thời gian nộp</th>
                            <th>Điểm</th>
                            <th>Trạng thái</th>
                            <th>Xem chi tiết</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($lan_thi_list as $idx => $lt): ?>
                        <tr>
                            <td><?php echo (int)($lt['so_lan'] ?? ($idx + 1)); ?></td>
                            <td>
                                <?php
                                echo $lt['thoi_gian_bat_dau']
                                    ? date('d/m/Y H:i:s', strtotime($lt['thoi_gian_bat_dau']))
                                    : '—';
                                ?>
                            </td>
                            <td>
                                <?php
                                echo $lt['thoi_gian_nop']
                                    ? date('d/m/Y H:i:s', strtotime($lt['thoi_gian_nop']))
                                    : '—';
                                ?>
                            </td>
                            <td>
                                <?php
                                if ($lt['diem_so'] !== null) {
                                    echo number_format((float)$lt['diem_so'], 2);
                                } else {
                                    echo '—';
                                }
                                ?>
                            </td>
                            <td>
                                <?php
                                $txt = textTrangThaiLanThi($lt['trang_thai']);
                                if ($lt['trang_thai'] === 'da_cham') {
                                    echo '<span class="badge bg-success">'.$txt.'</span>';
                                } elseif ($lt['trang_thai'] === 'da_nop') {
                                    echo '<span class="badge bg-primary">'.$txt.'</span>';
                                } elseif ($lt['trang_thai'] === 'dang_lam') {
                                    echo '<span class="badge bg-warning text-dark">'.$txt.'</span>';
                                } else {
                                    echo '<span class="badge bg-secondary">'.$txt.'</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <a href="student_exam_result.php?exam_id=<?php echo $exam_id; ?>&lan=<?php echo (int)$lt['id']; ?>"
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i> Xem
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Chi tiết lần thi -->
    <?php if ($lan_chi_tiet): ?>
        <div class="card mb-4 border-info">
            <div class="card-header bg-info text-white">
                <strong>Chi tiết lần thi số <?php echo (int)($lan_chi_tiet['so_lan'] ?? 0); ?></strong>
            </div>
            <div class="card-body">
                <p>
                    Bắt đầu: 
                    <strong>
                        <?php echo $lan_chi_tiet['thoi_gian_bat_dau']
                            ? date('d/m/Y H:i:s', strtotime($lan_chi_tiet['thoi_gian_bat_dau']))
                            : '—'; ?>
                    </strong>
                    &nbsp; | &nbsp;
                    Nộp bài:
                    <strong>
                        <?php echo $lan_chi_tiet['thoi_gian_nop']
                            ? date('d/m/Y H:i:s', strtotime($lan_chi_tiet['thoi_gian_nop']))
                            : '—'; ?>
                    </strong>
                </p>
                <p>
                    Điểm tổng: 
                    <strong>
                        <?php
                        if ($lan_chi_tiet['diem_so'] !== null) {
                            echo number_format((float)$lan_chi_tiet['diem_so'], 2);
                        } else {
                            echo '—';
                        }
                        ?>
                    </strong>
                    &nbsp; | &nbsp;
                    Trạng thái:
                    <span class="badge bg-secondary">
                        <?php echo htmlspecialchars(textTrangThaiLanThi($lan_chi_tiet['trang_thai'])); ?>
                    </span>
                </p>

                <hr>

                <?php if (empty($answers)): ?>
                    <p><em>Không tìm thấy chi tiết các câu trả lời cho lần thi này.</em></p>
                <?php else: ?>

                    <?php $stt = 1; ?>
                    <?php foreach ($answers as $ans): ?>
                        <?php
                        $qid      = $ans['id_cau_hoi'];
                        $loai     = $ans['loai_cau_hoi'];
                        $dung     = $ans['dung_hay_sai'];
                        $diem_max = (float)$ans['diem_so'];
                        $diem_dat = ($ans['diem_dat_duoc'] !== null) ? (float)$ans['diem_dat_duoc'] : null;

                        // Với nhiều đáp án, câu_tra_loi là JSON danh sách id
                        $selected_multi = [];
                        if ($loai === 'nhieu_dap_an' && !empty($ans['cau_tra_loi'])) {
                            $selected_multi = json_decode($ans['cau_tra_loi'], true) ?: [];
                        }
                        ?>
                        <div class="mb-4 p-3 border rounded">
                            <h5>
                                <strong>Câu <?php echo $stt++; ?>:</strong>
                                <?php echo htmlspecialchars($ans['noi_dung_cau_hoi']); ?>
                            </h5>
                            <p class="mb-1">
                                <em>(Điểm tối đa: <?php echo $diem_max; ?>;
                                    Điểm đạt được: <?php echo $diem_dat !== null ? $diem_dat : '—'; ?>)</em>
                            </p>
                            <p>
                                Kết quả:
                                <?php if ($dung === null): ?>
                                    <span class="badge bg-secondary">Chưa chấm</span>
                                <?php elseif ($dung == 1): ?>
                                    <span class="badge bg-success">Đúng</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Sai</span>
                                <?php endif; ?>
                            </p>

                            <!-- Hiển thị theo loại câu hỏi -->
                            <?php if (in_array($loai, ['mot_dap_an','nhieu_dap_an','dung_sai'])): ?>
                                <?php foreach ($choices[$qid] ?? [] as $lc): ?>
                                    <?php
                                    $isCorrect = (int)$lc['la_dap_an_dung'] === 1;

                                    $isChosen = false;
                                    if ($loai === 'mot_dap_an' || $loai === 'dung_sai') {
                                        $isChosen = ($ans['id_lua_chon'] == $lc['id']);
                                    } elseif ($loai === 'nhieu_dap_an') {
                                        $isChosen = in_array($lc['id'], $selected_multi);
                                    }
                                    ?>
                                    <div class="d-flex align-items-center mb-1">
                                        <span>
                                            - <?php echo htmlspecialchars($lc['noi_dung_lua_chon']); ?>
                                        </span>
                                        <?php if ($isCorrect): ?>
                                            <span class="badge bg-success ms-2">Đáp án đúng</span>
                                        <?php endif; ?>
                                        <?php if ($isChosen): ?>
                                            <span class="badge bg-primary ms-2">Bạn chọn</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>

                            <?php elseif (in_array($loai, ['tu_luan_ngan','tu_luan'])): ?>
                                <p><strong>Bài làm của bạn:</strong></p>
                                <div class="border rounded p-2" style="background:#fafafa;white-space:pre-wrap;">
                                    <?php echo htmlspecialchars($ans['cau_tra_loi'] ?? ''); ?>
                                </div>
                            <?php endif; ?>

                        </div>
                    <?php endforeach; ?>

                <?php endif; ?>

            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            Hãy chọn một lần thi ở bảng phía trên để xem chi tiết câu hỏi – đáp án.
        </div>
    <?php endif; ?>

    <a href="student_exams.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Quay lại danh sách bài thi
    </a>

<?php endif; ?>

</div>

<footer class="text-center py-3">
    © <?php echo date('Y'); ?> Hệ thống E-learning PTIT - Khu vực học viên.
</footer>

</body>
</html>
