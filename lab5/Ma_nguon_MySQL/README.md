# DNU JUDGE — Đề tài 20 / Phát triển phần mềm mã nguồn mở / Lab 05

**Đề tài:** Xây dựng hệ thống luyện tập và chấm bài lập trình trực tuyến.  
**Phiên bản:** 1.0 — nguyên mẫu thực hành chạy **cục bộ** bằng PHP 8.1+, PostgreSQL 14+, Docker và WSL/Ubuntu.  
**Tài liệu nền:** Lab 05 `HUONG_DAN (3).md`: cấu hình `db()`, `queryParams()`, `schema.sql`, mô-đun Repository/Stats, khóa `UNIQUE`, phân biệt trùng và lỗi CSDL, kiểm thử bền vững, `LEFT JOIN`.

> Dự án này là **phần chuyển đổi nghiệp vụ từ CommunityPass sang Online Judge**, không phải mã của CommunityPass. Không ghi đè tệp nguồn Lab05 gốc vì chưa được cung cấp. Có thể để nguyên thư mục gốc, đặt dự án này cạnh `Lab05/Ma_nguon` hoặc trong một thư mục con độc lập. **Không chạy `schema.sql` vào CSDL của bài Lab 05 cũ.**

## 1. Các chức năng đã lập trình

| Yêu cầu Đề tài 20 | Các thành phần |
| --- | --- |
| 1. Quản lý bài/chủ đề/mức độ/phiên bản giới hạn chạy | `public/teacher.php`, `teacher_edit.php`, `suite.php`, `src/TeacherService.php`, `database/schema.sql` |
| 2. Soạn mã, lưu bản nháp, chạy thử stdin → stdout/stderr | `public/problem.php`, `public/app.js`, `public/api.php`, bảng `drafts`, `trial_runs`, CLI worker |
| 3. Nộp, hàng đợi, mỗi ca và tổng hợp | `submissions`, `judging_runs`, `case_results`, `src/JudgeService.php`, `src/Runner.php` |
| 4. Tra cứu mã/lịch sử/tiến độ, phân quyền lớp | `public/history.php`, `submission.php`, `src/StudentStats.php`, `canViewSubmission()` |
| 5. Tạo bộ test v2, chấm lại và giữ nguyên lịch sử v1 | `test_suite_versions`, `JudgeService::rejudge()`, `suite.php`, `submission.php` |
| Quản trị thực thi và tài khoản | `public/admin.php` hiển thị hàng đợi, quản lý tài khoản/lớp; worker vận hành **chỉ qua CLI** |

**Phiên bản:** `problems` 1–N `problem_versions`, `topics` 1–N `problems`, `levels` 1–N `problems`, `problem_versions` 1–N `test_suite_versions`, mỗi `test_suite_versions` 1–N `test_cases`; một `submissions` có nhiều `judging_runs`, mỗi lần chấm có nhiều `case_results`. Mỗi bản nộp lưu ID phiên bản đề, bộ test lúc nộp và ngôn ngữ. Khi chấm lại, phiên bản đề phải khớp với bộ chấm mới.

## 2. Chuẩn bị trên Windows 11 / WSL Ubuntu

Mở terminal **Ubuntu/WSL**, không chạy các lệnh Bash trong PowerShell. Đặt thư mục dự án vào WSL, ví dụ `~/DeTai20_Lab05_OnlineJudge`. Nếu đã cài PHP/PostgreSQL theo Lab 05, có thể bỏ qua phần cài trùng.

```bash
sudo apt update
sudo apt install -y php-cli php-pgsql postgresql postgresql-client git unzip python3 g++
sudo service postgresql start
php -v
php -m | grep pgsql
psql --version
```

Docker: khuyến nghị **Docker Desktop bật WSL Integration** cho Ubuntu, hoặc cài Docker Engine hợp lệ trong Ubuntu. Xác minh Docker daemon thực sự chạy:

```bash
docker version
docker info
docker pull python:3.12-alpine
docker pull gcc:14
```

Nếu gặp `Cannot connect to Docker daemon`, xử lý Docker Desktop/WSL Integration hoặc Docker Engine, **không** chạy trực tiếp mã nộp bằng `shell_exec`/`python`/`g++` trong PHP Web để chữa tạm.

### Tạo PostgreSQL riêng (chỉ 1 lần)

```bash
sudo -u postgres psql
```

Trong `psql`:

```sql
CREATE ROLE dnu_judge LOGIN;
\password dnu_judge
CREATE DATABASE dnu_judge_lab5 OWNER dnu_judge;
\q
```

Tạo role/database **chỉ khi chưa tồn tại**. Nếu có rồi, giữ lại, không `DROP DATABASE` hoặc đổi mật khẩu bừa bãi. Có thể đổi tên role/database và dùng tên thực khi cấu hình.

Vào thư mục dự án và thiết lập kết nối:

```bash
cd ~/DeTai20_Lab05_OnlineJudge
bash lab.sh db-config
bash lab.sh check
bash lab.sh schema
```

`db-config` hỏi `host` (`127.0.0.1`), `port` (`5432`), `dbname` (`dnu_judge_lab5`), `user` (`dnu_judge`), `password` (mật khẩu đã tạo). Cấu hình vào `.local/db.json` và đã có `.gitignore`; **không đưa lên GitHub**. Ngoài ra `src/Database.php` hỗ trợ cấu hình qua `PGHOST`, `PGPORT`, `PGDATABASE`, `PGUSER`, `PGPASSWORD`.

### Nạp 10 bài và tài khoản mô phỏng

Trong terminal WSL:

```bash
read -rsp 'Mật khẩu demo (>=10 ký tự): ' SEED_PASSWORD; echo
export SEED_PASSWORD
bash lab.sh seed
unset SEED_PASSWORD
```

Các tên đăng nhập mẫu: **`student_a`**, **`student_b`**, **`lecturer`**, **`admin`**. Mật khẩu là chuỗi bạn vừa nhập (4 tài khoản demo chung mật khẩu **chỉ dùng trên máy cá nhân**, thay bằng mật khẩu khác nhau khi dùng thực tế). Seed có **10 bài tự biên soạn, 71 test case, 40 tệp mã mẫu (20 Python + 20 C++)**. Thư mục `private_seed/` chứa **đáp án và dữ liệu kín**, được Git bỏ qua; không công khai hay đưa vào thư mục `public/`. Nếu tạo kho Git để người học truy cập, giữ `private_seed/` ngoài kho hoặc trong gói riêng dành cho giảng viên.

### Chạy ứng dụng — cần 2 terminal

**Terminal 1 — Web:**

```bash
cd ~/DeTai20_Lab05_OnlineJudge
bash lab.sh run
```

Mở **http://127.0.0.1:8105** trên Windows, đăng nhập `student_a` bằng mật khẩu demo.

**Terminal 2 — worker cách ly:**

```bash
cd ~/DeTai20_Lab05_OnlineJudge
bash lab.sh worker
```

Worker phải duy trì hoạt động để nhận hàng đợi. Chạy một công việc rồi thoát: `bash lab.sh worker-once`. Dừng hai terminal bằng Ctrl+C. Web PHP **không** được cấp Docker socket; chỉ tiến trình CLI worker có quyền sử dụng Docker Engine.

Kiểm tra dữ liệu thật bằng `bash lab.sh sql`:

```sql
SELECT COUNT(*) FROM problems;
SELECT COUNT(*) FROM test_cases;
SELECT id, status, verdict, passed_count, total_count FROM judging_runs ORDER BY id DESC LIMIT 10;
\q
```

Với CSDL mới sau seed: 10 bài, 71 test case. Khởi động lại PHP Web không xóa được dữ liệu.

## 3. Thử các tình huống chính

### Người học

1. Đăng nhập `student_a`, chọn **Tổng hai số nguyên**.
2. Chọn Python, nhập `a,b=map(int,input().split());print(a+b)`.
3. Nhập stdin `2 3`, nhấn **Chạy thử** → sau khi worker xử lý, xem stdout `5`.
4. Nhấn **Lưu bản nháp** → nội dung được lưu trong PostgreSQL, không chỉ Session.
5. Nhấn **Nộp bài** → hệ thống lưu một `submission` và tạo `judging_run` `queued`.
6. Xem chi tiết/lịch sử: nếu đủ 7 ca đúng là `AC`.
7. Thử `print(0)` để kiểm tra `WA`; thử mã lỗi cú pháp để kiểm tra `CE`, `1/0` để kiểm tra `RE`, `while True: pass` để kiểm tra `TLE` (với Docker đang chạy).

**C++17:** chọn C++, dùng `private_seed/samples/tong_hai_so/correct.cpp` làm mã mẫu (chỉ cho giảng viên/kiểm thử). Worker biên dịch tệp nguồn trong container GCC và chạy chương trình trong container riêng.

### Giảng viên và quản trị viên

1. Đăng nhập `lecturer` → **Quản lý**. Tạo bài mới với chủ đề, mức độ, ràng buộc input/output, ví dụ, giới hạn chạy và **mảng JSON 5–50 ca**.
2. Vào **Bộ test** của một bài, tạo bộ dữ liệu v2 từ JSON 5+ ca. Bộ v1 không bị cập nhật/xóa.
3. Mở bài nộp đã có của người học trong lớp, nhấn **Chấm lại**, chọn v2 **phù hợp phiên bản đề**, xem cả lịch sử v1/v2.
4. Dùng **Sửa đề** để tạo phiên bản đề mới; bộ test được sao chép thành phiên bản mới, bài nộp cũ tiếp tục giữ ID phiên bản cũ.
5. Đăng nhập `admin` → **Hệ thống**, xem trạng thái hàng đợi, tạo người học/giảng viên/lớp và thêm thành viên; worker vẫn phải khởi động từ terminal.

Ví dụ tạo 5 test case mới với JSON trong form (chỉ demo cho bài Tổng hai số):

```json
[
  {"input":"1 2\n","output":"3\n"},
  {"input":"0 0\n","output":"0\n"},
  {"input":"-1 2\n","output":"1\n"},
  {"input":"100 5\n","output":"105\n"},
  {"input":"-8 -2\n","output":"-10\n"}
]
```

## 4. Quy tắc chấm và tính đúng đắn

* `AC`: stdout đúng với expected stdout theo quy tắc: chuẩn hóa CRLF/CR → LF, bỏ khoảng trắng **cuối chuỗi** bằng `rtrim`; không bỏ khoảng trắng bên trong dòng.
* `WA`: chương trình chạy được nhưng đầu ra không khớp.
* `CE`: lỗi biên dịch C++, lỗi cú pháp Python; C++ hết thời gian biên dịch cũng báo CE trong nguyên mẫu.
* `RE`: thoát với mã khác 0 hoặc lỗi khi chạy.
* `TLE`: vượt giới hạn thời gian tính theo đồng hồ (có khoảng đệm khởi động container).
* `SYSTEM_ERROR`: lỗi Docker/dịch vụ worker/dữ liệu hệ thống; **không được quy thành WA**. Trạng thái `system_error` có thể chấm lại sau khi sửa môi trường.

Worker thực thi tệp đơn, stdin/stdout, chạy trong container `--network none`, `--read-only`, `--cap-drop ALL`, `--security-opt no-new-privileges`, `--pids-limit`, giới hạn memory, CPU, thời gian; **trình duyệt không gọi Docker**. Output và stderr chạy thử bị cắt giới hạn; với chấm kín, hệ thống chỉ lưu verdict và thời gian từng test, **không lưu/trả stdout, input hoặc expected output của từng ca trong `case_results`**. Giao diện/API không đọc test ẩn. Có thể suy luận thông tin về dữ liệu qua phản hồi AC/WA nếu nộp nhiều lần; nguyên mẫu giới hạn yêu cầu nhưng **không bảo đảm chống mọi kênh suy luận**.

Chống trùng dùng `UNIQUE(user_id,idempotency_key)` ở PostgreSQL và `INSERT .. ON CONFLICT ... DO NOTHING`. Gửi lại **cùng key và cùng payload** → cùng `submission_id`; gửi key mới → bài mới; cùng key mà đổi payload → từ chối. Phân quyền mã nguồn dựa trên chủ sở hữu, giảng viên có lớp chứa người nộp hoặc admin. Form POST sử dụng CSRF token, SQL dùng `pg_query_params`.

Khi chấm lại: tạo hàng `judging_runs` mới, cập nhật `submissions.latest_run_id` **lúc enqueue**; worker chỉ kết thúc hàng run của nó nếu lease token còn hợp lệ. Kết quả run cũ đến muộn không thay `latest_run_id` và không ghi đè case/results của run mới. `FOR UPDATE SKIP LOCKED` cho phép chạy nhiều worker; lease token hỗ trợ thu hồi tác vụ bị gián đoạn. Các tác vụ mất lease tối đa 5 lần chuyển `SYSTEM_ERROR`.

## 5. Kiểm thử theo rubric / đề tài

```bash
php tests/unit.php                  # 22 kiểm thử logic không cần DB
python3 tests/samples.py           # 81 kiểm thử fixture Python đúng/sai
python3 tests/samples.py --cpp     # thêm biên dịch và thử mẫu C++ nếu có g++
bash lab.sh test                    # UNIT + INTEGRATION PostgreSQL
bash lab.sh extend                  # kiểm thử mở rộng (cùng bộ test đầy đủ)
```

Nếu `INTEGRATION: NOT_RUN`, nguyên nhân là thiếu `php-pgsql`, cấu hình hoặc PostgreSQL. **NOT_RUN không phải PASS**. Môi trường tạo gói tại đây kiểm tra được cú pháp PHP, unit và fixture Python, nhưng **không có PostgreSQL/pgsql hoặc Docker daemon**, vì thế chưa chạy được các bài test tích hợp/dịch vụ chấm thật. Cần chạy lại trên Ubuntu/WSL của bạn để xác nhận cuối cùng.

**Ma trận kiểm thử bắt buộc**:

| Tình huống | Kết quả cần đối chiếu |
|---|---|
| Đúng / sai dữ liệu biên / lỗi cú pháp / lỗi runtime / vô hạn | `AC`, `WA`, `CE`, `RE`, `TLE` tương ứng |
| Ngắt Docker/worker khi xử lý | `SYSTEM_ERROR`, hoặc công việc đang chờ thuê lại; **không `WA`** |
| Gửi HTTP lại cùng `request_key` | Cùng `submission_id`, chỉ 1 row; gửi UUID mới tạo row mới |
| Run v1 hoàn thành sau khi tạo run v2 | v1 vẫn nằm trong lịch sử; `latest_run_id` trỏ v2 |
| Student A mở `/submission.php?id=<của B>` hoặc `api.php?action=submission-status` | HTTP 403; không thấy mã của B |
| Bất kỳ giao diện/API nào | Không nhận test ẩn/expected output, lease token, mật khẩu DB, Docker endpoint |
| Lớp chưa có lượt nộp | `StudentStats` bằng `LEFT JOIN` vẫn đếm `submissions=0` |

## 6. Cấu trúc dự án

```text
DeTai20_Lab05_OnlineJudge/
├── lab.sh
├── database/schema.sql
├── .gitignore
├── .local/db.json              # TỰ TẠO, không công khai
├── .runtime/                  # TẠM thời của worker, không công khai
├── private_seed/              # DỮ LIỆU KÍN, không commit Git
│   ├── seed.json              # 10 bài, 71 test case
│   └── samples/<slug>/        # 10 × correct/wrong × Python/C++
├── cli/{config,psql,seed,worker}.php
├── src/{Database,Policy,JudgeService,TrialService,Runner,StudentStats,TeacherService,Web}.php
├── public/{router,index,login,logout,problem,history,submission,api,teacher,teacher_edit,suite,admin}.php
├── public/{app.js,style.css}
└── tests/{unit,integration}.php, tests/samples.py
```

## 7. Giới hạn và bảo mật

Dự án này phục vụ **học phần, chạy cục bộ**; không nên mở ra Internet hoặc chấm mã từ người lạ khi chưa được rà soát chuyên sâu. Dù Docker giảm rủi ro, không có sandbox phổ thông nào bảo đảm cách ly hoàn toàn khi chạy mã tùy ý. Để triển khai công khai cần worker trên máy riêng/hạ tầng cách ly mạnh hơn (rootless Docker/gVisor hoặc tương đương), giới hạn lưu lượng và tài nguyên theo người dùng, TLS, giám sát, backup, quản lý secrets, phân quyền DB theo vai trò, chống suy luận test kín, chống spam và nâng cấp cơ chế cấp phát tài nguyên. Không cấp quyền Docker cho user chạy PHP Web. Các Docker image trong ví dụ cần kéo trước; không dùng mẫu `private_seed` làm dữ liệu công khai.

Mốc thời gian trong kết quả là thời gian thực thi container (wall time, có ảnh hưởng khởi động), chưa phải phép đo CPU chuẩn hóa giữa mọi hệ thống. Hệ thống kiểm tra theo tập ca thử, **không bảo đảm đúng trong mọi đầu vào**.

---

**Nộp bài:** lưu README + mã PHP/SQL + nhật ký `bash lab.sh check`, `bash lab.sh test`, `bash lab.sh extend`, ảnh hai luồng sử dụng và bài kiểm thử. Nếu chia repo cho học viên, không đưa `private_seed/`, `.local/`, `.runtime/`, mật khẩu/đáp án riêng lên GitHub; giáo viên kiểm tra seed từ gói riêng khi cần. Không được sửa test để tự nhận PASS.
