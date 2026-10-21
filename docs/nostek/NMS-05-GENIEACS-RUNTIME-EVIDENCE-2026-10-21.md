# NMS-05 GenieACS lab runtime evidence — 2026-10-21

## Decision

**NMS-05 VERIFIED.** One owner-approved, non-production ZTE F660 completed the
bounded GenieACS inform/read matrix on the isolated Maung staging overlay.
No production system, OLT configuration, ONT service parameter, Caddy route,
`maung_edge` membership or Maung repository was changed.

Raw logs and device identifiers remain only in the owner-controlled evidence
directory on Maung. This document intentionally excludes serials, hashes, MAC
addresses, source public addresses and credentials.

## Scope

- Host identity: approved `maung-apps` staging host — PASS.
- Base NMSOLT endpoint remained loopback-only on port 18080.
- GenieACS CWMP used the one approved private Maung bind on port 17547.
- GenieACS UI remained loopback-only on port 13000.
- MongoDB, NBI and file service remained unpublished.
- `NMSOLT_DEVICE_WRITES_ENABLED=false`.
- Device scope: one approved lab ZTE F660.
- OLT/ONT writes: none.

## Runtime results

| Check | Result |
| --- | --- |
| ACS topology validator | PASS |
| MongoDB health | PASS |
| CWMP listener | PASS |
| GenieACS UI | PASS |
| Internal NBI | PASS |
| Device count | exactly 1 |
| Manufacturer/product identity | ZTE / F660 |
| Serial comparison | PASS, compared locally without disclosure |
| Initial event | `0 BOOTSTRAP, 4 VALUE CHANGE` |
| Registration | new device registered |
| Periodic event | `2 PERIODIC` |
| Read-only task | `getParameterValues`, completed |
| Connection request for read task | not used |
| Parameter mutation | false |
| Pending tasks after completion | 0 |
| Faults | 0 |
| Presets | 0 |
| MongoDB persistence after restart | PASS |
| Base health while ACS stopped | PASS |
| Base health after ACS restart | PASS |
| CWMP/UI after restart | HTTP response observed / PASS |

The read matrix confirmed compatible identity, software and connection-request
metadata. Periodic-inform fields were not required as stored values because the
actual `2 PERIODIC` event is the authoritative runtime proof that the scheduler
operated. The queued read-only task was consumed by that Inform without an
active connection request.

## Isolation and rollback rehearsal

Only the five ACS overlay containers were stopped. The NMSOLT application,
PostgreSQL and Redis remained healthy throughout. MongoDB was restarted first,
followed by CWMP, NBI, file service and UI. The single device record persisted,
and both CWMP and UI responded after recovery.

## Source and change chain

- Base staging application image: `4b7437c7169e98a2b42be599bc241ece0a7299e7`.
- NMS-05 overlay source: PR #14, merged as
  `a6daef774f46d5aaeadcbba1529258fef90b29b6`.
- Published-service network repair: PR #15, merged as
  `08a57e6be31fbf00bcc9fe4cd5cb8f87bfa6a61f`.
- Maung host-identity guard: PR #16, merged as
  `2a1547cd7d06e4d07d6526862ea615a105366cd5`.

## Local evidence retained outside GitHub

- `nms05-runtime-20261021-181913.txt`
- `nms05-inform-20261021-225831.txt`
- `nms05-final-20261021-230052.txt`
- `nms05-read-parameters-20261021-231014.txt`

These filenames are recorded for handoff only. Their raw contents must not be
published because they may contain device or network identifiers.

## Next gate

Proceed to NMS-06 with read-only FTTH validation. Start with the approved ZTE
C320 using an RO-only SNMP community, manual Test/Refresh, bounded snapshots,
optical RX and UI evidence. Keep automated polling, Telnet and every device
write disabled until their separate gates are satisfied.
