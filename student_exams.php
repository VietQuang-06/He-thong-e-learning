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

$error = '';
$ds_bai_thi = [];

try {
    $pdo = Database::pdo();

    // Lấy danh sách bài thi thuộc các khóa học mà học viên đã đăng ký (đang học hoặc đã hoàn thành)
    $sql = "
        SELECT 
            bt.id,
            bt.tieu_de,
            bt.mo_ta,
            bt.id_khoa_hoc,
            bt.thoi_gian_bat_dau,
            bt.thoi_gian_ket_thuc,
            bt.thoi_luong_phut,
            bt.gioi_han_so_lan,
            bt.tron_cau_hoi,
            bt.trang_thai,
            kh.ten_khoa_hoc,
            -- Số lần thi của học viên
            (
                SELECT COUNT(*) 
                FROM lan_thi lt 
                WHERE lt.id_bai_thi = bt.id 
                  AND lt.id_hoc_vien = :id_hv
            ) AS so_lan_da_thi,
            -- Điểm cao nhất
            (
                SELECT MAX(lt2.diem_so)
                FROM lan_thi lt2
                WHERE lt2.id_bai_thi = bt.id
                  AND lt2.id_hoc_vien = :id_hv
            ) AS diem_cao_nhat
        FROM bai_thi bt
        JOIN khoa_hoc kh ON bt.id_khoa_hoc = kh.id
        WHERE bt.id_khoa_hoc IN (
            SELECT id_khoa_hoc
            FROM dang_ky_khoa_hoc
            WHERE id_hoc_vien = :id_hv
              AND trang_thai IN ('dang_hoc','hoan_thanh')
        )
        ORDER BY bt.thoi_gian_bat_dau IS NULL ASC, bt.thoi_gian_bat_dau DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id_hv' => $user_id]);
    $ds_bai_thi = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = 'Lỗi khi tải danh sách bài thi: ' . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Bài thi & kết quả - E-learning PTIT</title>

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
            <i class="bi bi-mortarboard-fill"></i>
            <a href="student_dashboard.php">Khu vực học viên</a> /
            <span>Bài thi & kết quả</span>
        </div>
        <div>
            Xin chào, <strong><?php echo htmlspecialchars($ho_ten); ?></strong>
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
            <div style="font-size: 0.9rem; color:#555;">Bài thi & kết quả</div>
        </div>
    </div>
</header>

<div class="container mt-4 mb-5">

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 style="color:#b30000;">Danh sách bài thi</h3>
        <a href="student_my_courses.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-journal-text"></i> Khóa học của tôi
        </a>
    </div>

    <?php if (empty($ds_bai_thi)): ?>
        <div class="alert alert-info">
            Hiện tại bạn chưa có bài thi nào trong các khóa đã đăng ký.
        </div>
    <?php else: ?>

        <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 25%;">Bài thi</th>
                        <th style="width: 20%;">Khóa học</th>
                        <th style="width: 20%;">Thời gian mở</th>
                        <th style="width: 10%;">Thời lượng</th>
                        <th style="width: 10%;">Lần thi</th>
                        <th style="width: 10%;">Điểm</th>
                        <th style="width: 15%;">Trạng thái / Hành động</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($ds_bai_thi as $bt): ?>
                    <?php
                    $so_lan_da_thi   = (int)($bt['so_lan_da_thi'] ?? 0);
                    $gioi_han_so_lan = (int)$bt['gioi_han_so_lan'];

                    $trang_thai_bt   = $bt['trang_thai']; // nhap / dang_mo / dong

                    $status_display = '';
                    $badge_class    = '';
                    $co_the_thi     = false;

                    if ($trang_thai_bt === 'nhap') {
                        $status_display = 'Chưa mở';
                        $badge_class    = 'bg-secondary';
                        $co_the_thi     = false;
                    } elseif ($trang_thai_bt === 'dong') {
                        $status_display = 'Đã đóng';
                        $badge_class    = 'bg-secondary';
                        $co_the_thi     = false;
                    } elseif ($trang_thai_bt === 'dang_mo') {
                        $status_display = 'Đang mở';
                        $badge_class    = 'bg-success';
                        $co_the_thi     = true;
                    }

                    // Nếu có giới hạn lượt và đã thi đủ => khóa lại
                    if ($gioi_han_so_lan > 0 && $so_lan_da_thi >= $gioi_han_so_lan) {
                        $status_display = 'Đã hết lượt thi';
                        $badge_class    = 'bg-secondary';
                        $co_the_thi     = false;
                    }
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($bt['tieu_de']); ?></strong>
                            <?php if (!empty($bt['mo_ta'])): ?>
                                <div class="small text-muted">
                                    <?php echo htmlspecialchars(mb_strimwidth($bt['mo_ta'], 0, 80, '...', 'UTF-8')); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($bt['ten_khoa_hoc']); ?></td>
                        <td>
                            <?php
                            if ($bt['thoi_gian_bat_dau'] || $bt['thoi_gian_ket_thuc']) {
                                $start_text = $bt['thoi_gian_bat_dau']
                                    ? date('d/m/Y H:i', strtotime($bt['thoi_gian_bat_dau']))
                                    : 'Không đặt';
                                $end_text = $bt['thoi_gian_ket_thuc']
                                    ? date('d/m/Y H:i', strtotime($bt['thoi_gian_ket_thuc']))
                                    : 'Không đặt';
                                echo '<div><small>Bắt đầu: ' . $start_text . '</small></div>';
                                echo '<div><small>Kết thúc: ' . $end_text . '</small></div>';
                            } else {
                                echo '—';
                            }
                            ?>
                        </td>
                        <td><?php echo (int)$bt['thoi_luong_phut']; ?> phút</td>
                        <td><?php echo $so_lan_da_thi . ' / ' . $gioi_han_so_lan; ?></td>
                        <td>
                            <?php
                            if ($bt['diem_cao_nhat'] !== null) {
                                echo htmlspecialchars($bt['diem_cao_nhat']);
                            } else {
                                echo '—';
                            }
                            ?>
                        </td>
                        <td>
                            <div class="mb-1">
                                <span class="badge <?php echo $badge_class; ?>">
                                    <?php echo htmlspecialchars($status_display); ?>
                                </span>
                            </div>

                            <?php if ($co_the_thi): ?>
                                <a href="student_do_exam.php?id=<?php echo (int)$bt['id']; ?>"
                                   class="btn btn-sm btn-danger w-100">
                                    <i class="bi bi-pencil-square"></i> Vào làm bài
                                </a>
                            <?php else: ?>
                                <button class="btn btn-sm btn-secondary w-100" disabled>
                                    <?php echo htmlspecialchars($status_display); ?>
                                </button>
                            <?php endif; ?>

                            <a href="student_exam_result.php?exam_id=<?php echo (int)$bt['id']; ?>"
                               class="btn btn-sm btn-outline-primary w-100 mt-1">
                                <i class="bi bi-bar-chart-line"></i> Xem lịch sử thi
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
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
