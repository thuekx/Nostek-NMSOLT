# NMS-06 — FTTH operations capability matrix

**Status:** VERIFIED. Sanitized ZTE C320 V2.1.0 runtime evidence covers identity,
manual PON/ONU inventory, optical RX and read-only staging UI behavior. Exact-head
CI reconciles stale/partial data, debounce, ODP, role and fail-closed fixtures.

## Reviewed families

| Family / detector | Inventory path in source | Fixture or contract evidence | Current claim | Lab evidence still required |
| --- | --- | --- | --- | --- |
| ZTE C300/C320 (zte, enterprise 3902) | Direct ZTE SNMP client; GPON port/ONU snapshots | OltPollingTest, SmartOltInventoryTest, ZteOnuRxPowerTest | C320 V2.1.0 runtime: 32 PON, 484 ONU, bounded optical sample PASS | Final CI and fixture/runtime reconciliation |
| ZTE C600 | ZTE path with C600 interface conventions and PHP fallback | C600 cases in OltPollingTest, ZteOnuRxPowerTest | Read path covered by fixtures; some write capabilities intentionally disabled | Same read-only lab checks; do not infer C300 write compatibility |
| C-Data EPON (17409) | CDataEponSnmpService through the non-ZTE resolver | CDataSnmpDriverTest, OltPollingTest | Inventory, identity/status and RX parsing covered by synthetic walks | Sanitized model/firmware and full/partial walk comparison |
| C-Data GPON (34592 and reviewed FD models) | CDataGponSnmpService; some firmware uses CLI enrichment | legacy, V3 and compact-model cases in CDataSnmpDriverTest; CLI parser tests | Multiple source variants covered; capability can be firmware-dependent | Record exact firmware and whether each value came from SNMP or CLI |
| HiOSO HA7302/HA7304 (25355) | HiosoEponSnmpService through the non-ZTE resolver | HiosoSnmpDriverTest, including partial walks, stale RX and debounce | Read inventory/RX and anti-flap behavior covered by fixtures | Sanitized repeated polls across a controlled loss/recovery cycle |
| V-SOL and every unreviewed family | No reviewed driver | SmartOltSupportMatrixTest | Must resolve to unknown and fail closed | Separate driver research and fixture/lab approval before any support claim |

A marketing label or similar enterprise OID is not sufficient proof of support.
The exact model, firmware and observed tables must match a reviewed row.

## Cross-cutting source evidence

| Requirement | Existing automated evidence | Remaining NMS-06 evidence |
| --- | --- | --- |
| Per-PON/ONU snapshot | polling, ZTE inventory, C-Data inventory and HiOSO driver tests | Compare sanitized lab counts against the device UI/CLI |
| Optical RX normalization | ZTE, C-Data value/driver and HiOSO tests | Compare a small approved sample including offline/no-signal sentinels |
| Stale/incomplete data | polling preservation/clearing tests and HiOSO partial-walk tests | Timestamped repeated polls with bounded freshness |
| Alarm confirmation/debounce | AlarmEngineTest and HiOSO debounce tests | Controlled lab loss/recovery timeline without customer impact |
| ODP correlation | AlarmOdpCorrelationTest, OdpTest, API ODP map tests | Synthetic or approved lab topology screenshot and expected grouping |
| Read-only user UI | inventory, alarm navigation, report and JavaScript component tests | Role-scoped staging screenshots; no secrets or customer PII |

## Lab evidence template

One row per exact device/firmware combination. Mask management addresses, serials,
MAC addresses, customer names and community strings.

| Field | Value |
| --- | --- |
| Approval/change ID and time window | Owner-approved bounded staging session, 2026-10-22 WIB |
| Exact vendor/model/firmware | ZTE C320 / V2.1.0 |
| Driver selected and detection evidence | ZTE GPON; enterprise 3902; sanitized sysDescr match |
| PON ports: device count / NMSOLT count | 32 / 32 |
| ONUs on sampled PON: device count / NMSOLT count | 14 / 14; 5 online |
| RX sample comparison and tolerance | About -27 dBm; detail and short history consistent |
| Offline/no-signal sentinel result | PENDING |
| Stale/partial-poll behavior | PENDING |
| Alarm debounce and recovery timeline | PENDING |
| ODP grouping result | PENDING |
| Read-only UI/role result | Admin staging UI PASS; auto-poll and alarms OFF; no mutation |
| Sanitized evidence location and exact commit/image | [runtime evidence](NMS-06-ZTE-C320-RUNTIME-EVIDENCE-2026-10-22.md); commit resolved by PR |
| Reviewer and decision | Exact-head CI PASS; NMS-06 VERIFIED |

## Fail-closed rules

- Unknown or ambiguous families remain unknown; do not borrow another vendor's
  driver because the UI looks similar.
- A missing/partial walk is not automatically proof that an ONU is offline.
- No write, reboot, provision, rename, delete, port toggle or configuration save
  is part of NMS-06 validation.
- Do not connect to a live device until NMS-03/NMS-04 are satisfied and the owner
  has approved the exact lab device, scope and time window.
- NMS-06 can be marked VERIFIED only when fixture results and sanitized lab
  evidence cover every capability claimed for the release's supported matrix.
