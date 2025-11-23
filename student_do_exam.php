<?php
session_start();
require_once 'config.php';

// Đặt múi giờ Việt Nam (nếu bạn đã đặt trong config.php thì dòng này cũng không sao)
date_default_timezone_set('Asia/Ho_Chi_Minh');

// ==============================
// 1. KIỂM TRA ĐĂNG NHẬP
// ==============================
if (!isset($_SESSION['user_id']) || ($_SESSION['vai_tro'] ?? '') !== 'hoc_vien') {
    header("Location: dang_nhap.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$ho_ten  = $_SESSION['ho_ten'] ?? 'Học viên';

$error = '';
$exam  = null;
$questions = [];
$choices   = [];
$lan_thi_id = 0;

// Lấy ID bài thi
$exam_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($exam_id <= 0) {
    $error = "Bài thi không hợp lệ.";
}

// PDO
$pdo = Database::pdo();

try {

    // ==============================
    // 2. LẤY THÔNG TIN BÀI THI
    // ==============================
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

    // ==============================
    // 3. KIỂM TRA HỌC VIÊN ĐÃ ĐĂNG KÝ KHÓA CHƯA
    // ==============================
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
            $error = "Bạn chưa đăng ký khóa học này.";
        }
    }

    // ==============================
    // 4. KIỂM TRA SỐ LẦN ĐÃ THI, GIỚI HẠN
    // ==============================
    $so_lan_da_thi = 0;
    if (!$error) {
        $sql = "
            SELECT COUNT(*) 
            FROM lan_thi 
            WHERE id_hoc_vien = :hv 
              AND id_bai_thi = :bt
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':hv' => $user_id,
            ':bt' => $exam_id
        ]);
        $so_lan_da_thi = (int)$stmt->fetchColumn();

        if ($so_lan_da_thi >= (int)$exam['gioi_han_so_lan']) {
            $error = "Bạn đã hết số lượt thi cho phép.";
        }
    }

    // ==============================
    // 5. KIỂM TRA THỜI GIAN THI
    // ==============================
    if (!$error) {
        if ($exam['trang_thai'] !== 'dang_mo') {
            $error = "Bài thi hiện không mở.";
        } else {
            // Dùng DateTime với timezone Việt Nam
            $now   = new DateTime('now');
            $start = !empty($exam['thoi_gian_bat_dau']) ? new DateTime($exam['thoi_gian_bat_dau']) : null;
            $end   = !empty($exam['thoi_gian_ket_thuc']) ? new DateTime($exam['thoi_gian_ket_thuc']) : null;

            if ($start && $now < $start) {
                $error = "Chưa đến thời gian làm bài thi. Bài thi mở lúc: " . $start->format('d/m/Y H:i');
            }

            if (!$error && $end && $now > $end) {
                $error = "Đã hết thời gian làm bài thi.";
            }
        }
    }

    // ==============================
    // 6. TẠO LẦN THI MỚI
    // ==============================
    if (!$error) {

        $sql = "
            INSERT INTO lan_thi (id_bai_thi, id_hoc_vien, thoi_gian_bat_dau, so_lan, trang_thai)
            VALUES (:bt, :hv, NOW(), :solan, 'dang_lam')
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':bt'    => $exam_id,
            ':hv'    => $user_id,
            ':solan' => $so_lan_da_thi + 1
        ]);

        $lan_thi_id = (int)$pdo->lastInsertId();
    }

    // ==============================
    // 7. LẤY DANH SÁCH CÂU HỎI
    // ==============================
    if (!$error) {
        $sql = "
            SELECT * FROM cau_hoi
            WHERE id_bai_thi = :bt
            ORDER BY thu_tu ASC, id ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':bt' => $exam_id]);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Nếu bài thi yêu cầu xáo trộn
        if ($exam['tron_cau_hoi']) {
            shuffle($questions);
        }

        // Lấy đáp án cho các câu hỏi có lựa chọn
        $question_ids = array_column($questions, 'id');
        if ($question_ids) {
            $placeholders = implode(',', array_fill(0, count($question_ids), '?'));

            $sql = "SELECT * FROM lua_chon WHERE id_cau_hoi IN ($placeholders) ORDER BY thu_tu ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($question_ids);

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $choices[$r['id_cau_hoi']][] = $r;
            }
        }
    }

} catch (PDOException $e) {
    $error = "Lỗi hệ thống: " . $e->getMessage();
}

// ==============================
// 8. XỬ LÝ NỘP BÀI
// ==============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_exam']) && !$error) {

    $tong_diem = 0;

    foreach ($questions as $q) {

        $qid  = $q['id'];
        $loai = $q['loai_cau_hoi'];
        $diem = (float)$q['diem_so'];

        $tra_loi = null;
        $id_lc   = null;
        $dung    = null;

        // ---- MỘT ĐÁP ÁN ----
        if ($loai === 'mot_dap_an') {
            $id_lc = isset($_POST['cau_'.$qid]) ? (int)$_POST['cau_'.$qid] : null;

            if ($id_lc) {
                $sql = "SELECT la_dap_an_dung FROM lua_chon WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$id_lc]);
                $is_correct = (int)$stmt->fetchColumn();

                if ($is_correct === 1) {
                    $dung = 1;
                    $tong_diem += $diem;
                } else {
                    $dung = 0;
                }
            }

        // ---- NHIỀU ĐÁP ÁN ----
        } elseif ($loai === 'nhieu_dap_an') {
            $id_lc_arr = isset($_POST['cau_'.$qid]) ? (array)$_POST['cau_'.$qid] : [];

            // Lấy đáp án đúng
            $sql = "SELECT id FROM lua_chon WHERE id_cau_hoi = ? AND la_dap_an_dung = 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$qid]);
            $right = $stmt->fetchAll(PDO::FETCH_COLUMN);

            sort($right);
            $selected = $id_lc_arr;
            sort($selected);

            if ($right === $selected && !empty($selected)) {
                $dung = 1;
                $tong_diem += $diem;
            } else {
                $dung = 0;
            }

            $tra_loi = json_encode($selected);
            $id_lc   = null; // nhiều đáp án -> không lưu 1 id_lua_chon đơn lẻ

        // ---- ĐÚNG / SAI ----
        } elseif ($loai === 'dung_sai') {
            $val = $_POST['cau_'.$qid] ?? null;
            if ($val !== null) {
                $id_lc = (int)$val;

                $sql = "SELECT la_dap_an_dung FROM lua_chon WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$id_lc]);
                $ok = (int)$stmt->fetchColumn();

                if ($ok === 1) {
                    $dung = 1;
                    $tong_diem += $diem;
                } else {
                    $dung = 0;
                }
            }

        // ---- TỰ LUẬN ----
        } elseif ($loai === 'tu_luan_ngan' || $loai === 'tu_luan') {
            $tra_loi = trim($_POST['cau_'.$qid] ?? '');
            $dung    = null; // chấm tay
        }

        // Lưu chi tiết
        $sql = "
            INSERT INTO bai_lam_chi_tiet
            (id_lan_thi, id_cau_hoi, id_lua_chon, cau_tra_loi, dung_hay_sai, diem_dat_duoc)
            VALUES (:lt, :cq, :lc, :tl, :d, :diem)
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':lt'   => $lan_thi_id,
            ':cq'   => $qid,
            ':lc'   => $id_lc,
            ':tl'   => $tra_loi,
            ':d'    => $dung,
            ':diem' => $dung === 1 ? $diem : 0
        ]);
    }

    // ==============================
    // 9. CẬP NHẬT LẦN THI
    // ==============================
    $sql = "
        UPDATE lan_thi
        SET thoi_gian_nop = NOW(),
            diem_so = :ds,
            trang_thai = 'da_nop'
        WHERE id = :id
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':ds' => $tong_diem,
        ':id' => $lan_thi_id
    ]);

    // Chuyển tới trang xem kết quả
    header("Location: student_exam_result.php?exam_id={$exam_id}&lan={$lan_thi_id}");
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Làm bài thi - E-learning PTIT</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Icons -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<div class="top-bar">
    <div class="container d-flex justify-content-between align-items-center">
        <div>
            <a href="student_exams.php" style="color:white;"><i class="bi bi-card-checklist"></i> Bài thi</a> /
            <span>Làm bài</span>
        </div>
        <div>
            Xin chào, <strong><?php echo htmlspecialchars($ho_ten); ?></strong> |
            <a href="dang_xuat.php" style="color:white;">Đăng xuất</a>
        </div>
    </div>
</div>

<header class="main-header">
    <div class="container py-2 d-flex align-items-center">
        <img src="image/ptit.png" style="height:55px;">
        <div class="ms-3">
            <div class="logo-text">HỆ THỐNG E-LEARNING PTIT</div>
            <div style="font-size:14px;color:#555;">Làm bài thi</div>
        </div>
    </div>
</header>

<div class="container mt-4 mb-5">

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <a href="student_exams.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Quay lại</a>

<?php else: ?>

    <h3 style="color:#b30000;"><?php echo htmlspecialchars($exam['tieu_de']); ?></h3>
    <p class="text-muted">
        Thuộc khóa học: <strong><?php echo htmlspecialchars($exam['ten_khoa_hoc']); ?></strong><br>
        Thời lượng: <strong><?php echo (int)$exam['thoi_luong_phut']; ?> phút</strong>
    </p>

    <hr>

    <form method="POST">

        <?php $stt = 1; ?>
        <?php foreach ($questions as $q): ?>

            <div class="mb-4 p-3 border rounded">

                <h5><strong>Câu <?php echo $stt++; ?>:</strong> <?php echo htmlspecialchars($q['noi_dung_cau_hoi']); ?></h5>

                <p class="mt-1"><em>(<?php echo $q['diem_so']; ?> điểm)</em></p>

                <?php
                $qid  = $q['id'];
                $loai = $q['loai_cau_hoi'];
                ?>

                <!-- Một đáp án -->
                <?php if ($loai === 'mot_dap_an'): ?>
                    <?php foreach ($choices[$qid] ?? [] as $c): ?>
                        <div class="form-check">
                            <input type="radio" class="form-check-input" 
                                   name="cau_<?php echo $qid; ?>" 
                                   value="<?php echo $c['id']; ?>">
                            <label class="form-check-label">
                                <?php echo htmlspecialchars($c['noi_dung_lua_chon']); ?>
                            </label>
                        </div>
                    <?php endforeach; ?>

                <!-- Nhiều đáp án -->
                <?php elseif ($loai === 'nhieu_dap_an'): ?>
                    <?php foreach ($choices[$qid] ?? [] as $c): ?>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input"
                                   name="cau_<?php echo $qid; ?>[]"
                                   value="<?php echo $c['id']; ?>">
                            <label class="form-check-label">
                                <?php echo htmlspecialchars($c['noi_dung_lua_chon']); ?>
                            </label>
                        </div>
                    <?php endforeach; ?>

                <!-- Đúng / Sai -->
                <?php elseif ($loai === 'dung_sai'): ?>
                    <?php foreach ($choices[$qid] ?? [] as $c): ?>
                        <div class="form-check">
                            <input type="radio" class="form-check-input"
                                   name="cau_<?php echo $qid; ?>"
                                   value="<?php echo $c['id']; ?>">
                            <label class="form-check-label">
                                <?php echo htmlspecialchars($c['noi_dung_lua_chon']); ?>
                            </label>
                        </div>
                    <?php endforeach; ?>

                <!-- Tự luận -->
                <?php elseif ($loai === 'tu_luan_ngan' || $loai === 'tu_luan'): ?>
                    <textarea class="form-control" rows="4" name="cau_<?php echo $qid; ?>"></textarea>

                <?php endif; ?>

            </div>
        <?php endforeach; ?>

        <div class="text-center">
            <button type="submit" name="submit_exam" class="btn btn-danger btn-lg">
                <i class="bi bi-check2-circle"></i> Nộp bài
            </button>
        </div>

    </form>

<?php endif; ?>

</div>

<footer class="text-center py-3">
    © <?php echo date("Y"); ?> Hệ thống E-learning PTIT
</footer>

</body>
</html>
