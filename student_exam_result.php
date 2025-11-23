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

$error    = '';
$exam     = null;
$lan_list = [];
$lan_chi_tiet = null;
$chi_tiet_cau_hoi = [];

// ------------------------
// 1. LẤY THAM SỐ GET
// ------------------------
$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
$lan_id  = isset($_GET['lan']) ? (int)$_GET['lan'] : 0;

if ($exam_id <= 0) {
    $error = "Bài thi không hợp lệ.";
}

try {
    $pdo = Database::pdo();

    // ------------------------
    // 2. LẤY THÔNG TIN BÀI THI
    // ------------------------
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

    // ------------------------
    // 3. KIỂM TRA HỌC VIÊN ĐÃ ĐĂNG KÝ KHÓA CHƯA
    // ------------------------
    if (!$error) {
        $sql = "
            SELECT *
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
        $dk = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$dk) {
            $error = "Bạn chưa đăng ký khóa học chứa bài thi này.";
        }
    }

    // ------------------------
    // 4. LẤY DANH SÁCH CÁC LẦN THI CỦA HỌC VIÊN
    // ------------------------
    if (!$error) {
        $sql = "
            SELECT *
            FROM lan_thi
            WHERE id_bai_thi = :bt
              AND id_hoc_vien = :hv
            ORDER BY thoi_gian_bat_dau DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':bt' => $exam_id,
            ':hv' => $user_id
        ]);
        $lan_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($lan_list)) {
            $error = "Bạn chưa có lần thi nào cho bài thi này.";
        }
    }

    // ------------------------
    // 5. LẤY CHI TIẾT 1 LẦN THI (NẾU CÓ lan=...)
// ------------------------
    if (!$error && $lan_id > 0) {
        // Kiểm tra lần thi này có thuộc về user + bài thi này không
        $sql = "
            SELECT *
            FROM lan_thi
            WHERE id = :id
              AND id_bai_thi = :bt
              AND id_hoc_vien = :hv
            LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id' => $lan_id,
            ':bt' => $exam_id,
            ':hv' => $user_id
        ]);
        $lan_chi_tiet = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($lan_chi_tiet) {
            // Lấy chi tiết từng câu trả lời
            $sql = "
                SELECT 
                    blct.*,
                    ch.noi_dung_cau_hoi,
                    ch.loai_cau_hoi,
                    ch.diem_so,
                    lc.noi_dung_lua_chon
                FROM bai_lam_chi_tiet blct
                JOIN cau_hoi ch ON blct.id_cau_hoi = ch.id
                LEFT JOIN lua_chon lc ON blct.id_lua_chon = lc.id
                WHERE blct.id_lan_thi = :lan
                ORDER BY ch.thu_tu ASC, ch.id ASC
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':lan' => $lan_id]);
            $chi_tiet_cau_hoi = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Nếu lan không hợp lệ -> bỏ chi tiết
            $lan_id = 0;
        }
    }

} catch (PDOException $e) {
    $error = "Lỗi hệ thống: " . $e->getMessage();
}

// ------------------------
// HÀM PHỤ TRỢ
// ------------------------
function textTrangThaiLanThi($code) {
    $map = [
        'dang_lam'  => 'Đang làm',
        'da_nop'    => 'Đã nộp',
        'da_cham'   => 'Đã chấm',
        'huy'       => 'Hủy',
    ];
    return $map[$code] ?? $code;
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Kết quả bài thi - E-learning PTIT</title>

    <!-- Bootstrap 5 -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">

    <!-- Icons -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<!-- TOP BAR -->
<div class="top-bar">
    <div class="container d-flex justify-content-between align-items-center">
        <div>
            <i class="bi bi-mortarboard-fill"></i>
            <a href="student_dashboard.php">Khu vực học viên</a> /
            <a href="student_exams.php">Bài thi & kết quả</a> /
            <span>Kết quả bài thi</span>
        </div>
        <div>
            Xin chào, <strong><?php echo htmlspecialchars($ho_ten); ?></strong>
            &nbsp;|&nbsp;
            <a href="dang_xuat.php" style="color:#fff;">Đăng xuất</a>
        </div>
    </div>
</div>

<!-- HEADER -->
<header class="main-header">
    <div class="container py-2 d-flex align-items-center">
        <img src="image/ptit.png" style="height:55px;" class="me-3" alt="PTIT">
        <div>
            <div class="logo-text">HỆ THỐNG E-LEARNING PTIT</div>
            <div style="font-size:0.9rem;color:#555;">Kết quả bài thi</div>
        </div>
    </div>
</header>

<div class="container mt-4 mb-5">

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <i class="bi bi-x-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
        <a href="student_exams.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Quay lại danh sách bài thi
        </a>

    <?php else: ?>

        <!-- THÔNG TIN CHUNG VỀ BÀI THI -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h3 style="color:#b30000;"><?php echo htmlspecialchars($exam['tieu_de']); ?></h3>
                <p class="mb-1">
                    <strong>Khóa học:</strong>
                    <?php echo htmlspecialchars($exam['ten_khoa_hoc']); ?>
                </p>
                <p class="mb-1">
                    <strong>Thời lượng:</strong>
                    <?php echo (int)$exam['thoi_luong_phut']; ?> phút
                </p>
                <p class="mb-0">
                    <strong>Giới hạn số lần thi:</strong>
                    <?php echo (int)$exam['gioi_han_so_lan']; ?> lần
                </p>
            </div>
        </div>

        <!-- DANH SÁCH CÁC LẦN THI -->
        <div class="card shadow-sm mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Các lần thi của bạn</strong>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width:10%;">Lần</th>
                                <th style="width:25%;">Thời gian bắt đầu</th>
                                <th style="width:25%;">Thời gian nộp</th>
                                <th style="width:10%;">Điểm</th>
                                <th style="width:15%;">Trạng thái</th>
                                <th style="width:15%;">Xem chi tiết</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $index = 1;
                        foreach ($lan_list as $lt):
                            $is_current = ($lan_id > 0 && $lan_id == (int)$lt['id']);
                        ?>
                            <tr class="<?php echo $is_current ? 'table-warning' : ''; ?>">
                                <td><?php echo $index++; ?></td>
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
                                        echo htmlspecialchars($lt['diem_so']);
                                    } else {
                                        echo '—';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <span class="badge 
                                        <?php
                                        if ($lt['trang_thai'] === 'da_nop' || $lt['trang_thai'] === 'da_cham') {
                                            echo 'bg-success';
                                        } elseif ($lt['trang_thai'] === 'dang_lam') {
                                            echo 'bg-warning text-dark';
                                        } else {
                                            echo 'bg-secondary';
                                        }
                                        ?>">
                                        <?php echo htmlspecialchars(textTrangThaiLanThi($lt['trang_thai'])); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <a href="student_exam_result.php?exam_id=<?php echo (int)$exam_id; ?>&lan=<?php echo (int)$lt['id']; ?>"
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i>
                                        Xem
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- NẾU CÓ CHỌN 1 LẦN THI -> HIỂN THỊ CHI TIẾT -->
        <?php if ($lan_chi_tiet): ?>
            <div class="card shadow-sm">
                <div class="card-header">
                    <strong>Chi tiết lần thi #<?php echo (int)$lan_chi_tiet['id']; ?></strong>
                </div>
                <div class="card-body">

                    <p>
                        <strong>Thời gian làm:</strong>
                        <?php
                        $start = $lan_chi_tiet['thoi_gian_bat_dau']
                            ? date('d/m/Y H:i:s', strtotime($lan_chi_tiet['thoi_gian_bat_dau']))
                            : '—';
                        $end = $lan_chi_tiet['thoi_gian_nop']
                            ? date('d/m/Y H:i:s', strtotime($lan_chi_tiet['thoi_gian_nop']))
                            : '—';
                        echo $start . ' → ' . $end;
                        ?>
                    </p>
                    <p>
                        <strong>Điểm đạt được:</strong>
                        <?php
                        if ($lan_chi_tiet['diem_so'] !== null) {
                            echo htmlspecialchars($lan_chi_tiet['diem_so']);
                        } else {
                            echo '—';
                        }
                        ?>
                    </p>

                    <?php if (empty($chi_tiet_cau_hoi)): ?>
                        <em>Chưa có dữ liệu chi tiết câu hỏi cho lần thi này.</em>
                    <?php else: ?>
                        <?php
                        $stt = 1;
                        foreach ($chi_tiet_cau_hoi as $row):
                            $loai = $row['loai_cau_hoi'];
                            $dung = $row['dung_hay_sai'];
                        ?>
                            <div class="mb-4 p-3 border rounded">
                                <h5>
                                    Câu <?php echo $stt++; ?>:
                                    <?php echo htmlspecialchars($row['noi_dung_cau_hoi']); ?>
                                </h5>
                                <p class="mb-1">
                                    <em>(<?php echo $row['diem_so']; ?> điểm)</em>
                                </p>
                                <p class="mb-1">
                                    <strong>Kết quả:</strong>
                                    <?php
                                    if ($dung === null) {
                                        echo '<span class="badge bg-secondary">Chưa chấm</span>';
                                    } elseif ((int)$dung === 1) {
                                        echo '<span class="badge bg-success"><i class="bi bi-check-circle"></i> Đúng</span>';
                                    } else {
                                        echo '<span class="badge bg-danger"><i class="bi bi-x-circle"></i> Sai</span>';
                                    }
                                    ?>
                                </p>
                                <p class="mb-1">
                                    <strong>Điểm câu này:</strong>
                                    <?php
                                    if ($row['diem_dat_duoc'] !== null) {
                                        echo htmlspecialchars($row['diem_dat_duoc']);
                                    } else {
                                        echo '—';
                                    }
                                    ?>
                                </p>

                                <p class="mb-1">
                                    <strong>Câu trả lời của bạn:</strong><br>
                                    <?php
                                    if ($loai === 'mot_dap_an' || $loai === 'dung_sai') {
                                        if ($row['noi_dung_lua_chon']) {
                                            echo htmlspecialchars($row['noi_dung_lua_chon']);
                                        } else {
                                            echo '<em>Không trả lời</em>';
                                        }
                                    } elseif ($loai === 'nhieu_dap_an') {
                                        if (!empty($row['cau_tra_loi'])) {
                                            $ids = json_decode($row['cau_tra_loi'], true);
                                            if (is_array($ids) && count($ids) > 0) {
                                                // Lấy text của các lựa chọn đã chọn
                                                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                                                $sql = "SELECT noi_dung_lua_chon FROM lua_chon WHERE id IN ($placeholders)";
                                                $stmt = $pdo->prepare($sql);
                                                $stmt->execute($ids);
                                                $texts = $stmt->fetchAll(PDO::FETCH_COLUMN);

                                                if (!empty($texts)) {
                                                    echo '<ul class="mb-1">';
                                                    foreach ($texts as $t) {
                                                        echo '<li>' . htmlspecialchars($t) . '</li>';
                                                    }
                                                    echo '</ul>';
                                                } else {
                                                    echo '<em>Không xác định được nội dung lựa chọn.</em>';
                                                }
                                            } else {
                                                echo '<em>Không trả lời</em>';
                                            }
                                        } else {
                                            echo '<em>Không trả lời</em>';
                                        }
                                    } else {
                                        // Tự luận
                                        if (!empty($row['cau_tra_loi'])) {
                                            echo nl2br(htmlspecialchars($row['cau_tra_loi']));
                                        } else {
                                            echo '<em>Không trả lời</em>';
                                        }
                                    }
                                    ?>
                                </p>
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

        <div class="mt-3">
            <a href="student_exams.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Quay lại danh sách bài thi
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
