# Nostek-NMSOLT — project status tree

Updated **2026-10-21 WIB**. State reflects GitHub evidence, not intended plans.
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
+-- NMS-03 [VERIFIED] Maung read-only discovery
|   +-- collector executed 2026-10-20T00:52:49+07:00; raw SHA-256 0187497B...1844ECB
|   +-- 8 vCPU; ~13 GiB available RAM; 43 GiB root free
|   +-- current Docker/ports/networks/proxy/backup inventory sanitized
|   +-- bounded NMS-04 base budget: 2 vCPU / 3 GiB / 12 GiB
+-- NMS-04 [VERIFIED] Isolated Maung staging
|   +-- runner PR #11 / run 39449952838 / merge 3ad09b8
|   +-- permission fix PR #12 / run 39470245938 / merge 4b7437c
|   +-- PASS: loopback-only base stack, writes disabled, migrations and processes healthy
|   +-- PASS: backup restore 38/38 tables; bounded resources; rollback/restart
|   +-- PASS: pre-existing containers unchanged; Caddy and maung_edge unchanged
+-- NMS-05 [VERIFIED] Isolated GenieACS lab
|   +-- overlay PR #14; network repair PR #15; host guard PR #16
|   +-- PASS: one approved ZTE F660 Bootstrap + Periodic Inform
|   +-- PASS: local serial match and compatible read-only parameter task
|   +-- PASS: 0 faults/tasks/presets; Mongo persistence and ACS rollback
|   +-- PASS: base stack remained healthy; no device/production write
+-- NMS-06 [BLOCKED] FTTH operations validation FTTH operations validation
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
  [#7](https://github.com/thuekx/Nostek-NMSOLT/pull/7),
  [#8](https://github.com/thuekx/Nostek-NMSOLT/pull/8),
  [#9](https://github.com/thuekx/Nostek-NMSOLT/pull/9),
  [#10](https://github.com/thuekx/Nostek-NMSOLT/pull/10),
  [#11](https://github.com/thuekx/Nostek-NMSOLT/pull/11),
  [#12](https://github.com/thuekx/Nostek-NMSOLT/pull/12),
  [#13](https://github.com/thuekx/Nostek-NMSOLT/pull/13),
  [#14](https://github.com/thuekx/Nostek-NMSOLT/pull/14),
  [#15](https://github.com/thuekx/Nostek-NMSOLT/pull/15),
  [#16](https://github.com/thuekx/Nostek-NMSOLT/pull/16)
- Readiness details: [NMS-04](NMS-04-STAGING-READINESS.md),
  [NMS-05](NMS-05-GENIEACS-LAB-MATRIX.md),
  [NMS-06](NMS-06-FTTH-CAPABILITY-MATRIX.md),
  [NMS-07–12](NMS-07-12-GATED-READINESS.md).
- Definitions: [ROADMAP.md](ROADMAP.md); resume: [HANDOFF.md](HANDOFF.md).

A readiness PASS does not make the phase VERIFIED. NMS-00 through NMS-05
currently satisfy their full definitions of done; NMS-06 and later remain blocked.
