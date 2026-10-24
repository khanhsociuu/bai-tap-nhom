<?php
declare(strict_types=1);
require_once __DIR__.'/Database.php';
require_once __DIR__.'/JudgeService.php';
if (PHP_SAPI!=='cli') {
    ini_set('display_errors','0');ini_set('log_errors','1');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: DENY');
}
if (PHP_SAPI!=='cli' && session_status()===PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode','1');
    ini_set('session.cookie_httponly','1');
    ini_set('session.cookie_samesite','Lax');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') ini_set('session.cookie_secure','1');
    session_start();
}
function h(mixed $v):string { return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function auth():?array { return $_SESSION['account']??null; }
function requireAuth(?string $role=null):array {
    $a=auth(); if (!$a) { header('Location: /login.php',true,303); exit; }
    if ($role && !in_array($a['role'],[$role,'admin'],true)) { http_response_code(403); exit('Không có quyền'); }
    return $a;
}
function csrf():string { return $_SESSION['csrf']??=bin2hex(random_bytes(32)); }
function verifyCsrf():void { if (!hash_equals(csrf(),(string)($_POST['csrf']??''))) { http_response_code(419); exit('CSRF không hợp lệ'); } }
function gotoPath(string $path):never { header('Location: '.$path,true,303); exit; }
function message(string $message):void { $_SESSION['message']=$message; }
function canViewSubmission(PgSql\Connection $c,int $viewerId,string $viewerRole,int $submissionId):bool {
    $s=one($c,'SELECT user_id FROM submissions WHERE id=$1',[$submissionId]);
    if (!$s) return false;
    if ($viewerRole==='admin'||(int)$s['user_id']===$viewerId) return true;
    if ($viewerRole!=='teacher') return false;
    return one($c,'SELECT 1 FROM class_memberships m JOIN classrooms cl ON cl.id=m.classroom_id
          WHERE m.user_id=$1 AND cl.teacher_id=$2 LIMIT 1',[$s['user_id'],$viewerId])!==null;
}
function layoutStart(string $title):void {
    $a=auth(); $flash=$_SESSION['message']??null; unset($_SESSION['message']);
    echo '<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.h($title).' | DNU Judge</title><link rel="stylesheet" href="/style.css?v=2"></head><body>';
    echo '<header class="top"><div class="wrap nav"><a class="brand" href="/">&lt;/&gt; DNU <span>JUDGE</span></a><nav><a href="/">Bài tập</a>';
    if ($a) { echo '<a href="/dashboard.php">Tiến độ</a><a href="/history.php">Lịch sử</a>'; if ($a['role']!=='student') echo '<a href="/teacher.php">Quản lý</a>'; if ($a['role']==='admin') echo '<a href="/admin.php">Hệ thống</a>'; echo '<span class="user">'.h($a['display_name']).' · '.h($a['role']).'</span><form action="/logout.php" method="post" class="inline"><input type="hidden" name="csrf" value="'.h(csrf()).'"><button class="linkbtn">Đăng xuất</button></form>'; }
    else echo '<a href="/login.php">Đăng nhập</a>';
    echo '</nav></div></header><main class="wrap">';
    if ($flash) echo '<div class="alert">'.h($flash).'</div>';
}
function layoutEnd():void { echo '</main><footer class="wrap foot">DNU Judge · Dự án thực hành PHP + PostgreSQL · Kết quả dựa trên bộ ca thử, không chứng minh chương trình luôn đúng.</footer><script src="/app.js?v=2"></script></body></html>'; }
function failure(string $message,int $code=400):never { http_response_code($code);layoutStart('Thông báo');echo '<section class="panel"><h2>'.h($message).'</h2><p><a href="/">Về danh sách bài</a></p></section>';layoutEnd();exit; }
function jsonSend(array $data,int $status=200):never { http_response_code($status);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);exit; }
