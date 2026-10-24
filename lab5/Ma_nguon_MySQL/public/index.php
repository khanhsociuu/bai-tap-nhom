<?php
require __DIR__.'/../src/Web.php';
$c=db();
$topic=(int)($_GET['topic']??0);$level=(int)($_GET['level']??0);$search=trim((string)($_GET['q']??''));
$all=rows($c,'SELECT p.id,p.title,p.slug,t.name AS topic,l.name AS level,pv.version_no,
  (SELECT COUNT(*) FROM test_cases tc WHERE tc.suite_version_id=p.current_suite_id) AS test_count
  FROM problems p JOIN topics t ON t.id=p.topic_id JOIN levels l ON l.id=p.level_id
  JOIN problem_versions pv ON pv.id=p.current_version_id
  WHERE p.published=true AND ($1::bigint=0 OR p.topic_id=$1)
   AND ($2::bigint=0 OR p.level_id=$2) AND ($3::text=\'\' OR p.title ILIKE \'%\'||$3||\'%\') ORDER BY p.id',[$topic,$level,$search]);
$topics=rows($c,'SELECT * FROM topics ORDER BY id');$levels=rows($c,'SELECT * FROM levels ORDER BY id');
layoutStart('Danh sách bài tập');
?>
<section class="hero"><div><div class="eyebrow">ONLINE PROGRAMMING JUDGE · LAB 05</div><h1>Luyện tập lập trình,<br><em>chấm tự động.</em></h1><p>Viết Python hoặc C++ · Chạy thử với stdin · Nộp bài và theo dõi từng lần chấm.</p><a href="#problems" class="btn">Khám phá bài tập →</a></div><div class="hero-stat"><div class="hero-num"><?=count($all)?></div><span>Bài tập trong danh sách</span><div class="mini">Kết quả chấm dựa trên dữ liệu thử.</div></div></section>
<section id="problems" class="section-title"><h2>Danh sách bài tập</h2><p>Chọn chủ đề và mức độ để tìm bài phù hợp.</p></section>
<form method="get" class="filters"><input name="q" placeholder="Tìm kiếm bài tập..." value="<?=h($search)?>"><select name="topic"><option value="0">Tất cả chủ đề</option><?php foreach($topics as $t): ?><option value="<?=h($t['id'])?>" <?=$topic==(int)$t['id']?'selected':''?>><?=h($t['name'])?></option><?php endforeach; ?></select><select name="level"><option value="0">Tất cả mức độ</option><?php foreach($levels as $l): ?><option value="<?=h($l['id'])?>" <?=$level==(int)$l['id']?'selected':''?>><?=h($l['name'])?></option><?php endforeach; ?></select><button class="btn">Lọc</button></form>
<div class="table-holder"><table><thead><tr><th>#</th><th>Bài tập</th><th>Chủ đề</th><th>Mức độ</th><th>Test cases</th><th></th></tr></thead><tbody><?php foreach($all as $p): ?><tr><td class="mono"><?=h($p['id'])?></td><td><a class="stronglink" href="/problem.php?id=<?=h($p['id'])?>"><?=h($p['title'])?></a><small><?=h($p['slug'])?></small></td><td><?=h($p['topic'])?></td><td><span class="chip"><?=h($p['level'])?></span></td><td><?=h($p['test_count'])?></td><td><a href="/problem.php?id=<?=h($p['id'])?>">Giải bài →</a></td></tr><?php endforeach; ?></tbody></table><?php if(!$all): ?><p class="muted" style="padding:20px">Không có bài phù hợp.</p><?php endif; ?></div>
<?php layoutEnd(); ?>
