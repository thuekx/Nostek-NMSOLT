# Nostek-NMSOLT — project status tree

Updated **2026-10-20 WIB**. State reflects GitHub evidence, not intended plans.
See [PROJECT-STATUS.yaml](PROJECT-STATUS.yaml) for the machine-readable checkpoint.

```text
Nostek-NMSOLT — ONE SOURCE / TWO INDEPENDENT DOCKER DEPLOYMENTS
|
+-- NMS-00 [VERIFIED] Git source baseline and public identity
|   +-- initial commit 24332a2; mobile excluded; MIT attribution retained
+-- NMS-01 [VERIFIED] Repeatable CI and image builds
|   +-- PR #1 / run 39278668150 / head b20149c / merge 63f4b77
|   +-- Compose + images + Go + Vue 57 + Laravel 122 PASS
+-- NMS-02 [VERIFIED] Default-off device-write boundary
|   +-- PR #2 / run 39284353892 / head 0fcb962 / merge 732debb
|   +-- route, queued job and Telegram boundaries covered
|
+-- NMS-03 [BLOCKED] Maung read-only discovery
|   +-- readiness PR #3 / run 39290385687 / merge f925529
|   +-- BLOCKER: authorized host run + sanitized resource/network/backup evidence
+-- NMS-04 [BLOCKED] Isolated Maung staging
|   +-- source readiness PR #5 / run 39291789107 / merge f382366
|   +-- PASS: base/ACS rendered topology policy
|   +-- BLOCKER: NMS-03 + approved deploy/restore/rollback evidence
+-- NMS-05 [BLOCKED] Isolated GenieACS lab
|   +-- source readiness PR #6 / run 39294335613 / merge f5917c0
|   +-- PASS: GenieACS version/non-root/private-service/loopback policy
|   +-- BLOCKER: prerequisites + approved lab ONT inform/read evidence
+-- NMS-06 [BLOCKED] FTTH operations validation
|   +-- source readiness PR #7 / run 39295106373 / merge 9c6722c
|   +-- PASS: reviewed family routing + unknown vendor fail-closed
|   +-- BLOCKER: approved per-model polling/RX/stale/alarm/ODP/UI lab evidence
+-- NMS-07 [BLOCKED] Maung SSO/RBAC and /nmsolt routing
|   +-- PREPARED: fail-closed identity and reversible routing evidence contract
|   +-- BLOCKER: Maung identity schema + approved staging/proxy test
+-- NMS-08 [BLOCKED] Maung WO/CRM integration
|   +-- PREPARED: ownership, external IDs, provenance and idempotency contract
|   +-- BLOCKER: approved contracts + synthetic integration environment
+-- NMS-09 [BLOCKED] Controlled Maung production go-live
|   +-- BLOCKER: NMS-03..08 VERIFIED + explicit change approval
+-- NMS-10 [BLOCKED] Second Nostek AI deployment
|   +-- BLOCKER: resource/security evidence + host decision
+-- NMS-11 [BLOCKED] High-risk action governance
|   +-- PREPARED: preview/approval/single-use execution/verification/audit lifecycle
|   +-- BLOCKER: broker + lab proof + explicit per-action approval
+-- NMS-12 [BLOCKED] Release/commercial readiness
|   +-- PREPARED: release/DR/observability/isolation/SBOM/license checklist
|   +-- BLOCKER: upstream gates + product/release decisions
|
+-- MOBILE [DEFERRED] Not distributed
+-- PRODUCTION/DEVICE WRITES [GATED] Not authorized
```

## Durable evidence

- PRs: [#1](https://github.com/thuekx/Nostek-NMSOLT/pull/1),
  [#2](https://github.com/thuekx/Nostek-NMSOLT/pull/2),
  [#3](https://github.com/thuekx/Nostek-NMSOLT/pull/3),
  [#4](https://github.com/thuekx/Nostek-NMSOLT/pull/4),
  [#5](https://github.com/thuekx/Nostek-NMSOLT/pull/5),
  [#6](https://github.com/thuekx/Nostek-NMSOLT/pull/6),
  [#7](https://github.com/thuekx/Nostek-NMSOLT/pull/7)
- Readiness details: [NMS-04](NMS-04-STAGING-READINESS.md),
  [NMS-05](NMS-05-GENIEACS-LAB-MATRIX.md),
  [NMS-06](NMS-06-FTTH-CAPABILITY-MATRIX.md),
  [NMS-07–12](NMS-07-12-GATED-READINESS.md).
- Definitions: [ROADMAP.md](ROADMAP.md); resume: [HANDOFF.md](HANDOFF.md).

A readiness PASS does not make the phase VERIFIED. Only NMS-00 through NMS-02
currently satisfy their full definitions of done.
