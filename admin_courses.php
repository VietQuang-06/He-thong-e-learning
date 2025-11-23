<?php
session_start();
require_once 'config.php';

// Chỉ cho phép Admin truy cập
if (!isset($_SESSION['user_id']) || ($_SESSION['vai_tro'] ?? '') !== 'quan_tri') {
    header("Location: dang_nhap.php");
    exit;
}

$ho_ten_admin = $_SESSION['ho_ten'] ?? 'Quản trị hệ thống';

$pdo    = Database::pdo();
$errors = [];
$thong_bao = '';

// =========================
// THÔNG BÁO TỪ QUERY STRING
// =========================
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'them_thanh_cong':
            $thong_bao = "Thêm khóa học thành công.";
            break;
        case 'cap_nhat_thanh_cong':
            $thong_bao = "Cập nhật khóa học thành công.";
            break;
        case 'xoa_thanh_cong':
            $thong_bao = "Xóa khóa học thành công.";
            break;
        case 'loi_he_thong':
            $thong_bao = "Có lỗi hệ thống, vui lòng thử lại.";
            break;
        case 'loi_id':
            $thong_bao = "ID khóa học không hợp lệ.";
            break;
    }
}

// =========================
// XÓA KHÓA HỌC (GET ?delete)
// =========================
if (isset($_GET['delete'])) {
    $id_xoa = (int)$_GET['delete'];

    if ($id_xoa <= 0) {
        header("Location: admin_courses.php?msg=loi_id");
        exit;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM khoa_hoc WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id_xoa]);
        header("Location: admin_courses.php?msg=xoa_thanh_cong");
        exit;
    } catch (PDOException $e) {
        header("Location: admin_courses.php?msg=loi_he_thong");
        exit;
    }
}

// ====================================
// LẤY DANH SÁCH GIẢNG VIÊN (dùng chung)
// ====================================
$ds_giang_vien = [];
try {
    $stmt_gv = $pdo->query("
        SELECT id, ho_ten 
        FROM nguoi_dung 
        WHERE vai_tro = 'giang_vien'
        ORDER BY ho_ten
    ");
    $ds_giang_vien = $stmt_gv->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Lỗi hệ thống khi lấy giảng viên: " . $e->getMessage();
}

// =====================================
// XỬ LÝ CẬP NHẬT (SỬA KHÓA HỌC) - POST
// =====================================
$editing_id = 0;          // id khóa học đang sửa (nếu có)
$editing_row = null;      // dữ liệu khóa học đang sửa

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['mode'] ?? '') === 'update') {
    $editing_id        = (int)($_POST['id'] ?? 0);
    $ten_khoa_hoc      = trim($_POST['ten_khoa_hoc'] ?? '');
    $duong_dan_tom_tat = trim($_POST['duong_dan_tom_tat'] ?? '');
    $mo_ta             = trim($_POST['mo_ta'] ?? '');
    $id_giang_vien     = (int)($_POST['id_giang_vien'] ?? 0);
    $danh_muc          = trim($_POST['danh_muc'] ?? '');
    $hoc_phi           = trim($_POST['hoc_phi'] ?? '0');
    $ngay_bat_dau      = $_POST['ngay_bat_dau'] ?: null;
    $ngay_ket_thuc     = $_POST['ngay_ket_thuc'] ?: null;
    $trang_thai        = $_POST['trang_thai'] ?? 'nhap';

    if ($editing_id <= 0) {
        $errors[] = "ID khóa học không hợp lệ.";
    }
    if ($ten_khoa_hoc === '') {
        $errors[] = "Tên khóa học không được để trống.";
    }
    if ($duong_dan_tom_tat === '') {
        $errors[] = "Slug (đường dẫn tóm tắt) không được để trống.";
    }
    if ($id_giang_vien <= 0) {
        $errors[] = "Vui lòng chọn giảng viên phụ trách.";
    }
    if (!in_array($trang_thai, ['nhap','cong_bo','luu_tru'], true)) {
        $errors[] = "Trạng thái không hợp lệ.";
    }
    if ($hoc_phi === '' || !is_numeric($hoc_phi) || (float)$hoc_phi < 0) {
        $errors[] = "Học phí phải là số >= 0.";
    }

    // Kiểm tra slug trùng với khóa học khác
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                SELECT id 
                FROM khoa_hoc 
                WHERE duong_dan_tom_tat = :slug AND id <> :id
                LIMIT 1
            ");
            $stmt->execute([
                ':slug' => $duong_dan_tom_tat,
                ':id'   => $editing_id
            ]);
            if ($stmt->fetch()) {
                $errors[] = "Slug này đã được dùng cho khóa học khác.";
            }
        } catch (PDOException $e) {
            $errors[] = "Lỗi hệ thống khi kiểm tra slug: " . $e->getMessage();
        }
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE khoa_hoc
                SET ten_khoa_hoc = :ten,
                    duong_dan_tom_tat = :slug,
                    mo_ta = :mota,
                    id_giang_vien = :gv,
                    danh_muc = :dm,
                    hoc_phi = :hp,
                    ngay_bat_dau = :nbd,
                    ngay_ket_thuc = :nkt,
                    trang_thai = :tt,
                    ngay_cap_nhat = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                ':ten'  => $ten_khoa_hoc,
                ':slug' => $duong_dan_tom_tat,
                ':mota' => $mo_ta,
                ':gv'   => $id_giang_vien,
                ':dm'   => $danh_muc,
                ':hp'   => (float)$hoc_phi,
                ':nbd'  => $ngay_bat_dau,
                ':nkt'  => $ngay_ket_thuc,
                ':tt'   => $trang_thai,
                ':id'   => $editing_id,
            ]);

            header("Location: admin_courses.php?msg=cap_nhat_thanh_cong");
            exit;
        } catch (PDOException $e) {
            $errors[] = "Lỗi hệ thống khi cập nhật: " . $e->getMessage();
        }
    } else {
        // Nếu có lỗi, giữ lại dữ liệu nhập để hiển thị ở form sửa
        $editing_row = [
            'id'                => $editing_id,
            'ten_khoa_hoc'      => $ten_khoa_hoc,
            'duong_dan_tom_tat' => $duong_dan_tom_tat,
            'mo_ta'             => $mo_ta,
            'id_giang_vien'     => $id_giang_vien,
            'danh_muc'          => $danh_muc,
            'hoc_phi'           => $hoc_phi,
            'ngay_bat_dau'      => $ngay_bat_dau,
            'ngay_ket_thuc'     => $ngay_ket_thuc,
            'trang_thai'        => $trang_thai,
        ];
    }
}

// =======================================
// NẾU CÓ ?edit=ID MÀ CHƯA Ở PHẦN POST LỖI
// =======================================
if ($editing_row === null && isset($_GET['edit'])) {
    $editing_id = (int)$_GET['edit'];
    if ($editing_id > 0) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM khoa_hoc WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $editing_id]);
            $editing_row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$editing_row) {
                $editing_id = 0;
            }
        } catch (PDOException $e) {
            $errors[] = "Lỗi hệ thống khi lấy khóa học: " . $e->getMessage();
            $editing_id = 0;
        }
    } else {
        $editing_id = 0;
    }
}

// =========================
// LỌC & TÌM KIẾM DANH SÁCH
// =========================
$trang_thai_filter = $_GET['trang_thai'] ?? 'tat_ca'; // tat_ca | nhap | cong_bo | luu_tru
$giang_vien_filter = $_GET['giang_vien'] ?? 'tat_ca';
$tu_khoa           = trim($_GET['q'] ?? '');

$ds_khoa_hoc = [];
$error_list  = '';

try {
    $sql = "
        SELECT kh.id,
               kh.ten_khoa_hoc,
               kh.duong_dan_tom_tat,
               kh.mo_ta,
               kh.danh_muc,
               kh.hoc_phi,
               kh.ngay_bat_dau,
               kh.ngay_ket_thuc,
               kh.trang_thai,
               kh.ngay_tao,
               nd.ho_ten AS ten_giang_vien
        FROM khoa_hoc kh
        LEFT JOIN nguoi_dung nd ON kh.id_giang_vien = nd.id
        WHERE 1=1
    ";

    $params = [];

    if ($trang_thai_filter !== 'tat_ca') {
        $sql .= " AND kh.trang_thai = :trang_thai";
        $params[':trang_thai'] = $trang_thai_filter;
    }

    if ($giang_vien_filter !== 'tat_ca') {
        $sql .= " AND kh.id_giang_vien = :id_gv";
        $params[':id_gv'] = (int)$giang_vien_filter;
    }

    if ($tu_khoa !== '') {
        $sql .= " AND (kh.ten_khoa_hoc LIKE :kw 
                   OR kh.duong_dan_tom_tat LIKE :kw 
                   OR kh.danh_muc LIKE :kw)";
        $params[':kw'] = '%' . $tu_khoa . '%';
    }

    $sql .= " ORDER BY kh.ngay_tao DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $ds_khoa_hoc = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_list = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý khóa học - Admin</title>

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
                <div class="logo-text">QUẢN LÝ KHÓA HỌC</div>
                <div class="logo-subtext">Danh sách, sửa, xóa khóa học e-learning</div>
            </div>
        </div>

        <nav>
            <a href="admin_dashboard.php" class="btn btn-sm btn-outline-secondary">
                &laquo; Về trang tổng quan
            </a>
        </nav>
    </div>
</header>

<div class="container mt-4 mb-5">

    <h4 class="mb-3" style="color:#b30000;">Danh sách khóa học</h4>

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
            Lỗi khi lấy danh sách: <?php echo htmlspecialchars($error_list, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <!-- FORM SỬA KHÓA HỌC (NẾU ĐANG EDIT) -->
    <?php if ($editing_id > 0 && $editing_row): ?>
        <div class="card mb-4">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <strong>Sửa khóa học: <?php echo htmlspecialchars($editing_row['ten_khoa_hoc']); ?></strong>
                <a href="admin_courses.php" class="btn btn-sm btn-outline-secondary">
                    Đóng form sửa
                </a>
            </div>
            <div class="card-body">
                <form method="post" class="row g-3">
                    <input type="hidden" name="mode" value="update">
                    <input type="hidden" name="id" value="<?php echo (int)$editing_row['id']; ?>">

                    <div class="col-md-6">
                        <label class="form-label">Tên khóa học <span class="text-danger">*</span></label>
                        <input type="text" name="ten_khoa_hoc" class="form-control"
                               value="<?php echo htmlspecialchars($editing_row['ten_khoa_hoc']); ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Slug (đường dẫn tóm tắt) <span class="text-danger">*</span></label>
                        <input type="text" name="duong_dan_tom_tat" class="form-control"
                               value="<?php echo htmlspecialchars($editing_row['duong_dan_tom_tat']); ?>" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Giảng viên phụ trách</label>
                        <select name="id_giang_vien" class="form-select" required>
                            <option value="">-- Chọn giảng viên --</option>
                            <?php foreach ($ds_giang_vien as $gv): ?>
                                <option value="<?php echo $gv['id']; ?>"
                                    <?php if ((int)$editing_row['id_giang_vien'] === (int)$gv['id']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($gv['ho_ten']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Danh mục</label>
                        <input type="text" name="danh_muc" class="form-control"
                               value="<?php echo htmlspecialchars($editing_row['danh_muc'] ?? ''); ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Học phí (VND)</label>
                        <input type="number" name="hoc_phi" min="0" step="1000" class="form-control"
                               value="<?php echo htmlspecialchars($editing_row['hoc_phi'] ?? 0); ?>">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Ngày bắt đầu</label>
                        <input type="date" name="ngay_bat_dau" class="form-control"
                               value="<?php echo htmlspecialchars($editing_row['ngay_bat_dau'] ?? ''); ?>">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Ngày kết thúc</label>
                        <input type="date" name="ngay_ket_thuc" class="form-control"
                               value="<?php echo htmlspecialchars($editing_row['ngay_ket_thuc'] ?? ''); ?>">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Trạng thái</label>
                        <select name="trang_thai" class="form-select">
                            <option value="nhap"    <?php if ($editing_row['trang_thai'] === 'nhap')    echo 'selected'; ?>>Nháp</option>
                            <option value="cong_bo" <?php if ($editing_row['trang_thai'] === 'cong_bo') echo 'selected'; ?>>Công bố</option>
                            <option value="luu_tru" <?php if ($editing_row['trang_thai'] === 'luu_tru') echo 'selected'; ?>>Lưu trữ</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Mô tả</label>
                        <textarea name="mo_ta" class="form-control" rows="4"><?php
                            echo htmlspecialchars($editing_row['mo_ta'] ?? '');
                        ?></textarea>
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Lưu thay đổi
                        </button>
                        <a href="admin_courses.php" class="btn btn-secondary">Hủy</a>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- BỘ LỌC & TÌM KIẾM -->
    <form class="row g-2 mb-3" method="get">
        <div class="col-md-3">
            <select name="trang_thai" class="form-select">
                <option value="tat_ca"   <?php if ($trang_thai_filter === 'tat_ca')   echo 'selected'; ?>>Tất cả trạng thái</option>
                <option value="nhap"     <?php if ($trang_thai_filter === 'nhap')     echo 'selected'; ?>>Nháp</option>
                <option value="cong_bo"  <?php if ($trang_thai_filter === 'cong_bo')  echo 'selected'; ?>>Công bố</option>
                <option value="luu_tru"  <?php if ($trang_thai_filter === 'luu_tru')  echo 'selected'; ?>>Lưu trữ</option>
            </select>
        </div>

        <div class="col-md-3">
            <select name="giang_vien" class="form-select">
                <option value="tat_ca">Tất cả giảng viên</option>
                <?php foreach ($ds_giang_vien as $gv): ?>
                    <option value="<?php echo $gv['id']; ?>"
                        <?php if ($giang_vien_filter == $gv['id']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($gv['ho_ten']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-3">
            <input type="text"
                   name="q"
                   class="form-control"
                   placeholder="Tìm theo tên khóa học, slug, danh mục..."
                   value="<?php echo htmlspecialchars($tu_khoa, ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-md-1 d-grid">
            <button class="btn btn-primary" type="submit">
                <i class="bi bi-search"></i>
            </button>
        </div>

        <div class="col-md-2 d-grid">
            <a href="admin_course_add.php" class="btn btn-success">
                <i class="bi bi-plus-circle"></i> Thêm khóa học
            </a>
        </div>
    </form>

    <!-- BẢNG DANH SÁCH KHÓA HỌC -->
    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
            <tr>
                <th>#</th>
                <th>Tên khóa học</th>
                <th>Slug</th>
                <th>Giảng viên</th>
                <th>Danh mục</th>
                <th>Học phí</th>
                <th>Thời gian</th>
                <th>Trạng thái</th>
                <th>Ngày tạo</th>
                <th>Hành động</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($ds_khoa_hoc)): ?>
                <tr>
                    <td colspan="10" class="text-center text-muted">
                        Chưa có khóa học nào.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($ds_khoa_hoc as $index => $kh): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($kh['ten_khoa_hoc']); ?></strong><br>
                            <small class="text-muted">
                                <?php echo htmlspecialchars(mb_substr($kh['mo_ta'] ?? '', 0, 60)); ?>
                                <?php if (!empty($kh['mo_ta']) && mb_strlen($kh['mo_ta']) > 60) echo '...'; ?>
                            </small>
                        </td>
                        <td><code><?php echo htmlspecialchars($kh['duong_dan_tom_tat']); ?></code></td>
                        <td><?php echo htmlspecialchars($kh['ten_giang_vien'] ?? 'Chưa gán'); ?></td>
                        <td><?php echo htmlspecialchars($kh['danh_muc'] ?? ''); ?></td>
                        <td><?php echo number_format($kh['hoc_phi'], 0, ',', '.'); ?> đ</td>
                        <td>
                            <?php
                            $bd = $kh['ngay_bat_dau'] ?: '';
                            $kt = $kh['ngay_ket_thuc'] ?: '';
                            echo ($bd || $kt)
                                ? htmlspecialchars($bd . ' → ' . $kt)
                                : '<span class="text-muted">Chưa thiết lập</span>';
                            ?>
                        </td>
                        <td>
                            <?php
                            if ($kh['trang_thai'] === 'nhap') {
                                echo '<span class="badge bg-secondary">Nháp</span>';
                            } elseif ($kh['trang_thai'] === 'cong_bo') {
                                echo '<span class="badge bg-success">Công bố</span>';
                            } else {
                                echo '<span class="badge bg-warning text-dark">Lưu trữ</span>';
                            }
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars($kh['ngay_tao']); ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="admin_courses.php?edit=<?php echo $kh['id']; ?>"
                                   class="btn btn-outline-primary" title="Sửa">
                                    <i class="bi bi-pencil-square"></i>
                                </a>
                                <a href="admin_courses.php?delete=<?php echo $kh['id']; ?>"
                                   class="btn btn-outline-danger"
                                   onclick="return confirm('Bạn có chắc chắn muốn xóa khóa học này?');"
                                   title="Xóa">
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

</div>

<footer>
    <div class="container text-center">
        © <?php echo date('Y'); ?> Hệ thống E-learning PTIT - Quản lý khóa học.
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
