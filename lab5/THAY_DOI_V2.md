# DNU JUDGE 2.0 — Nâng cấp Đề tài 20

## Thay đổi chính

1. **Dashboard riêng** `public/dashboard.php`: tổng số bài AC (đã từng chấm AC), lượt nộp, bài đã thử, tỷ lệ hoàn thành, tiến độ theo chủ đề và lịch sử gần đây.
2. **Thư viện bài tập**: lọc tìm kiếm/chủ đề/mức độ/trạng thái Chưa làm – Đang luyện – Đã giải; hiển thị cá nhân hóa sau đăng nhập.
3. **Trình giải bài**: giao diện hai cột, CodeMirror cục bộ với tô màu Python/C++ (có phương án textarea dự phòng), tab stdin và stdout/stderr, phím tắt Ctrl+Enter, nút lưu nháp.
4. **Chấm lại**: bảng so sánh hai lần chấm gần nhất cùng mã nguồn; vẫn giữ lịch sử đầy đủ.
5. **Giảng viên**: thống kê trực quan bài quản lý, bài công khai, tiến độ lớp; liên kết nhanh tạo đề, quản lý test.
6. **An toàn & điều phối**: bổ sung heartbeat giữa các ca nhằm không hết hạn lease khi chấm bộ nhiều test; worker hết lease ngừng thực thi; API không cho tạo nháp cho bài ẩn, yêu cầu quyền quản lý đề để chấm lại, thêm header chống MIME sniff/frame.

## Kiểm tra còn phải thực hiện trên máy người dùng

- Docker daemon và 2 image Python/GCC; 5 tình huống chấm AC, WA, CE, RE, TLE và lỗi worker.
- Kết nối PHP ↔ PostgreSQL, dịch vụ Web ↔ worker; phân quyền student A vs B.
- Chấm lại bộ test v2, xác nhận `latest_run_id` vẫn trỏ về run mới và run cũ còn lưu.
- CodeMirror được đóng gói cục bộ, không cần CDN; editor textarea vẫn là phương án dự phòng.

**Không có migration schema** cho thay đổi 2.0. Giữ nguyên DB và `.local/db.json`; không chạy lại seed nếu dữ liệu đã tồn tại.
