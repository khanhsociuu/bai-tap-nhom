# Biên bản kiểm tra mã nguồn — Đề tài 20 / Lab 05

## A. Phần đã kiểm tra được trong môi trường tạo gói

| Nội dung | Kết quả |
|---|---|
| Kiểm tra cú pháp toàn bộ tệp PHP (`php -l`) | Không có lỗi cú pháp |
| Kiểm tra cú pháp JS (`node --check public/app.js`) | Không có lỗi |
| `php tests/unit.php` | **TESTS=22 FAILED=0** |
| `python3 tests/samples.py` | **TESTS=81 FAILED=0** |
| `python3 tests/samples.py --cpp` | **TESTS=202 FAILED=0** (biên dịch và đối chiếu các ví dụ C++ cục bộ) |
| Tài nguyên mô phỏng | **10 bài, 71 ca thử, 20 ví dụ Python + 20 ví dụ C++** |

## B. Phần chưa được kiểm tra trên PostgreSQL / Docker

- Môi trường đóng gói thiếu `php-pgsql`, PostgreSQL và Docker; `php tests/integration.php` in **INTEGRATION: NOT_RUN (PGSQL_EXTENSION_MISSING)**.
- Chưa thực thi `bash lab.sh schema`, `seed`, luồng giao diện ↔ CSDL, worker ↔ Docker bằng môi trường đích.
- Chưa thực nghiệm thực tế 5 phép thử chấm AC/WA/CE/RE/TLE qua Docker, mất worker, chấm lại đồng thời, và quyền truy cập qua HTTP. Có mã xử lý và test tích hợp tương ứng nhưng chỉ được đánh giá là **đã lập trình, chưa xác minh môi trường đích**.
- **NOT_RUN khác PASS**. Mọi tuyên bố chấm thực tế phải được xác nhận sau khi chạy trên WSL.

## C. Ma trận bài kiểm thử để quay màn hình nộp giảng viên

| STT | Thao tác | Kỳ vọng | Đã xác minh ở môi trường đích? |
|---|---|---|---|
| 1 | Chạy 10 bài mẫu đúng và sai (Python/C++) | Mẫu đúng AC, sai WA | Chưa (đã kiểm tra fixture cục bộ) |
| 2 | Nộp Python cú pháp sai | CE | Chưa |
| 3 | Nộp Python `1/0` | RE | Chưa |
| 4 | Nộp `while True: pass` | TLE | Chưa |
| 5 | Ngắt Docker, worker nhận tác vụ | SYSTEM_ERROR/chờ retry, không WA | Chưa |
| 6 | Gửi lại 2 request chung UUID | Cùng ID, chỉ 1 dòng DB | Chưa (có test tích hợp) |
| 7 | Gửi UUID mới | Tạo submission mới | Chưa (có test tích hợp) |
| 8 | Tạo bộ test v2, chấm lại | v2 hiện hành, v1 giữ lịch sử | Chưa (có test tích hợp) |
| 9 | A mở source B và gọi API status B | HTTP 403 | Chưa (có test tích hợp) |
| 10 | Xem Web/API/Network | Không có test kín, Docker config, mật khẩu | Chưa (đã rà soát trường trả về bằng kiểm thử unit) |
| 11 | Dừng rồi bật PHP | Mọi bản nộp vẫn tồn tại | Chưa |
| 12 | Lớp chưa có submission | Thống kê số lượt bằng 0 | Chưa |

## D. Lưu ý

* Bộ `private_seed` là tài nguyên kín cho người thực hành/giảng viên, `.gitignore` đã loại khỏi nguồn công khai.
* Không đăng tải ảnh màn hình chứa mật khẩu, tệp `.local/db.json` hoặc dữ liệu kiểm thử kín.
* Trước khi nộp bài, bổ sung **ảnh chụp lệnh trên máy WSL của bạn** cùng kết quả và ghi rõ phiên bản PHP/PostgreSQL/Docker.
