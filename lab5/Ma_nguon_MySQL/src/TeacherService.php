<?php
declare(strict_types=1);
require_once __DIR__.'/Database.php';
function canManageProblem(PgSql\Connection $c,array $a,int $pid):bool {
 if($a['role']==='admin') return true;
 return $a['role']==='teacher' && one($c,'SELECT 1 FROM problems WHERE id=$1 AND created_by=$2',[$pid,$a['id']])!==null;
}
function validSuite(string $raw):array {
 $tests=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
 if (!is_array($tests) || !array_is_list($tests) || count($tests)<5 || count($tests)>50)
   throw new InvalidArgumentException('Cần một mảng JSON từ 5 đến 50 test case');
 $out=[];
 foreach($tests as $i=>$t) {
   if (!is_array($t)||!isset($t['input'],$t['output'])||!is_string($t['input'])||!is_string($t['output'])
    ||strlen($t['input'])>16384||strlen($t['output'])>16384)
       throw new InvalidArgumentException('Mỗi test cần input, output dạng chuỗi (tối đa 16 KiB)');
   $out[]=['input'=>$t['input'],'output'=>$t['output'],'public'=>(bool)($t['public']??false)];
 }
 return $out;
}
function writeCases(PgSql\Connection $c,int $suiteId,array $tests):void {
 foreach($tests as $i=>$t) queryParams($c,'INSERT INTO test_cases(suite_version_id,position,input_data,expected_output,is_public)
 VALUES($1,$2,$3,$4,$5)',[$suiteId,$i+1,$t['input'],$t['output'],$t['public']?'true':'false']);
}
function versionFields(array $post):array {
 $keys=['statement','input_desc','output_desc','sample_input','sample_output'];
 $val=[];
 foreach($keys as $key) {
  $v=(string)($post[$key]??'');
  if(strlen($v)>30000 || ($key!=='sample_input' && trim($v)==='')) throw new InvalidArgumentException('Thiếu hoặc quá dài: '.$key);
  $val[$key]=$v;
 }
 $val['time_limit_ms']=(int)($post['time_limit_ms']??1500);
 $val['memory_limit_mb']=(int)($post['memory_limit_mb']??128);
 if($val['time_limit_ms']<100||$val['time_limit_ms']>5000||$val['memory_limit_mb']<32||$val['memory_limit_mb']>256)
    throw new InvalidArgumentException('Giới hạn chạy không hợp lệ');
 return $val;
}
function createProblem(PgSql\Connection $c,array $a,array $post):int {
 $v=versionFields($post);$tests=validSuite((string)($post['tests']??''));
 $name=trim((string)($post['title']??''));$slug=trim((string)($post['slug']??''));
 if($name===''||strlen($name)>160||!preg_match('/^[a-z0-9_-]{3,120}$/',$slug)) throw new InvalidArgumentException('Tên hoặc slug không hợp lệ');
 return tx($c,function()use($c,$a,$post,$v,$tests,$name,$slug){
  $p=one($c,'INSERT INTO problems(topic_id,level_id,slug,title,published,created_by) VALUES($1,$2,$3,$4,false,$5) RETURNING id',
    [(int)$post['topic_id'],(int)$post['level_id'],$slug,$name,$a['id']]);
  $id=(int)$p['id'];
  $pv=one($c,'INSERT INTO problem_versions(problem_id,version_no,statement,input_desc,output_desc,sample_input,sample_output,time_limit_ms,memory_limit_mb)
   VALUES($1,1,$2,$3,$4,$5,$6,$7,$8) RETURNING id',[$id,$v['statement'],$v['input_desc'],$v['output_desc'],$v['sample_input'],$v['sample_output'],$v['time_limit_ms'],$v['memory_limit_mb']]);
  $sv=one($c,'INSERT INTO test_suite_versions(problem_id,problem_version_id,version_no,created_by) VALUES($1,$2,1,$3) RETURNING id',[$id,$pv['id'],$a['id']]);
  writeCases($c,(int)$sv['id'],$tests);
  queryParams($c,'UPDATE problems SET current_version_id=$1,current_suite_id=$2,published=true WHERE id=$3',[$pv['id'],$sv['id'],$id]);return $id;
 });
}
function createSuite(PgSql\Connection $c,array $a,int $id,string $testsRaw):int {
 if(!canManageProblem($c,$a,$id)) throw new InvalidArgumentException('Không có quyền');
 $tests=validSuite($testsRaw);
 return tx($c,function()use($c,$a,$id,$tests){
  $p=one($c,'SELECT current_version_id FROM problems WHERE id=$1 FOR UPDATE',[$id]);
  $n=one($c,'SELECT COALESCE(MAX(version_no),0)+1 AS n FROM test_suite_versions WHERE problem_id=$1',[$id]);
  $s=one($c,'INSERT INTO test_suite_versions(problem_id,problem_version_id,version_no,created_by) VALUES($1,$2,$3,$4) RETURNING id',
   [$id,$p['current_version_id'],$n['n'],$a['id']]);
  writeCases($c,(int)$s['id'],$tests);
  queryParams($c,'UPDATE problems SET current_suite_id=$1 WHERE id=$2',[$s['id'],$id]);
  return (int)$s['id'];
 });
}
function editVersion(PgSql\Connection $c,array $a,int $pid,array $post):int {
 if(!canManageProblem($c,$a,$pid)) throw new InvalidArgumentException('Không có quyền');
 $v=versionFields($post);
 return tx($c,function()use($c,$a,$pid,$post,$v){
    $p=one($c,'SELECT * FROM problems WHERE id=$1 FOR UPDATE',[$pid]);
    $n=one($c,'SELECT COALESCE(MAX(version_no),0)+1 AS n FROM problem_versions WHERE problem_id=$1',[$pid]);
    $pv=one($c,'INSERT INTO problem_versions(problem_id,version_no,statement,input_desc,output_desc,sample_input,sample_output,time_limit_ms,memory_limit_mb)
    VALUES($1,$2,$3,$4,$5,$6,$7,$8,$9) RETURNING id',[$pid,$n['n'],$v['statement'],$v['input_desc'],$v['output_desc'],$v['sample_input'],$v['sample_output'],$v['time_limit_ms'],$v['memory_limit_mb']]);
    $sn=one($c,'SELECT COALESCE(MAX(version_no),0)+1 AS n FROM test_suite_versions WHERE problem_id=$1',[$pid]);
    $s=one($c,'INSERT INTO test_suite_versions(problem_id,problem_version_id,version_no,created_by) VALUES($1,$2,$3,$4) RETURNING id',[$pid,$pv['id'],$sn['n'],$a['id']]);
    queryParams($c,'INSERT INTO test_cases(suite_version_id,position,input_data,expected_output,is_public)
    SELECT $1,position,input_data,expected_output,is_public FROM test_cases WHERE suite_version_id=$2',[$s['id'],$p['current_suite_id']]);
    queryParams($c,'UPDATE problems SET current_version_id=$1,current_suite_id=$2,title=$3,topic_id=$4,level_id=$5 WHERE id=$6',
      [$pv['id'],$s['id'],trim((string)$post['title']),$post['topic_id'],$post['level_id'],$pid]);
    return (int)$pv['id'];
 });
}
