# Moodle Custom Plugins Collection

Bộ sưu tập các tiện ích mở rộng (Local Plugins) dành cho nền tảng Moodle LMS.

---

## 📦 Danh sách Plugin

### 1. `local_courseplan` - Quản lý Tiến độ Biên soạn Giáo trình & Đề cương
* **Phiên bản:** 1.2.0 (Build 2026111003)
* **Tương thích:** Moodle 4.x - 5.0+
* **Tính năng chính:**
  * Lập kế hoạch và đề cương chi tiết theo Chương / Mục / Bài học.
  * Phân công biên soạn cho từng giáo viên / giảng viên.
  * Nhập danh sách bài học tự động từ file **Excel (.xlsx)** / CSV với tính năng tự động khớp tài khoản giáo viên theo Họ tên, Username, Email.
  * Tải file mẫu Excel chuẩn có dropdown chọn yêu cầu nộp file (*Có / Không*).
  * Biểu đồ cột trực quan thống kê tiến độ biên soạn theo từng giáo viên (Số bài trong kế hoạch, Đã hoàn thành, Chưa hoàn thành) với cột **Tổng** hiển thị mặc định ở đầu trục.
  * Bảng thống kê chi tiết tỷ lệ hoàn thành (%) theo từng giáo viên.
  * Xuất báo cáo tiến độ chuẩn định dạng sang **Microsoft Excel (.xls)** và **Microsoft Word (.doc)**.

### 2. `local_antishot` - Bảo vệ Nội dung & Chống Chụp Màn Hình
* **Tương thích:** Moodle 4.x - 5.0+
* **Tính năng chính:**
  * Ngăn chặn hành vi chụp màn hình (PrintScreen, Snipping Tool, phím tắt chụp màn hình).
  * Chống sao chép nội dung câu hỏi thi và bài giảng.
  * Ghi nhận log cảnh báo các vi phạm về phía máy chủ.

---

## 🚀 Hướng dẫn Cài đặt trên Moodle

1. Tải hoặc clone repository về máy tính:
   ```bash
   git clone https://github.com/Meepo1217/Plugin_Moodle.git
   ```
2. Copy từng thư mục plugin (`courseplan` hoặc `antishot`) vào thư mục `moodle/local/` trên server:
   * Đường dẫn: `{đường_dẫn_moodle}/local/courseplan`
   * Đường dẫn: `{đường_dẫn_moodle}/local/antishot`
3. Truy cập vào Moodle với quyền Administrator (`http://your-moodle-domain/admin`) để hoàn tất nâng cấp cơ sở dữ liệu.
