# Session handoff — Nostek-NMSOLT

**Canonical restart page:** https://github.com/thuekx/Nostek-NMSOLT/blob/main/docs/nostek/HANDOFF.md  
**Companion files:** [ROADMAP.md](ROADMAP.md), [STATUS-TREE.md](STATUS-TREE.md),
[PROJECT-STATUS.yaml](PROJECT-STATUS.yaml).  
**Snapshot date:** 22 October 2026 (Asia/Jakarta). Refresh main, open PRs and
exact-head CI before resuming.

## Product direction

NMSOLT is the standalone network platform and authoritative telemetry/API
provider. Maung and Nostek-AI are consumers.

A future Maung `/nosteknmsolt` page is explicitly allowed as a thin consumer:
API settings, health/inventory/alarm summaries, search, quick customer/ONU detail
and deep links. Polling, drivers, GenieACS, optical history, network business
logic and device credentials stay in NMSOLT. Maung must not query NMSOLT's
database directly.

## Safety boundary

Safe source work may continue automatically through dependency gates. Do not
deploy to production, change Caddy or maung_edge, modify the Maung/CRM/WO
repositories, inspect unauthorized secrets, mutate a live OLT/ONT or bypass an
approval. Source-readiness PASS is not runtime verification.

## Verified checkpoint

- **NMS-00 through NMS-05 VERIFIED:** public baseline, repeatable CI, default-off
  write boundary, read-only host discovery, isolated staging and isolated
  GenieACS lab completed with sanitized evidence.
- **NMS-06 VERIFIED:** owner-approved ZTE C320 V2.1.0 validation completed using
  SNMP v2c read-only and bounded read-only UI/CLI paths. Inventory reported
  32 PON and 484 ONU (256 online); sampled optical detail/history was around
  -27 dBm. No device mutation occurred.
- **PR #18:** exact head
  `902f5263b36b2d9a6006d825b4d38b3de0c4060b`, successful
  [run 39648137609](https://github.com/thuekx/Nostek-NMSOLT/actions/runs/39648137609),
  squash merge `c639f1277e7b37bcd5aee69d7ec58634758fa984`. Vue, Laravel,
  Go, Compose and both image builds passed. The Net-SNMP Timeticks `NaNd`
  rendering defect was fixed with regression coverage.
- **PR #19:** exact head
  `18b473545884ddf49d65847827e3473da3d02744`, successful
  [run 39652098876](https://github.com/thuekx/Nostek-NMSOLT/actions/runs/39652098876),
  squash merge `62b414f04ac4b5e4f024669e431be29f4d0845d1`. The provider-centric
  roadmap and bundled-GenieACS product direction are canonical.

- **PR #21:** exact head
  `6bc53a374e5ed5fc2209fb88dbfe7c105131cb23`, successful
  [run 39770297399](https://github.com/thuekx/Nostek-NMSOLT/actions/runs/39770297399),
  squash merge `f43baa3fa0f4959b8513011e01e79a6db8509f51`. Go, Vue,
  Laravel, Compose, both image builds and the fail-closed Nostek namespace
  validator passed. The PR branch was deleted.

Raw device identifiers, communities and screenshots containing customer/serial
data remain outside the public repository.

## Active and gated phases

- **NMS-07 IN PROGRESS:** audit and stabilize Nostek-NMSOLT functions, UI,
  packaging, read paths and performance. The active cleanup standardizes the
  product name as **Nostek-NMSOLT**, short name **Nostek-NMS**, internal
  `nos-*` namespace and `nos-snmp-poller`. The upstream MIT copyright notice
  remains unchanged.
- **NMS-08 BLOCKED on NMS-07:** API v1 read-only and OpenAPI.
- **NMS-09 BLOCKED on NMS-08 and separate Maung scope:** `/nosteknmsolt`
  thin consumer.
- **NMS-10 BLOCKED on NMS-08:** Nostek-AI Apps/tool adapter.
- **NMS-11 BLOCKED:** write governance; writes remain globally disabled.
- **NMS-12 BLOCKED:** release and production readiness.

See [NMS-07-12-GATED-READINESS.md](NMS-07-12-GATED-READINESS.md) for exact gates.

## PR #20 merged checkpoint

Owner approved merging PR #20. GitHub confirms it is merged as
`1a38c6b309a84f07db89398f2f26d727d92c3128`. Its exact reviewed head
`fc5ce840bcc548479a308e1de16fc292f1d21e49` passed all five CI jobs in
[run 39773898154](https://github.com/thuekx/Nostek-NMSOLT/actions/runs/39773898154): Go, Vue/JS, Compose, Laravel
(including Vite build), and application/GenieACS image builds.
The merge tree is identical to that tested head; this is not a separate
workflow execution on the squash-merge SHA and is not runtime verification.

Search batching, MAC-only navigation, input validation and regression tests
are now in main. PR #21 identity cleanup and PR #22 Cloud checkpoint remain
preserved. PR #20 is closed/merged, not pending review or CI. The earlier failed
fixture run `39714702681` and original successful run `39715103922` are historical.
No open PR existed at this checkpoint recheck; always refresh GitHub on restart.

Local namespace, inventory-path, YAML gate and link checks passed; all 60 JS
tests passed. Local Vite build lacked PHP/Ziggy dependencies; the properly
provisioned CI Vite build passed. No host/device/production action occurred.
NMS-07 implementation was explicitly paused by the owner after merge. This
update only reconciles documentation; NMS-07 remains IN_PROGRESS and NMS-08–12
remain BLOCKED. Start implementation only when the next session is instructed.

## Alignment with Nostek AI (2026-10-22)

Owner decisions NMS-AI-Q1…Q9 (Nostek-AI thread "Arsitektur NMSOLT di nostek-platform"):
NMSOLT runs on the Nostek-AI VM `nostek-platform` with read-only device credentials,
data under `/srv/data/nmsolt`, CWMP only from approved ONT subnets behind a DNS name; the
Maung staging data is migrated (write credentials blanked, APP_KEY moved by the owner,
NMSOLT Telegram off, Maung polling off first) and Maung staging is kept 14 days, then
retired. Host steps are Nostek-AI packages (track NET-1: NET-1-1 disk, NET-1-2 install +
backup 1.2, NET-1-3 migrate, NET-1-4 trial, NET-1-5 `nms_*` tools = NMS-10). NMS-08 also
carries the Control Center conditions (org/site bound to the token server-side, negative
cross-site test, support access per CC ADR-14).

## NMS-07-A unified package (2026-10-22)

Branch `step/nms-07-a-unified-package`: core/with-acs Compose, `compose.platform.yaml`,
MongoDB auth, GenieACS secrets out of argv, healthchecks, limits, digest pins,
`scripts/nostek/nmsolt-ops.sh` and `monitor/checks.json`. Workspace ops integration
58/58 PASS with real PostgreSQL/Redis/MongoDB/GenieACS and a stand-in app; the same test
runs in CI job `ops-integration`. Details: [NMS-07-UNIFIED-PACKAGE.md](NMS-07-UNIFIED-PACKAGE.md).
The PR needs exact-head CI and owner merge. NMS-07 stays IN_PROGRESS.

## NMS-07-B data growth (2026-10-22)

Branch `step/nms-07-b-data-growth` (on top of NMS-07-A): `history:prune` (daily 03:30),
duplicate `onu_rx_hourly` index dropped, `PollOltJob` on queue `polling` with a dedicated
worker pool. Details: [NMS-07-DATA-GROWTH.md](NMS-07-DATA-GROWTH.md).

## NMS-07-C/E ONU read path (2026-10-22)

Branch `step/nms-07-c-perf-matrix` (on top of NMS-07-B): performance harness
`tests/perf/run-onu-list-bench.sh` (throwaway PostgreSQL, synthetic snapshots). The planned
`onu_state` table was built, equivalence-tested and measured, then dropped: it was 25 %
slower than the snapshot path. The real limit is the ONU monitor/API list sending every ONU
in one response (27.6 MB, 1.7 s, 266 MB at 51,000 ONUs). Server-side paging (E2) goes with D.
Details: [NMS-07-PERFORMANCE.md](NMS-07-PERFORMANCE.md).

## NMS-07-B2 daily RX rollup (2026-10-22)

Branch `step/nms-07-b2-rx-daily` (on top of C/E): `onu_rx_daily` + `optical:aggregate-rx-daily`
(00:25), the hourly prune keeps rows not yet rolled up, and the ONU detail chart gains 90d/1y/3y.
It also fixes an existing defect: hourly aggregation stalled for ever after a polling gap
over 48 hours, which also stopped the raw-sample prune. Disk note for NET-1: 400 days of
hourly at 5,000 ONUs is about 7.8 GB [INFERRED from the measured 160 B/row]. Details:
[NMS-07-DATA-GROWTH.md](NMS-07-DATA-GROWTH.md#nms-07-b2--daily-rx-rollup-owner-nms-ai-q5-a-hourly-400-days-daily-3-years).

## NMS-07-D-1 write-gate visibility (2026-10-22)

Branch `step/nms-07-d-ui-journey` (on top of B2). The UI now shows the device write
boundary: a shared `device_writes` prop is built from the route table, there is a
read-only banner, a request guard (Inertia + axios) stops gated requests with a toast, and
`v-device-write` locks 56 controls. A drift test fails if a screen calls a gated route
without marking it. Checked in a real browser. Details:
[NMS-07-UI-JOURNEY.md](NMS-07-UI-JOURNEY.md).

## NMS-07-D-2a status honesty (2026-10-22)

Branch `step/nms-07-d2-stale-states` (on top of D-1). `App\Support\Freshness` marks an OLT
STALE when its data is older than 3 poll intervals, UNKNOWN when it was never polled, and
PAUSED when polling is off. Only FRESH is green on the dashboard (pills and cards), the OLT
list, the OLT detail and the PON-port page. API v1 and Telegram keep the legacy counts
until NMS-08. Details: [NMS-07-UI-JOURNEY.md](NMS-07-UI-JOURNEY.md).

## NMS-07-D-2b stale ONU, map, alarm and vendor pages (2026-10-22)

Branch `step/nms-07-d2b-onu-states` (on top of D-2a). ONU rows carry `stale`; the ONU
monitor, map, C-Data and HiOSO pages grey out the last known state of a non-fresh OLT and
say so; the alarm page names OLTs whose alarms are not being evaluated. Details:
[NMS-07-UI-JOURNEY.md](NMS-07-UI-JOURNEY.md).

## NMS-07-D-2c partial and permission states (2026-10-22)

Branch `step/nms-07-d2c-permission-states` (on top of D-2b). `RoleJourneyTest` sweeps every
GET page for admin, operator, partner and demo: no server error, every sidebar link opens,
no link into a refused page. RX values that lag the ONU status show their age. Details:
[NMS-07-UI-JOURNEY.md](NMS-07-UI-JOURNEY.md).

## NMS-07-E2 ONU monitor scoped to one OLT (2026-10-22)

Branch `step/nms-07-e2-onu-monitor-scope` (on top of D-2c). The ONU monitor reads and sends
only the selected OLT's ONUs (partial reload on change). One 3,400-ONU OLT: 158 ms, 1.9 MB.
Details: [NMS-07-PERFORMANCE.md](NMS-07-PERFORMANCE.md).

## NMS-07-E3 alarm page, alarm engine and RX charts (2026-10-22)

Branch `step/nms-07-e3-alarm-rx-perf` (on top of E2). Harness `tests/perf/run-ops-bench.sh`.
At 200,000 alarms the alarm page went from 263 to 74 ms (active) and 454 to 137 ms (all):
the OLT snapshot was decoded twice per alarm row, and no index served status + newest
first. RX charts take 10–31 ms for every range. Details:
[NMS-07-PERFORMANCE.md](NMS-07-PERFORMANCE.md).

## NMS-07-E4 Go poller and poll-queue capacity (2026-10-22)

Branch `step/nms-07-e4-poller-bench` (on top of E3). Harness `tests/perf/run-poller-bench.sh`
(synthetic C300 via snmpsim, delay proxy). 3,400 ONUs: poller CPU under 1 s; over 20 ms RTT a
poll takes 74.5 s at max-repetitions 10 and 34.4 s at 25. Raising the default needs a
read-only check on a real OLT by the owner (BLOCKED). Details:
[NMS-07-PERFORMANCE.md](NMS-07-PERFORMANCE.md).

## TRF-1 PON port traffic graph (2026-10-22)

The NMS-07 PRs #24 to #34 are merged (owner, 2026-10-22). Owner request: traffic graphs per PON
port and per ONT; owner decision: PON first, ONT later. Branch `step/trf-1-pon-traffic`: the Go
poller reads IF-MIB `ifHCIn/OutOctets` for each GPON port, the poll job stores per-port rates
(raw 8 d, hourly 100 d, daily 3 y) and the PON port page shows the chart. Six extra GETs per poll
in the bench. Details: [TRF-1-PON-TRAFFIC.md](TRF-1-PON-TRAFFIC.md).

TRF-1 is merged (PR #35, 2026-10-22).

## NMS-07-A2 GenieACS image build (2026-10-22)

Finding from the Nostek-AI NET-1-2 work: `start` runs `compose up --no-build`, but `build --tag`
built only the app image, so on a fresh host the local-only image `nmsolt-genieacs:1.2.16-local`
was missing and `start` failed. Branch `step/nms-07-a2-acs-image-build`: `build` (with-acs) also
builds the GenieACS image; `start` refuses with `RESULT image_<service>=FAIL ... not built yet`
before `compose up` when a locally built image is missing; `preflight` reports the same as WARN.
Workspace `tests/ops/integration.sh`: **RESULT ops-integration passed=69 failed=0** (new: preflight
WARN and start refusal with the GenieACS image untagged, build runs both compose builds via a
docker shim). NET-1-2 keeps its own `compose build genieacs-cwmp` workaround until a later package drops it.

## Exact next action

Refresh GitHub. Check the NMS-07-A2 PR (exact-head CI, owner merge). Next safe source work: per-ONT
traffic through GenieACS (`parseAcsTrafficMonitor` exists, unused). Still BLOCKED on the owner:
real-OLT check of `max-repetitions` 25 (the PON counters are VERIFIED on OLT 192.168.254.2), the ZTE ONU traffic OID walk.
NMS-07 evidence that needs a host stays open (real app image, queue latency under real
workers). Do not modify the retained LICENSE attribution.

For cross-device continuation, use the post-merge prompt in [CLOUD-HANDOFF.md](CLOUD-HANDOFF.md). PR #20 search fixes are already in main; do not recreate them.

## New-session prompt

> Continue thuekx/Nostek-NMSOLT from GitHub. Read HANDOFF.md, ROADMAP.md,
> PROJECT-STATUS.yaml, STATUS-TREE.md and NMS-07-UNIFIED-PACKAGE.md; verify main,
> open PRs and exact-head CI. NMS-00 through NMS-06 are VERIFIED; NMS-07-A unified
> package is on branch step/nms-07-a-unified-package; runtime target is the
> Nostek-AI VM nostek-platform (track NET-1). NMSOLT is
> the standalone network platform/API provider. Maung and Nostek-AI are
> consumers; a future Maung /nosteknmsolt page is a thin API settings and
> quick-view surface, not a polling or database layer. Continue NMS-07 product
> stabilization, then API v1/OpenAPI, the Maung consumer, Nostek-AI tools, write
> governance and release readiness. Do not change production, Caddy, maung_edge,
> Maung/CRM/WO repos, secrets or devices without their separate gates. Never
> claim VERIFIED without evidence.
