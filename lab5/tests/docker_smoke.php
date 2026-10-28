<?php
declare(strict_types=1);
/** Optional true Docker smoke tests. Run manually, outside public/web. */
require dirname(__DIR__).'/src/Runner.php';
require dirname(__DIR__).'/src/Policy.php';
if (PHP_SAPI!=='cli') exit(2);
if (!trim((string)shell_exec('command -v docker 2>/dev/null'))) {
    fwrite(STDERR,"DOCKER_SMOKE: NOT_RUN (docker not installed or not in PATH)\n");exit(2);
}
$cppImage = getenv('DNU_CPP_IMAGE') ?: null;
if (!$cppImage) {
    foreach (['dnu-judge-cpp:1','gcc:14'] as $candidate) {
        $p = @proc_open(['docker','image','inspect',$candidate],
            [0=>['file','/dev/null','r'],1=>['file','/dev/null','w'],2=>['file','/dev/null','w']],$pipes);
        if (is_resource($p) && proc_close($p)===0) { $cppImage=$candidate; break; }
    }
}
if ($cppImage) putenv('DNU_CPP_IMAGE='.$cppImage);
$runner=new Runner();$passed=0;$failed=0;
function smoke(string $label,string $expected,array $r):void {
    global $passed,$failed;
    $actual=$r['kind'];
    if ($actual===$expected && ($expected!=='OK'||Policy::equalOutput($r['stdout'],"5\n"))) {
        echo "PASS: $label ($actual)\n";$passed++;
    } else {
        echo "FAIL: $label expected=$expected actual=$actual\n";$failed++;
    }
}
smoke('Python correct','OK',$runner->execute('python','a,b=map(int,input().split());print(a+b)',"2 3\n",3500,128));
smoke('Python syntax CE','CE',$runner->execute('python','print(',"",3500,128));
smoke('Python runtime RE','RE',$runner->execute('python','1/0',"",3500,128));
smoke('Python infinite loop TLE','TLE',$runner->execute('python','while True: pass',"",500,128));
if (!$cppImage) {
    fwrite(STDERR,"C++ image MISSING. Run: bash lab.sh images\n");
    $failed++;
} else {
smoke('C++ correct','OK',$runner->execute('cpp',"#include <iostream>\nint main(){int a,b;std::cin>>a>>b;std::cout<<a+b<<'\\n';}","2 3\n",3500,128));
smoke('C++ compile CE','CE',$runner->execute('cpp','int main( {',"",3500,128));
}
echo "DOCKER_SMOKE: PASS=$passed FAIL=$failed\n";
exit($failed?1:0);
