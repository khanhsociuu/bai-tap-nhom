<?php
declare(strict_types=1);
function db(): PgSql\Connection {
    if (!extension_loaded('pgsql')) throw new RuntimeException('PGSQL_EXTENSION_MISSING');
    $configFile = dirname(__DIR__) . '/.local/db.json';
    if (is_file($configFile)) {
        $cfg = json_decode((string)file_get_contents($configFile), true, 512, JSON_THROW_ON_ERROR);
        foreach (['PGHOST'=>'host','PGPORT'=>'port','PGDATABASE'=>'dbname','PGUSER'=>'user','PGPASSWORD'=>'password'] as $key=>$prop) {
            if (!getenv($key) && isset($cfg[$prop])) putenv($key.'='.$cfg[$prop]);
        }
    }
    foreach (['PGHOST','PGDATABASE','PGUSER'] as $k) {
        if (!getenv($k)) throw new RuntimeException('DATABASE_CONFIG_MISSING');
    }
    $c = @pg_connect('connect_timeout=3');
    if ($c === false) throw new RuntimeException('DATABASE_UNAVAILABLE');
    return $c;
}
function queryParams(PgSql\Connection $c, string $sql, array $params=[]): PgSql\Result {
    $r = @pg_query_params($c,$sql,$params);
    if ($r === false) throw new RuntimeException('DATABASE_OPERATION_FAILED');
    return $r;
}
function rows(PgSql\Connection $c,string $sql,array $params=[]): array {
    return pg_fetch_all(queryParams($c,$sql,$params)) ?: [];
}
function one(PgSql\Connection $c,string $sql,array $params=[]): ?array {
    $r = rows($c,$sql,$params); return $r[0]??null;
}
function tx(PgSql\Connection $c,callable $task): mixed {
    queryParams($c,'BEGIN');
    try { $value=$task(); queryParams($c,'COMMIT'); return $value; }
    catch(Throwable $e) { @pg_query($c,'ROLLBACK'); throw $e; }
}
