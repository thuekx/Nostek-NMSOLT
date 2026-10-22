#!/usr/bin/env bash
# NMS-07-E4 performance harness: the Go SNMP poller (cmd/nos-snmp-poller) against a simulated
# ZTE C300 agent (snmpsim, synthetic data) behind a UDP proxy that adds a round-trip time and
# counts requests. Workspace only; no real OLT.
#   tests/perf/run-poller-bench.sh [onus] [repeats]
# BENCH_MAXREP="10 25", BENCH_RTT_MS="0 20" and BENCH_TRAFFIC="0 1" choose the matrix. SNMPSIM_BIN may point to an
# installed snmpsim-command-responder; otherwise a pinned snmpsim is installed in a temp venv.
set -euo pipefail

ONUS="${1:-3400}"
REPEATS="${2:-3}"
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
AGENT_PORT="${BENCH_AGENT_PORT:-16161}"
PROXY_PORT="${BENCH_PROXY_PORT:-16162}"
TMP="$(mktemp -d)"
PIDS=()

cleanup() {
  for pid in "${PIDS[@]}"; do kill "$pid" >/dev/null 2>&1 || true; done
  rm -rf "$TMP"
}
trap cleanup EXIT

if [ -z "${SNMPSIM_BIN:-}" ]; then
  echo "PROGRESS installing snmpsim" >&2
  python3 -m venv "$TMP/venv" >/dev/null
  "$TMP/venv/bin/pip" install -q 'snmpsim-lextudio==1.1.1' >/dev/null
  SNMPSIM_BIN="$TMP/venv/bin/snmpsim-command-responder"
fi

(cd "$ROOT" && go build -mod=mod -o "$TMP/nos-snmp-poller" ./cmd/nos-snmp-poller)

# snmpsim refuses to run as root and must read the data as the unprivileged user. The data
# file name is the SNMP community; the value is synthetic and public.
chmod 755 "$TMP"
mkdir -p "$TMP/data" "$TMP/cache"
python3 "$ROOT/tests/perf/snmp/gen_c300_snmprec.py" "$ONUS" "$TMP/data/bench.snmprec"
chmod -R a+rX "$TMP/data"
RUN_AS=()
if [ "$(id -u)" = 0 ]; then
  chown nobody "$TMP/cache"
  RUN_AS=(--process-user=nobody --process-group="$(id -gn nobody)")
fi
"$SNMPSIM_BIN" --data-dir="$TMP/data" --cache-dir="$TMP/cache" "${RUN_AS[@]}" \
  --agent-udpv4-endpoint="127.0.0.1:${AGENT_PORT}" >"$TMP/agent.log" 2>&1 &
PIDS+=("$!")
for _ in $(seq 1 120); do
  grep -q 'Listening at UDP/IPv4' "$TMP/agent.log" && break
  sleep 1
done

TIMEFORMAT='%R %U %S'
for rtt in ${BENCH_RTT_MS:-0 20}; do
  python3 "$ROOT/tests/perf/snmp/udp_delay_proxy.py" "$PROXY_PORT" "$AGENT_PORT" "$rtt" "$TMP/count" &
  PIDS+=("$!")
  sleep 1
  for maxrep in ${BENCH_MAXREP:-10 25}; do
  # TRF-1: BENCH_TRAFFIC="0 1" measures the cost of the PON octet-counter GETs (--include-traffic).
  for traffic in ${BENCH_TRAFFIC:-0}; do
    extra=()
    [ "$traffic" = 1 ] && extra=(--include-traffic)
    for i in $(seq 1 "$REPEATS"); do
      echo "PROGRESS rtt=${rtt}ms maxrep=${maxrep} traffic=${traffic} ${i}/${REPEATS}" >&2
      before="$(cat "$TMP/count")"
      t="$( { time NOS_SNMP_COMMUNITY=bench "$TMP/nos-snmp-poller" --host 127.0.0.1 --port "$PROXY_PORT" \
        --include-rx "${extra[@]}" --walk-mode bulk --max-repetitions "$maxrep" --timeout 10s >"$TMP/poll.json"; } 2>&1 )"
      onus="$(python3 -c 'import json,sys; d=json.load(open(sys.argv[1])); print(len(d["onus"]) if d["ok"] else -1)' "$TMP/poll.json")"
      ports="$(python3 -c 'import json,sys; d=json.load(open(sys.argv[1])); print((d.get("traffic") or {}).get("count", 0))' "$TMP/poll.json")"
      read -r real user sys <<<"$t"
      echo "BENCH rtt=${rtt} maxrep=${maxrep} traffic=${traffic} wall_s=${real} cpu_s=$(awk -v u="$user" -v s="$sys" 'BEGIN {printf "%.2f", u + s}') requests=$(( $(cat "$TMP/count") - before )) onus=${onus} traffic_ports=${ports}" | tee -a "$TMP/bench.out"
    done
    grep "^BENCH rtt=${rtt} maxrep=${maxrep} traffic=${traffic} " "$TMP/bench.out" |
      sed -E 's/.*wall_s=([0-9.]+) cpu_s=([0-9.]+) requests=([0-9]+) onus=(-?[0-9]+) traffic_ports=([0-9]+)/\1 \2 \3 \4 \5/' | sort -n |
      awk -v r="$rtt" -v m="$maxrep" -v tr="$traffic" '{w[NR]=$1; c[NR]=$2; q=$3; o=$4; p=$5} END {printf "RESULT perf_poller_rtt%sms_maxrep%s_traffic%s=MEASURED onus=%d traffic_ports=%d median_wall_s=%.1f poller_cpu_s=%.2f requests=%d runs=%d\n", r, m, tr, o, p, w[int((NR+1)/2)], c[int((NR+1)/2)], q, NR}'
  done
  done
  kill "${PIDS[-1]}" >/dev/null 2>&1 || true
  unset 'PIDS[-1]'
done
