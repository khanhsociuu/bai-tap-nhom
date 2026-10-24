<?php
require __DIR__.'/../src/Web.php';
$a=requireAuth('admin');$c=db();$error='';
if($_SERVER['REQUEST_METHOD']==='POST') {
 verifyCsrf();
 try {
  $act=(string)($_POST['action']??'');
  if($act==='user') {
   $username=trim((string)($_POST['username']??''));$name=trim((string)($_POST['name']??''));$role=(string)($_POST['role']??'');$pass=(string)($_POST['password']??'');
   if(!preg_match('/^[a-z][a-z0-9_]{2,39}$/',$username)||$name===''||strlen($name)>120||strlen($pass)<12||!in_array($role,['student','teacher'],true))
      throw new InvalidArgumentException('Tài khoản không hợp lệ; mật khẩu phải từ 12 ký tự');
   queryParams($c,'INSERT INTO users(username,display_name,role,password_hash) VALUES($1,$2,$3,$4)',[$username,$name,$role,password_hash($pass,PASSWORD_DEFAULT)]);
   message('Đã thêm tài khoản');
  }elseif($act==='class') {
   $name=trim((string)($_POST['name']??''));$teacher=(int)($_POST['teacher_id']??0);
   if($name===''||strlen($name)>120||!one($c,"SELECT 1 FROM users WHERE id=$1 AND role='teacher'",[$teacher])) throw new InvalidArgumentException('Tên lớp hoặc giảng viên không hợp lệ');
   queryParams($c,'INSERT INTO classrooms(name,teacher_id) VALUES($1,$2)',[$name,$teacher]);message('Đã tạo lớp');
  }elseif($act==='member') {
   $cid=(int)($_POST['classroom_id']??0);$uid=(int)($_POST['user_id']??0);
   if(!one($c,"SELECT 1 FROM users WHERE id=$1 AND role='student'",[$uid])) throw new InvalidArgumentException('Người học không hợp lệ');
   queryParams($c,'INSERT INTO class_memberships(classroom_id,user_id) VALUES($1,$2) ON CONFLICT DO NOTHING',[$cid,$uid]);message('Đã thêm thành viên');
  }else throw new InvalidArgumentException('Thao tác không hợp lệ');
  gotoPath('/admin.php');
 }catch(Throwable $e){$error=$e instanceof InvalidArgumentException?$e->getMessage():'Không lưu được. Kiểm tra tên trùng hoặc khóa ngoại.';}
}
$teachers=rows($c,"SELECT id,username FROM users WHERE role='teacher' ORDER BY username");
$students=rows($c,"SELECT id,username FROM users WHERE role='student' ORDER BY username");
$classes=rows($c,'SELECT cl.id,cl.name,u.username teacher,COUNT(m.user_id) students FROM classrooms cl JOIN users u ON u.id=cl.teacher_id LEFT JOIN class_memberships m ON m.classroom_id=cl.id GROUP BY cl.id,u.username ORDER BY cl.id');
$counts=rows($c,'SELECT status,count(*) n FROM judging_runs GROUP BY status');
$recent=rows($c,'SELECT id,status,verdict,attempts,created_at FROM judging_runs ORDER BY id DESC LIMIT 25');
layoutStart('Quản trị dịch vụ'); ?>
<h1 class="page-title">Quản trị hệ thống</h1><p class="note">Trạng thái hàng đợi được xem trên Web; Docker và kết nối worker chỉ quản lý qua terminal máy chủ, không cấp quyền Docker cho tiến trình PHP Web.</p>
<?php if($error):?><div class="error"><?=h($error)?></div><?php endif; ?>
<div class="grid2"><div class="panel"><h2>Hàng đợi chấm</h2><div class="casegrid"><?php foreach($counts as $cnt):?><span class="chip"><?=h($cnt['status'])?>: <?=h($cnt['n'])?></span><?php endforeach;?></div><div class="table-holder" style="margin-top:16px"><table><thead><tr><th>Run</th><th>Trạng thái</th><th>Verdict</th><th>Lượt thử</th></tr></thead><tbody><?php foreach($recent as $r):?><tr><td>#<?=h($r['id'])?></td><td><?=h($r['status'])?></td><td><?=h($r['verdict']??'-')?></td><td><?=h($r['attempts'])?></td></tr><?php endforeach;?></tbody></table></div></div>
<div class="panel"><h2>Tài khoản & lớp học</h2><form method="post" class="stack"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="user"><label>Tên đăng nhập<input required name="username" pattern="[a-z][a-z0-9_]{2,39}"></label><label>Họ và tên<input required name="name"></label><label>Vai trò<select name="role"><option value="student">Người học</option><option value="teacher">Giảng viên</option></select></label><label>Mật khẩu ban đầu (≥12 ký tự)<input required type="password" minlength="12" name="password"></label><button class="btn secondary">Thêm tài khoản</button></form><hr><form method="post" class="stack"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="class"><label>Tên lớp<input required name="name"></label><label>Giảng viên<select name="teacher_id"><?php foreach($teachers as $t):?><option value="<?=h($t['id'])?>"><?=h($t['username'])?></option><?php endforeach;?></select></label><button class="btn secondary">Tạo lớp</button></form><hr><form method="post" class="stack"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="member"><label>Lớp học<select name="classroom_id"><?php foreach($classes as $cl):?><option value="<?=h($cl['id'])?>"><?=h($cl['name'])?></option><?php endforeach;?></select></label><label>Người học<select name="user_id"><?php foreach($students as $s):?><option value="<?=h($s['id'])?>"><?=h($s['username'])?></option><?php endforeach;?></select></label><button class="btn secondary">Thêm vào lớp</button></form></div></div>
<section class="panel"><h2>Danh sách lớp</h2><div class="table-holder"><table><thead><tr><th>ID</th><th>Lớp</th><th>Giảng viên</th><th>Học viên</th></tr></thead><tbody><?php foreach($classes as $cl):?><tr><td>#<?=h($cl['id'])?></td><td><?=h($cl['name'])?></td><td><?=h($cl['teacher'])?></td><td><?=h($cl['students'])?></td></tr><?php endforeach;?></tbody></table></div></section>
<?php layoutEnd(); ?>
