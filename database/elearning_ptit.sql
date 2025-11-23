-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: 127.0.0.1
-- Thời gian đã tạo: Th10 23, 2025 lúc 09:17 AM
-- Phiên bản máy phục vụ: 10.4.32-MariaDB
-- Phiên bản PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `elearning_ptit`
--

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `bai_dang_dien_dan`
--

CREATE TABLE `bai_dang_dien_dan` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_chu_de` int(10) UNSIGNED NOT NULL,
  `id_nguoi_dung` int(10) UNSIGNED NOT NULL,
  `id_cha` int(10) UNSIGNED DEFAULT NULL,
  `noi_dung` text NOT NULL,
  `ngay_tao` datetime NOT NULL DEFAULT current_timestamp(),
  `ngay_cap_nhat` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `bai_giang`
--

CREATE TABLE `bai_giang` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_khoa_hoc` int(10) UNSIGNED NOT NULL,
  `tieu_de` varchar(255) NOT NULL,
  `loai_noi_dung` enum('video','pdf','html','tep','link') NOT NULL DEFAULT 'video',
  `duong_dan_noi_dung` varchar(255) DEFAULT NULL,
  `noi_dung_html` longtext DEFAULT NULL,
  `thu_tu_hien_thi` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `hien_thi` tinyint(1) NOT NULL DEFAULT 1,
  `ngay_tao` datetime NOT NULL DEFAULT current_timestamp(),
  `ngay_cap_nhat` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `bai_giang`
--

INSERT INTO `bai_giang` (`id`, `id_khoa_hoc`, `tieu_de`, `loai_noi_dung`, `duong_dan_noi_dung`, `noi_dung_html`, `thu_tu_hien_thi`, `hien_thi`, `ngay_tao`, `ngay_cap_nhat`) VALUES
(3, 1, '1: Đăng ký đề tài + Tìm hiểu về Git.', 'video', 'https://www.youtube.com/watch?v=Mx9gcGFlEgw&list=PLRhlTlpDUWsxdl2IrPgo08LC0sKceVcMf', NULL, 1, 1, '2025-11-23 14:34:28', '2025-11-23 14:34:28');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `bai_lam_chi_tiet`
--

CREATE TABLE `bai_lam_chi_tiet` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_lan_thi` int(10) UNSIGNED NOT NULL,
  `id_cau_hoi` int(10) UNSIGNED NOT NULL,
  `id_lua_chon` int(10) UNSIGNED DEFAULT NULL,
  `cau_tra_loi` text DEFAULT NULL,
  `dung_hay_sai` tinyint(1) DEFAULT NULL,
  `diem_dat_duoc` decimal(5,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `bai_thi`
--

CREATE TABLE `bai_thi` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_khoa_hoc` int(10) UNSIGNED NOT NULL,
  `tieu_de` varchar(255) NOT NULL,
  `mo_ta` text DEFAULT NULL,
  `thoi_gian_bat_dau` datetime DEFAULT NULL,
  `thoi_gian_ket_thuc` datetime DEFAULT NULL,
  `thoi_luong_phut` int(10) UNSIGNED NOT NULL DEFAULT 60,
  `gioi_han_so_lan` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `tron_cau_hoi` tinyint(1) NOT NULL DEFAULT 1,
  `trang_thai` enum('nhap','dang_mo','dong') NOT NULL DEFAULT 'nhap',
  `ngay_tao` datetime NOT NULL DEFAULT current_timestamp(),
  `ngay_cap_nhat` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `cau_hinh_he_thong`
--

CREATE TABLE `cau_hinh_he_thong` (
  `ma_cau_hinh` varchar(100) NOT NULL,
  `gia_tri` text NOT NULL,
  `mo_ta` varchar(255) DEFAULT NULL,
  `id_cap_nhat` int(10) UNSIGNED DEFAULT NULL,
  `ngay_cap_nhat` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `cau_hoi`
--

CREATE TABLE `cau_hoi` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_bai_thi` int(10) UNSIGNED NOT NULL,
  `noi_dung_cau_hoi` text NOT NULL,
  `loai_cau_hoi` enum('mot_dap_an','nhieu_dap_an','dung_sai','tu_luan_ngan','tu_luan') NOT NULL DEFAULT 'mot_dap_an',
  `diem_so` decimal(5,2) NOT NULL DEFAULT 1.00,
  `thu_tu` int(10) UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `chu_de_dien_dan`
--

CREATE TABLE `chu_de_dien_dan` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_khoa_hoc` int(10) UNSIGNED NOT NULL,
  `id_nguoi_dung` int(10) UNSIGNED NOT NULL,
  `tieu_de` varchar(255) NOT NULL,
  `noi_dung` text NOT NULL,
  `bi_khoa` tinyint(1) NOT NULL DEFAULT 0,
  `ngay_tao` datetime NOT NULL DEFAULT current_timestamp(),
  `ngay_cap_nhat` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `dang_ky_khoa_hoc`
--

CREATE TABLE `dang_ky_khoa_hoc` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_hoc_vien` int(10) UNSIGNED NOT NULL,
  `id_khoa_hoc` int(10) UNSIGNED NOT NULL,
  `ngay_dang_ky` datetime NOT NULL DEFAULT current_timestamp(),
  `trang_thai` enum('cho_duyet','dang_hoc','hoan_thanh','huy') NOT NULL DEFAULT 'dang_hoc',
  `tien_do_phan_tram` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `diem_cuoi_ky` decimal(5,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `dang_ky_khoa_hoc`
--

INSERT INTO `dang_ky_khoa_hoc` (`id`, `id_hoc_vien`, `id_khoa_hoc`, `ngay_dang_ky`, `trang_thai`, `tien_do_phan_tram`, `diem_cuoi_ky`) VALUES
(1, 3, 1, '2025-11-22 10:46:30', 'dang_hoc', 0, NULL),
(3, 8, 1, '2025-11-23 13:38:32', 'dang_hoc', 0, NULL),
(4, 7, 1, '2025-11-23 13:44:04', 'dang_hoc', 0, NULL);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `dat_lai_mat_khau`
--

CREATE TABLE `dat_lai_mat_khau` (
  `id` int(10) UNSIGNED NOT NULL,
  `email` varchar(150) NOT NULL,
  `token` varchar(255) NOT NULL,
  `ngay_tao` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `khoa_hoc`
--

CREATE TABLE `khoa_hoc` (
  `id` int(10) UNSIGNED NOT NULL,
  `ten_khoa_hoc` varchar(255) NOT NULL,
  `duong_dan_tom_tat` varchar(255) NOT NULL,
  `mo_ta` text DEFAULT NULL,
  `id_giang_vien` int(10) UNSIGNED NOT NULL,
  `danh_muc` varchar(100) DEFAULT NULL,
  `hoc_phi` decimal(12,2) NOT NULL DEFAULT 0.00,
  `ngay_bat_dau` date DEFAULT NULL,
  `ngay_ket_thuc` date DEFAULT NULL,
  `trang_thai` enum('nhap','cong_bo','luu_tru') NOT NULL DEFAULT 'nhap',
  `ngay_tao` datetime NOT NULL DEFAULT current_timestamp(),
  `ngay_cap_nhat` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `khoa_hoc`
--

INSERT INTO `khoa_hoc` (`id`, `ten_khoa_hoc`, `duong_dan_tom_tat`, `mo_ta`, `id_giang_vien`, `danh_muc`, `hoc_phi`, `ngay_bat_dau`, `ngay_ket_thuc`, `trang_thai`, `ngay_tao`, `ngay_cap_nhat`) VALUES
(1, 'Thực tập cơ sở', 'thuc-tap-co-so', '', 5, '', 0.00, '2025-09-01', '2025-12-01', 'cong_bo', '2025-11-22 08:16:14', '2025-11-22 10:53:50');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `lan_thi`
--

CREATE TABLE `lan_thi` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_bai_thi` int(10) UNSIGNED NOT NULL,
  `id_hoc_vien` int(10) UNSIGNED NOT NULL,
  `thoi_gian_bat_dau` datetime NOT NULL DEFAULT current_timestamp(),
  `thoi_gian_nop` datetime DEFAULT NULL,
  `diem_so` decimal(5,2) DEFAULT NULL,
  `trang_thai` enum('dang_lam','da_nop','da_cham','huy') NOT NULL DEFAULT 'dang_lam',
  `so_lan` tinyint(3) UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `lua_chon`
--

CREATE TABLE `lua_chon` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_cau_hoi` int(10) UNSIGNED NOT NULL,
  `noi_dung_lua_chon` text NOT NULL,
  `la_dap_an_dung` tinyint(1) NOT NULL DEFAULT 0,
  `thu_tu` int(10) UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `luot_xem_bai_giang`
--

CREATE TABLE `luot_xem_bai_giang` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_bai_giang` int(10) UNSIGNED NOT NULL,
  `id_hoc_vien` int(10) UNSIGNED NOT NULL,
  `thoi_gian_xem` datetime NOT NULL DEFAULT current_timestamp(),
  `vi_tri_cuoi` int(10) UNSIGNED DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `luot_xem_bai_giang`
--

INSERT INTO `luot_xem_bai_giang` (`id`, `id_bai_giang`, `id_hoc_vien`, `thoi_gian_xem`, `vi_tri_cuoi`) VALUES
(3, 3, 3, '2025-11-23 14:36:14', 0);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `nguoi_dung`
--

CREATE TABLE `nguoi_dung` (
  `id` int(10) UNSIGNED NOT NULL,
  `ho_ten` varchar(100) NOT NULL,
  `ma_sinh_vien` varchar(20) DEFAULT NULL,
  `lop_hoc` varchar(50) DEFAULT NULL,
  `gioi_tinh` enum('nam','nu') NOT NULL DEFAULT 'nam',
  `email` varchar(150) NOT NULL,
  `mat_khau` varchar(255) NOT NULL,
  `vai_tro` enum('hoc_vien','giang_vien','quan_tri') NOT NULL DEFAULT 'hoc_vien',
  `so_dien_thoai` varchar(20) DEFAULT NULL,
  `trang_thai` enum('hoat_dong','khong_hoat_dong','chan') NOT NULL DEFAULT 'hoat_dong',
  `anh_dai_dien` varchar(255) DEFAULT NULL,
  `ngay_tao` datetime NOT NULL DEFAULT current_timestamp(),
  `ngay_cap_nhat` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `nguoi_dung`
--

INSERT INTO `nguoi_dung` (`id`, `ho_ten`, `ma_sinh_vien`, `lop_hoc`, `gioi_tinh`, `email`, `mat_khau`, `vai_tro`, `so_dien_thoai`, `trang_thai`, `anh_dai_dien`, `ngay_tao`, `ngay_cap_nhat`) VALUES
(1, 'Quản trị hệ thống', NULL, NULL, 'nam', 'admin@ptit.edu.vn', '123456', 'quan_tri', '0123456789', 'hoat_dong', NULL, '2025-11-21 22:37:53', '2025-11-21 22:37:53'),
(3, 'Nguyễn Việt Quang', 'B22DVCN269', 'D22VHCN01-B', 'nam', 'vietnguyenquang244@gmail.com', '123456', 'hoc_vien', '0368128691', 'hoat_dong', NULL, '2025-11-21 23:29:14', '2025-11-21 23:29:14'),
(5, 'Nguyễn Văn A', '', '', 'nam', 'VanA@gmail.com', '123456', 'giang_vien', '0368128694', 'hoat_dong', NULL, '2025-11-22 08:11:16', '2025-11-23 12:47:25'),
(7, 'Nguyễn Doãn Thắng', 'B22DVCN289', 'D22VHCN01-B', 'nam', 'doanthang@gmail.com', '123456', 'hoc_vien', '0987654321', 'hoat_dong', NULL, '2025-11-23 12:46:23', '2025-11-23 12:46:23'),
(8, 'Trần Thị Minh Thư', 'B22DVCN305', 'D22VHCN01-B', 'nu', 'minhthu@gmail.com', '123456', 'hoc_vien', '0987654312', 'hoat_dong', NULL, '2025-11-23 13:38:13', '2025-11-23 13:38:13');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `thong_bao`
--

CREATE TABLE `thong_bao` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_nguoi_nhan` int(10) UNSIGNED NOT NULL,
  `tieu_de` varchar(255) NOT NULL,
  `noi_dung` text NOT NULL,
  `da_doc` tinyint(1) NOT NULL DEFAULT 0,
  `ngay_tao` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `bai_dang_dien_dan`
--
ALTER TABLE `bai_dang_dien_dan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_bai_dang_chu_de` (`id_chu_de`),
  ADD KEY `fk_bai_dang_nguoi_dung` (`id_nguoi_dung`),
  ADD KEY `fk_bai_dang_cha` (`id_cha`);

--
-- Chỉ mục cho bảng `bai_giang`
--
ALTER TABLE `bai_giang`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_bai_giang_khoa_hoc` (`id_khoa_hoc`);

--
-- Chỉ mục cho bảng `bai_lam_chi_tiet`
--
ALTER TABLE `bai_lam_chi_tiet`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_blct_lan_thi` (`id_lan_thi`),
  ADD KEY `fk_blct_cau_hoi` (`id_cau_hoi`),
  ADD KEY `fk_blct_lua_chon` (`id_lua_chon`);

--
-- Chỉ mục cho bảng `bai_thi`
--
ALTER TABLE `bai_thi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_bai_thi_khoa_hoc` (`id_khoa_hoc`);

--
-- Chỉ mục cho bảng `cau_hinh_he_thong`
--
ALTER TABLE `cau_hinh_he_thong`
  ADD PRIMARY KEY (`ma_cau_hinh`),
  ADD KEY `fk_cau_hinh_admin` (`id_cap_nhat`);

--
-- Chỉ mục cho bảng `cau_hoi`
--
ALTER TABLE `cau_hoi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_cau_hoi_bai_thi` (`id_bai_thi`);

--
-- Chỉ mục cho bảng `chu_de_dien_dan`
--
ALTER TABLE `chu_de_dien_dan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_chu_de_khoa_hoc` (`id_khoa_hoc`),
  ADD KEY `fk_chu_de_nguoi_dung` (`id_nguoi_dung`);

--
-- Chỉ mục cho bảng `dang_ky_khoa_hoc`
--
ALTER TABLE `dang_ky_khoa_hoc`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_dang_ky` (`id_hoc_vien`,`id_khoa_hoc`),
  ADD KEY `fk_dk_khoa_hoc` (`id_khoa_hoc`);

--
-- Chỉ mục cho bảng `dat_lai_mat_khau`
--
ALTER TABLE `dat_lai_mat_khau`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email` (`email`);

--
-- Chỉ mục cho bảng `khoa_hoc`
--
ALTER TABLE `khoa_hoc`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `duong_dan_tom_tat` (`duong_dan_tom_tat`),
  ADD KEY `fk_khoa_hoc_giang_vien` (`id_giang_vien`);

--
-- Chỉ mục cho bảng `lan_thi`
--
ALTER TABLE `lan_thi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_lan_thi_bai_thi` (`id_bai_thi`),
  ADD KEY `fk_lan_thi_hoc_vien` (`id_hoc_vien`);

--
-- Chỉ mục cho bảng `lua_chon`
--
ALTER TABLE `lua_chon`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_lua_chon_cau_hoi` (`id_cau_hoi`);

--
-- Chỉ mục cho bảng `luot_xem_bai_giang`
--
ALTER TABLE `luot_xem_bai_giang`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_luot_xem` (`id_bai_giang`,`id_hoc_vien`),
  ADD KEY `fk_xem_hoc_vien` (`id_hoc_vien`);

--
-- Chỉ mục cho bảng `nguoi_dung`
--
ALTER TABLE `nguoi_dung`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Chỉ mục cho bảng `thong_bao`
--
ALTER TABLE `thong_bao`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_thong_bao_nguoi_nhan` (`id_nguoi_nhan`);

--
-- AUTO_INCREMENT cho các bảng đã đổ
--

--
-- AUTO_INCREMENT cho bảng `bai_dang_dien_dan`
--
ALTER TABLE `bai_dang_dien_dan`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `bai_giang`
--
ALTER TABLE `bai_giang`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT cho bảng `bai_lam_chi_tiet`
--
ALTER TABLE `bai_lam_chi_tiet`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `bai_thi`
--
ALTER TABLE `bai_thi`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT cho bảng `cau_hoi`
--
ALTER TABLE `cau_hoi`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT cho bảng `chu_de_dien_dan`
--
ALTER TABLE `chu_de_dien_dan`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `dang_ky_khoa_hoc`
--
ALTER TABLE `dang_ky_khoa_hoc`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT cho bảng `dat_lai_mat_khau`
--
ALTER TABLE `dat_lai_mat_khau`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `khoa_hoc`
--
ALTER TABLE `khoa_hoc`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT cho bảng `lan_thi`
--
ALTER TABLE `lan_thi`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT cho bảng `lua_chon`
--
ALTER TABLE `lua_chon`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT cho bảng `luot_xem_bai_giang`
--
ALTER TABLE `luot_xem_bai_giang`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT cho bảng `nguoi_dung`
--
ALTER TABLE `nguoi_dung`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT cho bảng `thong_bao`
--
ALTER TABLE `thong_bao`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Các ràng buộc cho các bảng đã đổ
--

--
-- Các ràng buộc cho bảng `bai_dang_dien_dan`
--
ALTER TABLE `bai_dang_dien_dan`
  ADD CONSTRAINT `fk_bai_dang_cha` FOREIGN KEY (`id_cha`) REFERENCES `bai_dang_dien_dan` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bai_dang_chu_de` FOREIGN KEY (`id_chu_de`) REFERENCES `chu_de_dien_dan` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bai_dang_nguoi_dung` FOREIGN KEY (`id_nguoi_dung`) REFERENCES `nguoi_dung` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `bai_giang`
--
ALTER TABLE `bai_giang`
  ADD CONSTRAINT `fk_bai_giang_khoa_hoc` FOREIGN KEY (`id_khoa_hoc`) REFERENCES `khoa_hoc` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `bai_lam_chi_tiet`
--
ALTER TABLE `bai_lam_chi_tiet`
  ADD CONSTRAINT `fk_blct_cau_hoi` FOREIGN KEY (`id_cau_hoi`) REFERENCES `cau_hoi` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_blct_lan_thi` FOREIGN KEY (`id_lan_thi`) REFERENCES `lan_thi` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_blct_lua_chon` FOREIGN KEY (`id_lua_chon`) REFERENCES `lua_chon` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `bai_thi`
--
ALTER TABLE `bai_thi`
  ADD CONSTRAINT `fk_bai_thi_khoa_hoc` FOREIGN KEY (`id_khoa_hoc`) REFERENCES `khoa_hoc` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `cau_hinh_he_thong`
--
ALTER TABLE `cau_hinh_he_thong`
  ADD CONSTRAINT `fk_cau_hinh_admin` FOREIGN KEY (`id_cap_nhat`) REFERENCES `nguoi_dung` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `cau_hoi`
--
ALTER TABLE `cau_hoi`
  ADD CONSTRAINT `fk_cau_hoi_bai_thi` FOREIGN KEY (`id_bai_thi`) REFERENCES `bai_thi` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `chu_de_dien_dan`
--
ALTER TABLE `chu_de_dien_dan`
  ADD CONSTRAINT `fk_chu_de_khoa_hoc` FOREIGN KEY (`id_khoa_hoc`) REFERENCES `khoa_hoc` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_chu_de_nguoi_dung` FOREIGN KEY (`id_nguoi_dung`) REFERENCES `nguoi_dung` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `dang_ky_khoa_hoc`
--
ALTER TABLE `dang_ky_khoa_hoc`
  ADD CONSTRAINT `fk_dk_hoc_vien` FOREIGN KEY (`id_hoc_vien`) REFERENCES `nguoi_dung` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_dk_khoa_hoc` FOREIGN KEY (`id_khoa_hoc`) REFERENCES `khoa_hoc` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `khoa_hoc`
--
ALTER TABLE `khoa_hoc`
  ADD CONSTRAINT `fk_khoa_hoc_giang_vien` FOREIGN KEY (`id_giang_vien`) REFERENCES `nguoi_dung` (`id`) ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `lan_thi`
--
ALTER TABLE `lan_thi`
  ADD CONSTRAINT `fk_lan_thi_bai_thi` FOREIGN KEY (`id_bai_thi`) REFERENCES `bai_thi` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_lan_thi_hoc_vien` FOREIGN KEY (`id_hoc_vien`) REFERENCES `nguoi_dung` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `lua_chon`
--
ALTER TABLE `lua_chon`
  ADD CONSTRAINT `fk_lua_chon_cau_hoi` FOREIGN KEY (`id_cau_hoi`) REFERENCES `cau_hoi` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `luot_xem_bai_giang`
--
ALTER TABLE `luot_xem_bai_giang`
  ADD CONSTRAINT `fk_xem_bai_giang` FOREIGN KEY (`id_bai_giang`) REFERENCES `bai_giang` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_xem_hoc_vien` FOREIGN KEY (`id_hoc_vien`) REFERENCES `nguoi_dung` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `thong_bao`
--
ALTER TABLE `thong_bao`
  ADD CONSTRAINT `fk_thong_bao_nguoi_nhan` FOREIGN KEY (`id_nguoi_nhan`) REFERENCES `nguoi_dung` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
