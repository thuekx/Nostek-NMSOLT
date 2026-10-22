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

## Not measured yet (rest of NMS-07-E)

Not measured yet:

- alarm list and alarm engine timing under synthetic load;
- the optical RX history queries (raw, hourly, daily);
- queue latency with the dedicated poll pool;
- the Go poller's throughput against a simulated SNMP agent;
- the disk saved by dropping the duplicate index (NMS-07-B).
