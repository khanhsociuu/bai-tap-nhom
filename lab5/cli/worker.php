<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/JudgeService.php';
require dirname(__DIR__).'/src/Runner.php';
require dirname(__DIR__).'/src/TrialService.php';
if (PHP_SAPI!=='cli') exit(1);
$once=in_array('--once',$argv,true);

/** Docker is used only by CLI worker, never by public PHP endpoints. */
function dockerHasImage(string $image): bool {
    try {
        $p=@proc_open(['docker','image','inspect',$image],
            [0=>['file','/dev/null','r'],1=>['file','/dev/null','w'],2=>['file','/dev/null','w']],$pipes);
        return is_resource($p) && proc_close($p)===0;
    } catch (Throwable $e) { return false; }
}
if (!dockerHasImage('python:3.12-alpine')) {
    fwrite(STDERR,"Missing Python Docker image. Run: bash lab.sh images\n");
    exit(2);
}
// Allow Python submissions to be processed while the much larger C++ image
// is being prepared. C++ returns SYSTEM_ERROR until an image is available.
$cppImage = dockerHasImage('dnu-judge-cpp:1') ? 'dnu-judge-cpp:1'
    : (dockerHasImage('gcc:14') ? 'gcc:14' : null);
if ($cppImage===null) {
    fwrite(STDERR,"WARNING: C++ image is missing; Python can still be judged. Run: bash lab.sh images\n");
} else {
    putenv('DNU_CPP_IMAGE='.$cppImage);
}
try {
    $c=db();$worker=new JudgeService($c);$trials=new TrialService($c);$runner=new Runner();
} catch(Throwable $e) {
    fwrite(STDERR,'Worker initialization failed: '.$e->getMessage()."\n");exit(2);
}
echo 'Worker READY - Docker, PostgreSQL, images available. Press Ctrl+C to stop.'.PHP_EOL;
$count=0;
while(true) {
    $run=null;$trial=null;
    try {
        // Alternate preference so queued compile jobs never starve interactive trials.
        if ($count++%2===0) $trial=$trials->claim();
        if (!$trial) $run=$worker->claim();
        if (!$run && !$trial) $trial=$trials->claim();
        if ($run) {
            echo 'Judging run #'.$run['id'].PHP_EOL;
            echo $worker->judge($run,[$runner,'execute']) ? "Recorded\n" : "Stale result rejected\n";
        } elseif ($trial) {
            echo 'Trying #'.$trial['id'].PHP_EOL;
            echo $trials->process($trial,[$runner,'execute']) ? "Trial recorded\n" : "Trial stale\n";
        } elseif ($once) echo "No queued jobs\n";
    } catch(Throwable $e) {
        fwrite(STDERR,'Worker database/queue error: '.$e->getMessage()."\n");
        if ($once) exit(2);
        sleep(3);
        try {
            $c=db();$worker=new JudgeService($c);$trials=new TrialService($c);
        } catch(Throwable $retry) {
            fwrite(STDERR,"PostgreSQL not ready; will retry.\n");
            sleep(3);
        }
    }
    if($once)break;
    if(!$run&&!$trial)sleep(2);
}
