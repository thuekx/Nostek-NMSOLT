#!/usr/bin/env bash
# NMS-07-E performance harness (alarm page, alarm engine, optical RX charts): throwaway PostgreSQL 16 (same digest as the stack),
# synthetic alarms and RX history. Workspace only.
#   tests/perf/run-ops-bench.sh [onus] [rx_onus] [cleared_alarms] [repeats]
set -euo pipefail

ONUS="${1:-3400}"
RX_ONUS="${2:-1000}"
CLEARED="${3:-200000}"
REPEATS="${4:-5}"
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
NAME="nmsbench-ops-pg-$$"
PORT="${BENCH_PG_PORT:-55433}"
IMAGE="postgres:16-alpine@sha256:deb28ee471183cb0c303113dac9b2433c51eeeeb678def7af9a9ec5820367815"
TMP="$(mktemp -d)"
chmod 700 "$TMP"

cleanup() { docker rm -fv "$NAME" >/dev/null 2>&1 || true; rm -rf "$TMP"; }
trap cleanup EXIT

# Generated per run, kept in a 0600 file, never on a command line.
PW="$(head -c 24 /dev/urandom | base64 | tr -dc 'A-Za-z0-9')"
umask 077
printf 'POSTGRES_PASSWORD=%s\nPOSTGRES_DB=nms\n' "$PW" >"$TMP/pg.env"
printf 'BENCH_SLOW_MS=%s\nAPP_KEY=base64:%s\nDB_CONNECTION=pgsql\nDB_HOST=127.0.0.1\nDB_PORT=%s\nDB_DATABASE=nms\nDB_USERNAME=postgres\nDB_PASSWORD=%s\nCACHE_STORE=array\nQUEUE_CONNECTION=sync\nSESSION_DRIVER=array\nLOG_CHANNEL=stderr\n' \
  "${BENCH_SLOW_MS:-0}" "$(head -c 32 /dev/urandom | base64)" "$PORT" "$PW" >"$TMP/app.env"

docker run -d --name "$NAME" --env-file "$TMP/pg.env" -p "127.0.0.1:${PORT}:5432" "$IMAGE" >/dev/null
# The image starts a temporary server for initdb, then restarts: pg_isready alone can answer
# during the first one (seen as "server closed the connection unexpectedly"). Wait for the
# second "ready" line.
for _ in $(seq 1 90); do
  [ "$(docker logs "$NAME" 2>&1 | grep -c 'ready to accept connections')" -ge 2 ] &&
    docker exec "$NAME" pg_isready -U postgres -d nms >/dev/null 2>&1 && break
  sleep 1
done

run() { (set -a; . "$TMP/app.env"; set +a; cd "$ROOT" && "$@"); }

run php artisan migrate --force --no-interaction >/dev/null
# BENCH_NO_ALARM_INDEX=1 measures the alarm page as it was before the NMS-07-E3 indexes.
if [ "${BENCH_NO_ALARM_INDEX:-0}" = 1 ]; then
  docker exec "$NAME" psql -U postgres -d nms -qc \
    "drop index if exists alarm_events_status_last_seen_idx; drop index if exists alarm_events_last_seen_idx"
fi
run php tests/perf/ops_bench.php seed "$ONUS" "$RX_ONUS" "$CLEARED" || { echo "RESULT perf_seed=FAIL"; exit 1; }
docker exec "$NAME" psql -U postgres -d nms -Atc \
  "select 'SIZE ' || relname || '=' || pg_size_pretty(pg_total_relation_size(oid)) from pg_class where relname in ('alarm_events','onu_rx_samples','onu_rx_hourly','onu_rx_daily') order by relname"

# BENCH_MODES="a b" limits the run to those modes.
for mode in ${BENCH_MODES:-alarm-page alarm-page-all alarm-eval-steady alarm-eval-burst rx-24h rx-7d rx-30d rx-90d rx-1y rx-3y}; do
  for i in $(seq 1 "$REPEATS"); do
    echo "PROGRESS ${mode} ${i}/${REPEATS}" >&2
    run php tests/perf/ops_bench.php "$mode" | tee -a "$TMP/bench.out"
  done
  # Median of the runs (ms) and the largest peak memory.
  grep "^BENCH ${mode} " "$TMP/bench.out" | sed -E 's/.* ms=([0-9.]+) peak_mb=([0-9.]+).*/\1 \2/' | sort -n |
    awk -v m="$mode" '{ms[NR]=$1; if ($2>mb) mb=$2} END {printf "RESULT perf_%s=MEASURED median_ms=%.0f peak_mb=%.0f runs=%d\n", m, ms[int((NR+1)/2)], mb, NR}'
done
