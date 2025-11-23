<?php
session_start();
require_once 'config.php';

// Chỉ cho phép Admin truy cập
if (!isset($_SESSION['user_id']) || ($_SESSION['vai_tro'] ?? '') !== 'quan_tri') {
    header("Location: dang_nhap.php");
    exit;
}

$pdo          = Database::pdo();
$ho_ten_admin = $_SESSION['ho_ten'] ?? 'Quản trị hệ thống';
$id_admin     = (int)($_SESSION['user_id'] ?? 0);

$errors    = [];
$thong_bao = '';

// =======================
// THÔNG BÁO QUA ?msg=
// =======================
if (isset($_GET['msg'])) {
    $mapMsg = [
        'them_thanh_cong'     => "Thêm cấu hình mới thành công.",
        'cap_nhat_thanh_cong' => "Cập nhật cấu hình thành công.",
        'xoa_thanh_cong'      => "Xóa cấu hình thành công.",
        'loi_id'              => "Mã cấu hình không hợp lệ.",
        'loi_he_thong'        => "Có lỗi hệ thống, vui lòng thử lại."
    ];
    $thong_bao = $mapMsg[$_GET['msg']] ?? '';
}

// =======================
// XỬ LÝ XÓA (?delete=...)
// =======================
if (isset($_GET['delete'])) {
    $ma_xoa = trim($_GET['delete']);
    if ($ma_xoa === '') {
        header("Location: admin_settings.php?msg=loi_id");
        exit;
    }
    try {
        $stmt = $pdo->prepare("DELETE FROM cau_hinh_he_thong WHERE ma_cau_hinh = :ma");
        $stmt->execute([':ma' => $ma_xoa]);
        header("Location: admin_settings.php?msg=xoa_thanh_cong");
        exit;
    } catch (PDOException $e) {
        header("Location: admin_settings.php?msg=loi_he_thong");
        exit;
    }
}

// =======================
// BIẾN DÙNG CHO FORM
// =======================
$editing_key  = '';
$editing_row  = null;

// =======================
// XỬ LÝ POST (THÊM / SỬA)
// =======================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = $_POST['mode'] ?? '';

    // THÊM CẤU HÌNH MỚI
    if ($mode === 'add') {
        $ma_cau_hinh = trim($_POST['ma_cau_hinh'] ?? '');
        $gia_tri     = trim($_POST['gia_tri'] ?? '');
        $mo_ta       = trim($_POST['mo_ta'] ?? '');

        if ($ma_cau_hinh === '') {
            $errors[] = "Mã cấu hình không được để trống.";
        } elseif (!preg_match('/^[A-Za-z0-9_.-]+$/', $ma_cau_hinh)) {
            $errors[] = "Mã cấu hình chỉ nên dùng chữ, số, dấu gạch dưới, gạch ngang, dấu chấm.";
        }

        if ($gia_tri === '') {
            $errors[] = "Giá trị cấu hình không được để trống.";
        }

        // Kiểm tra trùng key
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("SELECT ma_cau_hinh FROM cau_hinh_he_thong WHERE ma_cau_hinh = :ma");
                $stmt->execute([':ma' => $ma_cau_hinh]);
                if ($stmt->fetch()) {
                    $errors[] = "Mã cấu hình này đã tồn tại. Hãy chọn mã khác hoặc sửa cấu hình đó.";
                }
            } catch (PDOException $e) {
                $errors[] = "Lỗi khi kiểm tra mã cấu hình: " . $e->getMessage();
            }
        }

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO cau_hinh_he_thong (ma_cau_hinh, gia_tri, mo_ta, id_cap_nhat, ngay_cap_nhat)
                    VALUES (:ma, :gt, :mt, :admin, NOW())
                ");
                $stmt->execute([
                    ':ma'    => $ma_cau_hinh,
                    ':gt'    => $gia_tri,
                    ':mt'    => $mo_ta,
                    ':admin' => $id_admin > 0 ? $id_admin : null
                ]);

                header("Location: admin_settings.php?msg=them_thanh_cong");
                exit;
            } catch (PDOException $e) {
                $errors[] = "Lỗi khi thêm cấu hình mới: " . $e->getMessage();
            }
        }
    }

    // CẬP NHẬT CẤU HÌNH
    if ($mode === 'update') {
        $editing_key = trim($_POST['ma_cau_hinh'] ?? '');
        $gia_tri     = trim($_POST['gia_tri'] ?? '');
        $mo_ta       = trim($_POST['mo_ta'] ?? '');

        if ($editing_key === '') {
            $errors[] = "Mã cấu hình không hợp lệ.";
        }
        if ($gia_tri === '') {
            $errors[] = "Giá trị cấu hình không được để trống.";
        }

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE cau_hinh_he_thong
                    SET gia_tri = :gt,
                        mo_ta   = :mt,
                        id_cap_nhat = :admin,
                        ngay_cap_nhat = NOW()
                    WHERE ma_cau_hinh = :ma
                ");
                $stmt->execute([
                    ':gt'    => $gia_tri,
                    ':mt'    => $mo_ta,
                    ':admin' => $id_admin > 0 ? $id_admin : null,
                    ':ma'    => $editing_key
                ]);

                header("Location: admin_settings.php?msg=cap_nhat_thanh_cong");
                exit;
            } catch (PDOException $e) {
                $errors[] = "Lỗi khi cập nhật cấu hình: " . $e->getMessage();
            }
        }

        // Nếu lỗi, giữ lại dữ liệu để hiển thị form sửa
        $editing_row = [
            'ma_cau_hinh' => $editing_key,
            'gia_tri'     => $gia_tri,
            'mo_ta'       => $mo_ta,
            'id_cap_nhat' => $id_admin,
            'ngay_cap_nhat' => date('Y-m-d H:i:s')
        ];
    }
}

// =======================
// LOAD CẤU HÌNH ĐANG SỬA (?edit=...)
// =======================
if ($editing_row === null && isset($_GET['edit'])) {
    $editing_key = trim($_GET['edit']);
    if ($editing_key !== '') {
        try {
            $stmt = $pdo->prepare("
                SELECT ch.*, nd.ho_ten AS ten_admin
                FROM cau_hinh_he_thong ch
                LEFT JOIN nguoi_dung nd ON ch.id_cap_nhat = nd.id
                WHERE ch.ma_cau_hinh = :ma
                LIMIT 1
            ");
            $stmt->execute([':ma' => $editing_key]);
            $editing_row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$editing_row) {
                $editing_key = '';
            }
        } catch (PDOException $e) {
            $errors[] = "Lỗi khi tải cấu hình: " . $e->getMessage();
            $editing_key = '';
        }
    }
}

// =======================
// TÌM & LỌC DANH SÁCH CẤU HÌNH
// =======================
$tu_khoa = trim($_GET['q'] ?? '');
$ds_cau_hinh = [];
$error_list = '';

try {
    $sql = "
        SELECT ch.*, nd.ho_ten AS ten_admin
        FROM cau_hinh_he_thong ch
        LEFT JOIN nguoi_dung nd ON ch.id_cap_nhat = nd.id
        WHERE 1=1
    ";
    $params = [];

    if ($tu_khoa !== '') {
        $sql .= " AND (ch.ma_cau_hinh LIKE :kw OR ch.mo_ta LIKE :kw)";
        $params[':kw'] = '%' . $tu_khoa . '%';
    }

    $sql .= " ORDER BY ch.ma_cau_hinh ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $ds_cau_hinh = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_list = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Cấu hình hệ thống - Admin</title>

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
                <div class="logo-text">CẤU HÌNH HỆ THỐNG</div>
                <div class="logo-subtext">Quản lý các tham số cấu hình chung của hệ thống e-learning</div>
            </div>
        </div>

        <a href="admin_dashboard.php" class="btn btn-sm btn-outline-secondary">
            &laquo; Về trang tổng quan
        </a>
    </div>
</header>

<div class="container mt-4 mb-5">

    <h4 class="mb-3" style="color:#b30000;">Danh sách cấu hình hệ thống</h4>

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
            Lỗi khi lấy danh sách cấu hình: <?php echo htmlspecialchars($error_list, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <!-- TÌM KIẾM -->
    <form class="row g-2 mb-3" method="get">
        <div class="col-md-4">
            <input type="text"
                   name="q"
                   class="form-control"
                   placeholder="Tìm theo mã cấu hình hoặc mô tả..."
                   value="<?php echo htmlspecialchars($tu_khoa, ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="col-md-2 d-grid">
            <button class="btn btn-primary" type="submit">
                <i class="bi bi-search"></i> Lọc
            </button>
        </div>
        <div class="col-md-2 d-grid">
            <a href="admin_settings.php" class="btn btn-outline-secondary">
                Đặt lại
            </a>
        </div>
    </form>

    <!-- BẢNG CẤU HÌNH -->
    <div class="table-responsive mb-4">
        <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
            <tr>
                <th>#</th>
                <th>Mã cấu hình</th>
                <th>Giá trị</th>
                <th>Mô tả</th>
                <th>Người cập nhật</th>
                <th>Ngày cập nhật</th>
                <th>Hành động</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($ds_cau_hinh)): ?>
                <tr>
                    <td colspan="7" class="text-center text-muted">
                        Chưa có cấu hình nào.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($ds_cau_hinh as $i => $ch): ?>
                    <tr <?php if ($editing_key !== '' && $editing_key === $ch['ma_cau_hinh']) echo 'class="table-warning"'; ?>>
                        <td><?php echo $i + 1; ?></td>
                        <td><code><?php echo htmlspecialchars($ch['ma_cau_hinh']); ?></code></td>
                        <td>
                            <span class="text-break">
                                <?php echo nl2br(htmlspecialchars(mb_strimwidth($ch['gia_tri'], 0, 80, '...','UTF-8'))); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($ch['mo_ta'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($ch['ten_admin'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($ch['ngay_cap_nhat'] ?? ''); ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="admin_settings.php?edit=<?php echo urlencode($ch['ma_cau_hinh']); ?>"
                                   class="btn btn-outline-primary" title="Sửa cấu hình">
                                    <i class="bi bi-pencil-square"></i>
                                </a>
                                <a href="admin_settings.php?delete=<?php echo urlencode($ch['ma_cau_hinh']); ?>"
                                   class="btn btn-outline-danger"
                                   onclick="return confirm('Bạn có chắc chắn muốn xóa cấu hình này?');"
                                   title="Xóa cấu hình">
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

    <!-- FORM SỬA CẤU HÌNH (NẾU ĐANG EDIT) -->
    <?php if (!empty($editing_row)): ?>
        <div class="card mb-4">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <strong>Sửa cấu hình: <?php echo htmlspecialchars($editing_row['ma_cau_hinh']); ?></strong>
                <a href="admin_settings.php" class="btn btn-sm btn-outline-secondary">
                    Đóng form sửa
                </a>
            </div>
            <div class="card-body">
                <form method="post" class="row g-3">
                    <input type="hidden" name="mode" value="update">
                    <input type="hidden" name="ma_cau_hinh" value="<?php echo htmlspecialchars($editing_row['ma_cau_hinh']); ?>">

                    <div class="col-12">
                        <label class="form-label">Giá trị <span class="text-danger">*</span></label>
                        <textarea name="gia_tri" class="form-control" rows="4" required><?php
                            echo htmlspecialchars($editing_row['gia_tri']);
                        ?></textarea>
                        <div class="form-text">
                            Có thể là chuỗi JSON, số, hoặc nội dung text. Ví dụ: <code>{"max_attempts":3}</code>.
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Mô tả</label>
                        <input type="text" name="mo_ta" class="form-control"
                               value="<?php echo htmlspecialchars($editing_row['mo_ta'] ?? ''); ?>">
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Lưu thay đổi
                        </button>
                        <a href="admin_settings.php" class="btn btn-secondary">Hủy</a>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- FORM THÊM CẤU HÌNH MỚI -->
    <div class="card">
        <div class="card-header bg-light">
            <strong>Thêm cấu hình mới</strong>
        </div>
        <div class="card-body">
            <form method="post" class="row g-3">
                <input type="hidden" name="mode" value="add">

                <div class="col-md-4">
                    <label class="form-label">Mã cấu hình <span class="text-danger">*</span></label>
                    <input type="text"
                           name="ma_cau_hinh"
                           class="form-control"
                           placeholder="VD: site_name, max_login_fail..."
                           value="<?php echo htmlspecialchars($_POST['ma_cau_hinh'] ?? ''); ?>">
                    <div class="form-text">
                        Chỉ nên dùng chữ, số, dấu gạch ngang, gạch dưới, dấu chấm.
                    </div>
                </div>

                <div class="col-md-8">
                    <label class="form-label">Giá trị <span class="text-danger">*</span></label>
                    <textarea name="gia_tri" class="form-control" rows="3"><?php
                        echo htmlspecialchars($_POST['gia_tri'] ?? '');
                    ?></textarea>
                </div>

                <div class="col-12">
                    <label class="form-label">Mô tả</label>
                    <input type="text" name="mo_ta" class="form-control"
                           placeholder="Mô tả ý nghĩa của cấu hình này (tùy chọn)"
                           value="<?php echo htmlspecialchars($_POST['mo_ta'] ?? ''); ?>">
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-plus-circle"></i> Thêm cấu hình
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<footer>
    <div class="container text-center">
        © <?php echo date('Y'); ?> Hệ thống E-learning PTIT - Cấu hình hệ thống.
    </div>
</footer>

</body>
</html>
