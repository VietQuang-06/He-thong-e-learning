<?php
session_start();
require_once 'config.php';

// Chỉ cho Admin truy cập
if (!isset($_SESSION['user_id']) || ($_SESSION['vai_tro'] ?? '') !== 'quan_tri') {
    header("Location: dang_nhap.php");
    exit;
}

$pdo             = Database::pdo();
$ho_ten_admin    = $_SESSION['ho_ten'] ?? 'Quản trị hệ thống';

$errors          = [];   // lỗi chung cho bài thi
$questionErrors  = [];   // lỗi cho câu hỏi
$answerErrors    = [];   // lỗi cho đáp án
$thong_bao       = '';

// =======================
// THÔNG BÁO (msg trên URL)
// =======================
if (isset($_GET['msg'])) {
    $mapMsg = [
        'them_thanh_cong'                 => "Thêm bài thi thành công.",
        'cap_nhat_thanh_cong'             => "Cập nhật bài thi thành công.",
        'xoa_thanh_cong'                  => "Xóa bài thi thành công.",
        'them_cau_hoi_thanh_cong'         => "Thêm câu hỏi thành công.",
        'cap_nhat_cau_hoi_thanh_cong'     => "Cập nhật câu hỏi thành công.",
        'xoa_cau_hoi_thanh_cong'          => "Xóa câu hỏi thành công.",
        'them_dap_an_thanh_cong'          => "Thêm đáp án thành công.",
        'cap_nhat_dap_an_thanh_cong'      => "Cập nhật đáp án thành công.",
        'xoa_dap_an_thanh_cong'           => "Xóa đáp án thành công.",
        'chon_dap_an_dung_thanh_cong'     => "Cập nhật đáp án đúng thành công.",
        'loi_he_thong'                    => "Có lỗi hệ thống, vui lòng thử lại.",
        'loi_id'                          => "ID không hợp lệ."
    ];
    $thong_bao = $mapMsg[$_GET['msg']] ?? '';
}

// =======================
// LẤY DANH SÁCH KHÓA HỌC
// =======================
$ds_khoa_hoc = [];
try {
    $stmt = $pdo->query("SELECT id, ten_khoa_hoc FROM khoa_hoc ORDER BY ten_khoa_hoc");
    $ds_khoa_hoc = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Lỗi khi lấy danh sách khóa học: " . $e->getMessage();
}

// =======================
// BIẾN DÙNG CHO FORM
// =======================
$editing_exam_id      = 0;
$editing_exam_row     = null;
$editing_question_row = null;
$editing_answer_row   = null;

// =======================
// HÀNH ĐỘNG GET: XÓA / CHỌN ĐÁP ÁN ĐÚNG
// =======================

// Xóa bài thi
if (isset($_GET['delete'])) {
    $id_xoa = (int)$_GET['delete'];
    if ($id_xoa <= 0) {
        header("Location: admin_exams.php?msg=loi_id");
        exit;
    }
    try {
        $stmt = $pdo->prepare("DELETE FROM bai_thi WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id_xoa]);
        header("Location: admin_exams.php?msg=xoa_thanh_cong");
        exit;
    } catch (PDOException $e) {
        header("Location: admin_exams.php?msg=loi_he_thong");
        exit;
    }
}

// Xóa câu hỏi
if (isset($_GET['q_delete'])) {
    $q_id    = (int)$_GET['q_delete'];
    $exam_id = (int)($_GET['edit'] ?? 0);
    if ($q_id <= 0 || $exam_id <= 0) {
        header("Location: admin_exams.php?msg=loi_id");
        exit;
    }
    try {
        $stmt = $pdo->prepare("DELETE FROM cau_hoi WHERE id = :id AND id_bai_thi = :bt");
        $stmt->execute([':id' => $q_id, ':bt' => $exam_id]);
        header("Location: admin_exams.php?edit={$exam_id}&msg=xoa_cau_hoi_thanh_cong");
        exit;
    } catch (PDOException $e) {
        header("Location: admin_exams.php?edit={$exam_id}&msg=loi_he_thong");
        exit;
    }
}

// Xóa đáp án
if (isset($_GET['ans_delete'])) {
    $ans_id  = (int)$_GET['ans_delete'];
    $q_id    = (int)($_GET['q_edit'] ?? 0);
    $exam_id = (int)($_GET['edit'] ?? 0);
    if ($ans_id <= 0 || $q_id <= 0 || $exam_id <= 0) {
        header("Location: admin_exams.php?msg=loi_id");
        exit;
    }
    try {
        $stmt = $pdo->prepare("DELETE FROM lua_chon WHERE id = :id AND id_cau_hoi = :q");
        $stmt->execute([':id' => $ans_id, ':q' => $q_id]);
        header("Location: admin_exams.php?edit={$exam_id}&q_edit={$q_id}&msg=xoa_dap_an_thanh_cong");
        exit;
    } catch (PDOException $e) {
        header("Location: admin_exams.php?edit={$exam_id}&q_edit={$q_id}&msg=loi_he_thong");
        exit;
    }
}

// Chọn / bỏ chọn đáp án đúng qua GET
if (isset($_GET['ans_correct'])) {
    $ans_id  = (int)$_GET['ans_correct'];
    $q_id    = (int)($_GET['q_edit'] ?? 0);
    $exam_id = (int)($_GET['edit'] ?? 0);
    if ($ans_id <= 0 || $q_id <= 0 || $exam_id <= 0) {
        header("Location: admin_exams.php?msg=loi_id");
        exit;
    }
    try {
        $stmt = $pdo->prepare("
            SELECT ch.loai_cau_hoi, lc.la_dap_an_dung
            FROM cau_hoi ch
            JOIN lua_chon lc ON ch.id = lc.id_cau_hoi
            WHERE ch.id = :q AND ch.id_bai_thi = :bt AND lc.id = :ans
        ");
        $stmt->execute([
            ':q'   => $q_id,
            ':bt'  => $exam_id,
            ':ans' => $ans_id
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $loai = $row['loai_cau_hoi'];
            $cur  = (int)$row['la_dap_an_dung'];

            if ($loai === 'nhieu_dap_an') {
                // toggle
                $new = $cur ? 0 : 1;
                $stmt = $pdo->prepare("UPDATE lua_chon SET la_dap_an_dung = :new WHERE id = :id");
                $stmt->execute([':new' => $new, ':id' => $ans_id]);
            } else {
                // chỉ 1 đáp án đúng
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("UPDATE lua_chon SET la_dap_an_dung = 0 WHERE id_cau_hoi = :q");
                $stmt->execute([':q' => $q_id]);
                $stmt = $pdo->prepare("UPDATE lua_chon SET la_dap_an_dung = 1 WHERE id = :id");
                $stmt->execute([':id' => $ans_id]);
                $pdo->commit();
            }
            header("Location: admin_exams.php?edit={$exam_id}&q_edit={$q_id}&msg=chon_dap_an_dung_thanh_cong");
            exit;
        } else {
            header("Location: admin_exams.php?edit={$exam_id}&q_edit={$q_id}&msg=loi_he_thong");
            exit;
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        header("Location: admin_exams.php?edit={$exam_id}&q_edit={$q_id}&msg=loi_he_thong");
        exit;
    }
}

// =======================
// XỬ LÝ POST (CẬP NHẬT / THÊM)
// =======================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = $_POST['mode'] ?? '';

    // 1. Cập nhật bài thi
    if ($mode === 'exam_update') {
        $editing_exam_id = (int)($_POST['id'] ?? 0);
        $tieu_de         = trim($_POST['tieu_de'] ?? '');
        $id_khoa_hoc     = (int)($_POST['id_khoa_hoc'] ?? 0);
        $mo_ta           = trim($_POST['mo_ta'] ?? '');
        $thoi_gian_bat_dau  = $_POST['thoi_gian_bat_dau'] ?: null;
        $thoi_gian_ket_thuc = $_POST['thoi_gian_ket_thuc'] ?: null;
        $thoi_luong_phut = (int)($_POST['thoi_luong_phut'] ?? 60);
        $gioi_han_so_lan = (int)($_POST['gioi_han_so_lan'] ?? 1);
        $tron_cau_hoi    = isset($_POST['tron_cau_hoi']) ? 1 : 0;
        $trang_thai      = $_POST['trang_thai'] ?? 'nhap';

        if ($editing_exam_id <= 0) {
            $errors[] = "ID bài thi không hợp lệ.";
        }
        if ($tieu_de === '') {
            $errors[] = "Tiêu đề bài thi không được để trống.";
        }
        if ($id_khoa_hoc <= 0) {
            $errors[] = "Vui lòng chọn khóa học.";
        }
        if (!in_array($trang_thai, ['nhap','dang_mo','dong'], true)) {
            $errors[] = "Trạng thái bài thi không hợp lệ.";
        }

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE bai_thi
                    SET tieu_de            = :t,
                        id_khoa_hoc        = :kh,
                        mo_ta              = :m,
                        thoi_gian_bat_dau  = :bd,
                        thoi_gian_ket_thuc = :kt,
                        thoi_luong_phut    = :tl,
                        gioi_han_so_lan    = :sl,
                        tron_cau_hoi       = :tron,
                        trang_thai         = :tt,
                        ngay_cap_nhat      = NOW()
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':t'    => $tieu_de,
                    ':kh'   => $id_khoa_hoc,
                    ':m'    => $mo_ta,
                    ':bd'   => $thoi_gian_bat_dau,
                    ':kt'   => $thoi_gian_ket_thuc,
                    ':tl'   => $thoi_luong_phut,
                    ':sl'   => $gioi_han_so_lan,
                    ':tron' => $tron_cau_hoi,
                    ':tt'   => $trang_thai,
                    ':id'   => $editing_exam_id
                ]);
                header("Location: admin_exams.php?edit={$editing_exam_id}&msg=cap_nhat_thanh_cong");
                exit;
            } catch (PDOException $e) {
                $errors[] = "Lỗi khi cập nhật bài thi: " . $e->getMessage();
            }
        }

        $editing_exam_row = [
            'id'                 => $editing_exam_id,
            'tieu_de'            => $tieu_de,
            'id_khoa_hoc'        => $id_khoa_hoc,
            'mo_ta'              => $mo_ta,
            'thoi_gian_bat_dau'  => $thoi_gian_bat_dau,
            'thoi_gian_ket_thuc' => $thoi_gian_ket_thuc,
            'thoi_luong_phut'    => $thoi_luong_phut,
            'gioi_han_so_lan'    => $gioi_han_so_lan,
            'tron_cau_hoi'       => $tron_cau_hoi,
            'trang_thai'         => $trang_thai,
        ];
    }

    // 2. Thêm câu hỏi
    if ($mode === 'q_add') {
        $exam_id      = (int)($_POST['exam_id'] ?? 0);
        $noi_dung     = trim($_POST['noi_dung'] ?? '');
        $loai_cau_hoi = $_POST['loai_cau_hoi'] ?? 'mot_dap_an';
        $diem_so      = (float)($_POST['diem_so'] ?? 1);
        $thu_tu       = (int)($_POST['thu_tu'] ?? 1);

        $editing_exam_id = $exam_id;

        if ($exam_id <= 0) {
            $questionErrors[] = "Bài thi không hợp lệ.";
        }
        if ($noi_dung === '') {
            $questionErrors[] = "Nội dung câu hỏi không được để trống.";
        }
        if (!in_array($loai_cau_hoi, ['mot_dap_an','nhieu_dap_an','dung_sai','tu_luan_ngan','tu_luan'], true)) {
            $questionErrors[] = "Loại câu hỏi không hợp lệ.";
        }

        if (empty($questionErrors)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO cau_hoi (id_bai_thi, noi_dung_cau_hoi, loai_cau_hoi, diem_so, thu_tu)
                    VALUES (:bt, :nd, :loai, :diem, :tt)
                ");
                $stmt->execute([
                    ':bt'   => $exam_id,
                    ':nd'   => $noi_dung,
                    ':loai' => $loai_cau_hoi,
                    ':diem' => $diem_so,
                    ':tt'   => $thu_tu
                ]);
                header("Location: admin_exams.php?edit={$exam_id}&msg=them_cau_hoi_thanh_cong");
                exit;
            } catch (PDOException $e) {
                $questionErrors[] = "Lỗi khi thêm câu hỏi: " . $e->getMessage();
            }
        }
    }

    // 3. Cập nhật câu hỏi
    if ($mode === 'q_update') {
        $exam_id      = (int)($_POST['exam_id'] ?? 0);
        $q_id         = (int)($_POST['q_id'] ?? 0);
        $noi_dung     = trim($_POST['noi_dung'] ?? '');
        $loai_cau_hoi = $_POST['loai_cau_hoi'] ?? 'mot_dap_an';
        $diem_so      = (float)($_POST['diem_so'] ?? 1);
        $thu_tu       = (int)($_POST['thu_tu'] ?? 1);

        $editing_exam_id = $exam_id;

        if ($exam_id <= 0 || $q_id <= 0) {
            $questionErrors[] = "Bài thi hoặc câu hỏi không hợp lệ.";
        }
        if ($noi_dung === '') {
            $questionErrors[] = "Nội dung câu hỏi không được để trống.";
        }
        if (!in_array($loai_cau_hoi, ['mot_dap_an','nhieu_dap_an','dung_sai','tu_luan_ngan','tu_luan'], true)) {
            $questionErrors[] = "Loại câu hỏi không hợp lệ.";
        }

        if (empty($questionErrors)) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE cau_hoi
                    SET noi_dung_cau_hoi = :nd,
                        loai_cau_hoi     = :loai,
                        diem_so          = :diem,
                        thu_tu           = :tt
                    WHERE id = :id AND id_bai_thi = :bt
                ");
                $stmt->execute([
                    ':nd'   => $noi_dung,
                    ':loai' => $loai_cau_hoi,
                    ':diem' => $diem_so,
                    ':tt'   => $thu_tu,
                    ':id'   => $q_id,
                    ':bt'   => $exam_id
                ]);
                header("Location: admin_exams.php?edit={$exam_id}&q_edit={$q_id}&msg=cap_nhat_cau_hoi_thanh_cong");
                exit;
            } catch (PDOException $e) {
                $questionErrors[] = "Lỗi khi cập nhật câu hỏi: " . $e->getMessage();
            }
        }

        $editing_question_row = [
            'id'               => $q_id,
            'id_bai_thi'       => $exam_id,
            'noi_dung_cau_hoi' => $noi_dung,
            'loai_cau_hoi'     => $loai_cau_hoi,
            'diem_so'          => $diem_so,
            'thu_tu'           => $thu_tu
        ];
    }

    // 4. Thêm đáp án
    if ($mode === 'ans_add') {
        $exam_id     = (int)($_POST['exam_id'] ?? 0);
        $q_id        = (int)($_POST['question_id'] ?? 0);
        $noi_dung_da = trim($_POST['noi_dung_da'] ?? '');
        $thu_tu_da   = (int)($_POST['thu_tu_da'] ?? 1);
        $is_correct  = isset($_POST['la_dap_an_dung']) ? 1 : 0;

        $editing_exam_id = $exam_id;

        if ($exam_id <= 0 || $q_id <= 0) {
            $answerErrors[] = "Bài thi hoặc câu hỏi không hợp lệ.";
        }
        if ($noi_dung_da === '') {
            $answerErrors[] = "Nội dung đáp án không được để trống.";
        }

        // Lấy loại câu hỏi
        $loai_cau_hoi = null;
        if (empty($answerErrors)) {
            $stmt = $pdo->prepare("SELECT loai_cau_hoi FROM cau_hoi WHERE id = :id AND id_bai_thi = :bt");
            $stmt->execute([':id' => $q_id, ':bt' => $exam_id]);
            $rowQ = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$rowQ) {
                $answerErrors[] = "Câu hỏi không tồn tại.";
            } else {
                $loai_cau_hoi = $rowQ['loai_cau_hoi'];
            }
        }

        if (empty($answerErrors)) {
            try {
                $pdo->beginTransaction();

                // Nếu chỉ một đáp án đúng → reset trước
                if ($is_correct == 1 && in_array($loai_cau_hoi, ['mot_dap_an','dung_sai'], true)) {
                    $stmt = $pdo->prepare("UPDATE lua_chon SET la_dap_an_dung = 0 WHERE id_cau_hoi = :q");
                    $stmt->execute([':q' => $q_id]);
                }

                $stmt = $pdo->prepare("
                    INSERT INTO lua_chon (id_cau_hoi, noi_dung_lua_chon, la_dap_an_dung, thu_tu)
                    VALUES (:q, :nd, :dung, :tt)
                ");
                $stmt->execute([
                    ':q'    => $q_id,
                    ':nd'   => $noi_dung_da,
                    ':dung' => $is_correct,
                    ':tt'   => $thu_tu_da
                ]);

                $pdo->commit();
                header("Location: admin_exams.php?edit={$exam_id}&q_edit={$q_id}&msg=them_dap_an_thanh_cong");
                exit;
            } catch (PDOException $e) {
                $pdo->rollBack();
                $answerErrors[] = "Lỗi khi thêm đáp án: " . $e->getMessage();
            }
        }
    }

    // 5. Cập nhật đáp án
    if ($mode === 'ans_update') {
        $exam_id     = (int)($_POST['exam_id'] ?? 0);
        $q_id        = (int)($_POST['question_id'] ?? 0);
        $ans_id      = (int)($_POST['ans_id'] ?? 0);
        $noi_dung_da = trim($_POST['noi_dung_da'] ?? '');
        $thu_tu_da   = (int)($_POST['thu_tu_da'] ?? 1);
        $is_correct  = isset($_POST['la_dap_an_dung']) ? 1 : 0;

        $editing_exam_id = $exam_id;

        if ($exam_id <= 0 || $q_id <= 0 || $ans_id <= 0) {
            $answerErrors[] = "Thông tin bài thi / câu hỏi / đáp án không hợp lệ.";
        }
        if ($noi_dung_da === '') {
            $answerErrors[] = "Nội dung đáp án không được để trống.";
        }

        // Lấy loại câu hỏi
        $loai_cau_hoi = null;
        if (empty($answerErrors)) {
            $stmt = $pdo->prepare("SELECT loai_cau_hoi FROM cau_hoi WHERE id = :id AND id_bai_thi = :bt");
            $stmt->execute([':id' => $q_id, ':bt' => $exam_id]);
            $rowQ = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$rowQ) {
                $answerErrors[] = "Câu hỏi không tồn tại.";
            } else {
                $loai_cau_hoi = $rowQ['loai_cau_hoi'];
            }
        }

        if (empty($answerErrors)) {
            try {
                $pdo->beginTransaction();

                if ($is_correct == 1 && in_array($loai_cau_hoi, ['mot_dap_an','dung_sai'], true)) {
                    $stmt = $pdo->prepare("UPDATE lua_chon SET la_dap_an_dung = 0 WHERE id_cau_hoi = :q");
                    $stmt->execute([':q' => $q_id]);
                }

                $stmt = $pdo->prepare("
                    UPDATE lua_chon
                    SET noi_dung_lua_chon = :nd,
                        la_dap_an_dung    = :dung,
                        thu_tu            = :tt
                    WHERE id = :id AND id_cau_hoi = :q
                ");
                $stmt->execute([
                    ':nd'   => $noi_dung_da,
                    ':dung' => $is_correct,
                    ':tt'   => $thu_tu_da,
                    ':id'   => $ans_id,
                    ':q'    => $q_id
                ]);

                $pdo->commit();
                header("Location: admin_exams.php?edit={$exam_id}&q_edit={$q_id}&msg=cap_nhat_dap_an_thanh_cong");
                exit;
            } catch (PDOException $e) {
                $pdo->rollBack();
                $answerErrors[] = "Lỗi khi cập nhật đáp án: " . $e->getMessage();
            }
        }

        $editing_answer_row = [
            'id'                => $ans_id,
            'id_cau_hoi'        => $q_id,
            'noi_dung_lua_chon' => $noi_dung_da,
            'la_dap_an_dung'    => $is_correct,
            'thu_tu'            => $thu_tu_da
        ];
    }
}

// =======================
// LOAD BÀI THI ĐANG SỬA
// =======================
if ($editing_exam_row === null && isset($_GET['edit'])) {
    $editing_exam_id = (int)$_GET['edit'];
    if ($editing_exam_id > 0) {
        try {
            $stmt = $pdo->prepare("
                SELECT bt.*, kh.ten_khoa_hoc
                FROM bai_thi bt
                LEFT JOIN khoa_hoc kh ON bt.id_khoa_hoc = kh.id
                WHERE bt.id = :id
                LIMIT 1
            ");
            $stmt->execute([':id' => $editing_exam_id]);
            $editing_exam_row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$editing_exam_row) {
                $editing_exam_id = 0;
            }
        } catch (PDOException $e) {
            $errors[] = "Lỗi khi tải bài thi: " . $e->getMessage();
            $editing_exam_id = 0;
        }
    } else {
        $editing_exam_id = 0;
    }
}

// =======================
// LOAD CÂU HỎI ĐANG SỬA
// =======================
if ($editing_question_row === null && isset($_GET['q_edit']) && $editing_exam_id > 0) {
    $q_id = (int)$_GET['q_edit'];
    if ($q_id > 0) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM cau_hoi WHERE id = :id AND id_bai_thi = :bt");
            $stmt->execute([':id' => $q_id, ':bt' => $editing_exam_id]);
            $editing_question_row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $questionErrors[] = "Lỗi khi tải câu hỏi: " . $e->getMessage();
        }
    }
}

// =======================
// LỌC & LẤY DANH SÁCH BÀI THI
// =======================
$id_kh_filter = $_GET['khoa_hoc'] ?? 'tat_ca';
$tu_khoa      = trim($_GET['q'] ?? '');

$ds_bai_thi   = [];
$error_list   = '';

try {
    $sql = "
        SELECT bt.*, kh.ten_khoa_hoc
        FROM bai_thi bt
        LEFT JOIN khoa_hoc kh ON bt.id_khoa_hoc = kh.id
        WHERE 1=1
    ";
    $params = [];

    if ($id_kh_filter !== 'tat_ca') {
        $sql .= " AND bt.id_khoa_hoc = :kh";
        $params[':kh'] = (int)$id_kh_filter;
    }

    if ($tu_khoa !== '') {
        $sql .= " AND bt.tieu_de LIKE :kw";
        $params[':kw'] = '%' . $tu_khoa . '%';
    }

    $sql .= " ORDER BY bt.ngay_tao DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $ds_bai_thi = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_list = $e->getMessage();
}

// =======================
// LẤY DS CÂU HỎI CỦA BÀI THI
// =======================
$ds_cau_hoi = [];
if ($editing_exam_id > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT *
            FROM cau_hoi
            WHERE id_bai_thi = :bt
            ORDER BY thu_tu ASC, id ASC
        ");
        $stmt->execute([':bt' => $editing_exam_id]);
        $ds_cau_hoi = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $questionErrors[] = "Lỗi khi lấy danh sách câu hỏi: " . $e->getMessage();
    }
}

// =======================
// LẤY DS ĐÁP ÁN (NẾU CÂU HỎI TRẮC NGHIỆM)
// =======================
$ds_dap_an     = [];
$isTracNghiem  = false;

if (!empty($editing_question_row)) {
    $isTracNghiem = in_array(
        $editing_question_row['loai_cau_hoi'],
        ['mot_dap_an','nhieu_dap_an','dung_sai'],
        true
    );

    if ($isTracNghiem) {
        try {
            $stmt = $pdo->prepare("
                SELECT *
                FROM lua_chon
                WHERE id_cau_hoi = :q
                ORDER BY thu_tu ASC, id ASC
            ");
            $stmt->execute([':q' => $editing_question_row['id']]);
            $ds_dap_an = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $answerErrors[] = "Lỗi khi lấy danh sách đáp án: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý bài thi & câu hỏi</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<!-- Thanh đỏ trên cùng -->
<div class="top-bar">
    <div class="container d-flex justify-content-between align-items-center">
        <div>
            <i class="bi bi-house-door-fill"></i>
            <a href="admin_dashboard.php">Trang quản trị</a>
        </div>
        <div>
            Xin chào, <strong><?php echo htmlspecialchars($ho_ten_admin); ?></strong>
            &nbsp;|&nbsp;
            <a href="dang_xuat.php" style="color:#fff;">Đăng xuất</a>
        </div>
    </div>
</div>

<header class="main-header">
    <div class="container py-2 d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center">
            <img src="image/ptit.png" alt="PTIT Logo" style="height:55px;" class="me-3">
            <div>
                <div class="logo-text">QUẢN LÝ BÀI THI & CÂU HỎI</div>
                <div class="logo-subtext">Danh sách, sửa, xóa bài thi; thêm, sửa, xóa câu hỏi & đáp án</div>
            </div>
        </div>

        <a href="admin_dashboard.php" class="btn btn-sm btn-outline-secondary">
            &laquo; Về trang tổng quan
        </a>
    </div>
</header>

<div class="container mt-4 mb-5">

    <h4 class="mb-3" style="color:#b30000;">Danh sách bài thi</h4>

    <?php if ($thong_bao): ?>
        <div class="alert alert-info">
            <?php echo htmlspecialchars($thong_bao, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $e): ?>
                    <li><?php echo htmlspecialchars($e, ENT_QUOTES, 'UTF-8'); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($error_list): ?>
        <div class="alert alert-danger">
            Lỗi khi lấy danh sách bài thi: <?php echo htmlspecialchars($error_list, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <!-- BỘ LỌC -->
    <form class="row g-2 mb-3" method="get">
        <div class="col-md-3">
            <select name="khoa_hoc" class="form-select">
                <option value="tat_ca">Tất cả khóa học</option>
                <?php foreach ($ds_khoa_hoc as $kh): ?>
                    <option value="<?php echo $kh['id']; ?>"
                        <?php if ($id_kh_filter == $kh['id']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($kh['ten_khoa_hoc']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-3">
            <input type="text"
                   name="q"
                   class="form-control"
                   placeholder="Tìm theo tiêu đề bài thi..."
                   value="<?php echo htmlspecialchars($tu_khoa, ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-md-2 d-grid">
            <button class="btn btn-primary" type="submit">
                <i class="bi bi-search"></i>
            </button>
        </div>

        <div class="col-md-2 d-grid">
            <a href="exam_add.php" class="btn btn-success">
                <i class="bi bi-plus-circle"></i> Thêm bài thi
            </a>
        </div>
    </form>

    <!-- DANH SÁCH BÀI THI -->
    <div class="table-responsive mb-4">
        <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
            <tr>
                <th>#</th>
                <th>Tiêu đề</th>
                <th>Khóa học</th>
                <th>Thời lượng</th>
                <th>Lần thi</th>
                <th>Trạng thái</th>
                <th>Ngày tạo</th>
                <th>Hành động</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($ds_bai_thi)): ?>
                <tr>
                    <td colspan="8" class="text-center text-muted">Chưa có bài thi nào.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($ds_bai_thi as $i => $bt): ?>
                    <tr <?php if ($editing_exam_id == $bt['id']) echo 'class="table-warning"'; ?>>
                        <td><?php echo $i + 1; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($bt['tieu_de']); ?></strong><br>
                            <small class="text-muted">
                                <?php echo htmlspecialchars($bt['mo_ta'] ?? ''); ?>
                            </small>
                        </td>
                        <td><?php echo htmlspecialchars($bt['ten_khoa_hoc'] ?? ''); ?></td>
                        <td><?php echo (int)$bt['thoi_luong_phut']; ?> phút</td>
                        <td><?php echo (int)$bt['gioi_han_so_lan']; ?></td>
                        <td>
                            <?php
                            if ($bt['trang_thai'] === 'nhap') {
                                echo '<span class="badge bg-secondary">Nháp</span>';
                            } elseif ($bt['trang_thai'] === 'dang_mo') {
                                echo '<span class="badge bg-success">Đang mở</span>';
                            } else {
                                echo '<span class="badge bg-danger">Đóng</span>';
                            }
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars($bt['ngay_tao']); ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="admin_exams.php?edit=<?php echo $bt['id']; ?>"
                                   class="btn btn-outline-primary" title="Sửa & quản lý câu hỏi">
                                    <i class="bi bi-pencil-square"></i>
                                </a>
                                <a href="admin_exams.php?delete=<?php echo $bt['id']; ?>"
                                   class="btn btn-outline-danger"
                                   onclick="return confirm('Bạn có chắc chắn muốn xóa bài thi này?');"
                                   title="Xóa bài thi">
                                    <i class="bi bi-trash3"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- FORM SỬA BÀI THI + QUẢN LÝ CÂU HỎI & ĐÁP ÁN -->
    <?php if ($editing_exam_id > 0 && $editing_exam_row): ?>

        <hr class="my-4">

        <h4 class="mb-3" style="color:#b30000;">Sửa bài thi & quản lý câu hỏi</h4>

        <!-- FORM SỬA BÀI THI -->
        <div class="card mb-4">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <strong>Sửa bài thi: <?php echo htmlspecialchars($editing_exam_row['tieu_de']); ?></strong>
                <a href="admin_exams.php" class="btn btn-sm btn-outline-secondary">
                    Đóng form
                </a>
            </div>
            <div class="card-body">
                <form method="post" class="row g-3">
                    <input type="hidden" name="mode" value="exam_update">
                    <input type="hidden" name="id" value="<?php echo (int)$editing_exam_row['id']; ?>">

                    <div class="col-md-6">
                        <label class="form-label">Tiêu đề bài thi <span class="text-danger">*</span></label>
                        <input type="text" name="tieu_de" class="form-control"
                               value="<?php echo htmlspecialchars($editing_exam_row['tieu_de']); ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Thuộc khóa học <span class="text-danger">*</span></label>
                        <select name="id_khoa_hoc" class="form-select" required>
                            <option value="">-- Chọn khóa học --</option>
                            <?php foreach ($ds_khoa_hoc as $kh): ?>
                                <option value="<?php echo $kh['id']; ?>"
                                    <?php if ((int)$editing_exam_row['id_khoa_hoc'] === (int)$kh['id']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($kh['ten_khoa_hoc']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Mô tả</label>
                        <textarea name="mo_ta" class="form-control" rows="3"><?php
                            echo htmlspecialchars($editing_exam_row['mo_ta'] ?? '');
                        ?></textarea>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Thời gian mở</label>
                        <input type="datetime-local" name="thoi_gian_bat_dau" class="form-control"
                               value="<?php echo htmlspecialchars($editing_exam_row['thoi_gian_bat_dau'] ?? ''); ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Thời gian đóng</label>
                        <input type="datetime-local" name="thoi_gian_ket_thuc" class="form-control"
                               value="<?php echo htmlspecialchars($editing_exam_row['thoi_gian_ket_thuc'] ?? ''); ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Thời lượng (phút)</label>
                        <input type="number" name="thoi_luong_phut" min="1" class="form-control"
                               value="<?php echo (int)($editing_exam_row['thoi_luong_phut'] ?? 60); ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Giới hạn số lần thi</label>
                        <input type="number" name="gioi_han_so_lan" min="1" class="form-control"
                               value="<?php echo (int)($editing_exam_row['gioi_han_so_lan'] ?? 1); ?>">
                    </div>

                    <div class="col-md-4 d-flex align-items-center">
                        <div class="form-check mt-4">
                            <input type="checkbox" name="tron_cau_hoi" class="form-check-input"
                                <?php if (!empty($editing_exam_row['tron_cau_hoi'])) echo 'checked'; ?>>
                            <label class="form-check-label">Xáo trộn câu hỏi</label>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Trạng thái</label>
                        <select name="trang_thai" class="form-select">
                            <option value="nhap"    <?php if ($editing_exam_row['trang_thai'] === 'nhap') echo 'selected'; ?>>Nháp</option>
                            <option value="dang_mo" <?php if ($editing_exam_row['trang_thai'] === 'dang_mo') echo 'selected'; ?>>Đang mở</option>
                            <option value="dong"    <?php if ($editing_exam_row['trang_thai'] === 'dong') echo 'selected'; ?>>Đóng</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Lưu thay đổi bài thi
                        </button>
                        <a href="admin_exams.php" class="btn btn-secondary">Hủy</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- LỖI CÂU HỎI / ĐÁP ÁN -->
        <?php if (!empty($questionErrors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($questionErrors as $e): ?>
                        <li><?php echo htmlspecialchars($e, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($answerErrors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($answerErrors as $e): ?>
                        <li><?php echo htmlspecialchars($e, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- DANH SÁCH CÂU HỎI -->
        <h5 class="mb-3">Danh sách câu hỏi trong bài thi</h5>

        <div class="table-responsive mb-3">
            <table class="table table-striped table-hover align-middle">
                <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Nội dung câu hỏi</th>
                    <th>Loại</th>
                    <th>Điểm</th>
                    <th>Thứ tự</th>
                    <th>Hành động</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($ds_cau_hoi)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted">
                            Chưa có câu hỏi nào cho bài thi này.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($ds_cau_hoi as $i => $ch): ?>
                        <tr <?php
                            if (!empty($editing_question_row) && $editing_question_row['id'] == $ch['id']) {
                                echo 'class="table-warning"';
                            }
                        ?>>
                            <td><?php echo $i + 1; ?></td>
                            <td><?php echo nl2br(htmlspecialchars($ch['noi_dung_cau_hoi'])); ?></td>
                            <td><?php echo htmlspecialchars($ch['loai_cau_hoi']); ?></td>
                            <td><?php echo htmlspecialchars($ch['diem_so']); ?></td>
                            <td><?php echo (int)$ch['thu_tu']; ?></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="admin_exams.php?edit=<?php echo $editing_exam_id; ?>&q_edit=<?php echo $ch['id']; ?>"
                                       class="btn btn-outline-primary" title="Sửa câu hỏi & đáp án">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                    <a href="admin_exams.php?edit=<?php echo $editing_exam_id; ?>&q_delete=<?php echo $ch['id']; ?>"
                                       class="btn btn-outline-danger"
                                       onclick="return confirm('Xóa câu hỏi này?');"
                                       title="Xóa câu hỏi">
                                        <i class="bi bi-trash3"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- FORM SỬA CÂU HỎI (NẾU ĐANG EDIT) -->
        <?php if (!empty($editing_question_row)): ?>
            <div class="card mb-4">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <strong>Sửa câu hỏi</strong>
                    <a href="admin_exams.php?edit=<?php echo $editing_exam_id; ?>"
                       class="btn btn-sm btn-outline-secondary">Đóng form câu hỏi</a>
                </div>
                <div class="card-body">
                    <form method="post" class="row g-3">
                        <input type="hidden" name="mode" value="q_update">
                        <input type="hidden" name="exam_id" value="<?php echo $editing_exam_id; ?>">
                        <input type="hidden" name="q_id" value="<?php echo $editing_question_row['id']; ?>">

                        <div class="col-12">
                            <label class="form-label">Nội dung câu hỏi <span class="text-danger">*</span></label>
                            <textarea name="noi_dung" class="form-control" rows="3" required><?php
                                echo htmlspecialchars($editing_question_row['noi_dung_cau_hoi']);
                            ?></textarea>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Loại câu hỏi</label>
                            <select name="loai_cau_hoi" class="form-select">
                                <option value="mot_dap_an"   <?php if ($editing_question_row['loai_cau_hoi'] === 'mot_dap_an')   echo 'selected'; ?>>Trắc nghiệm 1 đáp án</option>
                                <option value="nhieu_dap_an" <?php if ($editing_question_row['loai_cau_hoi'] === 'nhieu_dap_an') echo 'selected'; ?>>Trắc nghiệm nhiều đáp án</option>
                                <option value="dung_sai"     <?php if ($editing_question_row['loai_cau_hoi'] === 'dung_sai')     echo 'selected'; ?>>Đúng / Sai</option>
                                <option value="tu_luan_ngan" <?php if ($editing_question_row['loai_cau_hoi'] === 'tu_luan_ngan') echo 'selected'; ?>>Tự luận ngắn</option>
                                <option value="tu_luan"      <?php if ($editing_question_row['loai_cau_hoi'] === 'tu_luan')      echo 'selected'; ?>>Tự luận</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Điểm số</label>
                            <input type="number" step="0.25" name="diem_so" class="form-control"
                                   value="<?php echo htmlspecialchars($editing_question_row['diem_so']); ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Thứ tự</label>
                            <input type="number" name="thu_tu" class="form-control"
                                   value="<?php echo (int)$editing_question_row['thu_tu']; ?>">
                        </div>

                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Lưu câu hỏi
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- QUẢN LÝ ĐÁP ÁN CHỈ HIỆN KHI LÀ TRẮC NGHIỆM -->
            <?php if ($isTracNghiem): ?>

                <h5 class="mb-3">Đáp án cho câu hỏi này</h5>

                <!-- DANH SÁCH ĐÁP ÁN -->
                <div class="table-responsive mb-3">
                    <table class="table table-striped table-hover align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Nội dung đáp án</th>
                            <th>Đúng?</th>
                            <th>Thứ tự</th>
                            <th>Hành động</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($ds_dap_an)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted">
                                    Chưa có đáp án nào. Hãy thêm đáp án bên dưới.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($ds_dap_an as $i => $da): ?>
                                <tr <?php
                                    if (!empty($editing_answer_row) && $editing_answer_row['id'] == $da['id']) {
                                        echo 'class="table-warning"';
                                    }
                                ?>>
                                    <td><?php echo $i + 1; ?></td>
                                    <td><?php echo nl2br(htmlspecialchars($da['noi_dung_lua_chon'])); ?></td>
                                    <td>
                                        <?php if ($da['la_dap_an_dung']): ?>
                                            <span class="badge bg-success">Đúng</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Sai</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo (int)$da['thu_tu']; ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="admin_exams.php?edit=<?php echo $editing_exam_id; ?>&q_edit=<?php echo $editing_question_row['id']; ?>&ans_correct=<?php echo $da['id']; ?>"
                                               class="btn btn-outline-success"
                                               title="Chọn / bỏ chọn đáp án đúng">
                                                <i class="bi bi-check2-circle"></i>
                                            </a>
                                            <a href="admin_exams.php?edit=<?php echo $editing_exam_id; ?>&q_edit=<?php echo $editing_question_row['id']; ?>&ans_delete=<?php echo $da['id']; ?>"
                                               class="btn btn-outline-danger"
                                               onclick="return confirm('Xóa đáp án này?');"
                                               title="Xóa đáp án">
                                                <i class="bi bi-trash3"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- FORM SỬA ĐÁP ÁN (NẾU ĐANG EDIT) -->
                <?php if (!empty($editing_answer_row)): ?>
                    <div class="card mb-3">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <strong>Sửa đáp án</strong>
                            <a href="admin_exams.php?edit=<?php echo $editing_exam_id; ?>&q_edit=<?php echo $editing_question_row['id']; ?>"
                               class="btn btn-sm btn-outline-secondary">Đóng form đáp án</a>
                        </div>
                        <div class="card-body">
                            <form method="post" class="row g-3">
                                <input type="hidden" name="mode" value="ans_update">
                                <input type="hidden" name="exam_id" value="<?php echo $editing_exam_id; ?>">
                                <input type="hidden" name="question_id" value="<?php echo $editing_question_row['id']; ?>">
                                <input type="hidden" name="ans_id" value="<?php echo $editing_answer_row['id']; ?>">

                                <div class="col-12">
                                    <label class="form-label">Nội dung đáp án <span class="text-danger">*</span></label>
                                    <textarea name="noi_dung_da" class="form-control" rows="2" required><?php
                                        echo htmlspecialchars($editing_answer_row['noi_dung_lua_chon']);
                                    ?></textarea>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Thứ tự</label>
                                    <input type="number" name="thu_tu_da" class="form-control"
                                           value="<?php echo (int)$editing_answer_row['thu_tu']; ?>">
                                </div>

                                <div class="col-md-4 d-flex align-items-center">
                                    <div class="form-check mt-4">
                                        <input type="checkbox" name="la_dap_an_dung" class="form-check-input"
                                            <?php if (!empty($editing_answer_row['la_dap_an_dung'])) echo 'checked'; ?>>
                                        <label class="form-check-label">Là đáp án đúng</label>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-save"></i> Lưu đáp án
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- FORM THÊM ĐÁP ÁN MỚI -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <strong>Thêm đáp án mới</strong>
                    </div>
                    <div class="card-body">
                        <form method="post" class="row g-3">
                            <input type="hidden" name="mode" value="ans_add">
                            <input type="hidden" name="exam_id" value="<?php echo $editing_exam_id; ?>">
                            <input type="hidden" name="question_id" value="<?php echo $editing_question_row['id']; ?>">

                            <div class="col-12">
                                <label class="form-label">Nội dung đáp án <span class="text-danger">*</span></label>
                                <textarea name="noi_dung_da" class="form-control" rows="2" required><?php
                                    echo htmlspecialchars($_POST['noi_dung_da'] ?? '');
                                ?></textarea>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Thứ tự</label>
                                <input type="number" name="thu_tu_da" class="form-control"
                                       value="<?php echo htmlspecialchars($_POST['thu_tu_da'] ?? (count($ds_dap_an) + 1)); ?>">
                            </div>

                            <div class="col-md-4 d-flex align-items-center">
                                <div class="form-check mt-4">
                                    <?php
                                    // Nếu là nhiều đáp án, mặc định tick sẵn khi mở form lần đầu
                                    $defaultChecked = false;
                                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['mode'] ?? '') === 'ans_add') {
                                        $defaultChecked = !empty($_POST['la_dap_an_dung']);
                                    } else {
                                        if (($editing_question_row['loai_cau_hoi'] ?? '') === 'nhieu_dap_an') {
                                            $defaultChecked = true;
                                        }
                                    }
                                    ?>
                                    <input type="checkbox" name="la_dap_an_dung" class="form-check-input"
                                           <?php if ($defaultChecked) echo 'checked'; ?>>
                                    <label class="form-check-label">Là đáp án đúng</label>
                                </div>
                            </div>

                            <div class="col-12">
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-plus-circle"></i> Thêm đáp án
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            <?php endif; ?> <!-- end if isTracNghiem -->

        <?php endif; ?> <!-- end if editing_question_row -->

        <!-- FORM THÊM CÂU HỎI MỚI -->
        <div class="card">
            <div class="card-header bg-light">
                <strong>Thêm câu hỏi mới cho bài thi</strong>
            </div>
            <div class="card-body">
                <form method="post" class="row g-3">
                    <input type="hidden" name="mode" value="q_add">
                    <input type="hidden" name="exam_id" value="<?php echo $editing_exam_id; ?>">

                    <div class="col-12">
                        <label class="form-label">Nội dung câu hỏi <span class="text-danger">*</span></label>
                        <textarea name="noi_dung" class="form-control" rows="3" required><?php
                            echo htmlspecialchars($_POST['noi_dung'] ?? '');
                        ?></textarea>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Loại câu hỏi</label>
                        <?php $loai_default = $_POST['loai_cau_hoi'] ?? 'mot_dap_an'; ?>
                        <select name="loai_cau_hoi" class="form-select">
                            <option value="mot_dap_an"   <?php if ($loai_default === 'mot_dap_an')   echo 'selected'; ?>>Trắc nghiệm 1 đáp án</option>
                            <option value="nhieu_dap_an" <?php if ($loai_default === 'nhieu_dap_an') echo 'selected'; ?>>Trắc nghiệm nhiều đáp án</option>
                            <option value="dung_sai"     <?php if ($loai_default === 'dung_sai')     echo 'selected'; ?>>Đúng / Sai</option>
                            <option value="tu_luan_ngan" <?php if ($loai_default === 'tu_luan_ngan') echo 'selected'; ?>>Tự luận ngắn</option>
                            <option value="tu_luan"      <?php if ($loai_default === 'tu_luan')      echo 'selected'; ?>>Tự luận</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Điểm số</label>
                        <input type="number" step="0.25" name="diem_so" class="form-control"
                               value="<?php echo htmlspecialchars($_POST['diem_so'] ?? '1'); ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Thứ tự</label>
                        <input type="number" name="thu_tu" class="form-control"
                               value="<?php echo htmlspecialchars($_POST['thu_tu'] ?? (count($ds_cau_hoi) + 1)); ?>">
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-plus-circle"></i> Thêm câu hỏi
                        </button>
                    </div>
                </form>
            </div>
        </div>

    <?php endif; ?>

</div>

<footer>
    <div class="container text-center">
        © <?php echo date('Y'); ?> Hệ thống E-learning PTIT - Quản lý bài thi & câu hỏi.
    </div>
</footer>

</body>
</html>
