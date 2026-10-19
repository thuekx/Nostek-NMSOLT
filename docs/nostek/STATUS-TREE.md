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
+-- NMS-01 [BLOCKED] Repeatable CI and Docker build
|   +-- [PASS] base and ACS Docker Compose config
|   +-- [PASS] NMSOLT and GenieACS image builds
|   +-- [PASS] Go SNMP poller tests/vet
|   +-- [PASS] Vue / JS tests
|   +-- [FAIL] Laravel tests: 98 fail / 562 warnings / 122 pass
|   +-- [OPEN] Draft PR #1 (not merged)
|   +-- [NEXT] Vite assets in CI, locale assertions, fixture warnings
|
+-- NMS-02 [PENDING] Source security, read-only access and write isolation
+-- NMS-03 [PENDING] VM maung-apps resource/port/network audit (R0)
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
- Failed CI run: https://github.com/thuekx/Nostek-NMSOLT/actions/runs/39275735589
- Baseline: https://github.com/thuekx/Nostek-NMSOLT/commit/24332a2b4144cff03f04f64e4711ce7938d992ae
- Definition of each gate: [ROADMAP.md](ROADMAP.md)
- Resume instructions: [HANDOFF.md](HANDOFF.md)

A partial PASS within a phase does **not** mean the whole phase is VERIFIED. Never merge a failing CI PR for convenience.
