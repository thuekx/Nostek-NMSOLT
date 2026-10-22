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
|   +-- inherited kv-* namespace and kv poller are being replaced by nos-*
|   +-- audit functions, screens, read paths and performance
|   +-- repair misleading/broken behavior and add regression evidence
+-- NMS-08 [BLOCKED] API v1 read-only + OpenAPI
|   +-- stable IDs, freshness, pagination, scopes, redaction, rate limits
+-- NMS-09 [BLOCKED] Maung /nosteknmsolt thin consumer
|   +-- API settings, summaries, alarms, search and quick customer/ONU detail
+-- NMS-10 [BLOCKED] Nostek-AI Apps/tool adapter
|   +-- allow-listed read tools over NMSOLT API; no direct DB/device credentials
+-- NMS-11 [BLOCKED] High-risk write governance
|   +-- explicit preview, approval, single-use execution, verification and audit
+-- NMS-12 [BLOCKED] Release and production readiness
|
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
  [#18](https://github.com/thuekx/Nostek-NMSOLT/pull/18), with phase-specific references in
  [HANDOFF.md](HANDOFF.md).
- Readiness details: [NMS-04](NMS-04-STAGING-READINESS.md),
  [NMS-05](NMS-05-GENIEACS-LAB-MATRIX.md),
  [NMS-06](NMS-06-FTTH-CAPABILITY-MATRIX.md), and
  [NMS-07–12](NMS-07-12-GATED-READINESS.md).
- Definitions: [ROADMAP.md](ROADMAP.md); resume: [HANDOFF.md](HANDOFF.md).

NMS-00 through NMS-06 satisfy their definitions of done. NMS-07 is active for
safe source-level work; NMS-08 through NMS-12 remain dependency- or approval-gated.
