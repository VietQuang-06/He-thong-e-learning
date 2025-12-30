# 🎓 HỆ THỐNG E-LEARNING PTIT

Dự án xây dựng hệ thống học trực tuyến (E-Learning) mô phỏng theo môi trường đào tạo tại **Học viện Công nghệ Bưu chính Viễn thông – PTIT**.  
Hệ thống gồm 3 vai trò chính:

✔ Quản trị viên (Admin)  
✔ Giảng viên  
✔ Học viên  

Mỗi vai trò sẽ có trang Dashboard và chức năng riêng biệt.

---

## 📌 1. Tính năng hệ thống

### 🔑 1.1. Quản trị viên (Admin)

Trang chính: `admin_dashboard.php`

Chức năng:
- Quản lý người dùng (sinh viên, giảng viên, admin)
  - `admin_users.php`
  - `admin_user_add.php`
- Quản lý khóa học
  - `admin_courses.php`
  - `admin_course_add.php`
- Quản lý lớp học
  - `admin_classes.php`
- Quản lý bài giảng
  - `admin_lessons.php`
- Quản lý bài thi & ngân hàng câu hỏi
  - `admin_exams.php`
  - `exam_add.php`
- Cấu hình hệ thống
  - `admin_settings.php`

Dashboard hiển thị số liệu:
- Tổng số học viên
- Tổng số giảng viên
- Số lượng admin
- Số khóa học

---

### 🎓 1.2. Học viên (Student)

Trang chính: `student_dashboard.php`

Chức năng:
- Xem khóa học đã đăng ký  
  `student_my_courses.php`
- Xem chi tiết khóa học  
  `student_course_detail.php`
- Vào học & xem bài giảng  
  `student_course_learn.php`
  `student_lesson_view.php`
- Đăng ký khóa học  
  `student_register_course.php`
- Xem danh mục khóa học  
  `student_courses_catalog.php`
- Làm bài thi  
  `student_do_exam.php`
- Xem kết quả  
  `student_exam_result.php`
- Xem lịch sử thi  
  `student_exams.php`
- Diễn đàn thảo luận  
  `student_forum.php`
- Quản lý hồ sơ cá nhân  
  `student_profile.php`

---

### 👨‍🏫 1.3. Giảng viên (Teacher)

Trang chính: `giangvien_dashboard.php`

Chức năng:
- Quản lý khóa học phụ trách  
  `giangvien_courses.php`
- Xem danh sách sinh viên  
  `giangvien_course_students.php`
- Quản lý bài giảng  
  `giangvien_course_lessons.php`
- Quản lý kỳ thi  
  `giangvien_exams.php`
- Ngân hàng câu hỏi  
  `giangvien_exam_questions.php`
- Xem kết quả thi sinh viên  
  `giangvien_exam_results.php`
- Quản lý danh sách đăng ký  
  `giangvien_enrollments.php`
- Quản lý hồ sơ cá nhân  
  `giangvien_profile.php`
- Xem chi tiết khóa học  
  `giangvien_course_detail.php`

---

## 🗂 1.4. Cấu trúc thư mục

```text
project/
├─ css/                     # File CSS, Bootstrap
├─ database/                # File SQL hoặc script tạo DB
├─ image/                   # Hình ảnh, logo
├─ uploads/                 # File upload (video, tài liệu)
├─ config.php               # Cấu hình database
├─ dang_nhap.php            # Trang đăng nhập
├─ dang_xuat.php            # Đăng xuất

# ADMIN
├─ admin_dashboard.php
├─ admin_users.php
├─ admin_user_add.php
├─ admin_courses.php
├─ admin_course_add.php
├─ admin_classes.php
├─ admin_lessons.php
├─ admin_exams.php
├─ admin_settings.php
├─ exam_add.php

# GIẢNG VIÊN
├─ giangvien_dashboard.php
├─ giangvien_courses.php
├─ giangvien_course_detail.php
├─ giangvien_course_students.php
├─ giangvien_course_lessons.php
├─ giangvien_enrollments.php
├─ giangvien_exams.php
├─ giangvien_exam_questions.php
├─ giangvien_exam_results.php
├─ giangvien_profile.php

# HỌC VIÊN
├─ student_dashboard.php
├─ student_courses_catalog.php
├─ student_my_courses.php
├─ student_register_course.php
├─ student_course_detail.php
├─ student_course_learn.php
├─ student_lesson_view.php
├─ student_do_exam.php
├─ student_exam_result.php
├─ student_exams.php
├─ student_forum.php
├─ student_profile.php

# COMMON
├─ index.php
## 2. Công nghệ sử dụng


- Ngôn ngữ: **PHP (thuần, hướng thủ tục / OOP đơn giản)**  
- CSDL: **MySQL**
- Web server: **Apache** (XAMPP / Laragon / WAMP)
- Frontend:
  - **HTML5, CSS3, JavaScript**
  - **Bootstrap** (giao diện responsive, card, button, nav,…)
- Phiên làm việc: **PHP Session** để quản lý đăng nhập & phân quyền.

---

## 3. Cấu trúc CSDL (tóm tắt)

Database: `elearning_ptit`

Một số bảng chính:

- `nguoi_dung` – quản lý thông tin người dùng (admin, giảng viên, sinh viên)  
  - Gồm: tài khoản, mật khẩu (mã hóa), vai_trò, họ_tên, email, mã_sinh_viên, lớp_học, trạng_thái, …
- `khoa_hoc` – thông tin khóa học.
- `dang_ky_khoa_hoc` – mối quan hệ sinh viên – khóa học, trạng thái đăng ký, thời gian học.
- `bai_giang` – nội dung bài giảng (video, link, file, nội dung HTML, thứ tự hiển thị).
- `luot_xem_bai_giang` – log lượt xem bài giảng của sinh viên.
- `bai_thi`, `cau_hoi`, `lua_chon` – định nghĩa bài thi trắc nghiệm.
- `lan_thi`, `bai_lam_chi_tiet` – lưu kết quả làm bài của sinh viên.
- `chu_de_dien_dan`, `bai_dang_dien_dan` – chức năng diễn đàn / thảo luận (nếu có dùng).
- `cau_hinh_he_thong`, `thong_bao` – thông tin cấu hình, thông báo chung.

---

## 4. Cài đặt & chạy dự án trên localhost

### 4.1. Yêu cầu môi trường

- PHP >= 7.4  
- MySQL >= 5.7  
- XAMPP / WAMP / Laragon (khuyến nghị XAMPP)
- Trình duyệt: Chrome, Edge, Firefox…

### 4.2. Các bước cài đặt

1. **Clone hoặc copy source code** vào thư mục web server  
   - Với XAMPP: `htdocs/elearning_ptit`
2. **Tạo database**
   - Mở phpMyAdmin → tạo mới database: `elearning_ptit`
   - Import file `elearning_ptit.sql` (nếu có kèm trong dự án).
3. **Cấu hình kết nối CSDL**
   - Mở file `config.php` (hoặc file cấu hình tương đương).
   - Chỉnh thông tin:
     ```php
     $db_host = 'localhost';
     $db_name = 'elearning_ptit';
     $db_user = 'root';
     $db_pass = ''; // mật khẩu MySQL của bạn
     ```
4. **Tạo tài khoản admin mặc định**
   - Có thể được tạo sẵn trong file `.sql`,  
   - Hoặc tự INSERT một dòng vào bảng `nguoi_dung` với `vai_tro = 'admin'`.
5. **Chạy dự án**
   - Khởi động Apache + MySQL trong XAMPP.
   - Truy cập trình duyệt:
     - Trang đăng nhập: `http://localhost:3000/index.php` (hoặc URL tương ứng bạn cấu hình).
     - Sau khi đăng nhập:
       - Admin → `admin_dashboard.php`
       - Sinh viên → `student_dashboard.php`
       - Giảng viên → `teacher_dashboard.php` (nếu có).

---

