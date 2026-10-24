<?php
require dirname(__DIR__).'/src/Policy.php';
$tested=0;$failed=0;
function check(string $label,bool $pass):void {
 global $tested,$failed;$tested++;
 if(!$pass){$failed++;echo "FAIL: $label\n";}
}
check('Windows line endings',Policy::equalOutput("3\r\n","3\n"));
check('Trailing whitespace',Policy::equalOutput("3   \n\n","3\n"));
check('Wrong answer',!Policy::equalOutput("3\n","4\n"));
check('Case sensitive',!Policy::equalOutput("YES\n","yes\n"));
check('AC',Policy::overall(['AC','AC'])==='AC');
check('WA',Policy::overall(['AC','WA'])==='WA');
check('CE',Policy::overall(['CE'])==='CE');
check('RE',Policy::overall(['AC','RE'])==='RE');
check('TLE',Policy::overall(['TLE','WA'])==='TLE');
check('Priority',Policy::overall(['RE','CE'])==='CE');
foreach(['python','cpp'] as $lang) {
 try{Policy::checkSource($lang,'print(5)');check('valid '.$lang,true);}catch(Throwable){check('valid '.$lang,false);}
}
foreach([['java','class A {}'],['python',''],['cpp',str_repeat('a',65537)],['python',"abc\0def"]] as [$l,$code]){
 try{Policy::checkSource($l,$code);check('reject invalid',false);}catch(InvalidArgumentException){check('reject invalid',true);}
}
$public=Policy::publicRun(['id'=>5,'status'=>'done','verdict'=>'AC','lease_token'=>'SECRET','expected_output'=>'SECRET','input_data'=>'SECRET','stdout_data'=>'SECRET']);
check('Status allowed',$public['status']==='done');
check('Hidden fields filtered',!isset($public['input_data'])&&!isset($public['lease_token'])&&!isset($public['expected_output'])&&!isset($public['stdout_data']));
$data=json_decode(file_get_contents(dirname(__DIR__).'/private_seed/seed.json'),true,512,JSON_THROW_ON_ERROR);
check('10 authored problems',count($data)===10);
check('At least 5 each',count(array_filter($data,fn($p)=>count($p['tests'])>=5))===10);
check('71 hidden tests',array_sum(array_map(fn($p)=>count($p['tests']),$data))===71);
check('All 40 source files',count(glob(dirname(__DIR__).'/private_seed/samples/*/*.py'))===20 && count(glob(dirname(__DIR__).'/private_seed/samples/*/*.cpp'))===20);
echo "UNIT: TESTS=$tested FAILED=$failed\n";
exit($failed?1:0);
