<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/Database.php';
if(PHP_SAPI!=='cli')exit(1);
$errors=0;
function diag(string $label,bool $ok,string $note=''):void {
 global $errors;
 if (!$ok)$errors++;
 echo ($ok?'OK  ':'FAIL')." $label".($note!==''?' - '.$note:'')."\n";
}
echo "DNU JUDGE / local environment diagnosis\n";
diag('PHP CLI >= 8.1',PHP_VERSION_ID>=80100,PHP_VERSION);
diag('pgsql extension',extension_loaded('pgsql'));
diag('proc_open',function_exists('proc_open'));
try {
 $db=db();
 $info=one($db,'SELECT current_database() AS db,current_user AS usr');
 diag('PostgreSQL',true,$info['db'].' as '.$info['usr']);
 foreach(['users','problems','test_cases','submissions','judging_runs','trial_runs'] as $table){
  $present=one($db,'SELECT to_regclass($1) AS name',['public.'.$table]);
  diag('table '.$table,$present['name']!==null);
 }
 $counts=one($db,'SELECT (SELECT count(*) FROM problems) AS problems,(SELECT count(*) FROM test_cases) AS tests');
 echo 'DATA problems='.$counts['problems'].' test_cases='.$counts['tests']."\n";
 foreach(['judging_runs','trial_runs'] as $tbl){
  $r=rows($db,'SELECT status,count(*) AS n FROM '.$tbl.' GROUP BY status ORDER BY status');
  echo 'QUEUE '.$tbl.': '.implode(' ',array_map(fn($x)=>$x['status'].'='.$x['n'],$r))."\n";
 }
}catch(Throwable $e){diag('PostgreSQL',false,$e->getMessage());}
function imageReady(string $image):bool {
 try {
  $p=proc_open(['docker','image','inspect',$image],
   [0=>['file','/dev/null','r'],1=>['file','/dev/null','w'],2=>['file','/dev/null','w']],$pipes);
  return is_resource($p)&&proc_close($p)===0;
 }catch(Throwable $e){return false;}
}
$pythonReady=imageReady('python:3.12-alpine');
diag('Docker image python:3.12-alpine',$pythonReady,$pythonReady?'ready':'run bash lab.sh images');
$cppReady=imageReady('dnu-judge-cpp:1')||imageReady('gcc:14');
diag('Docker C++ image (dnu-judge-cpp:1 OR gcc:14)',$cppReady,$cppReady?'ready':'run bash lab.sh images');
$runtime=rtrim(sys_get_temp_dir(),'/');
diag('Linux runtime temp',is_dir($runtime)&&is_writable($runtime),$runtime);
echo "DOCTOR: ".($errors?'FAILURES='.$errors:'ALL BASIC CHECKS PASSED')."\n";
exit($errors?1:0);
