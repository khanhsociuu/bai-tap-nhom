<?php
require __DIR__.'/../src/Web.php';
$a=requireAuth();$c=db();
if ($a['role']==='student') {
 $list=rows($c,'SELECT s.id,s.created_at,s.language,p.title,p.id problem_id,j.status,j.verdict,j.passed_count,j.total_count
 FROM submissions s JOIN problems p ON p.id=s.problem_id JOIN judging_runs j ON j.id=s.latest_run_id WHERE s.user_id=$1 ORDER BY s.id DESC LIMIT 100',[$a['id']]);
} elseif ($a['role']==='admin') {
 $list=rows($c,'SELECT s.id,s.created_at,s.language,p.title,p.id problem_id,j.status,j.verdict,j.passed_count,j.total_count
 FROM submissions s JOIN problems p ON p.id=s.problem_id JOIN judging_runs j ON j.id=s.latest_run_id ORDER BY s.id DESC LIMIT 100');
} else {
 $list=rows($c,'SELECT DISTINCT s.id,s.created_at,s.language,p.title,p.id problem_id,j.status,j.verdict,j.passed_count,j.total_count
 FROM submissions s JOIN problems p ON p.id=s.problem_id JOIN judging_runs j ON j.id=s.latest_run_id
 JOIN class_memberships m ON m.user_id=s.user_id JOIN classrooms cl ON cl.id=m.classroom_id
 WHERE cl.teacher_id=$1 ORDER BY s.id DESC LIMIT 100',[$a['id']]);
}
layoutStart('Lịch sử'); ?>
<div class="eyebrow dark" style="margin-top:35px">BÀI NỘP / LỊCH SỬ</div><h1 class="page-title">Lịch sử bài nộp</h1><p class="muted">Hiển thị 100 bài gần nhất, tuân theo quyền tài khoản và lớp học.</p><div class="table-holder"><table><thead><tr><th>Mã</th><th>Bài tập</th><th>Ngôn ngữ</th><th>Ngày nộp</th><th>Trạng thái</th><th>Kết quả</th><th></th></tr></thead><tbody><?php foreach($list as $s):?><tr><td>#<?=h($s['id'])?></td><td><?=h($s['title'])?></td><td><?=h($s['language'])?></td><td><?=h($s['created_at'])?></td><td><?=h($s['status'])?></td><td><span class="chip <?=h($s['verdict']??$s['status'])?>"><?=h($s['verdict']??$s['status'])?></span> <small><?=h($s['passed_count'])?>/<?=h($s['total_count'])?> test</small></td><td><a href="/submission.php?id=<?=h($s['id'])?>">Xem →</a></td></tr><?php endforeach;?></tbody></table><?php if(!$list):?><div class="empty-state">Chưa có bài nộp nào trong phạm vi quyền truy cập của bạn.</div><?php endif;?></div>
<?php layoutEnd(); ?>
