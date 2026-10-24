<?php
require dirname(__DIR__).'/src/Database.php';
$pass=getenv('SEED_PASSWORD');
if (!$pass || strlen($pass)<10) {fwrite(STDERR,"Set SEED_PASSWORD with 10+ characters (local demo accounts only).\n");exit(1);}
$c=db();
try {
 tx($c,function()use($c,$pass){
  $users=[['student_a','Người học A','student'],['student_b','Người học B','student'],['lecturer','Giảng viên','teacher'],['admin','Quản trị viên','admin']];
  $ids=[];
  foreach($users as [$login,$name,$role]) {
    $row=one($c,'INSERT INTO users(username,display_name,role,password_hash) VALUES($1,$2,$3,$4)
      ON CONFLICT(username) DO UPDATE SET username=EXCLUDED.username RETURNING id',[$login,$name,$role,password_hash($pass,PASSWORD_DEFAULT)]);
    $ids[$login]=(int)$row['id'];
  }
  $class=one($c,'SELECT id FROM classrooms WHERE name=$1 AND teacher_id=$2',['CNTT - Lớp thực hành 01',$ids['lecturer']]);
  if(!$class)$class=one($c,'INSERT INTO classrooms(name,teacher_id) VALUES($1,$2) RETURNING id',['CNTT - Lớp thực hành 01',$ids['lecturer']]);
  foreach(['student_a','student_b'] as $student) queryParams($c,'INSERT INTO class_memberships(classroom_id,user_id) VALUES($1,$2) ON CONFLICT DO NOTHING',[$class['id'],$ids[$student]]);
  $seed=json_decode(file_get_contents(dirname(__DIR__).'/private_seed/seed.json'),true,512,JSON_THROW_ON_ERROR);
  foreach($seed as $p) {
    foreach(['topics'=>$p['topic'],'levels'=>$p['level']] as $table=>$name)
      queryParams($c,"INSERT INTO $table(name) VALUES($1) ON CONFLICT(name) DO NOTHING",[$name]);
    $topic=one($c,'SELECT id FROM topics WHERE name=$1',[$p['topic']]);
    $level=one($c,'SELECT id FROM levels WHERE name=$1',[$p['level']]);
    $exists=one($c,'SELECT id FROM problems WHERE slug=$1',[$p['slug']]);
    if($exists)continue;
    $prob=one($c,'INSERT INTO problems(topic_id,level_id,slug,title,created_by) VALUES($1,$2,$3,$4,$5) RETURNING id',
      [$topic['id'],$level['id'],$p['slug'],$p['title'],$ids['lecturer']]);
    $v=one($c,'INSERT INTO problem_versions(problem_id,version_no,statement,input_desc,output_desc,sample_input,sample_output,time_limit_ms,memory_limit_mb)
        VALUES($1,1,$2,$3,$4,$5,$6,$7,$8) RETURNING id',
      [$prob['id'],$p['statement'],$p['input_desc'],$p['output_desc'],$p['sample_input'],$p['sample_output'],$p['time_limit_ms'],$p['memory_limit_mb']]);
    $suite=one($c,'INSERT INTO test_suite_versions(problem_id,problem_version_id,version_no,created_by)
        VALUES($1,$2,1,$3) RETURNING id',[$prob['id'],$v['id'],$ids['lecturer']]);
    foreach($p['tests'] as $i=>$t) queryParams($c,'INSERT INTO test_cases(suite_version_id,position,input_data,expected_output,is_public)
       VALUES($1,$2,$3,$4,$5)',[$suite['id'],$i+1,$t['input'],$t['output'],($t['public']??false)?'true':'false']);
    queryParams($c,'UPDATE problems SET current_version_id=$1,current_suite_id=$2,published=true WHERE id=$3',[$v['id'],$suite['id'],$prob['id']]);
  }
 });
 echo "SEED_OK: 10 exercises, 71 test cases; accounts student_a, student_b, lecturer, admin.\n";
} catch(Throwable $e) {fwrite(STDERR,'SEED_FAILED: '.$e->getMessage()."\n");exit(1);}
