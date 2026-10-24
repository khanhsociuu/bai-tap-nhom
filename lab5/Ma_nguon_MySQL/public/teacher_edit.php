<?php
require __DIR__.'/../src/Web.php';require __DIR__.'/../src/TeacherService.php';
$a=requireAuth();$c=db();$id=(int)($_GET['id']??0);
if(!canManageProblem($c,$a,$id)) failure('Không có quyền sửa bài này',403);
$error='';
if($_SERVER['REQUEST_METHOD']==='POST') {
 verifyCsrf();try {$version=editVersion($c,$a,$id,$_POST);message('Đã lưu phiên bản đề mới #'.$version);gotoPath('/teacher_edit.php?id='.$id);}catch(Throwable $e){$error=$e instanceof InvalidArgumentException?$e->getMessage():'Lỗi lưu phiên bản';}
}
$p=one($c,'SELECT p.*,pv.* FROM problems p JOIN problem_versions pv ON pv.id=p.current_version_id WHERE p.id=$1',[$id]);if(!$p)failure('Không thấy bài',404);
$topics=rows($c,'SELECT * FROM topics');$levels=rows($c,'SELECT * FROM levels');
layoutStart('Sửa phiên bản đề'); ?>
<div class="subhead"><a href="/teacher.php">← Quản lý</a></div><h1 class="page-title">Sửa <?=h($p['title'])?> · v<?=h($p['version_no'])?></h1><p class="note">Khi lưu, tạo phiên bản đề mới và sao chép bộ test sang phiên bản mới. Các bài đã nộp trước đây tiếp tục gắn đúng phiên bản cũ.</p>
<?php if($error):?><p class="error"><?=h($error)?></p><?php endif; ?><section class="panel"><form class="stack" method="post"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><label>Tên bài<input name="title" required value="<?=h($p['title'])?>"></label><div class="grid2 compact"><label>Chủ đề<select name="topic_id"><?php foreach($topics as $t):?><option value="<?=h($t['id'])?>" <?=((int)$p['topic_id']===(int)$t['id']?'selected':'')?>><?=h($t['name'])?></option><?php endforeach;?></select></label><label>Mức độ<select name="level_id"><?php foreach($levels as $l):?><option value="<?=h($l['id'])?>" <?=((int)$p['level_id']===(int)$l['id']?'selected':'')?>><?=h($l['name'])?></option><?php endforeach;?></select></label></div><?php foreach(['statement'=>'Đề bài','input_desc'=>'Mô tả input','output_desc'=>'Mô tả output','sample_input'=>'Input ví dụ','sample_output'=>'Output ví dụ'] as $key=>$label):?><label><?=h($label)?><textarea name="<?=h($key)?>" rows="4" required><?=h($p[$key])?></textarea></label><?php endforeach;?><div class="grid2 compact"><label>Thời gian (ms)<input type="number" min="100" max="5000" name="time_limit_ms" value="<?=h($p['time_limit_ms'])?>"></label><label>Bộ nhớ (MB)<input type="number" min="32" max="256" name="memory_limit_mb" value="<?=h($p['memory_limit_mb'])?>"></label></div><button class="btn">Lưu thành phiên bản mới</button></form></section>
<?php layoutEnd(); ?>
