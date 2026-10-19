# Nostek-NMSOLT — project status tree

Updated **2026-10-19 WIB**. State reflects GitHub evidence, not merely intended plans. Open [PROJECT-STATUS.yaml](PROJECT-STATUS.yaml) for machine-readable checkpoint.

```text
Nostek-NMSOLT — ONE SOURCE / TWO INDEPENDENT DOCKER DEPLOYMENTS
|
+-- NMS-00 [VERIFIED] Git source baseline and public identity
|   +-- thuekx/Nostek-NMSOLT main: 24332a2
|   +-- public repository, 678 files; mobile excluded
|   +-- MIT attribution retained, Gitleaks/whitespace/prepush checks PASS
|
+-- NMS-01 [VERIFIED] Repeatable CI and Docker build
|   +-- [PASS] PR #1 run 39278668150 on b20149c
|   +-- [PASS] Compose base/ACS and loopback publishing
|   +-- [PASS] NMSOLT + GenieACS image builds (no push)
|   +-- [PASS] Go test/vet; Vue 57 tests
|   +-- [PASS] Laravel 122 tests / 4,505 assertions / 0 failures
|   +-- [NOTE] 660 non-fatal warnings remain visible as backlog
|   +-- [MERGED] main 63f4b77
|
+-- NMS-02 [IN_PROGRESS] Source security, read-only access and write isolation
|   +-- [OPEN] Draft PR #2, head ac425b7
|   +-- [PENDING] five-job CI and final review
|
+-- NMS-03 [BLOCKED] VM maung-apps resource/port/network audit (R0)
|   +-- [OPEN] Draft PR #3 with read-only collector
|   +-- [BLOCKER] authorized host execution and sanitized evidence
+-- NMS-04 [PENDING] Independent NMSOLT staging on maung-apps
+-- NMS-05 [PENDING] GenieACS + MongoDB optional lab and approved ONT
+-- NMS-06 [PENDING] FTTH polling, optical RX, alarms, ODP validation
+-- NMS-07 [PENDING] Maung Super Apps SSO, RBAC, /nmsolt ingress
+-- NMS-08 [PENDING] Maung WO/CRM inventory and event integration
+-- NMS-09 [PENDING] Approved Maung production go-live + rollback
+-- NMS-10 [PENDING] Second Docker installation for Nostek AI, R0
+-- NMS-11 [PENDING] Broker-governed high-risk OLT/ONT actions
+-- NMS-12 [PENDING] Product release, operations and commercial plan
|
+-- MOBILE [DEFERRED] Flutter not distributed / remains outside initial repo
+-- PRODUCTION CHANGES [GATED] No OLT/ONT write or VM deployment authorized yet
```

## Evidence and definition of done

- CI PR: https://github.com/thuekx/Nostek-NMSOLT/pull/1
- Verified CI run: https://github.com/thuekx/Nostek-NMSOLT/actions/runs/39278668150
- NMS-01 merge: https://github.com/thuekx/Nostek-NMSOLT/commit/63f4b77b002fa5ef9b1189d4a4c982c0246a1354
- NMS-02 draft: https://github.com/thuekx/Nostek-NMSOLT/pull/2
- NMS-03 draft: https://github.com/thuekx/Nostek-NMSOLT/pull/3
- Baseline: https://github.com/thuekx/Nostek-NMSOLT/commit/24332a2b4144cff03f04f64e4711ce7938d992ae
- Definition of each gate: [ROADMAP.md](ROADMAP.md)
- Resume instructions: [HANDOFF.md](HANDOFF.md)

A partial PASS within a phase does **not** mean the whole phase is VERIFIED. NMS-02 remains unverified until its own CI and review complete; NMS-03 remains blocked until host evidence exists.
