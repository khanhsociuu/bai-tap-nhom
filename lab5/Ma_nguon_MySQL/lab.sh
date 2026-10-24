#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"
case "${1:-help}" in
  check)
    php -v | head -n 1
    php -m | grep -qi '^pgsql$' || { echo 'MISSING php-pgsql'; exit 1; }
    psql --version
    docker --version
    php -r 'require "src/Database.php"; try { db(); echo "DATABASE_OK\n"; } catch (Throwable $e) { fwrite(STDERR,$e->getMessage()."\n"); exit(1); }'
    ;;
  db-config) php cli/config.php ;;
  schema) php -r 'require "src/Database.php"; $c=db(); $sql=file_get_contents("database/schema.sql"); if(!pg_query($c,$sql)){exit(1);} echo "SCHEMA_OK\n";' ;;
  seed) php cli/seed.php ;;
  sql) php cli/psql.php ;;
  run) php -S 127.0.0.1:8105 -t public public/router.php ;;
  worker) php cli/worker.php ;;
  worker-once) php cli/worker.php --once ;;
  examples) python3 tests/samples.py ;;
  test) php tests/unit.php && php tests/integration.php ;;
  extend) php tests/unit.php && php tests/integration.php ;;
  *) echo 'Usage: bash lab.sh {db-config|check|schema|seed|sql|run|worker|worker-once|examples|test|extend}' ;;
esac
