<?php
declare(strict_types=1);
require_once __DIR__.'/Database.php';
require_once __DIR__.'/Policy.php';
final class JudgeService {
    public function __construct(private PgSql\Connection $c) {}
    public function submit(int $userId,int $problemId,string $language,string $source,string $key): array {
        Policy::checkSource($language,$source);
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',$key))
            throw new InvalidArgumentException('Khóa yêu cầu không hợp lệ (UUID)');
        return tx($this->c,function() use($userId,$problemId,$language,$source,$key) {
            // Existing request must remain idempotent even after problem is unpublished.
            $old=one($this->c,'SELECT * FROM submissions WHERE user_id=$1 AND idempotency_key=$2',[$userId,$key]);
            if ($old) return $this->sameOrFail($old,$problemId,$language,$source);
            $p=one($this->c,'SELECT id,current_version_id,current_suite_id FROM problems WHERE id=$1 AND published=true',[$problemId]);
            if (!$p || !$p['current_version_id'] || !$p['current_suite_id']) throw new InvalidArgumentException('Bài tập không khả dụng');
            $created=one($this->c,'INSERT INTO submissions(user_id,problem_id,problem_version_id,suite_version_id,language,source_code,idempotency_key)
                 VALUES($1,$2,$3,$4,$5,$6,$7) ON CONFLICT(user_id,idempotency_key) DO NOTHING RETURNING id',
                 [$userId,$problemId,$p['current_version_id'],$p['current_suite_id'],$language,$source,$key]);
            if (!$created) {
                $old=one($this->c,'SELECT * FROM submissions WHERE user_id=$1 AND idempotency_key=$2',[$userId,$key]);
                if (!$old) throw new RuntimeException('Không đọc được yêu cầu trùng');
                return $this->sameOrFail($old,$problemId,$language,$source);
            }
            $sid=(int)$created['id'];
            $r=one($this->c,'INSERT INTO judging_runs(submission_id,suite_version_id,generation)
                              VALUES($1,$2,1) RETURNING id',[$sid,$p['current_suite_id']]);
            queryParams($this->c,'UPDATE submissions SET latest_run_id=$1 WHERE id=$2',[$r['id'],$sid]);
            return ['id'=>$sid,'duplicate'=>false];
        });
    }
    private function sameOrFail(array $old,int $pid,string $lang,string $source): array {
        if ((int)$old['problem_id']!==$pid || $old['language']!==$lang || !hash_equals($old['source_code'],$source))
            throw new InvalidArgumentException('Khóa yêu cầu đã được dùng cho nội dung khác');
        return ['id'=>(int)$old['id'],'duplicate'=>true];
    }
    public function rejudge(int $submissionId,int $suiteId): int {
        return tx($this->c,function() use($submissionId,$suiteId) {
            $s=one($this->c,'SELECT * FROM submissions WHERE id=$1 FOR UPDATE',[$submissionId]);
            if (!$s) throw new InvalidArgumentException('Không có bài nộp');
            $suite=one($this->c,'SELECT * FROM test_suite_versions WHERE id=$1 AND problem_id=$2 AND problem_version_id=$3',
                [$suiteId,$s['problem_id'],$s['problem_version_id']]);
            if (!$suite) throw new InvalidArgumentException('Phiên bản bộ chấm không phù hợp phiên bản đề');
            $last=one($this->c,'SELECT COALESCE(MAX(generation),0) AS n FROM judging_runs WHERE submission_id=$1',[$submissionId]);
            $r=one($this->c,'INSERT INTO judging_runs(submission_id,suite_version_id,generation) VALUES($1,$2,$3) RETURNING id',
                [$submissionId,$suiteId,(int)$last['n']+1]);
            queryParams($this->c,'UPDATE submissions SET latest_run_id=$1 WHERE id=$2',[$r['id'],$submissionId]);
            return (int)$r['id'];
        });
    }
    public function claim(): ?array {
        queryParams($this->c,"UPDATE judging_runs SET status='system_error',verdict='SYSTEM_ERROR',finished_at=now(),lease_token=NULL,lease_until=NULL
          WHERE attempts>=5 AND (status='queued' OR (status='running' AND lease_until<now()))");
        $token=bin2hex(random_bytes(24));
        // lease recovery; late old worker cannot commit because token changed.
        return one($this->c,"WITH next AS (
            SELECT id FROM judging_runs WHERE status='queued' OR (status='running' AND lease_until<now())
            ORDER BY id FOR UPDATE SKIP LOCKED LIMIT 1)
            UPDATE judging_runs j SET status='running', lease_token=$1,lease_until=now()+interval '90 seconds',attempts=attempts+1
            FROM next WHERE j.id=next.id AND j.attempts<5 RETURNING j.*",[$token]);
    }
    /** Renew lease between test cases: large suites must not be stolen mid-judge. */
    public function heartbeat(int $runId,string $token): bool {
        $result=queryParams($this->c,"UPDATE judging_runs SET lease_until=now()+interval '90 seconds'
          WHERE id=$1 AND status='running' AND lease_token=$2 AND lease_until>=now() RETURNING id",[$runId,$token]);
        return pg_num_rows($result)===1;
    }
    public function complete(int $runId,string $token,array $caseResults,string $verdict,bool $systemError=false): bool {
        if (!in_array($verdict,['AC','WA','CE','RE','TLE','SYSTEM_ERROR'],true)) throw new InvalidArgumentException('Verdict sai');
        return tx($this->c,function()use($runId,$token,$caseResults,$verdict,$systemError){
            $r=one($this->c,"SELECT id FROM judging_runs WHERE id=$1 AND status='running' AND lease_token=$2 AND lease_until>=now() FOR UPDATE",[$runId,$token]);
            if (!$r) return false;
            foreach ($caseResults as $x) queryParams($this->c,
                'INSERT INTO case_results(judging_run_id,test_case_id,verdict,elapsed_ms) VALUES($1,$2,$3,$4)',
                [$runId,$x['test_case_id'],$x['verdict'],$x['elapsed_ms']]);
            $passed=count(array_filter($caseResults,fn($x)=>$x['verdict']==='AC'));
            $total=one($this->c,'SELECT count(*) AS n FROM test_cases WHERE suite_version_id=(SELECT suite_version_id FROM judging_runs WHERE id=$1)',[$runId]);
            queryParams($this->c,"UPDATE judging_runs SET status=$1,verdict=$2,passed_count=$3,total_count=$4,finished_at=now(),lease_token=NULL,lease_until=NULL WHERE id=$5",
                [$systemError?'system_error':'done',$verdict,$passed,$total['n'],$runId]);
            // Do NOT update submissions latest_run_id: created during rejudge; old finish cannot clobber it.
            return true;
        });
    }
    public function judge(array $run,callable $executor): bool {
        $item=one($this->c,'SELECT s.source_code,s.language,pv.time_limit_ms,pv.memory_limit_mb
            FROM submissions s JOIN problem_versions pv ON pv.id=s.problem_version_id WHERE s.id=$1',[$run['submission_id']]);
        $cases=rows($this->c,'SELECT id,input_data,expected_output FROM test_cases WHERE suite_version_id=$1 ORDER BY position',[$run['suite_version_id']]);
        $results=[]; $labels=[];
        try {
            if (!$item || !$cases) throw new RuntimeException('Judge data unavailable');
            foreach ($cases as $tc) {
                // Stale workers must stop, not continue executing private tests.
                if (!$this->heartbeat((int)$run['id'],$run['lease_token'])) return false;
                $result=$executor($item['language'],$item['source_code'],$tc['input_data'],(int)$item['time_limit_ms'],(int)$item['memory_limit_mb']);
                if ($result['kind']==='SYSTEM_ERROR') throw new RuntimeException('Executor unavailable');
                $kind=$result['kind'];
                $verdict= $kind==='OK'
                    ? (Policy::equalOutput($result['stdout'],$tc['expected_output'])?'AC':'WA') : $kind;
                if (!in_array($verdict,['AC','WA','CE','RE','TLE'],true)) throw new RuntimeException('Unknown runtime result');
                $results[]=['test_case_id'=>(int)$tc['id'],'verdict'=>$verdict,'elapsed_ms'=>(int)$result['elapsed_ms']];
                $labels[]=$verdict;
                if ($verdict==='CE') break;
            }
            return $this->complete((int)$run['id'],$run['lease_token'],$results,Policy::overall($labels));
        } catch(Throwable $e) {
            error_log('Judge worker: run '.$run['id'].' executor failure: '.$e->getMessage());
            return $this->complete((int)$run['id'],$run['lease_token'],[], 'SYSTEM_ERROR',true);
        }
    }
}
