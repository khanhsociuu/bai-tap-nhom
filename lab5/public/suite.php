<?php
require __DIR__.'/../src/Web.php';require __DIR__.'/../src/TeacherService.php';
$a=requireAuth();$c=db();$id=(int)($_GET['id']??0);
if(!canManageProblem($c,$a,$id)) failure('Không có quyền xem trang này',403);
$error='';
if($_SERVER['REQUEST_METHOD']==='POST') {
 verifyCsrf();try{$sid=createSuite($c,$a,$id,(string)($_POST['tests']??''));message('Đã tạo bộ dữ liệu mới #'.$sid);gotoPath('/suite.php?id='.$id);}catch(Throwable $e){$error=$e instanceof InvalidArgumentException?$e->getMessage():'Lỗi tạo phiên bản';}
}
$p=one($c,'SELECT title,current_version_id FROM problems WHERE id=$1',[$id]);if(!$p)failure('Bài không tồn tại',404);
$versions=rows($c,'SELECT sv.id,sv.version_no,pv.version_no problem_no,sv.created_at,COUNT(tc.id) AS case_count
 FROM test_suite_versions sv JOIN problem_versions pv ON pv.id=sv.problem_version_id
 LEFT JOIN test_cases tc ON tc.suite_version_id=sv.id WHERE sv.problem_id=$1
 GROUP BY sv.id,pv.version_no ORDER BY sv.version_no DESC',[$id]);
layoutStart('Bộ dữ liệu chấm'); ?>
<div class="subhead"><a href="/teacher.php">← Quản lý</a></div><h1 class="page-title">Phiên bản bộ chấm · <?=h($p['title'])?></h1><p class="note">Không hiển thị đầu vào hoặc đáp án của dữ liệu kín trên Web. Giảng viên nhập bộ dữ liệu mới; phiên bản cũ vẫn lưu để đối chiếu/chấm lại.</p>
<?php if($error):?><p class="error"><?=h($error)?></p><?php endif; ?>
<div class="grid2"><section class="panel"><h2>Các phiên bản</h2><div class="table-holder"><table><thead><tr><th>Bộ</th><th>Đề</th><th>Số test</th><th>Ngày tạo</th></tr></thead><tbody><?php foreach($versions as $sv):?><tr><td>v<?=h($sv['version_no'])?></td><td>v<?=h($sv['problem_no'])?></td><td><?=h($sv['case_count'])?></td><td><?=h($sv['created_at'])?></td></tr><?php endforeach; ?></tbody></table></div></section>
<section class="panel"><h2>Tạo phiên bản mới</h2><p class="tiny muted">Mỗi mục có `input` và `output`; từ 5 đến 50 ca. `public` tùy chọn; mọi dữ liệu thử đều chỉ được dùng bởi worker.</p><form method="post" class="stack"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><textarea name="tests" rows="12" required placeholder='[{"input":"1 2\n","output":"3\n"}, ...]'></textarea><button class="btn">Lưu bộ test mới</button></form></section></div>
<?php layoutEnd(); ?>
