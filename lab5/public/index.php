<?php
require __DIR__.'/../src/Web.php';
$c=db();$account=auth();$uid=(int)($account['id']??0);
$topic=max(0,(int)($_GET['topic']??0));$level=max(0,(int)($_GET['level']??0));$search=trim((string)($_GET['q']??''));
$status=(string)($_GET['status']??'all');if(!in_array($status,['all','solved','attempted','new'],true))$status='all';
$all=rows($c,"SELECT p.id,p.title,p.slug,t.name AS topic,l.name AS level,pv.version_no,
  (SELECT COUNT(*) FROM test_cases tc WHERE tc.suite_version_id=p.current_suite_id) AS test_count,
  EXISTS(SELECT 1 FROM submissions s WHERE s.problem_id=p.id AND s.user_id=$4) AS attempted,
  EXISTS(SELECT 1 FROM submissions s JOIN judging_runs j ON j.submission_id=s.id
     WHERE s.problem_id=p.id AND s.user_id=$4 AND j.verdict='AC' AND j.status='done') AS solved
  FROM problems p JOIN topics t ON t.id=p.topic_id JOIN levels l ON l.id=p.level_id
  JOIN problem_versions pv ON pv.id=p.current_version_id
  WHERE p.published=true AND ($1::bigint=0 OR p.topic_id=$1)
   AND ($2::bigint=0 OR p.level_id=$2) AND ($3::text='' OR p.title ILIKE '%'||$3||'%') ORDER BY p.id",[$topic,$level,$search,$uid]);
$filtered=array_values(array_filter($all,static function($p)use($status,$uid){
 if(!$uid||$status==='all')return true;
 $solved=$p['solved']==='t';$attempted=$p['attempted']==='t';
 return match($status){'solved'=>$solved,'attempted'=>$attempted&&!$solved,'new'=>!$attempted,default=>true};
}));
$tot=one($c,'SELECT COUNT(*) n FROM problems WHERE published=true');$total=(int)$tot['n'];
$personal=$uid?one($c,"SELECT COUNT(DISTINCT s.problem_id) n FROM submissions s JOIN judging_runs j ON j.submission_id=s.id
 JOIN problems p ON p.id=s.problem_id WHERE s.user_id=$1 AND j.status='done' AND j.verdict='AC' AND p.published=true",[$uid]):['n'=>0];
$topics=rows($c,'SELECT * FROM topics ORDER BY id');$levels=rows($c,'SELECT * FROM levels ORDER BY id');
layoutStart('Danh sách bài tập'); ?>
<section class="hero"><div><div class="eyebrow">DNU JUDGE · ONLINE PROGRAMMING PLATFORM</div><h1>Luyện code mỗi ngày.<br><em>Chinh phục mọi thử thách.</em></h1><p>10+ thử thách thực hành · Python & C++ · Chấm tự động và lưu mọi lần thử của bạn.</p><div class="hero-actions"><a href="#problems" class="btn light">Khám phá bài tập →</a><?php if($uid):?><a class="hero-outline" href="/dashboard.php">Xem tiến độ của tôi</a><?php endif;?></div></div><div class="hero-stats"><div><strong><?=h($total)?></strong><span>Bài tập hiện có</span></div><div><strong><?=h((int)$personal['n'])?></strong><span>Bài đã giải<?=!$uid?' (đăng nhập để theo dõi)':''?></span></div><div class="hero-stats-caption">● Python 3.12 & C++17 · stdin / stdout</div></div></section>
<section id="problems" class="section-title title-row"><div><div class="eyebrow dark">THƯ VIỆN BÀI TẬP</div><h2>Danh sách thử thách</h2><p>Tìm, lọc và chọn bài phù hợp với trình độ của bạn.</p></div><span class="chip"><?=count($filtered)?> kết quả</span></section>
<form method="get" class="filters refined"><label class="search-field"><span>⌕</span><input name="q" aria-label="Tìm kiếm bài tập" placeholder="Tìm theo tên bài tập..." value="<?=h($search)?>"></label><select name="topic" aria-label="Chủ đề"><option value="0">Tất cả chủ đề</option><?php foreach($topics as $t):?><option value="<?=h($t['id'])?>" <?=$topic==(int)$t['id']?'selected':''?>><?=h($t['name'])?></option><?php endforeach;?></select><select name="level" aria-label="Mức độ"><option value="0">Tất cả mức độ</option><?php foreach($levels as $l):?><option value="<?=h($l['id'])?>" <?=$level==(int)$l['id']?'selected':''?>><?=h($l['name'])?></option><?php endforeach;?></select><?php if($uid):?><select name="status" aria-label="Tiến độ"><option value="all">Mọi trạng thái</option><option value="new" <?=$status==='new'?'selected':''?>>Chưa làm</option><option value="attempted" <?=$status==='attempted'?'selected':''?>>Đang luyện</option><option value="solved" <?=$status==='solved'?'selected':''?>>Đã giải</option></select><?php endif;?><button class="btn">Lọc bài</button></form>
<div class="table-holder problems-table"><table><thead><tr><th>TRẠNG THÁI</th><th>BÀI TẬP</th><th>CHỦ ĐỀ</th><th>MỨC ĐỘ</th><th>TEST CASE</th><th></th></tr></thead><tbody><?php foreach($filtered as $item):$isSolved=$uid&&$item['solved']==='t';$isAttempt=$uid&&$item['attempted']==='t';?><tr><td><span class="progress-indicator <?=$isSolved?'solved':($isAttempt?'attempted':'') ?>" title="<?=$isSolved?'Đã giải':($isAttempt?'Đã thử':'Chưa làm')?>"><?=$isSolved?'✓':($isAttempt?'◷':'○')?></span></td><td><a class="stronglink" href="/problem.php?id=<?=h($item['id'])?>"><?=h($item['title'])?></a><small>#<?=h($item['id'])?> · <?=h($item['slug'])?></small></td><td><?=h($item['topic'])?></td><td><span class="chip level-chip"><?=h($item['level'])?></span></td><td><?=h($item['test_count'])?> ca</td><td><a class="table-action" href="/problem.php?id=<?=h($item['id'])?>">Giải bài ↗</a></td></tr><?php endforeach;?></tbody></table><?php if(!$filtered):?><div class="empty-state">Chưa tìm thấy bài phù hợp. Hãy thử bộ lọc khác.</div><?php endif;?></div>
<?php layoutEnd(); ?>
