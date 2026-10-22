# NMS-07-C/E — ONU read path: measured, and the `onu_state` table decision

**Date:** 2026-10-22 · **Branch:** `step/nms-07-c-perf-matrix` (on top of NMS-07-B) ·
**State:** measured on synthetic data in the workspace; no host or device action. NMS-07 stays IN PROGRESS.

## Question

Work package C planned an `onu_state` table (one row per ONU) to replace the per-OLT
JSON snapshot (`snmp_olts.last_test_result.port_onus`) for cross-OLT lists. The plan
said "with measured before/after". This is that measurement.

## Method

`tests/perf/run-onu-list-bench.sh [olts] [onus_per_olt] [repeats]`:

- throwaway PostgreSQL 16 container, same image digest as the stack;
- its password is generated per run and kept in a 0600 file, never on a command line;
- the real Laravel migrations;
- synthetic ZTE C300-shaped snapshots (64 ONUs per PON, all list fields plus snapshot-only
  detail, about 1.4 MB of JSON per 3,400 ONUs);
- each measurement in its own PHP process, so the peak memory is that path's alone.

It prints one `RESULT perf_<mode>=MEASURED … median_ms=… peak_mb=…` line per mode. PHP 8.4
CLI, no OPcache, workspace container: absolute numbers are for comparison, not a host SLA.

## Current code (snapshot path), median of 5 runs

| ONUs (OLTs) | ONU list `collect()` + JSON | list payload | search, hit | search, no match | snapshot load+decode only |
| --- | --- | --- | --- | --- | --- |
| 4,500 (2) | 154 ms, 48 MB | 2.4 MB | 78 ms | 98 ms | — |
| 10,200 (3) | 303 ms, 76 MB | 5.5 MB | 115 ms | 223 ms | 95 ms |
| 51,000 (15) | 1,735 ms, 266 MB | 27.6 MB | 184 ms | 650 ms | 400 ms |

PostgreSQL compresses the snapshots well (TOAST): `snmp_olts` is 0.7 MB at 10,200 ONUs and
3.1 MB at 51,000.

## `onu_state` read model: built, measured, not adopted

A full read model was built and tested on this branch, then left out of the PR:

- table `onu_states` with one row per position, the normalised payload and a hash;
- a projector called from the `SnmpOlt` saved hook, writing changed rows only;
- `collect()` reading from the table;
- a rebuild command;
- 7 tests, including exact equivalence with the snapshot path;
- the full suite stayed green.

| ONUs | snapshot path | read model, OLT rows as today | read model, narrow OLT select |
| --- | --- | --- | --- |
| 10,200 | 250 ms, 68 MB | 330 ms, 64 MB | 340 ms, 60 MB |
| 51,000 | 1,240 ms, 240 MB | 1,440–1,810 ms, 220 MB | 1,390 ms, 200 MB |

The read model is about 25 % slower and saves only 10–15 % memory. Where the time goes:

- At 10,200 ONUs, fetching the rows takes 73 ms and decoding them 25 ms. Loading and
  decoding the snapshots also takes 95 ms. So reading is the same cost both ways.
- Building the 10,200 output arrays takes about 150 ms on both paths, and that dominates.
- The table costs 7.5 MB on disk against 0.7 MB for the snapshots.
- Every poll pays about 100–150 ms per 3,400-ONU OLT to diff and write the table.
- A substring search on the table, without a trigram index, took 365 ms at 51,000 ONUs.
  The snapshot path takes 650 ms for the same search.

**Decision (executor, reversible):** do not add the table now. The snapshot path is
already as fast to read. The extra writes, disk and second source of truth buy nothing
measurable at the scale here. The code was dropped before the PR; this document keeps
the numbers so the question is not reopened without new evidence.

## What actually limits scale

1. **The ONU monitor page and `GET /api/v1/onus` send every ONU in one response.**
   `SmartOltController::onuMonitor` passes the whole `collect()` list to Inertia. That is
   2.4 MB at 4,500 ONUs and 27.6 MB at 51,000, built in 1.7 s with 266 MB of the 512 MB
   `memory_limit`. Around 100,000 ONUs the page would hit the memory limit. The fix is
   server-side filtering and paging of the list. **Done for the page in NMS-07-E2 below.**
2. **Global search without a match scans every snapshot.** It takes 650 ms at 51,000 ONUs.
   That is acceptable for an explicit search. If it moves to type-ahead at that scale, an
   indexed search table (serial/MAC/name with `pg_trgm`) is the evidence-backed use of a
   read model. Decide that with E2, not before.
3. At the stated current size (about 4,500 ONUs on Maung staging, from code comments,
   [STATED]), every path stays below 160 ms.

## NMS-07-E2 — ONU monitor scoped to one OLT (2026-10-22)

**Branch:** `step/nms-07-e2-onu-monitor-scope` (on top of D-2c).

The ONU monitor page already showed ONUs of one chosen OLT only, but the server sent every
ONU of every OLT and the browser filtered them. Now:

- without `olt_id` the page sends the OLT dropdown and no ONU (the page asks to pick an OLT);
- with `olt_id` it reads only that OLT's snapshot and sends only its ONUs; changing the OLT
  is an Inertia partial reload (`only: onus, refreshed_at, selected_olt_id`) and the URL
  keeps the choice;
- the dropdown query no longer loads any snapshot column;
- an id the user cannot see (tenant scope) selects nothing.

`/api/v1/onus` already pages its response (`per_page` at most 200), so its payload is
bounded; it still reads every snapshot when no `olt_id` is given. Cursor paging and the
contract belong to NMS-08.

**Measured** (`run-onu-list-bench.sh 1 3400 5`, one large C300-sized OLT, throwaway
PostgreSQL 16): list 158 ms, 42 MB peak, payload 1.9 MB. The page cost now follows the
largest single OLT, not the whole installation: at 51,000 ONUs it was 1,735 ms, 266 MB and
27.6 MB. Browser check (demo data): the first page load carries no ONU; switching OLT loads
about 7 KB for 14 ONUs.

## NMS-07-E3 — alarm page, alarm engine and optical charts (2026-10-22)

**Branch:** `step/nms-07-e3-alarm-rx-perf` (on top of E2). Harness:
`tests/perf/run-ops-bench.sh [onus] [rx_onus] [cleared_alarms] [repeats]` with
`tests/perf/ops_bench.php`: throwaway PostgreSQL 16 (stack digest, generated password in a
0600 file), one 3,400-ONU OLT, 200,000 cleared alarms (what `history:prune` keeps for 180
days) plus 500 active, and RX history for 300 ONUs (raw 3 days at 5 min, hourly 400 days,
daily 3 years: 0.26 M + 2.9 M + 0.33 M rows). Page timings are a warm worker (one warm-up
request first), as under PHP-FPM. `BENCH_SLOW_MS=n` prints queries slower than n ms;
`BENCH_MODES` picks modes; `BENCH_NO_ALARM_INDEX=1` measures without the new indexes.

| Path | Before | After |
| --- | --- | --- |
| Alarm page, active (20 per page) | 263 ms | 74 ms |
| Alarm page, all (active + cleared) | 454 ms | 137 ms |
| Alarm engine, steady poll (nothing changes) | 32–42 ms | unchanged |
| Alarm engine, burst (340 ONUs go LOS at once; debounce on) | 0.41–0.57 s | unchanged |
| RX chart, one ONU: 24 h / 7 d / 30 d (raw) | 14–31 ms | unchanged |
| RX chart, one ONU: 90 d (hourly), 1 y / 3 y (daily) | 10–15 ms | unchanged |

**Defects found and fixed:**

1. The alarm page decoded the whole OLT snapshot (about 1.5 MB at 3,400 ONUs) twice for
   every alarm row, only to decide whether the row links to a ZTE page: the `array` cast
   decodes on every access. The decision is now made once per OLT. The same pattern in
   `AlarmNotificationTargetResolver::resolve()` (API v1 alarm list, bell clicks) is memoized
   per OLT too. Effect: 263 → 77 ms on the active page without any index.
2. No index served "filter by status, newest first": PostgreSQL sorted every alarm on each
   view of the page, the bell and the summary. Migration
   `2026_10_22_030000_add_alarm_events_list_indexes` adds `(status, last_seen_at)` and
   `(last_seen_at)` (reversible). Effect on "all": 320 → 137 ms.

Remaining: `select distinct type` for the filter options takes 30–40 ms at 200,000 rows;
the "all" count with the unread check about 70–100 ms. Both are acceptable now.

The engine's burst cost (about 1.5 ms per new alarm) is far inside one poll interval (5
minutes); it is not changed.

The harness waits for the image's second "ready" line before connecting (the first belongs
to the initdb server; connecting then failed once with "server closed the connection
unexpectedly"), and removes the container's volume on exit; the same fix is applied to
`run-onu-list-bench.sh`.

## Not measured yet (rest of NMS-07-E)

Not measured yet:

- queue latency with the dedicated poll pool;
- the Go poller's throughput against a simulated SNMP agent;
- the disk saved by dropping the duplicate index (NMS-07-B).
