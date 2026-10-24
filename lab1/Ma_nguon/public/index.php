
<?php
$pageTitle = "CodeArena - Luyện tập lập trình trực tuyến";

$problems = [
    ["id"=>1, "title"=>"Tổng hai số", "topic"=>"Cơ bản", "level"=>"Dễ", "points"=>100, "solved"=>128],
    ["id"=>2, "title"=>"Kiểm tra số nguyên tố", "topic"=>"Toán học", "level"=>"Dễ", "points"=>100, "solved"=>96],
    ["id"=>3, "title"=>"Dãy Fibonacci", "topic"=>"Vòng lặp", "level"=>"Dễ", "points"=>150, "solved"=>84],
    ["id"=>4, "title"=>"Tổng chữ số", "topic"=>"Toán học", "level"=>"Dễ", "points"=>100, "solved"=>73],
    ["id"=>5, "title"=>"Đếm ký tự", "topic"=>"Xâu ký tự", "level"=>"Dễ", "points"=>150, "solved"=>65],
    ["id"=>6, "title"=>"Sắp xếp mảng", "topic"=>"Mảng", "level"=>"Trung bình", "points"=>200, "solved"=>52],
    ["id"=>7, "title"=>"Tìm kiếm nhị phân", "topic"=>"Tìm kiếm", "level"=>"Trung bình", "points"=>250, "solved"=>39],
    ["id"=>8, "title"=>"Ngoặc hợp lệ", "topic"=>"Ngăn xếp", "level"=>"Trung bình", "points"=>250, "solved"=>31],
    ["id"=>9, "title"=>"Đường đi ngắn nhất", "topic"=>"Đồ thị", "level"=>"Khó", "points"=>300, "solved"=>18],
    ["id"=>10, "title"=>"Bài toán cái balo", "topic"=>"Quy hoạch động", "level"=>"Khó", "points"=>350, "solved"=>12]
];

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($pageTitle) ?></title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
:root {
    --primary: #4f46e5;
    --primary-dark: #3730a3;
    --bg: #f6f7fb;
    --text: #172033;
    --muted: #778198;
    --border: #e9ebf2;
    --white: #ffffff;
}
* { box-sizing: border-box; }
body {
    margin: 0;
    background: var(--bg);
    color: var(--text);
    font-family: Inter, Arial, sans-serif;
    font-size: 14px;
}
a { text-decoration: none; color: inherit; }
button, input, select { font: inherit; }
button { cursor: pointer; }
.layout { min-height: 100vh; }
.sidebar {
    position: fixed;
    top: 0; left: 0; bottom: 0;
    width: 245px;
    background: #fff;
    border-right: 1px solid var(--border);
    padding: 25px 17px;
    z-index: 10;
    overflow-y: auto;
}
.logo {
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 0 9px 30px;
    font-size: 20px;
    font-weight: 800;
    letter-spacing: -.7px;
}
.logo-icon {
    width: 39px; height: 39px;
    border-radius: 12px;
    display: grid; place-items: center;
    color: white;
    background: linear-gradient(135deg, #6366f1, #4338ca);
    font-size: 20px;
}
.nav-label {
    color: #9aa2b4;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 1.2px;
    padding: 14px 12px 10px;
}
.nav-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    margin: 3px 0;
    color: #69748a;
    border-radius: 9px;
    font-weight: 600;
    transition: .2s;
}
.nav-link i { font-size: 18px; }
.nav-link:hover, .nav-link.active {
    background: #eeefff;
    color: var(--primary);
}
.sidebar-bottom {
    margin-top: 35px;
    border-top: 1px solid var(--border);
    padding-top: 20px;
}
.user-box {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 5px;
}
.avatar {
    width: 38px; height: 38px;
    border-radius: 50%;
    background: #e0e7ff;
    color: #4338ca;
    display: grid; place-items: center;
    font-weight: 800;
}
.user-name { font-size: 12px; font-weight: 700; }
.user-role { color: var(--muted); font-size: 11px; margin-top: 4px; }

.main { margin-left: 245px; }
.topbar {
    height: 73px;
    background: #fff;
    border-bottom: 1px solid var(--border);
    padding: 0 34px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
}
.breadcrumb { color: var(--muted); font-size: 12px; }
.breadcrumb strong { color: var(--text); }
.top-actions { display: flex; align-items: center; gap: 12px; }
.icon-btn {
    border: 1px solid var(--border);
    background: #fff;
    width: 37px; height: 37px;
    border-radius: 10px;
    display: grid; place-items: center;
    color: #59657b;
}
.primary-btn {
    background: var(--primary);
    color: #fff;
    border: 0;
    border-radius: 9px;
    padding: 11px 16px;
    font-weight: 700;
    display: inline-flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    transition: .2s;
}
.primary-btn:hover { background: var(--primary-dark); }
.content { padding: 30px 34px 40px; max-width: 1600px; margin: auto; }
.welcome {
    background: linear-gradient(110deg, #3730a3, #5b50e8 65%, #818cf8);
    color: white;
    padding: 32px 35px;
    border-radius: 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 25px;
    position: relative;
    overflow: hidden;
}
.welcome:after {
    content: "</>";
    position: absolute;
    right: 28px;
    top: 4px;
    font-size: 125px;
    font-weight: 800;
    opacity: .09;
}
.welcome-text { position: relative; z-index: 1; }
.welcome-tag {
    display: inline-flex;
    padding: 6px 10px;
    border-radius: 20px;
    background: #ffffff20;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 1px;
}
.welcome h1 {
    font-size: clamp(25px, 3vw, 34px);
    letter-spacing: -1.2px;
    line-height: 1.25;
    margin: 17px 0 10px;
}
.welcome p { color: #e0e7ff; line-height: 1.8; max-width: 570px; margin: 0 0 22px; }
.welcome .primary-btn { background: #fff; color: #4338ca; }
.welcome .primary-btn:hover { background: #eef2ff; }
.welcome-visual {
    position: relative;
    z-index: 1;
    width: 190px;
    min-width: 170px;
    background: #151c493d;
    border: 1px solid #ffffff25;
    border-radius: 14px;
    padding: 15px;
    font-family: Consolas, monospace;
    line-height: 2;
    color: #e0e7ff;
}
.welcome-visual .green { color: #86efac; }
.welcome-visual .yellow { color: #fde68a; }

.section-title {
    font-size: 17px;
    font-weight: 800;
    letter-spacing: -.4px;
    margin: 0;
}
.section-subtitle { color: var(--muted); margin: 7px 0 0; font-size: 12px; line-height: 1.6; }
.stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 17px;
    margin: 24px 0 30px;
}
.stat-card {
    background: white;
    border: 1px solid var(--border);
    border-radius: 13px;
    padding: 19px;
    display: flex;
    align-items: center;
    gap: 14px;
}
.stat-icon {
    width: 46px; height: 46px;
    flex-shrink: 0;
    border-radius: 12px;
    display: grid; place-items: center;
    font-size: 20px;
}
.purple { background: #eeedff; color: #635bdb; }
.green-bg { background: #dcfce7; color: #15803d; }
.orange-bg { background: #ffedd5; color: #c2410c; }
.blue-bg { background: #dbeafe; color: #1d4ed8; }
.stat-label { color: var(--muted); font-size: 11px; font-weight: 600; }
.stat-value { font-size: 23px; font-weight: 800; margin-top: 6px; letter-spacing: -.8px; }
.stat-note { font-size: 10px; color: #15945b; margin-top: 5px; }

.dashboard-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.8fr) minmax(260px, .9fr);
    gap: 22px;
}
.card {
    background: white;
    border: 1px solid var(--border);
    border-radius: 14px;
    overflow: hidden;
}
.card-head {
    padding: 21px 22px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    border-bottom: 1px solid var(--border);
}
.text-link { color: var(--primary); font-size: 12px; font-weight: 700; white-space: nowrap; }
.filter-row {
    display: flex;
    gap: 10px;
    padding: 17px 22px;
    border-bottom: 1px solid var(--border);
}
.search-box { position: relative; flex: 1; }
.search-box i { position: absolute; left: 12px; top: 11px; color: #9aa2b4; }
.search-box input {
    width: 100%;
    padding: 10px 12px 10px 36px;
    border: 1px solid var(--border);
    border-radius: 8px;
    outline: none;
}
.search-box input:focus, .filter-row select:focus { border-color: #818cf8; }
.filter-row select {
    max-width: 145px;
    padding: 10px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: white;
    color: #59657b;
    outline: none;
}
table { width: 100%; border-collapse: collapse; }
thead { background: #fafbfe; }
th {
    padding: 13px 15px;
    color: #8b94a7;
    text-align: left;
    font-size: 10px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .5px;
}
td { padding: 15px; border-top: 1px solid #f0f1f6; font-size: 12px; }
tbody tr:hover { background: #fafaff; }
.problem-name { font-weight: 700; font-size: 12px; }
.problem-id { color: #9aa2b4; font-size: 10px; display: block; margin-bottom: 5px; }
.topic { color: #68748a; }
.level {
    display: inline-block;
    padding: 5px 8px;
    border-radius: 5px;
    font-size: 10px;
    font-weight: 700;
    white-space: nowrap;
}
.level-easy { color: #16834b; background: #e2f8eb; }
.level-medium { color: #b66b0c; background: #fff2d9; }
.level-hard { color: #cf3c4c; background: #ffe6e9; }
.points { font-weight: 700; white-space: nowrap; }
.solve-btn {
    color: var(--primary);
    border: 1px solid #dedfff;
    background: #f5f5ff;
    border-radius: 7px;
    padding: 7px 10px;
    font-size: 11px;
    font-weight: 700;
}
.solve-btn:hover { background: #e9e8ff; }
.no-results { padding: 35px; text-align: center; color: var(--muted); }

.side-card { padding: 21px; margin-bottom: 18px; }
.side-card .section-title { font-size: 15px; }
.activity {
    display: flex;
    gap: 12px;
    margin-top: 22px;
}
.activity-icon {
    width: 36px; height: 36px; flex-shrink: 0;
    border-radius: 10px;
    display: grid; place-items: center;
}
.activity h3 { font-size: 12px; margin: 1px 0 5px; }
.activity p { font-size: 11px; line-height: 1.6; color: var(--muted); margin: 0; }
.progress-wrap { margin-top: 23px; }
.progress-label { display: flex; justify-content: space-between; font-size: 11px; margin-bottom: 9px; }
.progress-track { height: 7px; border-radius: 10px; background: #eeeff5; overflow: hidden; }
.progress-bar { height: 100%; background: var(--primary); border-radius: 10px; }
.notice {
    padding: 15px;
    border: 1px solid #dce5ff;
    border-radius: 11px;
    background: #f0f5ff;
    color: #43527a;
    font-size: 11px;
    line-height: 1.8;
}
.footer {
    color: #98a1b3;
    font-size: 11px;
    text-align: center;
    padding: 25px 10px;
}
.mobile-menu { display: none; }
@media(max-width:1200px) {
    .sidebar { width: 215px; }
    .main { margin-left: 215px; }
    .content { padding: 25px 22px; }
    .topbar { padding: 0 22px; }
    .stats { grid-template-columns: repeat(2, minmax(0,1fr)); }
    .dashboard-grid { grid-template-columns: 1fr; }
}
@media(max-width:760px) {
    .sidebar { display: none; }
    .main { margin-left: 0; }
    .topbar { height: 62px; padding: 0 15px; }
    .mobile-menu { display: grid; }
    .content { padding: 17px 13px 25px; }
    .welcome { padding: 25px 20px; }
    .welcome-visual { display: none; }
    .welcome h1 { font-size: 27px; }
    .stats { gap: 10px; margin: 15px 0 22px; }
    .stat-card { padding: 13px; gap: 10px; }
    .stat-icon { width: 38px; height: 38px; font-size: 17px; }
    .stat-value { font-size: 20px; }
    .dashboard-grid { gap: 15px; }
    .card-head { padding: 17px; }
    .filter-row { padding: 13px; }
    .filter-row select { max-width: 115px; }
    .table-wrap { overflow-x: auto; }
    table { min-width: 600px; }
    .breadcrumb { font-size: 11px; }
}
</style>
</head>

<body>
<div class="layout">

<aside class="sidebar">
    <a class="logo" href="index.php">
        <span class="logo-icon"><i class="bi bi-code-slash"></i></span>
        <span>CodeArena<span style="color:#6366f1">.</span></span>
    </a>

    <div class="nav-label">MENU CHÍNH</div>
    <a class="nav-link active" href="index.php"><i class="bi bi-grid-1x2-fill"></i> Tổng quan</a>
    <a class="nav-link" href="#problems"><i class="bi bi-journal-code"></i> Danh sách bài tập</a>
    <a class="nav-link" href="#submissions"><i class="bi bi-cloud-arrow-up"></i> Bài nộp của tôi</a>
    <a class="nav-link" href="#progress"><i class="bi bi-graph-up-arrow"></i> Tiến độ học tập</a>

    <div class="nav-label">KHÁM PHÁ</div>
    <a class="nav-link" href="#topics"><i class="bi bi-collection"></i> Chủ đề lập trình</a>
    <a class="nav-link" href="#ranking"><i class="bi bi-trophy"></i> Bảng xếp hạng</a>

    <div class="nav-label">HỆ THỐNG</div>
    <a class="nav-link" href="#guide"><i class="bi bi-question-circle"></i> Hướng dẫn sử dụng</a>

    <div class="sidebar-bottom">
        <div class="user-box">
            <div class="avatar">HV</div>
            <div>
                <div class="user-name">Học viên</div>
                <div class="user-role">Tài khoản khách · Demo</div>
            </div>
            <i class="bi bi-three-dots ms-auto" style="color:#929bad"></i>
        </div>
    </div>
</aside>

<div class="main">
    <header class="topbar">
        <div style="display:flex;align-items:center;gap:12px">
            <button class="icon-btn mobile-menu" onclick="alert('Mở menu khi sử dụng trên thiết bị di động.')" aria-label="Menu"><i class="bi bi-list"></i></button>
            <div class="breadcrumb">Trang chủ <span style="margin:0 7px">/</span> <strong>Tổng quan</strong></div>
        </div>
        <div class="top-actions">
            <button class="icon-btn" onclick="alert('Bạn chưa có thông báo mới.')" aria-label="Thông báo"><i class="bi bi-bell"></i></button>
            <a class="primary-btn" href="#problems"><i class="bi bi-plus-lg"></i> Làm bài ngay</a>
        </div>
    </header>

    <main class="content">
        <section class="welcome">
            <div class="welcome-text">
                <span class="welcome-tag"><i class="bi bi-stars me-1"></i> NỀN TẢNG LUYỆN LẬP TRÌNH</span>
                <h1>Xin chào, lập trình viên!<br>Hôm nay bạn muốn chinh phục gì?</h1>
                <p>Luyện tư duy thuật toán, thực hành viết mã và theo dõi hành trình tiến bộ của bạn qua từng bài tập.</p>
                <a class="primary-btn" href="#problems">Khám phá bài tập <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="welcome-visual">
                <div style="color:#a5b4fc">// Your next challenge</div>
                <div><span class="green">function</span> <span class="yellow">solve</span>() {</div>
                <div style="padding-left:15px">learn();</div>
                <div style="padding-left:15px">practice();</div>
                <div style="padding-left:15px">repeat();</div>
                <div>}</div>
                <div style="margin-top:9px;color:#86efac"><i class="bi bi-check-circle-fill"></i> Ready to code!</div>
            </div>
        </section>

        <section class="stats" id="progress">
            <div class="stat-card">
                <div class="stat-icon purple"><i class="bi bi-journal-check"></i></div>
                <div><div class="stat-label">TỔNG BÀI TẬP</div><div class="stat-value">10</div><div class="stat-note">Bài tập mẫu</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green-bg"><i class="bi bi-check2-circle"></i></div>
                <div><div class="stat-label">ĐÃ GIẢI</div><div class="stat-value" id="solvedCount">0</div><div class="stat-note">Trong phiên demo</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange-bg"><i class="bi bi-lightning-charge"></i></div>
                <div><div class="stat-label">ĐIỂM TÍCH LŨY</div><div class="stat-value" id="totalPoints">0</div><div class="stat-note">Điểm demo</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon blue-bg"><i class="bi bi-code-square"></i></div>
                <div><div class="stat-label">NGÔN NGỮ</div><div class="stat-value" style="font-size:19px">Python</div><div class="stat-note" style="color:#64748b">C++ có thể mở rộng</div></div>
            </div>
        </section>

        <div class="dashboard-grid">
            <section class="card" id="problems">
                <div class="card-head">
                    <div>
                        <h2 class="section-title">Danh sách bài tập</h2>
                        <p class="section-subtitle">Chọn thử thách phù hợp với trình độ của bạn.</p>
                    </div>
                    <span style="color:#7b8499;font-size:11px;white-space:nowrap">10 bài tập</span>
                </div>

                <div class="filter-row">
                    <div class="search-box">
                        <i class="bi bi-search"></i>
                        <input id="searchInput" type="search" placeholder="Tìm tên bài, chủ đề..." aria-label="Tìm bài tập">
                    </div>
                    <select id="levelFilter" aria-label="Lọc mức độ">
                        <option value="">Mọi mức độ</option>
                        <option value="Dễ">Dễ</option>
                        <option value="Trung bình">Trung bình</option>
                        <option value="Khó">Khó</option>
                    </select>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th style="padding-left:22px">BÀI TẬP</th>
                            <th>CHỦ ĐỀ</th>
                            <th>MỨC ĐỘ</th>
                            <th>ĐIỂM</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody id="problemTable">
                        <?php foreach ($problems as $p): ?>
                        <tr data-title="<?= h($p['title']) ?>" data-topic="<?= h($p['topic']) ?>" data-level="<?= h($p['level']) ?>">
                            <td style="padding-left:22px">
                                <span class="problem-id">BÀI #<?= sprintf('%02d', $p['id']) ?></span>
                                <span class="problem-name"><?= h($p['title']) ?></span>
                            </td>
                            <td class="topic"><?= h($p['topic']) ?></td>
                            <td>
                                <span class="level <?= $p['level']==='Dễ'?'level-easy':($p['level']==='Khó'?'level-hard':'level-medium') ?>">
                                    <?= h($p['level']) ?>
                                </span>
                            </td>
                            <td class="points"><?= (int)$p['points'] ?></td>
                            <td><button class="solve-btn" onclick="openProblem(<?= (int)$p['id'] ?>, '<?= h($p['title']) ?>')">Làm bài <i class="bi bi-arrow-up-right"></i></button></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="no-results" id="noResults" hidden>Không tìm thấy bài tập phù hợp.</div>
                </div>
                <div style="padding:16px 22px;border-top:1px solid #f0f1f6;display:flex;justify-content:space-between;align-items:center;color:#8992a5;font-size:11px">
                    <span>Hiển thị 10 bài tập mẫu</span>
                    <a class="text-link" href="#problems">Xem tất cả <i class="bi bi-arrow-right"></i></a>
                </div>
            </section>

            <aside>
                <section class="card side-card" id="submissions">
                    <h2 class="section-title">Hoạt động gần đây</h2>
                    <p class="section-subtitle">Theo dõi hoạt động trong phiên demo.</p>
                    <div class="activity">
                        <div class="activity-icon green-bg"><i class="bi bi-check-lg"></i></div>
                        <div><h3>Hệ thống đã sẵn sàng</h3><p>Giao diện luyện tập đã được tải thành công.</p></div>
                    </div>
                    <div class="activity">
                        <div class="activity-icon purple"><i class="bi bi-code-slash"></i></div>
                        <div><h3>10 bài tập mẫu</h3><p>Có thể tìm kiếm và lọc theo mức độ.</p></div>
                    </div>
                    <div class="activity">
                        <div class="activity-icon orange-bg"><i class="bi bi-hourglass-split"></i></div>
                        <div><h3>Chờ kết nối dịch vụ chấm</h3><p>Chưa thực thi mã nguồn thực tế.</p></div>
                    </div>
                </section>

                <section class="card side-card" id="topics">
                    <h2 class="section-title">Mục tiêu luyện tập</h2>
                    <p class="section-subtitle">Tiến độ minh họa trong giao diện.</p>
                    <div class="progress-wrap">
                        <div class="progress-label"><span>Cơ bản</span><strong>75%</strong></div>
                        <div class="progress-track"><div class="progress-bar" style="width:75%"></div></div>
                    </div>
                    <div class="progress-wrap">
                        <div class="progress-label"><span>Cấu trúc dữ liệu</span><strong>45%</strong></div>
                        <div class="progress-track"><div class="progress-bar" style="width:45%;background:#16a34a"></div></div>
                    </div>
                    <div class="progress-wrap">
                        <div class="progress-label"><span>Thuật toán nâng cao</span><strong>20%</strong></div>
                        <div class="progress-track"><div class="progress-bar" style="width:20%;background:#f59e0b"></div></div>
                    </div>
                </section>

                <div class="notice" id="guide">
                    <strong><i class="bi bi-shield-check me-1"></i> Lưu ý về kết quả chấm</strong><br>
                    Hệ thống thực tế sẽ đánh giá chương trình trên bộ test đã định nghĩa. Kết quả Accepted không khẳng định chương trình đúng với mọi đầu vào có thể có.
                </div>
            </aside>
        </div>

        <footer class="footer">
            <strong>CodeArena</strong> · Đề tài 20: Hệ thống luyện tập và chấm bài lập trình trực tuyến<br>
            PHP · MySQL · HTML · CSS · JavaScript
        </footer>
    </main>
</div>
</div>

<script>
const searchInput = document.getElementById('searchInput');
const levelFilter = document.getElementById('levelFilter');
const rows = [...document.querySelectorAll('#problemTable tr')];
const noResults = document.getElementById('noResults');

function filterProblems() {
    const keyword = searchInput.value.trim().toLocaleLowerCase('vi');
    const level = levelFilter.value;
    let count = 0;

    rows.forEach(row => {
        const title = row.dataset.title.toLocaleLowerCase('vi');
        const topic = row.dataset.topic.toLocaleLowerCase('vi');
        const matchText = title.includes(keyword) || topic.includes(keyword);
        const matchLevel = !level || row.dataset.level === level;
        const visible = matchText && matchLevel;

        row.style.display = visible ? '' : 'none';
        if (visible) count++;
    });

    noResults.hidden = count !== 0;
}
searchInput.addEventListener('input', filterProblems);
levelFilter.addEventListener('change', filterProblems);

function openProblem(id, title) {
    alert(
        'Bài tập #' + id + ': ' + title +
        '\n\nĐây là bản giao diện mẫu. Để viết, chạy thử và chấm mã thật, cần kết nối trang làm bài, cơ sở dữ liệu và dịch vụ thực thi cách ly.'
    );
}

document.querySelectorAll('.nav-link[href^="#"]').forEach(link => {
    link.addEventListener('click', () => {
        document.querySelectorAll('.nav-link').forEach(item => item.classList.remove('active'));
        link.classList.add('active');
    });
});
</script>
</body>
</html>
