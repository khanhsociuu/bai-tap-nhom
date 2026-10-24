<?php
require dirname(__DIR__).'/src/Database.php';
$c=db();
$command=['psql','-h',getenv('PGHOST'),'-p',getenv('PGPORT')?:'5432','-U',getenv('PGUSER'),'-d',getenv('PGDATABASE')];
$descriptors=[0=>STDIN,1=>STDOUT,2=>STDERR];
$p=proc_open($command,$descriptors,$pipes,null,['PGPASSWORD'=>getenv('PGPASSWORD')?:'']);
if(!is_resource($p)) exit(1);
exit(proc_close($p));
