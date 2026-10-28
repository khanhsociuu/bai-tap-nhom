#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT"
case "${1:-help}" in
  check)
    php -v | head -n 1
    php -m | grep -qi '^pgsql$' || { echo 'MISSING php-pgsql'; exit 1; }
    psql --version
    docker --version
    php -r 'require "src/Database.php"; try { db(); echo "DATABASE_OK\n"; } catch (Throwable $e) { fwrite(STDERR,$e->getMessage()."\n"); exit(1); }'
    ;;
  diagnose|doctor) php cli/doctor.php ;;
  images)
    if ! docker image inspect python:3.12-alpine >/dev/null 2>&1; then
      docker pull python:3.12-alpine
    fi
    if docker image inspect dnu-judge-cpp:1 >/dev/null 2>&1; then
      echo 'C++ local image already available: dnu-judge-cpp:1'
    elif docker image inspect gcc:14 >/dev/null 2>&1; then
      echo 'Official GCC image already available: gcc:14'
    else
      echo 'Building smaller local C++ image based on Alpine (requires network for apk packages)...'
      docker build --pull -f "$ROOT/docker/cpp.Dockerfile" -t dnu-judge-cpp:1 "$ROOT/docker" || {
        echo 'C++ image build failed. Check Docker network; you can also try: docker pull gcc:14' >&2
        exit 1
      }
    fi
    ;;
  db-config) php cli/config.php ;;
  schema) php -r 'require "src/Database.php"; $c=db(); $sql=file_get_contents("database/schema.sql"); if(!pg_query($c,$sql)){exit(1);} echo "SCHEMA_OK\n";' ;;
  seed) php cli/seed.php ;;
  sql) php cli/psql.php ;;
  run)
    # Absolute paths prevent "Failed opening required public/router.php".
    exec php -d display_errors=Off -d log_errors=On -d error_log=/dev/stderr \
      -S 127.0.0.1:8105 -t "$ROOT/public" "$ROOT/public/router.php"
    ;;
  worker) php cli/worker.php ;;
  worker-once) php cli/worker.php --once ;;
  examples) python3 tests/samples.py ;;
  supervisor-test) python3 tests/supervisor_check.py ;;
  smoke) php tests/docker_smoke.php ;;
  test) php tests/unit.php && php tests/runner_contract.php && python3 tests/supervisor_check.py && php tests/integration.php ;;
  extend) php tests/unit.php && php tests/runner_contract.php && python3 tests/supervisor_check.py && php tests/integration.php ;;
  *) echo 'Usage: bash lab.sh {db-config|check|doctor|images|schema|seed|sql|run|worker|worker-once|examples|smoke|supervisor-test|test|extend}' ;;
esac
