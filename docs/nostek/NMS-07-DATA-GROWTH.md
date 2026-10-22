# NMS-07-B — bounded data growth and a separate poll worker pool

**Date:** 2026-10-22 · **Branch:** `step/nms-07-b-data-growth` (on top of NMS-07-A) ·
**State:** source PASS (synthetic tests); NMS-07 stays IN PROGRESS. No host or device action.

## Findings this step fixes

| # | Finding (source review, Nostek-AI architecture review R7/R8) | Fix |
| --- | --- | --- |
| B-ret | `polling_events`, `alarm_events`, `audit_logs` and `olt_config_backups` had no retention: they grow for ever on the shared `/srv` disk | `history:prune`, daily 03:30 (after `optical:prune-rx` 03:15, before the platform dumps 04:00 and restic 04:30) |
| B-idx | `onu_rx_hourly_lookup_idx` covers the same five columns in the same order as the unique index `onu_rx_hourly_bucket_unique`: double write cost and disk on every hourly aggregation | migration `2026_10_22_000000_drop_duplicate_onu_rx_hourly_lookup_index` (reversible) |
| B-poll | one `queue:work` process served every OLT poll and every other job: one slow OLT or a long report job delays all other polls, and stale data then looks like an outage | `PollOltJob` on queue `polling`; dedicated pool `nostek-nmsolt-poll-worker` (`NMSOLT_POLL_WORKERS`, default 2, 1–8); the general worker serves `default,polling` |

## Retention defaults (tunable in `.env`)

| Data | Default | Rule |
| --- | --- | --- |
| `polling_events` | 30 days | by `created_at` |
| `alarm_events` | 180 days after clearing | only `status = cleared`; active and pending alarms are never removed |
| `audit_logs` | 400 days | security evidence, kept longest |
| `olt_config_backups` | newest 7 per OLT + first successful backup of each of the last 12 months | failed captures never count as the monthly copy |
| optical RX | `optical:prune-rx`: raw 3 days, hourly 45 days (default), daily 1095 days | owner decision NMS-AI-Q5 sets hourly to 400 days on nostek-platform through `SNMP_POLLER_RX_HOURLY_RETENTION_DAYS=400` in that install's `.env`; the daily rollup is NMS-07-B2 below |

`history:prune --dry-run` prints what would be removed. Deletes run in chunks of 5000 ids
(no long table lock), bypass the demo/partner global scopes (retention applies to every
row), and a retention value below 1 day refuses without deleting anything.

## Evidence (workspace)

- `tests/Feature/HistoryPruneTest.php`: 7 tests (each table at the boundary, active/pending
  alarms kept, dry run, monthly backup selection with a failed capture and two OLTs,
  invalid retention refuses, schedule at `30 3 * * *`, duplicate index gone and unique index
  kept, poll jobs pushed on `polling`).
- Full Laravel suite (SQLite): 801 tests, 0 failed (122 clean + 679 with the warning that
  every run of this suite already shows on main; tracked for NMS-07-D).
- `docker/supervisord.conf` parsed with supervisor 4: three poll workers rendered for
  `NMSOLT_POLL_WORKERS=3`, the general worker on `default,polling`.
- `nmsolt-ops.sh status` now also reports `process_poll-worker`; ops integration test updated.

Not measured here: real queue latency under load and the disk saved by dropping the duplicate index (needs the
performance matrix, NMS-07-E).

## NMS-07-B2 — daily RX rollup (owner NMS-AI-Q5 A: hourly 400 days, daily 3 years)

**Branch:** `step/nms-07-b2-rx-daily` (on top of NMS-07-C/E).

| Piece | What |
| --- | --- |
| `onu_rx_daily` | one row per ONU per day: min of the hourly minimums, max of the hourly maximums, average weighted by sample count, total samples; unique key = upsert key = chart lookup |
| `optical:aggregate-rx-daily` | daily 00:25 (after the 00:05 hourly run); skips today; re-processes the last summarised day; at most 31 days per run (`--days`, `--max-days` for backfill) |
| `optical:prune-rx` | keeps every hourly row that is not yet rolled up into a daily row, then prunes daily rows older than `SNMP_POLLER_RX_DAILY_RETENTION_DAYS` (default 1095) |
| chart ranges | ONU detail gains 90 days (hourly), 1 year and 3 years (daily); axis labels show month + year when ticks are a month or more apart |

**Defect found and fixed (existing code):** `optical:aggregate-rx` resumed from the last
hour that had a summary and processed at most 48 hours per run. After a polling gap longer
than 48 hours, it went round the same empty hours for ever. Examples of such a gap: the
poller down, or the NET-1-3 migration window. The raw-sample prune then refuses to run
(`hourlyLagsBehind`), so `onu_rx_samples` grows without bound.

Both aggregation commands now jump to the next hour or day that has data. A test fails
without the fix and passes with it:
`OnuRxDailyTest::test_hourly_aggregation_jumps_a_polling_gap_longer_than_its_batch`.

### Measured (workspace, throwaway PostgreSQL 16, synthetic)

The test data was 10,000 ONUs × 7 days × 24 hourly rows, 1.68 M rows.

- `optical:aggregate-rx-daily --days=8` wrote 70,000 daily rows in 11.2 s, including the
  Laravel bootstrap. That is about 1.5 s per day of data, so the nightly run (one day)
  takes about 2 s.
- Size including indexes: hourly 274 MB per 1.68 M rows, daily 11 MB per 70,000 rows.
  Both come to about 160 bytes per row.

### Disk consequence of NMS-AI-Q5 A [INFERRED from the measured row size]

| ONUs | hourly, 400 days | daily, 3 years |
| --- | --- | --- |
| 5,000 | about 48 M rows, about 7.8 GB | about 5.5 M rows, about 0.9 GB |
| 10,000 | about 96 M rows, about 15.6 GB | about 11 M rows, about 1.8 GB |

The 400-day hourly table is the largest table in the database by far. It also sets the
size of the nightly `pg_dump` and of restic. NET-1-1 (disk size on nostek-platform) and
NET-1-2 (backup) should size `/srv` and the dump schedule against this.

The owner could keep fewer hourly days, since the daily rollup now covers the long range.
For example, 90 days of hourly is about 1.8 GB at 5,000 ONUs. Q5 is the owner's decision:
this note only gives the measured cost.

### Evidence

- `tests/Feature/OnuRxDailyTest.php`, 7 tests:
  - the weighted average;
  - idempotency;
  - skipping a gap;
  - the batch limit;
  - the hourly prune guard and daily retention;
  - long ranges served from the daily table;
  - the ranges accepted by the ONU detail page;
  - the schedule at `25 0 * * *`.
- Full Laravel suite (SQLite): 808 tests, 0 failed.
- Vitest: 60 tests passed, including the new year-label case.
