# NMS-07 source audit and feature matrix

Original audit base: `62b414f04ac4b5e4f024669e431be29f4d0845d1` (PR #19).
Alignment: main `b0cbf36ac6f59552a3ef4f3a01f187997d221f86` (PR #22); identity cleanup preserved.
PR #20: MERGED as `1a38c6b309a84f07db89398f2f26d727d92c3128`.
CI: aligned head `fc5ce840bcc548479a308e1de16fc292f1d21e49` passed all five jobs in run `39773898154`; original run `39715103922` is historical.
Execution: resumed 2026-10-22 (owner prompt); NMS-07-A unified package on branch `step/nms-07-a-unified-package`.
Scope: source inspection and synthetic CI only; no host/device/network operations.
State: **IN_PROGRESS**, not whole-product verification.

The companion `NMS-07-SOURCE-INVENTORY.json` enumerates 49 Vue page files
(including partial forms), five queued jobs, ten console commands and statically
named routes. Dynamic route registration and middleware must also be reviewed;
this inventory does not claim that every user journey works.

| Surface | Existing evidence to rerun in CI | Audit result / remaining gate |
| --- | --- | --- |
| Login, registration, profile, roles | Auth/*, RoleAccessTest, PartnerRoleTest, OperatorOltScopeTest | Existing tests; browser journeys still need isolated verification |
| Dashboard/search | DashboardTest, ApiV1ReadExtrasTest, GlobalSearchReadinessTest | Search now fetches telemetry in batches; MAC-only navigation fixed; invalid query shapes rejected |
| ZTE inventory, ports, ONU detail/history | SmartOltInventoryTest, SmartOltGponPortsPageTest, OnuRxHistoryTest | NMS-06 historical runtime evidence; page/performance audit still open |
| C-Data and HiOSO | CDataOltInventoryTest, CDataGponPortPagesTest, HiosoOltTest | Fixture evidence only for models not in approved runtime evidence |
| ODP/map/photos | OdpTest, OnuMapPageTest, OdpPhotoTest | Tenant isolation and stale/missing targets need journey matrix |
| Alarms/reports/notifications | AlarmEngineTest, AlarmNotificationNavigationTest, ReportTest | Large alarm page and export query budgets remain unmeasured |
| Settings/users/audit/Telegram | SecurityHardeningTest, UserListPageTest, AuditLogTest, TelegramWebhookTest | Settings redaction and all write-control visibility need audit |
| GenieACS reads/Wi-Fi controls | GenieacsConnectedDevicesTest, GenieacsOnuMatchTest, DeviceWriteBoundaryTest | Existing lab evidence retained; UI must clearly distinguish available reads from blocked writes |
| Queued mutations and interactive CLI | DeviceWriteBoundaryTest | Default-off gate retained; not a single-use approval broker (NMS-11 remains blocked) |
| Poller/scheduler/optical aggregation | OltPollingTest, HistoryPruneTest, Go tests/vet | NMS-07-B: poll queue + worker pool, retention for history tables, duplicate RX index dropped; no live polling executed; capacity benchmark remains open |
| Unified product package | Compose validators, image builds, `tests/ops/integration.sh` (CI `ops-integration`) | NMS-07-A: core/with-acs/platform Compose + `nmsolt-ops.sh`; workspace 58/58 with real PG/Redis/Mongo/GenieACS and a stand-in app; real app image under the tool first runs in Nostek-AI NET-1-2 |
| Consumer API | Existing Api/* tests | Legacy API exists, but NMS-08 scope/freshness/cursor/OpenAPI contract is not complete |

## Reproducible search defects and regression evidence

1. Before: ONU search calls `get()` on all visible OLT snapshots, even if the
   first OLT supplies all ten matches. After: `lazyById(25)` hydrates at most a
   batch of 25 OLT snapshots and stops when enough results exist. Model global
   scopes remain active. A synthetic 60-OLT test asserts only the first bounded
   telemetry query is fetched when its first record fills the result budget;
   another test checks a match beyond the first batch and scoped partner access.
   This bounds batch hydration, not individual snapshot size, worst-case query
   latency or DB work. Production memory/time improvement is **not measured**.
2. Before: MAC-only ONU results could have an empty `q` when opened in the web
   port page. After: nonempty serial, MAC, name, then interface supply the filter.
   A web response test checks the filter and ONU focus in the generated URL.
3. Before: array-valued `q` is cast to a string and may cause an error. After:
   both search controllers validate a nullable string of at most 200 characters
   and return a validation error for invalid shapes. Zero and small internal
   result limits are also respected.

## Completion checklist

- [ ] Every named route and page journey exercised using isolated synthetic data.
- [ ] All high-risk UI controls accurately reflect the installation write gate.
- [ ] Large ONU/alarm/search/polling latency, query and memory budgets measured.
- [ ] Unified base/ACS installer, health, backup, upgrade and rollback verified. (Source + synthetic runtime PASS in NMS-07-A; real app image on a host still open.)
- [x] Search PR #20: exact-head full CI passed and owner-approved merge completed. This does not verify whole-phase UI/performance/package criteria.

NMS-08 must not be marked unblocked by this partial audit alone.
