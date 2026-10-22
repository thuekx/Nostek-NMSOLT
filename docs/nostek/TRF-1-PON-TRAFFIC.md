# TRF-1 PON port traffic graph

Owner request 2026-10-22: traffic graphs per PON port and per ONT. Owner decision (decision card,
2026-10-22): **PON first, ONT later**. This step is the PON part. It is a feature track next to
NMS-07; it does not change NMS-07 or NMS-08 status.

## What was there before

- Only live uplink traffic (`smartolt.port.traffic`, CLI through `ZteCardUplinkService`), no history.
- No interface or ONU octet counter was polled anywhere [VERIFIED: `app/`, `cmd/`].
- `GenieACSParserService::parseAcsTrafficMonitor()` already reads ONU WAN byte counters from a
  GenieACS device, but nothing calls it [VERIFIED]. It is the likely path for per-ONT traffic.

## Design

| Part | Choice |
| --- | --- |
| Source | IF-MIB `ifHCInOctets` (`1.3.6.1.2.1.31.1.1.1.6`) / `ifHCOutOctets` (`.10`), Counter64, per GPON port ifIndex |
| Read | Go poller `--include-traffic`: GET in batches of 20 varbinds for the GPON port ifIndexes only (the ifTable also lists every ONU/uplink interface, so it is never walked). Failure never fails the poll; `traffic.ok=false` + error in the poll JSON |
| Transport | counters as decimal strings (`in_octets`, `out_octets`) on each port row: a Counter64 does not fit a PHP int or a JSON float exactly |
| Rate | `PonTrafficRecorder`: previous reading = previous poll's `last_test_result.ports`; rate = delta x 8 / gap. No row when a counter is missing, went backwards (reboot), or the gap is under 30 s or over 3 x poll interval (the STALE limit) |
| Storage | `pon_traffic_samples` (bps per port per poll), `pon_traffic_hourly` and `pon_traffic_daily` (avg + peak, sample count) |
| Retention | raw 8 days (24 h / 7 d charts), hourly 100 days (30 / 90 d), daily 3 years (1 / 3 y); env `SNMP_POLLER_TRAFFIC_*` |
| Jobs | `traffic:aggregate` hourly at :10 (redoes the last bucket, skips polling gaps), `traffic:prune` 03:20 (never drops what is not summarised) |
| UI | PON port page (`SmartOlt/PortDetail`, GPON ports): downstream/upstream chart, last/avg/peak, ranges 24h–3y, stale notice, "no counters" text instead of zero |
| Switch | `SNMP_POLLER_TRAFFIC=false` turns the GETs off |

Direction: `in` = into the OLT from the PON (customer upstream), `out` = OLT to the PON (downstream).

## Cost (synthetic, workspace)

`tests/perf/run-poller-bench.sh` with `BENCH_TRAFFIC="0 1"` (snmpsim C300, 3,400 ONUs, 54 PON ports,
20 ms RTT, max-repetitions 10, 3 runs each):

| | requests | median wall | poller CPU |
| --- | --- | --- | --- |
| without traffic | 2,747 | 71.0 s | 0.68 s |
| with traffic | 2,753 | 71.6 s | 0.67 s |

Six extra GETs per poll [MEASURED]. Storage: ~37k raw rows per day for a 128-port C300 [INFERRED].

## Found on the way

`OnuRxSample::needsDaily()` compared `$since` with `now()->subDays(90)` computed a moment later,
so the 90-day RX chart was served from the daily rollup instead of the hourly one. Fixed with an
hour of slack; `OnuRxDailyTest` reproduces it.

## Open

- [VERIFIED 2026-10-22, owner read-only check from nostek-platform] the real ZTE OLT 192.168.254.2
  answers `ifHCInOctets`/`ifHCOutOctets` as Counter64 on the first GPON port (ifName walk, ifIndex
  285278465). Two reads about a minute apart: in +4,477,284 bytes, out +49,435,712 bytes (out =
  downstream to the ONTs is the larger one, as expected). Other OLT models stay [UNKNOWN]; the page
  says "no counters" when they are missing.
- The PHP fallback poller (`SNMP_POLLER_DRIVER=php`) does not read the counters; the platform
  Compose runs the Go poller.
- C-Data and HiOSO are polled through their scanners and get no PON traffic yet.
- Per-ONT traffic: next step, first through GenieACS (WAN counters, parser exists), then OLT SNMP
  once the ZTE ONU traffic OID is known (BLOCKED: owner read-only walk).
