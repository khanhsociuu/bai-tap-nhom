<?php
require __DIR__.'/../src/Web.php';
if(auth()) gotoPath('/');
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    $u=trim((string)($_POST['username']??''));$p=(string)($_POST['password']??'');
    try { $row=one(db(),'SELECT id,username,display_name,role,password_hash FROM users WHERE username=$1',[$u]);
      if ($row && password_verify($p,$row['password_hash'])) {
          session_regenerate_id(true);unset($row['password_hash']);$_SESSION['account']=$row;gotoPath('/');
      } else $error='Sai tài khoản hoặc mật khẩu.';
    } catch(Throwable $e) {$error='Không kết nối được hệ thống.';}
}
layoutStart('Đăng nhập'); ?>
<div class="authbox panel"><div class="eyebrow">WELCOME BACK</div><h1>Đăng nhập</h1><p class="muted">Tài khoản học viên, giảng viên hoặc quản trị viên.</p><?php if($error):?><p class="error"><?=h($error)?></p><?php endif;?><form method="post" class="stack"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><label>Tên đăng nhập<input required autocomplete="username" name="username"></label><label>Mật khẩu<input required type="password" autocomplete="current-password" name="password"></label><button class="btn">Đăng nhập</button></form></div>
<?php layoutEnd(); ?>
