# KẾT QUẢ KIỂM TRA DNU JUDGE 2.0

Kiểm tra tại môi trường tạo gói (không có PostgreSQL hoặc Docker daemon):

| Hạng mục | Kết quả |
|---|---|
| Kiểm tra cú pháp mọi tệp `.php` bằng `php -l` | PASS |
| `node --check public/app.js` | PASS |
| `php tests/unit.php` | UNIT: TESTS=22 FAILED=0 |
| `python3 tests/samples.py` | SAMPLES: TESTS=81 FAILED=0 |
| `python3 tests/samples.py --cpp` | SAMPLES: TESTS=202 FAILED=0 |
| `php tests/docker_smoke.php` | NOT_RUN — Docker không có trong môi trường tạo gói |
| `php tests/integration.php` | NOT_RUN — PostgreSQL không có trong môi trường tạo gói |
| Web + PostgreSQL + Docker worker (end-to-end) | NOT_RUN, cần chạy trên máy của bạn |

Bổ sung kiểm thử tích hợp: xác nhận heartbeat gia hạn quyền của run đúng token, từ chối sai token; nhưng phần này chỉ được xác nhận khi chạy với PostgreSQL của bạn.

**Kết quả cú pháp và bài mẫu không thay thế phép thử Docker chấm thật.** Sau khi cập nhật bản v2 lên máy bạn, chạy `bash lab.sh smoke` và `bash lab.sh test`, thử các tình huống AC/WA/CE/RE/TLE/SYSTEM_ERROR, kiểm tra phân quyền student_a/student_b và chấm lại v1/v2.
