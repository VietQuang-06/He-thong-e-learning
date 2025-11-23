<?php
session_start();
require_once 'config.php';

// Chỉ cho phép Admin truy cập
if (!isset($_SESSION['user_id']) || ($_SESSION['vai_tro'] ?? '') !== 'quan_tri') {
    header("Location: dang_nhap.php");
    exit;
}

$ho_ten_admin = $_SESSION['ho_ten'] ?? 'Quản trị hệ thống';

// =========================
// Thông báo (message)
// =========================
$thong_bao = '';
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'them_thanh_cong':
            $thong_bao = "Thêm tài khoản thành công.";
            break;
        case 'xoa_thanh_cong':
            $thong_bao = "Xóa tài khoản thành công.";
            break;
        case 'sua_thanh_cong':
            $thong_bao = "Cập nhật tài khoản thành công.";
            break;
        case 'khong_duoc_xoa_chinh_minh':
            $thong_bao = "Bạn không thể tự xóa tài khoản của mình.";
            break;
        case 'loi_he_thong':
            $thong_bao = "Có lỗi hệ thống, vui lòng thử lại.";
            break;
        case 'loi_id':
            $thong_bao = "ID tài khoản không hợp lệ.";
            break;
    }
}

// =========================
// XÓA TÀI KHOẢN
// =========================
if (isset($_GET['delete'])) {
    $id_xoa = (int)$_GET['delete'];

    if ($id_xoa <= 0) {
        header("Location: admin_users.php?msg=loi_id");
        exit;
    }

    // Không cho admin tự xóa chính mình
    if ($id_xoa == ($_SESSION['user_id'] ?? 0)) {
        header("Location: admin_users.php?msg=khong_duoc_xoa_chinh_minh");
        exit;
    }

    try {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare("DELETE FROM nguoi_dung WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id_xoa]);

        header("Location: admin_users.php?msg=xoa_thanh_cong");
        exit;
    } catch (PDOException $e) {
        header("Location: admin_users.php?msg=loi_he_thong");
        exit;
    }
}

// =========================
// CHẾ ĐỘ SỬA TÀI KHOẢN
// =========================
$edit_mode   = false;
$edit_id     = 0;
$edit_errors = [];
$edit_data   = [
    'ho_ten'        => '',
    'ma_sinh_vien'  => '',
    'lop_hoc'       => '',
    'gioi_tinh'     => 'nam',
    'email'         => '',
    'mat_khau'      => '',
    'vai_tro'       => 'hoc_vien',
    'so_dien_thoai' => '',
    'trang_thai'    => 'hoat_dong',
];

// Xử lý submit form cập nhật
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_user') {
    $edit_mode = true;
    $edit_id   = (int)($_POST['id'] ?? 0);

    $edit_data['ho_ten']        = trim($_POST['ho_ten'] ?? '');
    $edit_data['ma_sinh_vien']  = trim($_POST['ma_sinh_vien'] ?? '');
    $edit_data['lop_hoc']       = trim($_POST['lop_hoc'] ?? '');
    $edit_data['gioi_tinh']     = $_POST['gioi_tinh'] ?? 'nam';
    $edit_data['email']         = trim($_POST['email'] ?? '');
    $mat_khau_moi               = trim($_POST['mat_khau'] ?? '');
    $edit_data['vai_tro']       = $_POST['vai_tro'] ?? 'hoc_vien';
    $edit_data['so_dien_thoai'] = trim($_POST['so_dien_thoai'] ?? '');
    $edit_data['trang_thai']    = $_POST['trang_thai'] ?? 'hoat_dong';

    if ($edit_id <= 0) {
        $edit_errors[] = "ID tài khoản không hợp lệ.";
    }

    // Validate
    if ($edit_data['ho_ten'] === '') {
        $edit_errors[] = "Vui lòng nhập họ tên.";
    }
    if ($edit_data['email'] === '') {
        $edit_errors[] = "Vui lòng nhập email.";
    }
    if (!in_array($edit_data['gioi_tinh'], ['nam', 'nu'], true)) {
        $edit_errors[] = "Giới tính không hợp lệ.";
    }

    // Nếu là học viên thì phải có mã SV & lớp
    if ($edit_data['vai_tro'] === 'hoc_vien') {
        if ($edit_data['ma_sinh_vien'] === '') {
            $edit_errors[] = "Học viên phải có mã sinh viên.";
        }
        if ($edit_data['lop_hoc'] === '') {
            $edit_errors[] = "Học viên phải có lớp học.";
        }
    }

    if (empty($edit_errors)) {
        try {
            $pdo = Database::pdo();

            // Kiểm tra email trùng (trừ chính nó)
            $check = $pdo->prepare("SELECT COUNT(*) FROM nguoi_dung WHERE email = :email AND id <> :id");
            $check->execute([
                ':email' => $edit_data['email'],
                ':id'    => $edit_id
            ]);
            if ($check->fetchColumn() > 0) {
                $edit_errors[] = "Email này đã được sử dụng bởi tài khoản khác.";
            } else {
                // ma_sinh_vien, lop_hoc NOT NULL -> nếu để trống thì lưu ''
                if ($edit_data['ma_sinh_vien'] === '') {
                    $edit_data['ma_sinh_vien'] = '';
                }
                if ($edit_data['lop_hoc'] === '') {
                    $edit_data['lop_hoc'] = '';
                }

                $sql = "UPDATE nguoi_dung
                        SET ho_ten = :ho_ten,
                            ma_sinh_vien = :ma_sinh_vien,
                            lop_hoc = :lop_hoc,
                            gioi_tinh = :gioi_tinh,
                            email = :email,
                            vai_tro = :vai_tro,
                            so_dien_thoai = :so_dien_thoai,
                            trang_thai = :trang_thai,
                            ngay_cap_nhat = NOW()";

                $params = [
                    ':ho_ten'        => $edit_data['ho_ten'],
                    ':ma_sinh_vien'  => $edit_data['ma_sinh_vien'],
                    ':lop_hoc'       => $edit_data['lop_hoc'],
                    ':gioi_tinh'     => $edit_data['gioi_tinh'],
                    ':email'         => $edit_data['email'],
                    ':vai_tro'       => $edit_data['vai_tro'],
                    ':so_dien_thoai' => $edit_data['so_dien_thoai'] !== '' ? $edit_data['so_dien_thoai'] : null,
                    ':trang_thai'    => $edit_data['trang_thai'],
                    ':id'            => $edit_id
                ];

                // Nếu nhập mật khẩu mới thì cập nhật
                if ($mat_khau_moi !== '') {
                    $sql .= ", mat_khau = :mat_khau";
                    $params[':mat_khau'] = $mat_khau_moi; // plain text theo yêu cầu
                }

                $sql .= " WHERE id = :id LIMIT 1";

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

                header("Location: admin_users.php?msg=sua_thanh_cong");
                exit;
            }
        } catch (PDOException $e) {
            $edit_errors[] = "Lỗi hệ thống: " . $e->getMessage();
        }
    }

} elseif (isset($_GET['edit'])) {
    // Lần đầu click "Sửa" -> load dữ liệu lên form
    $edit_mode = true;
    $edit_id   = (int)$_GET['edit'];

    if ($edit_id > 0) {
        try {
            $pdo = Database::pdo();
            $stmt = $pdo->prepare("SELECT * FROM nguoi_dung WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $edit_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $edit_data['ho_ten']        = $row['ho_ten'];
                $edit_data['ma_sinh_vien']  = $row['ma_sinh_vien'];
                $edit_data['lop_hoc']       = $row['lop_hoc'];
                $edit_data['gioi_tinh']     = $row['gioi_tinh'];
                $edit_data['email']         = $row['email'];
                $edit_data['mat_khau']      = ''; // không show mật khẩu hiện tại
                $edit_data['vai_tro']       = $row['vai_tro'];
                $edit_data['so_dien_thoai'] = $row['so_dien_thoai'];
                $edit_data['trang_thai']    = $row['trang_thai'];
            } else {
                $edit_mode    = false;
                $thong_bao    = "Không tìm thấy tài khoản cần sửa.";
            }
        } catch (PDOException $e) {
            $edit_mode = false;
            $thong_bao = "Lỗi hệ thống: " . $e->getMessage();
        }
    } else {
        $edit_mode = false;
        $thong_bao = "ID tài khoản không hợp lệ.";
    }
}

// =========================
// Lọc & tìm kiếm danh sách
// =========================
$vai_tro_filter = $_GET['vai_tro'] ?? 'tat_ca'; // tat_ca | hoc_vien | giang_vien | quan_tri
$tu_khoa        = trim($_GET['q'] ?? '');

$ds_nguoi_dung = [];
$error = '';

try {
    $pdo = Database::pdo();

    $sql = "SELECT 
                id, 
                ho_ten, 
                ma_sinh_vien, 
                lop_hoc, 
                email, 
                vai_tro,
                gioi_tinh,
                so_dien_thoai, 
                trang_thai, 
                ngay_tao
            FROM nguoi_dung
            WHERE 1=1";

    $params = [];

    // Lọc theo vai trò
    if ($vai_tro_filter !== 'tat_ca') {
        $sql .= " AND vai_tro = :vai_tro";
        $params[':vai_tro'] = $vai_tro_filter;
    }

    // Tìm kiếm theo họ tên / mã SV / email
    if ($tu_khoa !== '') {
        $sql .= " AND (ho_ten LIKE :kw OR ma_sinh_vien LIKE :kw OR email LIKE :kw)";
        $params[':kw'] = '%' . $tu_khoa . '%';
    }

    $sql .= " ORDER BY ngay_tao DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $ds_nguoi_dung = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý tài khoản - Admin</title>

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
            <i class="bi bi-house-door-fill"></i>
            <a href="admin_dashboard.php">Trang quản trị</a>
        </div>
        <div>
            Xin chào, <strong><?php echo htmlspecialchars($ho_ten_admin, ENT_QUOTES, 'UTF-8'); ?></strong>
            &nbsp;|&nbsp;
            <a href="dang_xuat.php" style="color:#fff;">Đăng xuất</a>
        </div>
    </div>
</div>

<!-- Header -->
<header class="main-header">
    <div class="container py-2 d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center">
            <img src="image/ptit.png" alt="PTIT Logo" style="height:55px;" class="me-3">
            <div>
                <div class="logo-text">QUẢN LÝ TÀI KHOẢN NGƯỜI DÙNG</div>
                <div class="logo-subtext">Sinh viên, giảng viên, quản trị viên</div>
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

    <h4 class="mb-3" style="color:#b30000;">Danh sách tài khoản</h4>

    <?php if ($thong_bao): ?>
        <div class="alert alert-info">
            <?php echo htmlspecialchars($thong_bao, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($edit_errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($edit_errors as $er): ?>
                    <li><?php echo htmlspecialchars($er, ENT_QUOTES, 'UTF-8'); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            Lỗi khi lấy dữ liệu: <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if ($edit_mode): ?>
        <!-- Form SỬA tài khoản -->
        <div class="card mb-4">
            <div class="card-header">
                <strong>Sửa tài khoản (ID: <?php echo htmlspecialchars($edit_id); ?>)</strong>
            </div>
            <div class="card-body">
                <form method="post" autocomplete="off">
                    <input type="hidden" name="action" value="update_user">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($edit_id); ?>">

                    <div class="mb-3">
                        <label class="form-label">Họ tên <span class="text-danger">*</span></label>
                        <input type="text" name="ho_ten" class="form-control"
                               value="<?php echo htmlspecialchars($edit_data['ho_ten'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Giới tính <span class="text-danger">*</span></label>
                            <select name="gioi_tinh" class="form-select">
                                <option value="nam" <?php if ($edit_data['gioi_tinh'] === 'nam') echo 'selected'; ?>>Nam</option>
                                <option value="nu"  <?php if ($edit_data['gioi_tinh'] === 'nu')  echo 'selected'; ?>>Nữ</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Mã sinh viên / Mã GV</label>
                            <input type="text" name="ma_sinh_vien" class="form-control"
                                   value="<?php echo htmlspecialchars($edit_data['ma_sinh_vien'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Lớp học</label>
                            <input type="text" name="lop_hoc" class="form-control"
                                   value="<?php echo htmlspecialchars($edit_data['lop_hoc'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Số điện thoại</label>
                        <input type="text" name="so_dien_thoai" class="form-control"
                               value="<?php echo htmlspecialchars($edit_data['so_dien_thoai'], ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control"
                               value="<?php echo htmlspecialchars($edit_data['email'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Mật khẩu mới (nếu muốn đổi)</label>
                        <input type="text" name="mat_khau" class="form-control"
                               value=""
                               placeholder="Để trống nếu không muốn thay đổi mật khẩu">
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Vai trò</label>
                            <select name="vai_tro" class="form-select">
                                <option value="hoc_vien"   <?php if ($edit_data['vai_tro'] === 'hoc_vien')   echo 'selected'; ?>>Học viên</option>
                                <option value="giang_vien" <?php if ($edit_data['vai_tro'] === 'giang_vien') echo 'selected'; ?>>Giảng viên</option>
                                <option value="quan_tri"   <?php if ($edit_data['vai_tro'] === 'quan_tri')   echo 'selected'; ?>>Quản trị viên</option>
                            </select>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Trạng thái</label>
                            <select name="trang_thai" class="form-select">
                                <option value="hoat_dong"        <?php if ($edit_data['trang_thai'] === 'hoat_dong')        echo 'selected'; ?>>Hoạt động</option>
                                <option value="khong_hoat_dong"  <?php if ($edit_data['trang_thai'] === 'khong_hoat_dong')  echo 'selected'; ?>>Không hoạt động</option>
                                <option value="chan"             <?php if ($edit_data['trang_thai'] === 'chan')             echo 'selected'; ?>>Chặn</option>
                            </select>
                        </div>
                    </div>

                    <button class="btn btn-primary mt-2" type="submit">
                        <i class="bi bi-save"></i> Cập nhật tài khoản
                    </button>
                    <a href="admin_users.php" class="btn btn-secondary mt-2">
                        Hủy sửa
                    </a>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- Bộ lọc & tìm kiếm -->
    <form class="row g-2 mb-3" method="get">
        <div class="col-md-3">
            <select name="vai_tro" class="form-select">
                <option value="tat_ca"    <?php if ($vai_tro_filter === 'tat_ca')    echo 'selected'; ?>>Tất cả vai trò</option>
                <option value="hoc_vien"  <?php if ($vai_tro_filter === 'hoc_vien')  echo 'selected'; ?>>Học viên</option>
                <option value="giang_vien"<?php if ($vai_tro_filter === 'giang_vien')echo 'selected'; ?>>Giảng viên</option>
                <option value="quan_tri"  <?php if ($vai_tro_filter === 'quan_tri')  echo 'selected'; ?>>Quản trị viên</option>
            </select>
        </div>
        <div class="col-md-5">
            <input type="text"
                   name="q"
                   class="form-control"
                   placeholder="Tìm theo họ tên, mã sinh viên hoặc email..."
                   value="<?php echo htmlspecialchars($tu_khoa, ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="col-md-2">
            <button class="btn btn-primary w-100" type="submit">
                <i class="bi bi-search"></i> Lọc / Tìm
            </button>
        </div>
        <div class="col-md-2">
            <a href="admin_user_add.php" class="btn btn-success w-100">
                <i class="bi bi-person-plus-fill"></i> Thêm tài khoản
            </a>
        </div>
    </form>

    <!-- Bảng danh sách -->
    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
            <tr>
                <th>#</th>
                <th>Họ tên</th>
                <th>Mã SV / Mã GV</th>
                <th>Lớp</th>
                <th>Giới tính</th>
                <th>Email</th>
                <th>Vai trò</th>
                <th>Điện thoại</th>
                <th>Trạng thái</th>
                <th>Ngày tạo</th>
                <th>Hành động</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($ds_nguoi_dung)): ?>
                <tr>
                    <td colspan="11" class="text-center text-muted">
                        Chưa có tài khoản nào phù hợp với điều kiện lọc.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($ds_nguoi_dung as $index => $nd): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td><?php echo htmlspecialchars($nd['ho_ten']); ?></td>
                        <td><?php echo htmlspecialchars($nd['ma_sinh_vien']); ?></td>
                        <td><?php echo htmlspecialchars($nd['lop_hoc']); ?></td>
                        <td>
                            <?php
                            if ($nd['gioi_tinh'] === 'nu') {
                                echo 'Nữ';
                            } elseif ($nd['gioi_tinh'] === 'nam') {
                                echo 'Nam';
                            } else {
                                echo '—';
                            }
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars($nd['email']); ?></td>
                        <td>
                            <?php
                            switch ($nd['vai_tro']) {
                                case 'hoc_vien':   echo 'Học viên'; break;
                                case 'giang_vien': echo 'Giảng viên'; break;
                                case 'quan_tri':   echo 'Quản trị viên'; break;
                                default:           echo htmlspecialchars($nd['vai_tro']);
                            }
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars($nd['so_dien_thoai']); ?></td>
                        <td>
                            <?php
                            if ($nd['trang_thai'] === 'hoat_dong') {
                                echo '<span class="badge bg-success">Hoạt động</span>';
                            } elseif ($nd['trang_thai'] === 'khong_hoat_dong') {
                                echo '<span class="badge bg-secondary">Không hoạt động</span>';
                            } elseif ($nd['trang_thai'] === 'chan') {
                                echo '<span class="badge bg-danger">Chặn</span>';
                            } else {
                                echo '<span class="badge bg-light text-dark">'
                                    . htmlspecialchars($nd['trang_thai']) . '</span>';
                            }
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars($nd['ngay_tao']); ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="admin_users.php?edit=<?php echo $nd['id']; ?>"
                                   class="btn btn-outline-primary" title="Sửa">
                                    <i class="bi bi-pencil-square"></i>
                                </a>
                                <a href="admin_users.php?delete=<?php echo $nd['id']; ?>"
                                   class="btn btn-outline-danger"
                                   title="Xóa"
                                   onclick="return confirm('Bạn có chắc chắn muốn xóa tài khoản này?');">
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
        © <?php echo date('Y'); ?> Hệ thống E-learning PTIT - Trang quản lý tài khoản.
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
