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

Raw device identifiers, communities and screenshots containing customer/serial
data remain outside the public repository.

## Active and gated phases

- **NMS-07 IN PROGRESS:** audit and stabilize NMSOLT functions, UI, read paths
  and performance; create a feature matrix and fix high-impact defects with
  regression evidence.
- **NMS-08 BLOCKED on NMS-07:** API v1 read-only and OpenAPI.
- **NMS-09 BLOCKED on NMS-08 and separate Maung scope:** `/nosteknmsolt`
  thin consumer.
- **NMS-10 BLOCKED on NMS-08:** Nostek-AI Apps/tool adapter.
- **NMS-11 BLOCKED:** write governance; writes remain globally disabled.
- **NMS-12 BLOCKED:** release and production readiness.

See [NMS-07-12-GATED-READINESS.md](NMS-07-12-GATED-READINESS.md) for exact gates.

## Current source audit (22 October 2026)

GitHub recheck: PR #1 is merged (`63f4b77`); PR #19 is already merged as
`62b414f`, so the former instruction to merge it is obsolete. No open PR was
present at the initial recheck. Latest existing CI run `39652098876` passed all
five jobs on PR #19 head `18b4735`; main has the identical tree
`44c11c13e9988485bd106a77a6075efbc3b87383`, but no separate main-SHA run.

NMS-07 source patch: bounded lazy search snapshots, preserved MAC-only deep-link
filter, validated search inputs and synthetic regression tests. See
[NMS-07-FEATURE-MATRIX.md](NMS-07-FEATURE-MATRIX.md) and the source inventory JSON.
Full NMS-07 remains IN_PROGRESS: UI journeys/write visibility, measured large-data
performance and unified ACS lifecycle packaging remain open. NMS-08–12 statuses
are unchanged; source tests do not satisfy their runtime/approval gates.

## Exact next action

Check the NMS-07 search-audit PR and exact-head CI. Resolve failures before
requesting review/merge approval. Do not merge automatically. Continue remaining
NMS-07 checklist work on the review branch while within authorized source scope.
After NMS-07 is fully verified, proceed to NMS-08; keep NMS-09 Maung repository
work, live operations and releases separately approval-gated.

## New-session prompt

> Continue thuekx/Nostek-NMSOLT from GitHub. Read HANDOFF.md, ROADMAP.md,
> PROJECT-STATUS.yaml and STATUS-TREE.md; verify main, open PRs and exact-head
> CI. NMS-00 through NMS-06 are VERIFIED; PR #18 merged as c639f127. NMSOLT is
> the standalone network platform/API provider. Maung and Nostek-AI are
> consumers; a future Maung /nosteknmsolt page is a thin API settings and
> quick-view surface, not a polling or database layer. Continue NMS-07 product
> stabilization, then API v1/OpenAPI, the Maung consumer, Nostek-AI tools, write
> governance and release readiness. Do not change production, Caddy, maung_edge,
> Maung/CRM/WO repos, secrets or devices without their separate gates. Never
> claim VERIFIED without evidence.
