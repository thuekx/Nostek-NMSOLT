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
Further NMS-07 implementation is currently paused by owner; this closeout only
updates documentation. A future implementation session must be explicitly started.

## Active work

NMS-07 is IN PROGRESS:

1. preserve the completed identity standard: `Nostek-NMSOLT`, short name
   `Nostek-NMS`, internal `nos-*` namespace and `nos-snmp-poller`;
2. preserve the existing LICENSE attribution required by the inherited MIT code;
3. unify the full installation path for app/PostgreSQL/Redis plus
   GenieACS/MongoDB with private internal services and explicitly bound CWMP;
4. add unified health, preflight, backup/restore, upgrade and rollback evidence;
5. audit functions, UI journeys, stale/partial states and performance;
6. proceed to NMS-08 only when NMS-07 has complete evidence.

## Required first actions in Cloud

Read `docs/nostek/HANDOFF.md`, `ROADMAP.md`, `PROJECT-STATUS.yaml`,
`STATUS-TREE.md`, `NMS-07-PRODUCT-STABILIZATION-AUDIT.md` and this file. Verify
the current `main` SHA, open PRs and exact-head CI. Never infer VERIFIED from a
plan or a green documentation-only test.

## Copy-ready Cloud prompt

Use the following prompt only when ready to resume implementation:

```text
Continue thuekx/Nostek-NMSOLT from current GitHub main in a Codex Cloud environment.
Read docs/nostek/HANDOFF.md, ROADMAP.md, PROJECT-STATUS.yaml, STATUS-TREE.md,
NMS-07-PRODUCT-STABILIZATION-AUDIT.md and CLOUD-HANDOFF.md; also read
NMS-07-FEATURE-MATRIX.md and NMS-07-SOURCE-INVENTORY.json. Follow applicable AGENTS.md.

Refresh main, open PRs, reviews and exact-head CI before source changes.
PR #20 is already merged as 1a38c6b309a84f07db89398f2f26d727d92c3128.
Its tested head fc5ce840bcc548479a308e1de16fc292f1d21e49 passed all five jobs:
https://github.com/thuekx/Nostek-NMSOLT/actions/runs/39773898154
The squash merge tree matches that head; no separate merge-SHA run is claimed.
Search batching, MAC navigation, input validation and regression tests are in main.
Do not recreate them or treat PR #20 as open. PR #21 identity cleanup remains intact.
No further NMS-07 implementation followed that merge; the owner paused work to
complete this documentation handoff. This new prompt authorizes resuming safe source work.
NMS-00–06 are recorded VERIFIED; NMS-07 IN_PROGRESS; NMS-08–12 BLOCKED.

Nostek-NMSOLT is the standalone network platform and telemetry/API provider.
Maung and Nostek-AI are consumers via versioned APIs/tools, without direct database
access or duplication of polling, drivers, GenieACS, history or device credentials.
GenieACS must ship bundled in the full app/PostgreSQL/Redis + GenieACS/MongoDB package
while retaining internal container/network/volume isolation. NBI/FS/Mongo private,
UI loopback, CWMP bound only to explicitly approved addresses.
Preserve Laravel/Vue/Inertia and Go unless measured profiling and a separate decision
justify migration. Preserve Nostek-NMSOLT/Nostek-NMS, nos-* namespace,
cmd/nos-snmp-poller, bin/nos-snmp-poller and required MIT LICENSE attribution.

Continue NMS-07: unified core/with-acs installation (recommended full with-acs),
preflight/config validation, health/status, PostgreSQL/storage/Mongo backup/restore,
pinned images/versions and reversible upgrade/rollback. Then audit UI journeys,
stale/partial/unavailable/permission states, write-gate visibility, and measured
ONU/alarm/search/optical/query/memory/queue/poller/retention performance.
Use isolated synthetic tests and Compose rendering; do not run host installers/deployments.

Do not access/change production, Maung hosts/repositories, Caddy, maung_edge,
CRM/WO, Nostek-AI runtime, secrets or live OLT/ONT. Device writes stay disabled.
Mark external/approval-dependent tasks BLOCKED and continue independent safe source work.
Use feature branches, PR review and exact-head tests/CI. Owner approval for PR #20
does not authorize merging future PRs. Never bypass a gate or approval.
Synchronize handoff, roadmap, YAML, status tree, audit and Cloud prompt after work.
Do not claim NMS-07 VERIFIED or unblock NMS-08 without all acceptance evidence.
Continue safe independent work within the active session until complete or genuinely blocked.
```
