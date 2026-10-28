# DNU JUDGE 3.1 – Sửa hai lỗi Docker Worker đã thấy trên máy Windows 11/WSL

## 1. Lỗi đã có bằng chứng trong ảnh

- `FAIL Docker image gcc:14` và `No such image: gcc:14`: gói `gcc:14` chưa tải xong; dòng `failed to copy: httpReadSeeker...` là lỗi tải image từ registry, không phải lỗi PostgreSQL. Ở bản v3, worker yêu cầu cả Python + C++ image nên không khởi động ngay cả khi image Python đã sẵn sàng.
- `Python infinite loop TLE expected=TLE actual=SYSTEM_ERROR` và `exit=137`: Docker kết thúc container bằng SIGKILL, mã 137 **không đủ để kết luận** là TLE (cũng có thể là OOM). BusyBox timeout trong Alpine có thể dẫn tới mã kết thúc khác GNU timeout.

## 2. Đã thay đổi trong bản vá 3.1

- `src/Runner.php`, `src/python_supervisor.py`: chạy Python bằng bộ giám sát đặt **bên trong container**; dùng `subprocess.wait(timeout)` và trả chính xác 124 khi hết hạn; mã 137 không bị tự gán TLE. Không chạy mã người học trong PHP Web.
- `cli/worker.php`: vẫn cho chạy Python nếu chỉ có image Python; báo cảnh báo thiếu C++ thay vì đóng worker. Đã có image C++ thì hỗ trợ cả hai ngôn ngữ.
- `docker/cpp.Dockerfile`, `lab.sh images`: hỗ trợ build image C++ `dnu-judge-cpp:1` từ Alpine + g++; image nhỏ hơn đáng kể so với tải toàn bộ `gcc:14`. Nếu máy đã có `gcc:14`, dùng luôn image đó.
- `cli/doctor.php`, `tests/docker_smoke.php`: kiểm tra cả image thay thế, trả kết quả dễ đọc.
- `tests/runner_contract.php`, `tests/supervisor_check.py`: mở rộng kiểm thử phân biệt TLE với SIGKILL/OOM.
- **Không thay đổi schema PostgreSQL**; không `schema` hoặc `seed` lại; không ghi đè `.local/db.json`.

## 3. Chép bản vá vào đúng vị trí

Dừng terminal `bash lab.sh worker` và `bash lab.sh run` bằng Ctrl+C, sao lưu thư mục `lab5` trước.

Giải nén `DNU_JUDGE_V31_BAN_VA_WORKER.zip`. Sao chép **nội dung bên trong** thư mục `DeTai20_Lab05_OnlineJudge/` vào:

`C:\Users\ADMIN\Documents\Ma_Nguon_Mo\lab5`

Chọn Replace khi trùng file. **Không** sao chép vào `lab5/Ma_nguon` (CommunityPass) và không xóa `.local`.

## 4. Cài image nhỏ hơn (nếu chưa có gcc:14)

Mở Docker Desktop đến khi có `Engine running`. Mở terminal Ubuntu/WSL:

```bash
cd /mnt/c/Users/ADMIN/Documents/Ma_Nguon_Mo/lab5
bash lab.sh images
```

Nếu `gcc:14` không có, lệnh sẽ **tự build** `dnu-judge-cpp:1` từ Alpine. Bước này vẫn cần mạng để tải các gói `g++`; đợi lệnh kết thúc, không coi download dở là thành công.

Kiểm tra:

```bash
docker image ls
bash lab.sh doctor
```

Bạn cần thấy `python:3.12-alpine` và ít nhất một trong `dnu-judge-cpp:1` hoặc `gcc:14`.

Nếu Docker Hub/mirror vẫn không truy cập được: bạn vẫn có thể chạy **Python** với worker phiên bản 3.1, nhưng phần C++ chưa hoạt động. Khi đường mạng ổn định, chạy lại `bash lab.sh images` hoặc `docker pull gcc:14`.

## 5. Kiểm thử trước khi mở website

```bash
bash lab.sh supervisor-test    # Kiểm tra logic timeout Python không cần Docker
bash lab.sh smoke              # Docker THẬT: OK, CE, RE, TLE, C++ OK/CE
```

Chỉ khi Docker smoke đạt 6/6 mới coi là cả Python và C++ đã sẵn sàng. Nếu thất bại, lưu nguyên log.

## 6. Ba terminal chạy hệ thống

Terminal 1 - Website:

```bash
cd /mnt/c/Users/ADMIN/Documents/Ma_Nguon_Mo/lab5
bash lab.sh run
```

Terminal 2 - Worker:

```bash
cd /mnt/c/Users/ADMIN/Documents/Ma_Nguon_Mo/lab5
bash lab.sh worker
```

Worker phải in `Worker READY`. Nếu báo WARNING thiếu C++ thì có thể chạy thử **Python** trước và làm lại image C++ sau.

Terminal 3 - Kiểm tra:

```bash
cd /mnt/c/Users/ADMIN/Documents/Ma_Nguon_Mo/lab5
bash lab.sh doctor
```

Vào `http://127.0.0.1:8105`, **tạo yêu cầu Chạy thử MỚI**, vì các lượt cũ ở trạng thái SYSTEM_ERROR không tự đổi kết quả.

Bài “Tổng hai số nguyên” → Python → stdin `2 3` → kết quả `5`; nộp mã đúng → AC, mã sai → WA. Sau khi image C++ sẵn sàng, chọn C++17 và thử lại.

## 7. Giới hạn xác minh và an toàn

Phiên bản vá đã qua kiểm thử tĩnh/giả lập và kiểm tra supervisor Python thực trên môi trường phát triển, nhưng **chưa thử end-to-end với Docker Desktop và PostgreSQL trên máy bạn**. Không khẳng định đã sửa xong tất cả cho đến khi `bash lab.sh smoke` và các thao tác trên Web đạt.

Chỉ dùng DNU JUDGE bản thực hành trên localhost với mã của nhóm. Tránh mở Docker socket hoặc dịch vụ Web ra Internet; Docker một mình không bảo đảm cách ly hoàn toàn mã độc từ người dùng không tin cậy.
