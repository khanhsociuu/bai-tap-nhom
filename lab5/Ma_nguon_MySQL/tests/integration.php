<?php
require dirname(__DIR__).'/src/JudgeService.php';
require dirname(__DIR__).'/src/Web.php';
$test=0;$fail=0;
function ok(string $why,bool $pass):void {
 global $test,$fail;$test++;
 if(!$pass){$fail++;echo "FAIL: $why\n";}
}
try {$c=db();}
catch(Throwable $e) {fwrite(STDERR,"INTEGRATION: NOT_RUN (".$e->getMessage().")\n");exit(2);}
$id=bin2hex(random_bytes(5));$userIds=[];$pid=null;
try {
  $u=one($c,'INSERT INTO users(username,display_name,role,password_hash) VALUES($1,$2,$3,$4) RETURNING id',
   ['test_a_'.$id,'Fixture A','student',password_hash('local_test_password',PASSWORD_DEFAULT)]);
  $ua=(int)$u['id'];$userIds[]=$ua;
  $u=one($c,'INSERT INTO users(username,display_name,role,password_hash) VALUES($1,$2,$3,$4) RETURNING id',
   ['test_b_'.$id,'Fixture B','student',password_hash('local_test_password',PASSWORD_DEFAULT)]);
  $ub=(int)$u['id'];$userIds[]=$ub;
  $topic=one($c,'INSERT INTO topics(name) VALUES($1) RETURNING id',['fixture_topic_'.$id]);
  $level=one($c,'INSERT INTO levels(name) VALUES($1) RETURNING id',['fixture_level_'.$id]);
  $p=one($c,'INSERT INTO problems(topic_id,level_id,slug,title,created_by) VALUES($1,$2,$3,$4,$5) RETURNING id',
   [$topic['id'],$level['id'],'fixture_'.$id,'Fixture problem',$ua]);$pid=(int)$p['id'];
  $pv=one($c,'INSERT INTO problem_versions(problem_id,version_no,statement,input_desc,output_desc,sample_input,sample_output,time_limit_ms,memory_limit_mb)
  VALUES($1,1,$2,$3,$4,$5,$6,1000,128) RETURNING id',[$pid,'sum','two','sum','1 2','3']);
  $suite=one($c,'INSERT INTO test_suite_versions(problem_id,problem_version_id,version_no,created_by) VALUES($1,$2,1,$3) RETURNING id',[$pid,$pv['id'],$ua]);
  foreach(range(1,5) as $i) queryParams($c,'INSERT INTO test_cases(suite_version_id,position,input_data,expected_output) VALUES($1,$2,$3,$4)',[$suite['id'],$i,'1 2','3']);
  queryParams($c,'UPDATE problems SET current_version_id=$1,current_suite_id=$2,published=true WHERE id=$3',[$pv['id'],$suite['id'],$pid]);
  $service=new JudgeService($c);
  $key=sprintf('%08s-0000-4000-8000-%012s',substr($id,0,8),substr(hash('sha256',$id),0,12));
  $source='print(sum(map(int,input().split())))';
  $first=$service->submit($ua,$pid,'python',$source,$key);
  $second=$service->submit($ua,$pid,'python',$source,$key);
  ok('CREATE first',!$first['duplicate']);
  ok('Idempotency same id',$second['duplicate']&&$first['id']===$second['id']);
  $single=one($c,'SELECT count(*) n FROM submissions WHERE user_id=$1 AND idempotency_key=$2',[$ua,$key]);
  ok('DB unique request',(int)$single['n']===1);
  try {$service->submit($ua,$pid,'python','print(8)',$key);ok('Reject changed payload',false);}
  catch(InvalidArgumentException){ok('Reject changed payload',true);}
  $key2=sprintf('%08s-0000-4000-8000-%012s',substr($id,0,8),substr(hash('sha256','second'.$id),0,12));
  $third=$service->submit($ua,$pid,'python',$source,$key2);
  ok('New intentional request',$third['id']!==$first['id']);
  ok('Private code denied to B',!canViewSubmission($c,$ub,'student',$first['id']));
  ok('Private code allowed to A',canViewSubmission($c,$ua,'student',$first['id']));
  $old=one($c,'SELECT * FROM judging_runs WHERE submission_id=$1 ORDER BY id LIMIT 1',[$first['id']]);
  queryParams($c,"UPDATE judging_runs SET status='running',lease_token='old-token',lease_until=now()+interval '90 seconds' WHERE id=$1",[$old['id']]);
  $newSuite=one($c,'INSERT INTO test_suite_versions(problem_id,problem_version_id,version_no,created_by) VALUES($1,$2,2,$3) RETURNING id',[$pid,$pv['id'],$ua]);
  foreach(range(1,5) as $i) queryParams($c,'INSERT INTO test_cases(suite_version_id,position,input_data,expected_output) VALUES($1,$2,$3,$4)',[$newSuite['id'],$i,'1 2','3']);
  $newRun=$service->rejudge($first['id'],(int)$newSuite['id']);
  $latest=one($c,'SELECT latest_run_id FROM submissions WHERE id=$1',[$first['id']]);
  ok('New run is latest',(int)$latest['latest_run_id']===$newRun);
  $oldComplete=$service->complete((int)$old['id'],'old-token',[], 'WA');
  $latest=one($c,'SELECT latest_run_id FROM submissions WHERE id=$1',[$first['id']]);
  ok('Late old result stored',$oldComplete);
  ok('Old result not override latest',(int)$latest['latest_run_id']===$newRun);
  $runHistory=one($c,'SELECT count(*) n FROM judging_runs WHERE submission_id=$1',[$first['id']]);
  ok('History preserved',(int)$runHistory['n']===2);
  $stale=$service->complete((int)$old['id'],'old-token',[], 'AC');
  ok('Old double result rejected',!$stale);
  $r=one($c,'SELECT * FROM judging_runs WHERE id=$1',[$newRun]);
  queryParams($c,"UPDATE judging_runs SET status='running',lease_token='new-token',lease_until=now()+interval '90 seconds' WHERE id=$1",[$newRun]);
  $r['lease_token']='new-token';
  $judged=$service->judge($r,fn()=>['kind'=>'OK','stdout'=>'3\n','elapsed_ms'=>8]);
  $done=one($c,'SELECT * FROM judging_runs WHERE id=$1',[$newRun]);
  ok('Worker records run',$judged);
  ok('Five stored case results',(int)one($c,'SELECT COUNT(*) n FROM case_results WHERE judging_run_id=$1',[$newRun])['n']===5);
  ok('Correct solution AC',$done['verdict']==='AC');
  $rid=$service->rejudge($first['id'],(int)$newSuite['id']);
  queryParams($c,"UPDATE judging_runs SET status='running',lease_token='wa-token',lease_until=now()+interval '90 seconds' WHERE id=$1",[$rid]);
  $bad=one($c,'SELECT * FROM judging_runs WHERE id=$1',[$rid]);
  $service->judge($bad,fn()=>['kind'=>'OK','stdout'=>"4\n",'elapsed_ms'=>8]);
  $wa=one($c,'SELECT verdict FROM judging_runs WHERE id=$1',[$rid]);
  ok('Wrong output WA',$wa['verdict']==='WA');
  $verdicts=['CE','RE','TLE'];
  foreach ($verdicts as $expect) {
    $rid=$service->rejudge($first['id'],(int)$newSuite['id']);
    queryParams($c,"UPDATE judging_runs SET status='running',lease_token='fixture-token',lease_until=now()+interval '90 seconds' WHERE id=$1",[$rid]);
    $row=one($c,'SELECT * FROM judging_runs WHERE id=$1',[$rid]);
    $service->judge($row,fn()=>['kind'=>$expect,'stdout'=>'','elapsed_ms'=>9]);
    $got=one($c,'SELECT verdict FROM judging_runs WHERE id=$1',[$rid]);
    ok($expect.' verdict',$got['verdict']===$expect);
  }
  $rid=$service->rejudge($first['id'],(int)$newSuite['id']);
  queryParams($c,"UPDATE judging_runs SET status='running',lease_token='system-token',lease_until=now()+interval '90 seconds' WHERE id=$1",[$rid]);
  $row=one($c,'SELECT * FROM judging_runs WHERE id=$1',[$rid]);
  $service->judge($row,fn()=>['kind'=>'SYSTEM_ERROR','stdout'=>'','elapsed_ms'=>0]);
  $got=one($c,'SELECT status,verdict FROM judging_runs WHERE id=$1',[$rid]);
  ok('System outage never counted as WA',$got['status']==='system_error' && $got['verdict']==='SYSTEM_ERROR');
  echo "INTEGRATION: TESTS=$test FAILED=$fail\n";
} catch(Throwable $e) { $fail++; fwrite(STDERR,'INTEGRATION ERROR: '.$e->getMessage()."\n"); }
finally {
 if ($pid!==null) {
  // Fixture cleanup only; never touch seeded or real submissions.
  try {
   queryParams($c,'UPDATE submissions SET latest_run_id=NULL WHERE problem_id=$1',[$pid]);
   queryParams($c,'DELETE FROM case_results WHERE judging_run_id IN (SELECT jr.id FROM judging_runs jr JOIN submissions s ON s.id=jr.submission_id WHERE s.problem_id=$1)',[$pid]);
   queryParams($c,'DELETE FROM judging_runs WHERE submission_id IN (SELECT id FROM submissions WHERE problem_id=$1)',[$pid]);
   queryParams($c,'DELETE FROM submissions WHERE problem_id=$1',[$pid]);
   queryParams($c,'DELETE FROM test_cases WHERE suite_version_id IN (SELECT id FROM test_suite_versions WHERE problem_id=$1)',[$pid]);
   queryParams($c,'UPDATE problems SET current_version_id=NULL,current_suite_id=NULL WHERE id=$1',[$pid]);
   queryParams($c,'DELETE FROM test_suite_versions WHERE problem_id=$1',[$pid]);
   queryParams($c,'DELETE FROM problem_versions WHERE problem_id=$1',[$pid]);
   queryParams($c,'DELETE FROM problems WHERE id=$1',[$pid]);
   queryParams($c,'DELETE FROM topics WHERE name=$1',['fixture_topic_'.$id]);
   queryParams($c,'DELETE FROM levels WHERE name=$1',['fixture_level_'.$id]);
  }catch(Throwable $e){$fail++;fwrite(STDERR,'Fixture cleanup failed: '.$e->getMessage()."\n");}
 }
 foreach($userIds as $uid) {try{queryParams($c,'DELETE FROM users WHERE id=$1',[$uid]);}catch(Throwable $e){$fail++;}}
}
exit($fail?1:0);
