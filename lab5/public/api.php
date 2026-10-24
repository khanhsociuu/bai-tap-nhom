<?php
require __DIR__.'/../src/Web.php';
require __DIR__.'/../src/TrialService.php';
require __DIR__.'/../src/TeacherService.php';
$a=auth();if (!$a) jsonSend(['error'=>'Cần đăng nhập'],401);
$action=(string)($_GET['action']??'');
try {
 $c=db();
 if ($_SERVER['REQUEST_METHOD']==='POST') {
    if(!hash_equals(csrf(),(string)($_POST['csrf']??''))) jsonSend(['error'=>'CSRF không hợp lệ'],419);
    $pid=(int)($_POST['problem_id']??0);
    if ($action==='draft') {
       Policy::checkSource((string)($_POST['language']??''),(string)($_POST['code']??''));
       if (!one($c,'SELECT 1 FROM problems WHERE id=$1 AND published=true',[$pid])) jsonSend(['error'=>'Bài tập không khả dụng'],404);
       queryParams($c,'INSERT INTO drafts(user_id,problem_id,language,code) VALUES($1,$2,$3,$4)
        ON CONFLICT(user_id,problem_id,language) DO UPDATE SET code=EXCLUDED.code,updated_at=now()',
        [$a['id'],$pid,$_POST['language'],$_POST['code']]);
       jsonSend(['ok'=>true]);
    }
    if ($action==='submit') {
       $known=one($c,'SELECT id FROM submissions WHERE user_id=$1 AND idempotency_key=$2',[$a['id'],(string)($_POST['request_key']??'')]);
       if (!$known) {
           $limit=one($c,"SELECT COUNT(*) AS n FROM submissions WHERE user_id=$1 AND created_at>now()-interval '1 hour'",[$a['id']]);
           if ((int)$limit['n']>=30) jsonSend(['error'=>'Tối đa 30 lần nộp mới mỗi giờ'],429);
       }
       $service=new JudgeService($c);
       $result=$service->submit((int)$a['id'],$pid,(string)($_POST['language']??''),(string)($_POST['code']??''),(string)($_POST['request_key']??''));
       jsonSend(['ok'=>true,'submission_id'=>$result['id'],'duplicate'=>$result['duplicate']]);
    }
    if ($action==='trial') {
       $service=new TrialService($c);
       $id=$service->create((int)$a['id'],$pid,(string)($_POST['language']??''),(string)($_POST['code']??''),(string)($_POST['stdin']??''));
       jsonSend(['ok'=>true,'trial_id'=>$id]);
    }
    if ($action==='rejudge') {
       $sid=(int)($_POST['submission_id']??0);
       if (!canViewSubmission($c,(int)$a['id'],$a['role'],$sid)) jsonSend(['error'=>'Không có quyền'],403);
       if ($a['role']==='student') jsonSend(['error'=>'Chỉ giảng viên/quản trị được chấm lại'],403);
       $target=one($c,'SELECT problem_id FROM submissions WHERE id=$1',[$sid]);
       if (!$target || !canManageProblem($c,$a,(int)$target['problem_id'])) jsonSend(['error'=>'Bạn không quản lý đề của bài nộp này'],403);
       $id=(new JudgeService($c))->rejudge($sid,(int)($_POST['suite_id']??0));
       jsonSend(['ok'=>true,'run_id'=>$id]);
    }
    jsonSend(['error'=>'Thao tác không hỗ trợ'],404);
 }
 if ($_SERVER['REQUEST_METHOD']==='GET' && $action==='trial-status') {
    $t=one($c,'SELECT id,status,result_kind,stdout_data,stderr_data,elapsed_ms FROM trial_runs WHERE id=$1 AND user_id=$2',[(int)($_GET['id']??0),$a['id']]);
    if (!$t) jsonSend(['error'=>'Không tìm thấy'],404);
    jsonSend($t);
 }
 if ($_SERVER['REQUEST_METHOD']==='GET' && $action==='submission-status') {
    $sid=(int)($_GET['id']??0);
    if (!canViewSubmission($c,(int)$a['id'],$a['role'],$sid)) jsonSend(['error'=>'Không có quyền'],403);
    $r=one($c,'SELECT j.id,j.submission_id,j.generation,j.status,j.verdict,j.passed_count,j.total_count,j.created_at,j.finished_at
                 FROM submissions s JOIN judging_runs j ON j.id=s.latest_run_id WHERE s.id=$1',[$sid]);
    jsonSend(['run'=>$r ? Policy::publicRun($r):null]);
 }
 jsonSend(['error'=>'Không có thao tác này'],404);
} catch(InvalidArgumentException $e) {jsonSend(['error'=>$e->getMessage()],422);}
catch(Throwable $e) {error_log('API failed: '.$e->getMessage());jsonSend(['error'=>'Lỗi hệ thống, vui lòng thử lại'],503);}
