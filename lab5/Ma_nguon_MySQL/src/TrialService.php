<?php
declare(strict_types=1);
require_once __DIR__.'/Database.php';
require_once __DIR__.'/Policy.php';
final class TrialService {
    public function __construct(private PgSql\Connection $c) {}
    public function create(int $userId,int $problemId,string $lang,string $src,string $stdin): int {
        Policy::checkSource($lang,$src);
        if (strlen($stdin)>16384) throw new InvalidArgumentException('Đầu vào chạy thử tối đa 16 KiB');
        $p=one($this->c,'SELECT id,current_version_id FROM problems WHERE id=$1 AND published=true',[$problemId]);
        if (!$p) throw new InvalidArgumentException('Bài tập không tồn tại');
        $cnt=one($this->c,"SELECT COUNT(*) AS n FROM trial_runs WHERE user_id=$1 AND created_at>now()-interval '1 hour'",[$userId]);
        if ((int)$cnt['n']>=20) throw new InvalidArgumentException('Giới hạn 20 lần chạy thử mỗi giờ');
        $r=one($this->c,'INSERT INTO trial_runs(user_id,problem_id,problem_version_id,language,source_code,stdin_data) VALUES($1,$2,$3,$4,$5,$6) RETURNING id',[$userId,$problemId,$p['current_version_id'],$lang,$src,$stdin]);
        return (int)$r['id'];
    }
    public function claim(): ?array {
        queryParams($this->c,"UPDATE trial_runs SET status='system_error',result_kind='SYSTEM_ERROR',lease_token=NULL,lease_until=NULL
         WHERE attempts>=5 AND (status='queued' OR (status='running' AND lease_until<now()))");
        return one($this->c,"WITH next AS (
            SELECT id FROM trial_runs WHERE status='queued' OR (status='running' AND lease_until<now())
            ORDER BY id FOR UPDATE SKIP LOCKED LIMIT 1)
            UPDATE trial_runs t SET status='running',lease_token=$1,lease_until=now()+interval '90 seconds',attempts=attempts+1
            FROM next WHERE t.id=next.id AND t.attempts<5 RETURNING t.*",[bin2hex(random_bytes(24))]);
    }
    public function process(array $trial,callable $executor): bool {
        $r=one($this->c,'SELECT pv.time_limit_ms,pv.memory_limit_mb FROM problem_versions pv WHERE pv.id=$1',[$trial['problem_version_id']]);
        try {
            if (!$r) throw new RuntimeException('Missing problem version');
            $x=$executor($trial['language'],$trial['source_code'],$trial['stdin_data'],(int)$r['time_limit_ms'],(int)$r['memory_limit_mb']);
            $kind=$x['kind'];
            if (!in_array($kind,['OK','CE','RE','TLE','SYSTEM_ERROR'],true)) $kind='SYSTEM_ERROR';
        } catch(Throwable $e) {$x=[];$kind='SYSTEM_ERROR';}
        $done=queryParams($this->c,"UPDATE trial_runs SET status=$1,result_kind=$2,stdout_data=$3,stderr_data=$4,elapsed_ms=$5,lease_token=NULL,lease_until=NULL
          WHERE id=$6 AND status='running' AND lease_token=$7 AND lease_until>=now() RETURNING id",
          [$kind==='SYSTEM_ERROR'?'system_error':'done',$kind,substr($x['stdout']??'',0,8192),substr($x['stderr']??'',0,8192),$x['elapsed_ms']??0,$trial['id'],$trial['lease_token']]);
        return pg_num_rows($done)===1;
    }
}
