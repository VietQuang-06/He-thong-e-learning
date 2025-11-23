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

// Lọc theo khóa học & trạng thái & từ khóa
$course_filter = isset($_GET['id_khoa_hoc']) ? (int)$_GET['id_khoa_hoc'] : 0;
$status_filter = $_GET['status'] ?? '';
$q             = trim($_GET['q'] ?? '');

$allowed_status = ['cho_duyet','dang_hoc','hoan_thanh','huy'];
if ($status_filter !== '' && !in_array($status_filter, $allowed_status, true)) {
    $status_filter = '';
}

$courses     = [];
$enrollments = [];

try {
    $pdo = Database::pdo();

    // 1. Lấy danh sách tất cả khóa học của giảng viên
    $sql = "
        SELECT id, ten_khoa_hoc, danh_muc
        FROM khoa_hoc
        WHERE id_giang_vien = :id_gv
        ORDER BY ten_khoa_hoc ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id_gv' => $giang_vien_id]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $course_ids = array_column($courses, 'id');

    // Nếu filter không thuộc các khóa của GV -> bỏ filter
    if ($course_filter > 0 && !in_array($course_filter, $course_ids, true)) {
        $course_filter = 0;
    }

    // 2. Xử lý DUYỆT / TỪ CHỐI (POST)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['dk_id'])) {

        $action = $_POST['action'];
        $dk_id  = (int)$_POST['dk_id'];

        // Để giữ lại filter sau khi xử lý (nếu redirect)
        $course_filter = isset($_POST['id_khoa_hoc_filter']) ? (int)$_POST['id_khoa_hoc_filter'] : $course_filter;
        $status_filter = $_POST['status_filter'] ?? $status_filter;
        $q             = trim($_POST['q_filter'] ?? $q);

        if ($dk_id > 0 && in_array($action, ['approve','reject'], true)) {

            // Kiểm tra bản ghi thuộc khóa của giảng viên không
            $sql = "
                SELECT dk.id, dk.trang_thai
                FROM dang_ky_khoa_hoc dk
                JOIN khoa_hoc kh ON dk.id_khoa_hoc = kh.id
                WHERE dk.id = :dk_id
                  AND kh.id_giang_vien = :id_gv
                LIMIT 1
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':dk_id' => $dk_id,
                ':id_gv' => $giang_vien_id
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                $errors[] = "Không tìm thấy đăng ký hoặc bạn không có quyền xử lý.";
            } else {
                // Chỉ xử lý nếu đang ở trạng thái chờ duyệt
                if ($row['trang_thai'] !== 'cho_duyet') {
                    $errors[] = "Chỉ xử lý được các yêu cầu đang chờ duyệt.";
                } else {
                    if ($action === 'approve') {
                        // Duyệt: chuyển sang đang học
                        $sql = "
                            UPDATE dang_ky_khoa_hoc
                            SET trang_thai = 'dang_hoc',
                                tien_do_phan_tram = COALESCE(tien_do_phan_tram, 0)
                            WHERE id = :dk_id
                        ";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([':dk_id' => $dk_id]);
                        $success = "Đã duyệt yêu cầu đăng ký và cho phép sinh viên học khóa này.";
                    } else {
                        // Từ chối: chuyển sang hủy
                        $sql = "
                            UPDATE dang_ky_khoa_hoc
                            SET trang_thai = 'huy'
                            WHERE id = :dk_id
                        ";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([':dk_id' => $dk_id]);
                        $success = "Đã từ chối yêu cầu đăng ký.";
                    }
                }
            }
        }
    }

    // 3. Lấy danh sách sinh viên đăng ký các khóa học của giảng viên
    $sql = "
        SELECT 
            dk.id,
            dk.id_hoc_vien,
            dk.id_khoa_hoc,
            dk.ngay_dang_ky,
            dk.trang_thai,
            dk.tien_do_phan_tram,
            dk.diem_cuoi_ky,
            nd.ho_ten,
            nd.ma_sinh_vien,
            nd.lop_hoc,
            nd.gioi_tinh,
            nd.email,
            nd.so_dien_thoai,
            kh.ten_khoa_hoc,
            kh.danh_muc
        FROM dang_ky_khoa_hoc dk
        JOIN nguoi_dung nd ON dk.id_hoc_vien = nd.id
        JOIN khoa_hoc kh ON dk.id_khoa_hoc = kh.id
        WHERE kh.id_giang_vien = :id_gv
    ";

    $params = [':id_gv' => $giang_vien_id];

    if ($course_filter > 0) {
        $sql .= " AND kh.id = :id_kh ";
        $params[':id_kh'] = $course_filter;
    }

    if ($status_filter !== '') {
        $sql .= " AND dk.trang_thai = :st ";
        $params[':st'] = $status_filter;
    }

    if ($q !== '') {
        $sql .= " AND (nd.ho_ten LIKE :kw OR nd.ma_sinh_vien LIKE :kw OR nd.email LIKE :kw) ";
        $params[':kw'] = '%'.$q.'%';
    }

    $sql .= "
        ORDER BY kh.ten_khoa_hoc ASC,
                 dk.ngay_dang_ky DESC,
                 nd.ho_ten ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $errors[] = "Lỗi hệ thống: " . $e->getMessage();
}

// Hàm map trạng thái đăng ký
function textTrangThaiDangKy($code) {
    $map = [
        'cho_duyet'   => 'Chờ duyệt',
        'dang_hoc'    => 'Đang học',
        'hoan_thanh'  => 'Hoàn thành',
        'huy'         => 'Đã hủy',
    ];
    return $map[$code] ?? $code;
}

// Hàm màu badge cho trạng thái
function badgeTrangThaiDangKy($code) {
    switch ($code) {
        case 'dang_hoc':    return 'bg-primary';
        case 'hoan_thanh':  return 'bg-success';
        case 'cho_duyet':   return 'bg-warning text-dark';
        case 'huy':         return 'bg-secondary';
        default:            return 'bg-light text-dark';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Danh sách sinh viên đăng ký - Giảng viên</title>

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
            <span> / Danh sách sinh viên đăng ký</span>
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
            <div style="font-size: 0.9rem; color:#555;">Quản lý sinh viên đăng ký khóa học</div>
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

    <!-- Bộ lọc -->
    <div class="card mb-3">
        <div class="card-body">
            <form class="row g-2 align-items-end" method="get">

                <div class="col-md-4">
                    <label class="form-label">Khóa học</label>
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

                <div class="col-md-3">
                    <label class="form-label">Trạng thái</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">-- Tất cả --</option>
                        <option value="cho_duyet"   <?php if ($status_filter==='cho_duyet')   echo 'selected'; ?>>Chờ duyệt</option>
                        <option value="dang_hoc"    <?php if ($status_filter==='dang_hoc')    echo 'selected'; ?>>Đang học</option>
                        <option value="hoan_thanh"  <?php if ($status_filter==='hoan_thanh')  echo 'selected'; ?>>Hoàn thành</option>
                        <option value="huy"         <?php if ($status_filter==='huy')         echo 'selected'; ?>>Đã hủy</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Tìm sinh viên</label>
                    <input type="text" name="q" class="form-control form-control-sm"
                           placeholder="Tên, mã SV, email..."
                           value="<?php echo htmlspecialchars($q); ?>">
                </div>

                <div class="col-md-2">
                    <button class="btn btn-danger btn-sm w-100" type="submit">
                        <i class="bi bi-funnel"></i> Lọc / Tìm
                    </button>
                </div>

            </form>
        </div>
    </div>

    <!-- Danh sách sinh viên đăng ký -->
    <div class="card">
        <div class="card-header">
            <strong>Danh sách sinh viên đăng ký khóa học của bạn</strong>
        </div>
        <div class="card-body p-0">
            <?php if (empty($enrollments)): ?>
                <div class="p-3">
                    <em>Chưa có sinh viên nào đăng ký theo điều kiện lọc.</em>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>Khóa học</th>
                            <th>Họ tên</th>
                            <th>Mã SV</th>
                            <th>Lớp</th>
                            <th>Giới tính</th>
                            <th>Email</th>
                            <th>Điện thoại</th>
                            <th>Trạng thái</th>
                            <th>Tiến độ</th>
                            <th>Ngày đăng ký</th>
                            <th>Hành động</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($enrollments as $row): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['ten_khoa_hoc']); ?></strong><br>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($row['danh_muc'] ?? ''); ?>
                                    </small>
                                </td>
                                <td><?php echo htmlspecialchars($row['ho_ten']); ?></td>
                                <td><?php echo htmlspecialchars($row['ma_sinh_vien']); ?></td>
                                <td><?php echo htmlspecialchars($row['lop_hoc']); ?></td>
                                <td>
                                    <?php
                                    if ($row['gioi_tinh'] === 'nam') {
                                        echo 'Nam';
                                    } elseif ($row['gioi_tinh'] === 'nu') {
                                        echo 'Nữ';
                                    } else {
                                        echo '—';
                                    }
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                <td><?php echo htmlspecialchars($row['so_dien_thoai']); ?></td>
                                <td>
                                    <?php
                                    $badgeClass = badgeTrangThaiDangKy($row['trang_thai']);
                                    $txt        = textTrangThaiDangKy($row['trang_thai']);
                                    echo '<span class="badge '.$badgeClass.'">'.htmlspecialchars($txt).'</span>';
                                    ?>
                                </td>
                                <td>
                                    <div class="progress" style="height: 16px;">
                                        <div class="progress-bar bg-danger"
                                             role="progressbar"
                                             style="width: <?php echo (int)$row['tien_do_phan_tram']; ?>%;">
                                            <?php echo (int)$row['tien_do_phan_tram']; ?>%
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    echo $row['ngay_dang_ky']
                                        ? date('d/m/Y H:i', strtotime($row['ngay_dang_ky']))
                                        : '—';
                                    ?>
                                </td>
                                <td>
                                    <?php if ($row['trang_thai'] === 'cho_duyet'): ?>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="dk_id" value="<?php echo (int)$row['id']; ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <!-- giữ lại filter -->
                                            <input type="hidden" name="id_khoa_hoc_filter" value="<?php echo (int)$course_filter; ?>">
                                            <input type="hidden" name="status_filter" value="<?php echo htmlspecialchars($status_filter); ?>">
                                            <input type="hidden" name="q_filter" value="<?php echo htmlspecialchars($q); ?>">
                                            <button type="submit" class="btn btn-sm btn-success mb-1">
                                                <i class="bi bi-check-lg"></i> Duyệt
                                            </button>
                                        </form>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="dk_id" value="<?php echo (int)$row['id']; ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="id_khoa_hoc_filter" value="<?php echo (int)$course_filter; ?>">
                                            <input type="hidden" name="status_filter" value="<?php echo htmlspecialchars($status_filter); ?>">
                                            <input type="hidden" name="q_filter" value="<?php echo htmlspecialchars($q); ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger mb-1"
                                                    onclick="return confirm('Từ chối yêu cầu đăng ký khóa học này?');">
                                                <i class="bi bi-x-lg"></i> Từ chối
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted small">Không có hành động</span>
                                    <?php endif; ?>
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
