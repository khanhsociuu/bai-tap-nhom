<?php
declare(strict_types=1);
/** Tests the Runner/CLI contract using a stub Docker command; NO Docker daemon involved. */
require dirname(__DIR__).'/src/Runner.php';
if(PHP_SAPI!=='cli')exit(2);
$mock=sys_get_temp_dir().'/dnu-docker-stub-'.bin2hex(random_bytes(5));
if(!mkdir($mock,0700))exit(2);
$script=<<<'PY'
#!/usr/bin/env python3
import os,sys
args=sys.argv[1:]
if len(args)<2 or args[0]!='run' or '--network' not in args or args[args.index('--network')+1]!='none' or '--pull' not in args:
    sys.exit(125)
volumes=[]
for i,s in enumerate(args):
    if s=='-v': volumes.append(args[i+1])
work=[x.split(':/work:')[0] for x in volumes if ':/work:' in x]
out=[x.split(':/out:')[0] for x in volumes if ':/out:' in x]
if not work or not out or not work[0].startswith('/tmp/'):
    sys.exit(125)
command=args[-1]
if 'g++' in command:
    src=open(os.path.join(work[0],'main.cpp')).read()
    if 'main(' in src and 'main( {' in src:
        print('error: expected syntax',file=sys.stderr);sys.exit(1)
    with open(os.path.join(out[0],'main'),'w') as fh:fh.write('compiled')
    sys.exit(0)
if 'timeout' not in command and 'supervise.py' not in command:sys.exit(125)
if 'main.py' in command or 'supervise.py' in command:
    source=open(os.path.join(work[0],'main.py')).read()
    if 'FAKE_INFRA' in source:sys.exit(125)
    if 'FAKE_OOM' in source:sys.exit(137)
    if 'while True' in source:sys.exit(124)
    if 'print(' in source and source.count('(')!=source.count(')'):
        print('SyntaxError: invalid syntax',file=sys.stderr);sys.exit(1)
    if '1/0' in source:
        print('ZeroDivisionError: division by zero',file=sys.stderr);sys.exit(1)
print('5')
PY;
file_put_contents($mock.'/docker',$script);chmod($mock.'/docker',0700);
$old=getenv('PATH');putenv('PATH='.$mock.':'.$old);
$test=0;$fail=0;
function verify(string $label,array $result,string $kind,?string $out=null):void {
    global $test,$fail;$test++;
    $ok=$result['kind']===$kind && ($out===null || trim($result['stdout'])===$out);
    if(!$ok){$fail++;echo "FAIL $label expected=$kind got={$result['kind']}\n";}
}
try{
 $runner=new Runner();
 verify('python correct',$runner->execute('python','print(5)',"2 3\n",2000,128),'OK','5');
 verify('python syntax',$runner->execute('python','print(',"",2000,128),'CE');
 verify('python runtime',$runner->execute('python','1/0',"",2000,128),'RE');
 verify('python timeout',$runner->execute('python','while True: pass',"",500,128),'TLE');
 verify('python infra',$runner->execute('python','FAKE_INFRA',"",2000,128),'SYSTEM_ERROR');
 verify('python exit 137 is NOT timeout',$runner->execute('python','FAKE_OOM',"",2000,128),'RE');
 verify('cpp correct',$runner->execute('cpp','int main() { return 0; }',"2 3\n",2000,128),'OK','5');
 verify('cpp syntax',$runner->execute('cpp','int main( {',"",2000,128),'CE');
 echo "RUNNER_MOCK: TESTS=$test FAILED=$fail (does not verify real Docker)\n";
}finally{
 putenv('PATH='.$old);unlink($mock.'/docker');rmdir($mock);
}
exit($fail?1:0);
