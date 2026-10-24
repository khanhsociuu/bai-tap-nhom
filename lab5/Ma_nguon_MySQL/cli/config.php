<?php
if (PHP_SAPI!=='cli') exit(1);
$dir=dirname(__DIR__).'/.local';
if (!is_dir($dir)) mkdir($dir,0700,true);
$config=[];
foreach (['host'=>'127.0.0.1','port'=>'5432','dbname'=>'dnu_judge_lab5','user'=>'dnu_judge','password'=>''] as $key=>$default) {
    echo ($key==='password') ? "password [hidden input]: " : "$key [$default]: ";
    if ($key==='password' && function_exists('stream_isatty') && stream_isatty(STDIN)) {
        system('stty -echo');
        try { $value=trim((string)fgets(STDIN)); }
        finally { system('stty echo'); echo "\n"; }
    } else $value=trim((string)fgets(STDIN));
    $config[$key]=$value!==''?$value:$default;
}
foreach (['host'=>'PGHOST','port'=>'PGPORT','dbname'=>'PGDATABASE','user'=>'PGUSER','password'=>'PGPASSWORD'] as $a=>$b) putenv($b.'='.$config[$a]);
try { require dirname(__DIR__).'/src/Database.php'; db(); }
catch(Throwable $e) {fwrite(STDERR,"Connection failed: ".$e->getMessage()."\n");exit(1);}
file_put_contents($dir.'/db.json',json_encode($config,JSON_PRETTY_PRINT)); chmod($dir.'/db.json',0600);
echo "Saved .local/db.json (ignored by Git)\n";
