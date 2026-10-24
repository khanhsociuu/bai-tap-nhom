<?php
require dirname(__DIR__).'/src/JudgeService.php';
require dirname(__DIR__).'/src/Runner.php';
require dirname(__DIR__).'/src/TrialService.php';
if (PHP_SAPI!=='cli') exit(1);
$once=in_array('--once',$argv,true);
$worker=new JudgeService(db());
$runner=new Runner();
$trials=new TrialService(db());
while (true) {
    $run=$worker->claim();
    if ($run) {
        echo 'Judging run #'.$run['id'].PHP_EOL;
        $ok=$worker->judge($run,[$runner,'execute']);
        echo $ok ? "Recorded\n":"Stale result rejected\n";
    } else {
        $trial=$trials->claim();
        if ($trial) {
            echo 'Trying #'.$trial['id'].PHP_EOL;
            echo $trials->process($trial,[$runner,'execute']) ? "Trial recorded\n":"Trial stale\n";
        } elseif ($once) echo "No queued jobs\n";
    }
    if ($once) break;
    if (!$run && !$trial) sleep(2);
}
