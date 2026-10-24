<?php
require __DIR__.'/../src/Web.php';
$a=requireAuth();$id=(int)($_GET['id']??0);$c=db();
$p=one($c,'SELECT p.id,p.title,p.slug,t.name topic,l.name level,pv.id version_id,pv.version_no,pv.statement,pv.input_desc,pv.output_desc,pv.sample_input,pv.sample_output,pv.time_limit_ms,pv.memory_limit_mb,sv.version_no suite_no
 FROM problems p JOIN topics t ON t.id=p.topic_id JOIN levels l ON l.id=p.level_id JOIN problem_versions pv ON pv.id=p.current_version_id
 JOIN test_suite_versions sv ON sv.id=p.current_suite_id WHERE p.published=true AND p.id=$1',[$id]);
if(!$p) failure('Không tìm thấy bài',404);
$drafts=rows($c,'SELECT language,code FROM drafts WHERE user_id=$1 AND problem_id=$2',[$a['id'],$id]);
$draftMap=[];foreach($drafts as $d)$draftMap[$d['language']]=$d['code'];
$history=rows($c,'SELECT s.id,s.language,r.status,r.verdict,r.passed_count,r.total_count,s.created_at FROM submissions s JOIN judging_runs r ON r.id=s.latest_run_id WHERE s.user_id=$1 AND s.problem_id=$2 ORDER BY s.id DESC LIMIT 6',[$a['id'],$id]);
layoutStart($p['title']); ?>
<div class="subhead"><a class="back-link" href="/">← Tất cả bài tập</a><div class="subtags"><span class="chip"><?=h($p['topic'])?></span><span class="chip"><?=h($p['level'])?></span><span class="chip">Đề v<?=h($p['version_no'])?> / Test v<?=h($p['suite_no'])?></span></div></div>
<div class="problem-heading"><div><div class="eyebrow dark">BÀI TẬP #<?=h($id)?></div><h1 class="page-title"><?=h($p['title'])?></h1></div><div class="problem-limits"><span>◷ <?=h($p['time_limit_ms'])?> ms</span><span>▣ <?=h($p['memory_limit_mb'])?> MB</span></div></div>
<div class="workspace coding-workspace"><article class="panel details problem-statement">
<div class="panel-heading"><h2><span class="indexno">01 /</span> Nội dung bài toán</h2><span class="tiny muted">Tiếng Việt</span></div>
<p class="preserve statement-copy"><?=h($p['statement'])?></p><h3>Dữ liệu vào</h3><p class="preserve"><?=h($p['input_desc'])?></p><h3>Dữ liệu ra</h3><p class="preserve"><?=h($p['output_desc'])?></p>
<h3>Ví dụ</h3><div class="sample-box"><div class="row-space"><span class="code-label">INPUT</span><button type="button" class="copy-sample" data-copy="sample-in">Sao chép</button></div><pre id="sample-in"><?=h($p['sample_input'])?></pre></div><div class="sample-box"><div class="row-space"><span class="code-label">OUTPUT</span><button type="button" class="copy-sample" data-copy="sample-out">Sao chép</button></div><pre id="sample-out"><?=h($p['sample_output'])?></pre></div>
<p class="note">Dữ liệu chấm kín và đáp án không được gửi tới trình duyệt. AC chỉ xác nhận mã vượt qua các ca thử hiện tại.</p></article>
<section class="panel editor editor-shell" data-problem="<?=h($p['id'])?>" data-csrf="<?=h(csrf())?>" id="editor">
<div class="editor-head"><div><span class="eyebrow dark">02 / CODE WORKSPACE</span><h2>Trình soạn mã</h2></div><div class="editor-options"><label for="language" class="sr-only">Ngôn ngữ</label><select id="language"><option value="python">Python 3.12</option><option value="cpp">C++17</option></select></div></div>
<div class="editor-toolbar"><span class="editor-file" id="editor-filename">main.py</span><span class="editor-help">Tab để thụt đầu dòng · Ctrl+Enter chạy thử</span></div>
<div class="editor-surface"><textarea spellcheck="false" id="code" aria-label="Soạn mã lập trình" autocomplete="off" autocapitalize="off" autocorrect="off"></textarea></div>
<div class="actionbar"><span id="editor-status" class="tiny muted" aria-live="polite">Thay đổi chỉ được lưu khi nhấn Lưu nháp.</span><button id="save" class="btn secondary" type="button">Lưu nháp</button><button id="try" class="btn secondary" type="button">▶ Chạy thử</button><button id="submit" class="btn" type="button">Nộp bài →</button></div>
<div class="console-section"><div class="console-tabs"><button type="button" class="console-tab active" data-pane="test-pane" aria-selected="true">⌘ Test case tự nhập</button><button type="button" class="console-tab" data-pane="result-pane" aria-selected="false">▤ Kết quả chạy thử</button></div>
<div id="test-pane" class="console-pane active"><label class="field-label" for="stdin">Dữ liệu đầu vào (stdin)</label><textarea id="stdin" rows="5" placeholder="Nhập đầu vào cho chương trình..."><?=h($p['sample_input'])?></textarea><p class="tiny muted">Ví dụ là dữ liệu công khai, không phải bộ test kín của hệ thống.</p></div>
<div id="result-pane" class="console-pane"><div id="console" class="console" aria-live="polite">Chưa chạy thử. Nhấn “Chạy thử” để nhận kết quả.</div></div></div>
</section></div>
<section class="section-title title-row"><div><h2>Bài nộp của tôi</h2><p>6 lần gần nhất của bài tập này</p></div><a href="/history.php">Xem lịch sử đầy đủ →</a></section>
<div class="table-holder"><table><thead><tr><th>MÃ BÀI</th><th>NGÔN NGỮ</th><th>THỜI GIAN</th><th>TRẠNG THÁI</th><th>QUA TEST</th><th></th></tr></thead><tbody><?php foreach($history as $s):?><tr><td>#<?=h($s['id'])?></td><td><?=h($s['language'])?></td><td><?=h($s['created_at'])?></td><td><span class="chip <?=h($s['verdict']??$s['status'])?>"><?=h($s['verdict']??$s['status'])?></span></td><td><?=h($s['passed_count'])?>/<?=h($s['total_count'])?></td><td><a href="/submission.php?id=<?=h($s['id'])?>">Chi tiết →</a></td></tr><?php endforeach;?></tbody></table><?php if(!$history):?><div class="empty-state">Chưa có bài nộp nào.</div><?php endif;?></div>
<script type="application/json" id="drafts"><?=json_encode($draftMap,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE)?></script>
<link rel="stylesheet" href="/vendor/codemirror/codemirror.css">
<link rel="stylesheet" href="/vendor/codemirror/material-darker.css">
<script src="/vendor/codemirror/codemirror.js"></script>
<script src="/vendor/codemirror/python.js"></script>
<script src="/vendor/codemirror/clike.js"></script>
<?php layoutEnd(); ?>
