# NMS-06 ZTE C320 runtime evidence — 2026-10-22

## Scope and safety

The owner approved one ZTE C320 for bounded staging validation. The management
address and SNMP community remain local and are not recorded here. The isolated
Maung staging application retained automated polling and alarms disabled. No
SNMP SET, provisioning, reboot, delete, port-state change, configuration save,
Caddy change, production change, or OLT/ONT mutation was performed.

## Observed read-only results

| Check | Sanitized result |
| --- | --- |
| Host and route | Intended Maung staging host and routed management path confirmed |
| SNMP identity | PASS; ZTE C320, software V2.1.0, enterprise 3902 |
| Manual connection test | PASS |
| Manual PON snapshot | PASS; 32 GPON ports discovered |
| Port state summary | 25 up, 7 down |
| ONU inventory summary | 484 registered; 256 online; 228 offline |
| Sample PON | 14 registered; 5 online |
| Sample ONU identity/state | ZTE F660 family; online/working; identifiers withheld |
| Optical sample | RX about -27 dBm (warning), TX about 2.46 dBm, distance about 2.92 km |
| RX history | Consistent bounded samples around -27 dBm |
| Automatic polling | OFF |
| Alarm delivery | OFF |
| Device writes | Not exercised; global default-off boundary retained |

Opening the ONU detail page invoked two read-only CLI display commands
(`show gpon onu detail-info` and `show pon power attenuation`). They returned
live state and optical values; no configuration command was sent. This proves
the read-only CLI detail path but is not evidence for any write capability.

## Findings

- The system uptime card rendered `NaNd` because Net-SNMP Timeticks can arrive
  as `(<centiseconds>) <human text>`; the UI treated the whole string as a
  number. The accompanying source change parses the parenthesized tick count
  and adds a regression test.
- Raw screenshots contain customer names and device identifiers. They remain
  outside the public repository; only aggregate and sampled sanitized facts are
  recorded here.
- Live evidence covers the C320 identity, PON/ONU inventory, one bounded optical
  sample, history rendering, and the read-only user interface. Existing
  automated fixtures remain the evidence for stale/partial data, debounce, ODP
  correlation, role restrictions, and unknown-vendor fail-closed behavior.

## Decision

NMS-06 remains IN_PROGRESS until this change passes the complete CI suite and
the reviewed fixture evidence is explicitly reconciled with the runtime matrix.
No additional live-device action is required for that review.
