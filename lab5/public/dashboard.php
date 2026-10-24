<?php
require __DIR__.'/../src/Web.php';
$a=requireAuth();$c=db();
if($a['role']!=='student') gotoPath('/teacher.php');
$uid=(int)$a['id'];
$tot=one($c,'SELECT COUNT(*) AS n FROM problems WHERE published=true');
$stats=one($c,"SELECT COUNT(*) AS submissions,
 COUNT(*) FILTER(WHERE j.status='done' AND j.verdict='AC') AS accepted,
 COUNT(DISTINCT s.problem_id) AS attempted
 FROM submissions s JOIN judging_runs j ON j.id=s.latest_run_id WHERE s.user_id=$1",[$uid]);
$solved=one($c,"SELECT COUNT(DISTINCT s.problem_id) AS n FROM submissions s JOIN judging_runs j ON j.submission_id=s.id
 JOIN problems p ON p.id=s.problem_id WHERE s.user_id=$1 AND j.status='done' AND j.verdict='AC' AND p.published=true",[$uid]);
$topics=rows($c,"SELECT t.name,COUNT(DISTINCT p.id) AS total,
 COUNT(DISTINCT p.id) FILTER (WHERE EXISTS (
 SELECT 1 FROM submissions s JOIN judging_runs j ON j.submission_id=s.id
 WHERE s.user_id=$1 AND s.problem_id=p.id AND j.status='done' AND j.verdict='AC')) AS solved
 FROM topics t JOIN problems p ON p.topic_id=t.id WHERE p.published=true
 GROUP BY t.id,t.name ORDER BY t.name",[$uid]);
$recent=rows($c,"SELECT s.id,p.title,s.language,j.status,j.verdict,j.passed_count,j.total_count,s.created_at
 FROM submissions s JOIN problems p ON p.id=s.problem_id JOIN judging_runs j ON j.id=s.latest_run_id
 WHERE s.user_id=$1 ORDER BY s.id DESC LIMIT 8",[$uid]);
$all=(int)$tot['n'];$done=(int)$solved['n'];$attempted=(int)$stats['attempted'];$submissions=(int)$stats['submissions'];
$percent=$all>0?(int)round($done*100/$all):0;
layoutStart('Tiến độ học tập'); ?>
<div class="subhead"><div class="eyebrow dark">HỌC TẬP / TỔNG QUAN</div><a class="btn secondary" href="/">Khám phá bài tập →</a></div>
<div class="dashboard-heading"><div><h1 class="page-title">Xin chào, <?=h($a['display_name'])?> 👋</h1><p class="muted">Theo dõi kỹ năng lập trình và những bài bạn đã chinh phục.</p></div><span class="dashboard-avatar"><?=h((function_exists('mb_substr')?mb_substr($a['display_name'],0,1):substr($a['display_name'],0,1)))?></span></div>
<div class="stat-grid">
 <div class="stat-card"><span>Bài đã giải</span><strong><?=h($done)?><small> / <?=h($all)?></small></strong><p>Có ít nhất một lần chấm AC</p></div>
 <div class="stat-card"><span>Đã thử sức</span><strong><?=h($attempted)?></strong><p>Bài tập đã từng nộp</p></div>
 <div class="stat-card"><span>Lượt nộp</span><strong><?=h($submissions)?></strong><p>Tất cả mã đã gửi lên hệ thống</p></div>
 <div class="stat-card featured"><span>Hoàn thành</span><strong><?=h($percent)?>%</strong><div class="progress-track"><div style="width:<?=h($percent)?>%"></div></div></div>
</div>
<div class="grid2 dashboard-grid"><section class="panel"><div class="panel-heading"><h2>Tiến độ theo chủ đề</h2><span class="muted tiny">Bài đã AC / bài công khai</span></div>
<?php foreach($topics as $t):$total=(int)$t['total'];$doneTopic=(int)$t['solved'];$pct=$total?(int)round(100*$doneTopic/$total):0;?>
<div class="topic-progress"><div class="row-space"><strong><?=h($t['name'])?></strong><span><?=h($doneTopic)?> / <?=h($total)?></span></div><div class="progress-track"><div style="width:<?=h($pct)?>%"></div></div></div>
<?php endforeach; ?></section><section class="panel"><div class="panel-heading"><h2>Hoạt động gần đây</h2><a href="/history.php">Xem tất cả →</a></div>
<?php if(!$recent):?><div class="empty-state">Chưa có bài nộp. Hãy bắt đầu với một bài dễ!</div><?php else:?>
<div class="activity-list"><?php foreach($recent as $r):?><a class="activity-item" href="/submission.php?id=<?=h($r['id'])?>"><span class="activity-icon">{ }</span><span><strong><?=h($r['title'])?></strong><small><?=h($r['language'])?> · <?=h($r['created_at'])?></small></span><span class="chip <?=h($r['verdict']??$r['status'])?>"><?=h($r['verdict']??$r['status'])?></span></a><?php endforeach;?></div>
<?php endif;?></section></div>
<?php layoutEnd(); ?>
