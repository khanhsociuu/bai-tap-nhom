<?php
require __DIR__.'/../src/Web.php';
require __DIR__.'/../src/TeacherService.php';
$a=requireAuth();$c=db();$sid=(int)($_GET['id']??0);
if(!canViewSubmission($c,(int)$a['id'],$a['role'],$sid)) failure('Không có quyền xem bài nộp này',403);
$s=one($c,'SELECT s.*,p.title,u.display_name,pv.version_no AS problem_ver,sv.version_no AS original_suite
 FROM submissions s JOIN problems p ON p.id=s.problem_id JOIN users u ON u.id=s.user_id
 JOIN problem_versions pv ON pv.id=s.problem_version_id JOIN test_suite_versions sv ON sv.id=s.suite_version_id
 WHERE s.id=$1',[$sid]);
if(!$s) failure('Không có bài nộp',404);
$runs=rows($c,'SELECT j.id,j.generation,j.status,j.verdict,j.passed_count,j.total_count,j.created_at,j.finished_at,sv.version_no suite_no
 FROM judging_runs j JOIN test_suite_versions sv ON sv.id=j.suite_version_id WHERE j.submission_id=$1 ORDER BY j.generation DESC',[$sid]);
$results=rows($c,'SELECT cr.judging_run_id,tc.position,cr.verdict,cr.elapsed_ms
 FROM case_results cr JOIN test_cases tc ON tc.id=cr.test_case_id
 JOIN judging_runs jr ON jr.id=cr.judging_run_id WHERE jr.submission_id=$1 ORDER BY jr.generation DESC,tc.position',[$sid]);
$byRun=[];foreach($results as $r)$byRun[$r['judging_run_id']][]=$r;
$comparison=array_slice($runs,0,2);
$suites=rows($c,'SELECT id,version_no FROM test_suite_versions WHERE problem_id=$1 AND problem_version_id=$2 ORDER BY version_no DESC',[$s['problem_id'],$s['problem_version_id']]);
layoutStart('Bài nộp #'.$sid); ?>
<div class="subhead"><a href="/problem.php?id=<?=h($s['problem_id'])?>">← <?=h($s['title'])?></a></div>
<h1 class="page-title">Bài nộp #<?=h($sid)?></h1>
<div class="metadata"><span>Người nộp: <b><?=h($s['display_name'])?></b></span><span>Ngôn ngữ: <b><?=h($s['language'])?></b></span><span>Phiên bản đề: <b>v<?=h($s['problem_ver'])?></b></span><span>Bộ chấm lúc nộp: <b>v<?=h($s['original_suite'])?></b></span></div>
<?php if(count($comparison)>1): ?>
<section class="panel comparison-panel"><div class="panel-heading"><div><div class="eyebrow dark">PHIÊN BẢN & LỊCH SỬ</div><h2>Đối chiếu hai lần chấm gần nhất</h2></div><span class="chip">Giữ nguyên mã nguồn</span></div>
<div class="compare-grid"><?php foreach($comparison as $i=>$compare): ?><div class="compare-card <?=$i===0?'current':''?>"><div class="row-space"><strong><?=$i===0?'LẦN MỚI NHẤT':'LẦN TRƯỚC'?></strong><span class="chip <?=h($compare['verdict']??$compare['status'])?>"><?=h($compare['verdict']??$compare['status'])?></span></div><div class="compare-verdict"><?=h($compare['verdict']??strtoupper($compare['status']))?></div><div class="compare-meta"><span>Lần chấm <b>#<?=h($compare['generation'])?></b></span><span>Bộ test <b>v<?=h($compare['suite_no'])?></b></span><span>Số test đạt <b><?=h($compare['passed_count'])?>/<?=h($compare['total_count'])?></b></span><span>Thời gian <b><?=h($compare['created_at'])?></b></span></div></div><?php endforeach;?></div>
<p class="tiny muted">Đây là các kết quả lưu độc lập cho cùng mã nguồn. Không hiển thị input, expected output hoặc stdout của test kín.</p></section>
<?php endif; ?>
<div class="workspace reverse"><section class="panel"><h2>Mã nguồn đã nộp (chỉ đọc)</h2><pre class="source-code"><?=h($s['source_code'])?></pre></section><section class="panel"><div class="row-space"><h2>Lịch sử chấm</h2><span class="chip">Không xóa phiên bản cũ</span></div><div id="submission-live" data-id="<?=h($sid)?>" class="tiny muted">Trang sẽ tự cập nhật trạng thái chấm mới nhất.</div>
<?php foreach($runs as $r): ?><div class="run-card"><div class="row-space"><b>Lần #<?=h($r['generation'])?> · Bộ test v<?=h($r['suite_no'])?></b><span class="chip verdict"><?=h($r['verdict']??$r['status'])?></span></div><p class="muted tiny">#<?=h($r['id'])?> · <?=h($r['created_at'])?> · Qua <?=h($r['passed_count'])?>/<?=h($r['total_count'])?> ca</p>
<?php if(isset($byRun[$r['id']])): ?><div class="casegrid"><?php foreach($byRun[$r['id']] as $x): ?><span class="casepill" title="<?=h($x['elapsed_ms'])?> ms">Test #<?=h($x['position'])?>: <?=h($x['verdict'])?></span><?php endforeach; ?></div><?php endif; ?>
</div><?php endforeach; ?>
<?php if($a['role']!=='student' && canManageProblem($c,$a,(int)$s['problem_id'])): ?><form id="rejudge-form" class="stack"><h3>Chấm lại (giảng viên)</h3><p class="tiny muted">Chỉ dùng bộ test phù hợp phiên bản đề của bài nộp. Lần cũ vẫn được giữ.</p><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="submission_id" value="<?=h($sid)?>"><select name="suite_id"><?php foreach($suites as $sv): ?><option value="<?=h($sv['id'])?>">Bộ test v<?=h($sv['version_no'])?></option><?php endforeach; ?></select><button class="btn" type="submit">Tạo lần chấm mới</button><p class="tiny" id="rejudge-result"></p></form><?php endif; ?>
</section></div>
<?php layoutEnd(); ?>
