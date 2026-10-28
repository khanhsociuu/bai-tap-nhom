# HƯỚNG DẪN CẬP NHẬT DNU JUDGE 3.0 — WINDOWS 11 / WSL

## 1. Cập nhật an toàn, không mất PostgreSQL

Dự án đang chạy của bạn: `C:\Users\ADMIN\Documents\Ma_Nguon_Mo\lab5`.

1. Dừng hai terminal **Web** và **Worker** bằng `Ctrl+C`.
2. Sao lưu thư mục `lab5` ở Windows, ví dụ `lab5_backup`. **Đặc biệt giữ `lab5\.local\db.json`.**
3. Giải nén `DNU_JUDGE_V3_Sua_Loi_Worker_Docker.zip`.
4. Mở thư mục `DeTai20_Lab05_OnlineJudge` bên trong ZIP.
5. Sao chép nội dung thư mục đó (`src`, `public`, `cli`, `tests`, `database`, `lab.sh`, tài liệu, v.v.) trực tiếp vào thư mục `lab5` và chọn **Replace** khi trùng file.
6. **Không** sao chép vào `lab5\Ma_nguon` (đó là CommunityPass Lab 5 cũ). **Không xóa `.local`**, không xóa database `dnu_judge_lab5`.
7. Bản v3 **không thay đổi schema**, không phải chạy lại `schema`, `seed`, tạo lại database hay thay đổi mật khẩu.

Lưu ý: `private_seed/` chứa code đúng/sai và test kín, chỉ lưu máy giảng viên/worker; không đẩy lên GitHub công khai.

## 2. Khởi động PostgreSQL và Docker

Mở Docker Desktop (thấy **Engine running**), bật WSL Integration cho Ubuntu. Mở 3 terminal Ubuntu/WSL. Nếu terminal là PowerShell, gõ `wsl` một lần trước khi chạy lệnh Linux.

```bash
cd /mnt/c/Users/ADMIN/Documents/Ma_Nguon_Mo/lab5
sudo service postgresql start
bash lab.sh images       # chỉ cần chạy lần đầu/thiếu image, có thể tải nhiều MB
bash lab.sh doctor       # kiểm tra PostgreSQL, bảng, Docker image, thư mục tạm
bash lab.sh smoke        # thử Docker thực với Python/C++ + lỗi CE/RE/TLE
```

**Nếu `smoke` có FAILED, chưa chạy thử bằng Web.** Chụp nguyên lỗi smoke/worker để sửa. Docker Desktop đôi khi cần thêm thời gian khi image GCC lần đầu tải về.

## 3. Ba terminal chạy hệ thống

**Terminal 1 – WEB** (giữ mở):

```bash
cd /mnt/c/Users/ADMIN/Documents/Ma_Nguon_Mo/lab5
bash lab.sh run
```

Web: http://127.0.0.1:8105

**Terminal 2 – WORKER** (giữ mở):

```bash
cd /mnt/c/Users/ADMIN/Documents/Ma_Nguon_Mo/lab5
bash lab.sh worker
```

Worker báo `Worker READY` khi đã xác nhận được các Docker image và PostgreSQL. Nếu báo thiếu image: trở lại terminal 3, chạy `bash lab.sh images`. Chạy lần thử bằng tay, có thể thấy `Trying #...` / `Trial recorded`, và nộp bài sẽ thấy `Judging run #...` / `Recorded`.

**Terminal 3 – DEBUG / KIỂM THỬ**:

```bash
cd /mnt/c/Users/ADMIN/Documents/Ma_Nguon_Mo/lab5
bash lab.sh doctor
php tests/unit.php
php tests/runner_contract.php
bash lab.sh test
```

`bash lab.sh test` bao gồm bài kiểm tra CSDL PostgreSQL; nếu môi trường chưa đủ, trả `NOT_RUN`/lỗi, không coi là thành công. `runner_contract.php` dùng **Docker giả lập** để kiểm tra luồng Runner; `smoke` mới là phép thử Docker thật.

## 4. Kiểm tra 5 tình huống quan trọng

1. Sinh viên `student_a` → Tổng hai số nguyên → Python → nhập stdin `2 3` → **Chạy thử**. stdout mong đợi `5`.
2. **Lưu nháp**, tải lại trang, xác minh mã vẫn tồn tại. Có thể lưu bản nháp rỗng.
3. **Nộp bài** bằng mã đúng → trạng thái queued/running/done và AC. Mã sai cho WA; lỗi cú pháp cho CE; lỗi chạy cho RE; vòng lặp vô tận cho TLE.
4. Đăng nhập `admin` → **Hệ thống**: nếu một lần chấm là `system_error`, sau khi sửa Docker có thể nhấn **Chấm lại**, tạo lượt mới, giữ lượt cũ.
5. `student_b` không truy cập được mã bài nộp của `student_a`. Giảng viên chỉ xem bài trong lớp được phân quyền.

**Kiểm thử chấm lại phiên bản test:** đăng nhập giảng viên, tạo phiên bản bộ test mới phù hợp phiên bản đề, chấm lại và đối chiếu hai lượt. `latest_run_id` phải trỏ lượt mới; kết quả cũ vẫn ở bảng `judging_runs`.

## 5. Cách đọc lỗi

- `Docker unavailable or image missing`: kiểm tra Docker Desktop và chạy `bash lab.sh images`.
- `Judge Docker ... infrastructure failure`: xem dòng lỗi Docker ở **terminal Worker** hoặc `bash lab.sh smoke`, không tính là WA.
- `Failed opening required public/router.php`: dùng **`bash lab.sh run`** của v3, script dùng đường dẫn tuyệt đối.
- Trang trả “Dịch vụ đang gặp sự cố – Mã hỗ trợ”: xem terminal Web để đối chiếu mã `[DNU-WEB ...]` (không hiển thị stack trace ra trình duyệt).
- Lỗi PostgreSQL: `sudo service postgresql start`, rồi `bash lab.sh doctor`.
- Website hiện `0 bài`: dùng `bash lab.sh doctor`; dữ liệu đã có thì **không seed lại**.

## 6. Giới hạn bảo mật

Bản này phục vụ thử nghiệm/lab **trên localhost**. Không mở website trực tiếp ra Internet hoặc cho phép người lạ nộp mã vào máy cá nhân. Docker là một lớp cách ly, không phải bảo đảm an toàn tuyệt đối. Khi triển khai thật cần máy/VM chuyên dụng, tài khoản Unix tách biệt cho Web và worker, giới hạn tài nguyên ở mức máy chủ, kiểm toán Docker image và hệ thống giám sát hoạt động.
