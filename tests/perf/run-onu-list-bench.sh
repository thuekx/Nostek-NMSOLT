#!/usr/bin/env bash
# NMS-07-E performance harness: throwaway PostgreSQL 16 (same digest as the stack),
# synthetic OLT snapshots, ONU list / global search / decode timings. Workspace only.
#   tests/perf/run-onu-list-bench.sh [olts] [onus_per_olt] [repeats]
set -euo pipefail

OLTS="${1:-3}"
PER_OLT="${2:-3400}"
REPEATS="${3:-5}"
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
NAME="nmsbench-pg-$$"
PORT="${BENCH_PG_PORT:-55432}"
IMAGE="postgres:16-alpine@sha256:deb28ee471183cb0c303113dac9b2433c51eeeeb678def7af9a9ec5820367815"
TMP="$(mktemp -d)"
chmod 700 "$TMP"

cleanup() { docker rm -f "$NAME" >/dev/null 2>&1 || true; rm -rf "$TMP"; }
trap cleanup EXIT

# Generated per run, kept in a 0600 file, never on a command line.
PW="$(head -c 24 /dev/urandom | base64 | tr -dc 'A-Za-z0-9')"
umask 077
printf 'POSTGRES_PASSWORD=%s\nPOSTGRES_DB=nms\n' "$PW" >"$TMP/pg.env"
printf 'APP_KEY=base64:%s\nDB_CONNECTION=pgsql\nDB_HOST=127.0.0.1\nDB_PORT=%s\nDB_DATABASE=nms\nDB_USERNAME=postgres\nDB_PASSWORD=%s\nCACHE_STORE=array\nQUEUE_CONNECTION=sync\nSESSION_DRIVER=array\nLOG_CHANNEL=stderr\n' \
  "$(head -c 32 /dev/urandom | base64)" "$PORT" "$PW" >"$TMP/app.env"

docker run -d --name "$NAME" --env-file "$TMP/pg.env" -p "127.0.0.1:${PORT}:5432" "$IMAGE" >/dev/null
for _ in $(seq 1 60); do
  docker exec "$NAME" pg_isready -U postgres -d nms >/dev/null 2>&1 && break
  sleep 1
done

run() { (set -a; . "$TMP/app.env"; set +a; cd "$ROOT" && "$@"); }

run php artisan migrate --force --no-interaction >/dev/null
run php tests/perf/onu_list_bench.php seed "$OLTS" "$PER_OLT"
docker exec "$NAME" psql -U postgres -d nms -Atc \
  "select 'SIZE snmp_olts=' || pg_size_pretty(pg_total_relation_size('snmp_olts'))"

for mode in decode list search-hit search-miss; do
  for i in $(seq 1 "$REPEATS"); do
    echo "PROGRESS ${mode} ${i}/${REPEATS}" >&2
    run php tests/perf/onu_list_bench.php "$mode" | tee -a "$TMP/bench.out"
  done
  # Median of the runs (ms) and the largest peak memory.
  grep "^BENCH ${mode} " "$TMP/bench.out" | sed -E 's/.* ms=([0-9.]+) peak_mb=([0-9.]+).*/\1 \2/' | sort -n |
    awk -v m="$mode" -v o="$OLTS" -v p="$PER_OLT" '{ms[NR]=$1; if ($2>mb) mb=$2} END {printf "RESULT perf_%s=MEASURED onus=%d median_ms=%.0f peak_mb=%.0f runs=%d\n", m, o*p, ms[int((NR+1)/2)], mb, NR}'
done
