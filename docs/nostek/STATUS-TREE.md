# Nostek-NMSOLT — project status tree

Updated **2026-10-22 WIB**. State reflects GitHub evidence, not intended plans.
See [PROJECT-STATUS.yaml](PROJECT-STATUS.yaml) for the machine-readable checkpoint.

```text
Nostek-NMSOLT — STANDALONE NETWORK PLATFORM / API PROVIDER
|
+-- NMS-00 [VERIFIED] Public source baseline
+-- NMS-01 [VERIFIED] Repeatable CI and image builds
+-- NMS-02 [VERIFIED] Default-off device-write boundary
+-- NMS-03 [VERIFIED] Maung host read-only discovery
+-- NMS-04 [VERIFIED] Isolated Maung staging
+-- NMS-05 [VERIFIED] Isolated GenieACS lab; F660 Inform/read task
+-- NMS-06 [VERIFIED] ZTE C320 read-only runtime validation
|   +-- PR #18 / run 39648137609 / head 902f526 / merge c639f12
|   +-- SNMP, PON/ONU inventory, optical detail and UI evidence passed
|
+-- NMS-07 [IN PROGRESS] NMSOLT product stabilization
|   +-- canonical identity: Nostek-NMSOLT; short name: Nostek-NMS
|   +-- PASS: PR #21 / run 39770297399 / merge f43baa3; nos-* identity enforced
|   +-- PR #20 MERGED: 1a38c6b; exact-head fc5ce840 / CI 39773898154 PASS
|   +-- A [SOURCE PASS, PR merged] unified package core/with-acs + platform overlay,
|   |       nmsolt-ops.sh (preflight/status/backup/restore/upgrade/rollback), checks.json;
|   |       workspace ops integration 58/58 (real PG/Redis/Mongo/GenieACS, stand-in app)
|   +-- B [SOURCE PASS, PR merged] history:prune retention, duplicate RX index dropped,
|   |       poll queue + worker pool
|   |   +-- B2 [SOURCE PASS, PR merged] daily RX rollup 3 y, 90d/1y/3y charts,
|   |           hourly aggregation stall after >48 h polling gap fixed
|   +-- C [MEASURED, NOT ADOPTED] onu_state read model 25 % slower than the snapshot
|   |       path (10k/51k ONUs, PostgreSQL); dropped, numbers in NMS-07-PERFORMANCE.md
|   +-- D [IN PROGRESS] D-1 write gate visible (banner, request guard, 56 locked controls,
|   |       browser-checked; PR merged); D-2a stale never green (PR merged);
|   |       D-2b ONU monitor/map/alarms/C-Data/HiOSO stale states (PR merged);
|   |       D-2c partial RX + role sweep (PR merged)
|   +-- E [IN PROGRESS] ONU list/search measured (4.5k/10k/51k); E2 ONU monitor scoped to
|           one OLT (PR merged); E3 alarm page 3x faster + RX charts measured
|           (PR merged); E4 Go poller vs simulated C300 measured (PR merged);
|           host-only: queue latency, real-OLT max-repetitions check
|   +-- A2 [SOURCE PASS, PR merged] `build` also builds the local GenieACS image;
|           `start`/`preflight` check locally built images (finding from NET-1-2)
+-- TRF-1 [SOURCE PASS, PR merged] PON port traffic graph: IF-MIB octet counters (Go poller),
|   per-port rates raw 8 d / hourly 100 d / daily 3 y, chart on the PON port page;
|   real-OLT counters VERIFIED (192.168.254.2, owner read-only); per-ONT: GenieACS ON HOLD,
|   ZTE SNMP per-ONU counters measured, not accurate (not built);
|   TRF-4 live per-ONT traffic card on the ONU page via read-only CLI
+-- NMS-08 [BLOCKED] API v1 read-only + OpenAPI
|   +-- stable IDs, freshness, pagination, scopes, redaction, rate limits
+-- NMS-09 [BLOCKED] Maung /nosteknmsolt thin consumer
|   +-- API settings, summaries, alarms, search and quick customer/ONU detail
+-- NMS-10 [BLOCKED] Nostek-AI tool adapter (Nostek-AI track NET-1-5)
|   +-- host broker + stdio MCP nms_* (owner-only) over API v1; no direct DB/device credentials
+-- NMS-11 [BLOCKED] High-risk write governance
|   +-- explicit preview, approval, single-use execution, verification and audit
+-- NMS-12 [BLOCKED] Release and production readiness
|
+-- RUNTIME [DECIDED] nostek-platform via Nostek-AI track NET-1 (install = NET-1-2 package,
|   wraps nmsolt-ops.sh); Maung staging retired after migration
+-- MOBILE [DEFERRED] Not distributed
+-- PRODUCTION/DEVICE WRITES [GATED] Not authorized
```

## Architecture boundary

- NMSOLT owns polling, device drivers, GenieACS integration, telemetry history and network-domain logic.
- Maung and Nostek-AI are consumers of versioned NMSOLT APIs/tools.
- A future Maung `/nosteknmsolt` page is a thin integration and quick-view surface; it must not duplicate NMSOLT collectors or query its database directly.
- Another NMSOLT deployment is optional only when capacity, tenant or security isolation requires it—not because a consumer needs a page.

## Durable evidence

- Verified runtime chain: PRs [#1](https://github.com/thuekx/Nostek-NMSOLT/pull/1) through
  [#21](https://github.com/thuekx/Nostek-NMSOLT/pull/21), with phase-specific references in
  [HANDOFF.md](HANDOFF.md).
- Readiness details: [NMS-04](NMS-04-STAGING-READINESS.md),
  [NMS-05](NMS-05-GENIEACS-LAB-MATRIX.md),
  [NMS-06](NMS-06-FTTH-CAPABILITY-MATRIX.md), and
  [NMS-07–12](NMS-07-12-GATED-READINESS.md).
- Definitions: [ROADMAP.md](ROADMAP.md); resume: [HANDOFF.md](HANDOFF.md).

NMS-00 through NMS-06 satisfy their definitions of done. NMS-07 is active for
safe source-level work; NMS-08 through NMS-12 remain dependency- or approval-gated.

PR #20 is merged as `1a38c6b309a84f07db89398f2f26d727d92c3128` after owner approval.
All five CI jobs passed on `fc5ce840bcc548479a308e1de16fc292f1d21e49` in run
39773898154. Merge tree matches the tested head; no separate merge-SHA run is
claimed. NMS-07 implementation resumed 2026-10-22 (owner prompt); NMS-07-A is on
branch `step/nms-07-a-unified-package`, see [NMS-07-UNIFIED-PACKAGE.md](NMS-07-UNIFIED-PACKAGE.md).
