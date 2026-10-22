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

> Continue `thuekx/Nostek-NMSOLT` from current GitHub main in a Codex Cloud
> environment. Read the six canonical files under `docs/nostek`, verify main,
> open PRs and exact-head CI, then continue NMS-07 automatically through safe
> source work. Nostek-NMSOLT is the provider; Maung and Nostek-AI are consumers.
> GenieACS must ship as a bundled subsystem in the full NMSOLT package while
> remaining isolated internally. Preserve Laravel/Vue/Go unless profiling proves
> otherwise. Do not access or change production, Maung, Caddy, maung_edge,
> CRM/WO, secrets or live OLT/ONT. Keep device writes disabled. Use branches,
> tests, PR review and exact evidence; merge and delete branches only when all
> gates pass. Mark external or approval-dependent steps BLOCKED and continue
> independent safe work.
