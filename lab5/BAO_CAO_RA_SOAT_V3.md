# Báo cáo rà soát — DNU JUDGE 3.0 / Đề tài 20

## A. Phát hiện và đã sửa

| Vấn đề của v2 | V3 đã làm | Kiểm tra |
|---|---|---|
| Runner ghi file tạm vào `.runtime` dưới `/mnt/c/...` (WSL/Windows); Docker bind mount có thể chậm hoặc sai quyền | Chuyển sang thư mục riêng dưới Linux `sys_get_temp_dir()` (`/tmp` trên Ubuntu), phân quyền hạn chế, xóa sau khi chấm | Runner contract giả lập |
| Tải Docker image khi đang chấm làm chậm/timeout sai | Worker xác nhận image sẵn có; `bash lab.sh images` tải trước; `--pull never` khi thực thi | Chạy thật `bash lab.sh smoke` trên máy có Docker |
| Timeout phía host tính cả thời gian Docker khởi động | Thêm `timeout` bên trong container, watchdog bên ngoài có khoảng đệm; hỗ trợ mã thoát 124 của GNU và 143 của BusyBox | Runner contract; Docker smoke còn cần máy người dùng |
| Hạ tầng lỗi nhưng biên dịch bị gán CE trong vài trường hợp | Phân biệt lỗi Docker/permission/không có tệp nhị phân -> `SYSTEM_ERROR`; không chấm người học sai | Runner contract |
| Worker có thể mất hàng đợi nếu PostgreSQL gián đoạn | Bắt lỗi vòng lặp, thử kết nối lại; luân phiên ưu tiên trial và bài nộp | Cần thử thực tế |
| Khởi động Web dùng router tương đối gây lỗi thiếu `public/router.php` | `lab.sh run` dùng đường dẫn tuyệt đối, log lỗi ở stderr | Bash -n và PHP lint |
| PHP trang chủ/chi tiết có thể trả HTTP 500 trắng | Exception handler báo 503, mã tra cứu, ghi lỗi an toàn ở terminal | PHP lint, cần chạy PHP Web thật |
| Frontend báo lỗi `Unexpected end of JSON input` khó hiểu | Kiểm tra phản hồi JSON, hiển thị HTTP status và gợi ý kiểm tra máy chủ | Node syntax check |
| Bản nháp rỗng không lưu được | Cho phép mã trống ở chức năng Lưu nháp; nộp bài vẫn từ chối trống | Unit tests |
| Lỗi hệ thống phải giữ lịch sử khi chấm lại | Admin có thao tác chấm lại lượt `system_error` theo phiên bản bộ chấm ban đầu | Cần PostgreSQL integration |
| Khó kiểm tra trạng thái | Bổ sung `bash lab.sh doctor`, báo số bài/test, trạng thái queue và Docker images | PHP lint |

## B. Yêu cầu đề tài: đã lập trình nhưng cần kiểm thử trên máy người dùng

1. CRUD bài/chủ đề/mức độ, đề–bộ chấm nhiều phiên bản, 5+ ca/bài: ĐÃ CÓ; nên kiểm thử thêm luồng giảng viên tạo/sửa/chuyển phiên bản.
2. Trình soạn mã Python/C++, lưu nháp và chạy thử: ĐÃ CÓ; v3 sửa Runner và API; **phải xác nhận bằng Docker smoke và Web**.
3. Nộp bài, idempotency, queue, lần thực thi và verdict AC/WA/CE/RE/TLE/SYSTEM_ERROR: ĐÃ CÓ CODE; **chưa xác nhận end-to-end**.
4. Lịch sử, tiến độ và phân quyền người học/lớp: ĐÃ CÓ; cần chạy test người A/B trên phiên đăng nhập riêng.
5. Version bộ chấm, chấm lại, giữ mã và run cũ, chống kết quả cũ ghi đè: ĐÃ CÓ CODE; cần test đồng thời và DB thực tế.

## C. Bằng chứng kiểm thử trong môi trường đóng gói

- Kiểm tra cú pháp PHP toàn dự án: PASS.
- Kiểm tra cú pháp JavaScript `public/app.js`: PASS.
- `php tests/unit.php`: **TESTS=24 FAILED=0**.
- `php tests/runner_contract.php` (mô phỏng Docker, không dùng daemon): **TESTS=7 FAILED=0**.
- `python3 tests/samples.py` (mã Python mẫu): **TESTS=81 FAILED=0**.
- Biên dịch/chạy một mẫu C++ Tổng hai số nguyên bằng g++: PASS, stdin `2 3` -> stdout `5`.
- **Chưa chạy** `bash lab.sh smoke` với Docker thực và `tests/integration.php` với PostgreSQL: môi trường đóng gói không có Docker daemon hoặc extension PostgreSQL. Không khẳng định đã hết lỗi trên WSL của người dùng.

## D. Việc nên làm tiếp theo nếu muốn triển khai công khai

- Tách quyền Web và worker thành hai Unix users; Web không được quyền Docker socket.
- Phát triển worker heartbeat/monitoring riêng để hiển thị trạng thái online/offline cho admin.
- Bổ sung kiểm thử tích hợp tự động trên Docker/PG ở CI, kiểm thử đồng thời nhiều worker và lease khi bị ngắt đột ngột.
- Thêm xuất CSV tiến độ theo lớp, lọc bài theo trạng thái, và phân trang dữ liệu nhiều bài nộp.
- Dùng HTTPS, reverse proxy, session/CSRF hardening, rate limit nhiều tài khoản/IP, secrets manager khi triển khai mạng.

**Không dùng bản nguyên mẫu này để thực thi mã người lạ trên máy chứa dữ liệu cá nhân.**
