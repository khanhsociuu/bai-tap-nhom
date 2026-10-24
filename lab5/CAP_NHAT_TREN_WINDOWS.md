# Hướng dẫn cập nhật DNU JUDGE 2.0 — Windows 11 + VS Code + WSL

## A. KHÔNG mất cơ sở dữ liệu

Bạn đã tạo PostgreSQL `dnu_judge_lab5` và nạp 10 bài / 71 test. **KHÔNG xóa database** và **KHÔNG chạy lại `seed`**. Để an toàn, đóng terminal chạy Web/worker (`Ctrl+C`) và sao lưu toàn bộ thư mục `C:\Users\ADMIN\Documents\Ma_Nguon_Mo\lab5` trước khi chép bản mới.

1. Tải và giải nén `DeTai20_Lab05_OnlineJudge_V2.zip`.
2. Vào bên trong thư mục `DeTai20_Lab05_OnlineJudge` vừa giải nén.
3. Sao chép các mục `src`, `public`, `cli`, `database`, `tests`, `lab.sh`, `README.md` và `THAY_DOI_V2.md`.
4. Dán chúng vào **`C:\Users\ADMIN\Documents\Ma_Nguon_Mo\lab5`** rồi chọn **Replace** cho file trùng.
5. **Không dán vào `lab5\Ma_nguon`** (đó là mã CommunityPass bài Lab 5 gốc).
6. **Không xóa hay ghi đè `lab5\.local`**: trong đó có `db.json` và mật khẩu kết nối PostgreSQL. Dữ liệu thật nằm trong PostgreSQL chứ không nằm trong ZIP.

## B. Chạy Docker

Mở Docker Desktop trên Windows, bật **Settings → Resources → WSL Integration** cho Ubuntu. Trong terminal WSL của VS Code:

```bash
cd /mnt/c/Users/ADMIN/Documents/Ma_Nguon_Mo/lab5
docker info
docker pull python:3.12-alpine
docker pull gcc:14
bash lab.sh check
bash lab.sh smoke
```

Khi `smoke` đạt, Docker chạy được các mẫu Python/C++ an toàn trong container, độc lập với dữ liệu PostgreSQL.

## C. Chạy hệ thống

Terminal Ubuntu thứ nhất:

```bash
cd /mnt/c/Users/ADMIN/Documents/Ma_Nguon_Mo/lab5
bash lab.sh run
```

Terminal Ubuntu thứ hai (giữ chạy cùng lúc):

```bash
cd /mnt/c/Users/ADMIN/Documents/Ma_Nguon_Mo/lab5
bash lab.sh worker
```

Mở http://127.0.0.1:8105. Tài khoản demo: `student_a`, `student_b`, `lecturer`, `admin` và mật khẩu **bạn đã đặt trong lần seed thành công trước**.

## D. Kiểm tra Đề tài 20

Terminal thứ ba:

```bash
cd /mnt/c/Users/ADMIN/Documents/Ma_Nguon_Mo/lab5
php tests/unit.php
bash lab.sh test
```

- Sinh viên: **Bài tập → Tiến độ → chọn bài → chạy thử → nộp → lịch sử**.
- Giảng viên: **Quản lý → Sửa đề → Bộ test v2 → chấm lại bài trong lớp/thuộc đề mình quản lý → so sánh lần chấm**.
- Khi dừng worker/Docker giữa tác vụ, xử lý đúng là `SYSTEM_ERROR` hoặc chờ thử lại, không phải `WA`.
- Worker không được chạy trong PHP Web. Không mở cổng 8105 ra Internet cho tới khi hệ thống được kiểm thử và triển khai cách ly riêng.

**Nếu `bash lab.sh check` còn báo `docker: command not found`, kiểm tra Docker Desktop đã mở và WSL Integration đã bật; khởi động lại WSL bằng `wsl --shutdown` trong PowerShell (sẽ dừng các terminal WSL hiện có), sau đó mở lại Ubuntu.**
