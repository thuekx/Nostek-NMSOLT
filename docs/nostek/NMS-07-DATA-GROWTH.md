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
| optical RX | unchanged (`optical:prune-rx`: raw 3 days, hourly 45 days) | owner decision NMS-AI-Q5 sets hourly to 400 days on nostek-platform through `SNMP_POLLER_RX_HOURLY_RETENTION_DAYS=400` in that install's `.env`; a daily rollup for 3 years is still open (NMS-07-B2) |

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
