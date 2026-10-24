# Nostek-NMSOLT — Codex Cloud handoff

This handoff starts from the completed NMS-07 identity checkpoint on GitHub main. A Cloud
task starts from GitHub; it cannot see unpushed files, the Maung VM, private
management networks or credentials stored on the original PC.

## Cloud task setup

1. Create/select a Codex Cloud environment for `thuekx/Nostek-NMSOLT`.
2. Start from the repository default branch `main` after refreshing it.
3. Do not add production, Maung, OLT/ONT, SNMP, ACS or Caddy credentials.
4. Allow only repository dependency installation and isolated CI-equivalent tests.
5. Use a feature branch and GitHub PR for every change.

## Canonical context

- NMS-00 through NMS-06 are VERIFIED.
- PR #19 merged the provider-centric roadmap as `62b414f04ac4b5e4f024669e431be29f4d0845d1`.
- PR #21 passed all five gates in run 39770297399 and merged the canonical identity/namespace as `f43baa3fa0f4959b8513011e01e79a6db8509f51`.
- Nostek-NMSOLT is the standalone network platform and data/API provider.
- Maung and Nostek-AI are consumers. A future Maung `/nosteknmsolt` page is a
  thin API/settings/quick-view surface, not a polling or database layer.
- GenieACS is a bundled Nostek-NMSOLT subsystem implemented as isolated
  containers; it is not intended to require a separately managed product server.
- Laravel/Vue/Inertia and the Go poller remain until profiling proves a rewrite
  is justified.
- Device writes, production, Caddy, `maung_edge`, Maung/CRM/WO repositories and
  secrets remain outside Cloud scope.

## PR #20 merged checkpoint

PR #20 is MERGED as `1a38c6b309a84f07db89398f2f26d727d92c3128` with owner approval.
All five CI jobs passed on exact head `fc5ce840bcc548479a308e1de16fc292f1d21e49` in
[run 39773898154](https://github.com/thuekx/Nostek-NMSOLT/actions/runs/39773898154). The squash merge tree matches
the tested head; no separate merge-SHA workflow execution is claimed.
The search changes are already in main; do not recreate or re-merge PR #20.
The source inventory retains its original audit provenance. Refresh main, open
PRs and CI on restart; this checkpoint is not a claim that main can never move.
NMS-07 implementation resumed on 2026-10-22 (owner prompt). NMS-07-A unified package
is on branch `step/nms-07-a-unified-package` (see NMS-07-UNIFIED-PACKAGE.md).

## Active work

NMS-07 is IN PROGRESS:

1. preserve the completed identity standard: `Nostek-NMSOLT`, short name
   `Nostek-NMS`, internal `nos-*` namespace and `nos-snmp-poller`;
2. preserve the existing LICENSE attribution required by the inherited MIT code;
3. NMS-07-A (merged, PR #24): unified core/with-acs package, platform
   bind-mount overlay, `scripts/nostek/nmsolt-ops.sh`, `monitor/checks.json`,
   ops integration test (CI job `ops-integration`);
4. NMS-07-B data growth (retention, duplicate RX index, poll worker pool; B2 daily RX
   rollup on step/nms-07-b2-rx-daily), C `onu_state` table measured and not adopted (NMS-07-PERFORMANCE.md),
   D UI journey and write-gate audit (D-1, D-2a/b/c), E2/E3/E4: all merged (#24-#34);
   TRF-1 PON traffic graph merged (PR #35); NMS-07-A2 GenieACS image build merged
   (PR #36);
5. runtime target is the Nostek-AI VM `nostek-platform`; host steps are Nostek-AI
   packages (track NET-1), never run from this repository;
6. proceed to NMS-08 only when NMS-07 has complete evidence.

## Required first actions in Cloud

Read `docs/nostek/HANDOFF.md`, `ROADMAP.md`, `PROJECT-STATUS.yaml`,
`STATUS-TREE.md`, `NMS-07-PRODUCT-STABILIZATION-AUDIT.md`,
`NMS-07-UNIFIED-PACKAGE.md` and this file. Verify
the current `main` SHA, open PRs and exact-head CI. Never infer VERIFIED from a
plan or a green documentation-only test.

## Copy-ready Cloud prompt

Use the following prompt only when ready to resume implementation:

```text
Continue thuekx/Nostek-NMSOLT from current GitHub main.
Read docs/nostek/HANDOFF.md, ROADMAP.md, PROJECT-STATUS.yaml, STATUS-TREE.md,
NMS-07-PRODUCT-STABILIZATION-AUDIT.md, NMS-07-UNIFIED-PACKAGE.md, NMS-07-FEATURE-MATRIX.md,
NMS-07-SOURCE-INVENTORY.json and CLOUD-HANDOFF.md. Follow applicable AGENTS.md.

Refresh main, open PRs, reviews and exact-head CI before source changes.
NMS-00–06 are VERIFIED; NMS-07 IN_PROGRESS; NMS-08–12 BLOCKED.
NMS-07-A (unified core/with-acs package, compose.platform.yaml, scripts/nostek/nmsolt-ops.sh,
monitor/checks.json, tests/ops/integration.sh) is merged (PR #24). Do not recreate it.

Nostek-NMSOLT is the standalone network platform and telemetry/API provider.
Maung and Nostek-AI are consumers via versioned APIs/tools, without direct database
access or duplication of polling, drivers, GenieACS, history or device credentials.
Runtime target: Nostek-AI VM nostek-platform (owner decisions NMS-AI-Q1…Q9); host steps are
Nostek-AI packages (track NET-1), never run from this repository.
Preserve Laravel/Vue/Inertia and Go unless measured profiling and a separate decision
justify migration. Preserve Nostek-NMSOLT/Nostek-NMS, nos-* namespace,
cmd/nos-snmp-poller, bin/nos-snmp-poller and required MIT LICENSE attribution.

The NMS-07 PRs #24-#34 (A, B, C/E, B2, D-1, D-2a/b/c, E2, E3, E4) are merged. NMS-07 host/owner
evidence remains (real app image, queue latency under real workers, real-OLT max-repetitions
check). TRF-1 PON traffic graph (owner decision: PON first, ONT later) is merged (PR #35,
docs/nostek/TRF-1-PON-TRAFFIC.md). NMS-07-A2 (build also makes the GenieACS image, start refuses
a missing local image) is merged (PR #36). Per-ONT traffic is not built: GenieACS ON HOLD (lab
ACS only), ZTE C320 V2.1.0 per-ONU SNMP counters measured and not accurate (TRF-1-PON-TRAFFIC.md,
"Per-ONT findings"); TRF-4 shows live per-ONT traffic on the ONU page from the read-only CLI (no history). WRITE-UI-1 (admin switch for device writes in Settings, env value `admin`; `false` stays a hard off) is merged (PR #40). The onu_state table was measured
and not adopted (NMS-07-PERFORMANCE.md); do not re-add it without new evidence.
Use isolated synthetic tests and Compose rendering; do not run host installers/deployments.

Do not access/change production, Maung hosts/repositories, Caddy, maung_edge,
CRM/WO, Nostek-AI runtime, secrets or live OLT/ONT. Device writes stay disabled.
Mark external/approval-dependent tasks BLOCKED and continue independent safe source work.
Use step/ branches, PR review and exact-head CI. Never merge; the owner merges.
Synchronize handoff, roadmap, YAML, status tree, audit and this prompt after work.
Do not claim NMS-07 VERIFIED or unblock NMS-08 without all acceptance evidence.
```
