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
- **PR #19:** exact head
  `18b473545884ddf49d65847827e3473da3d02744`, successful
  [run 39652098876](https://github.com/thuekx/Nostek-NMSOLT/actions/runs/39652098876),
  squash merge `62b414f04ac4b5e4f024669e431be29f4d0845d1`. The provider-centric
  roadmap and bundled-GenieACS product direction are canonical.

- **PR #21:** exact head
  `6bc53a374e5ed5fc2209fb88dbfe7c105131cb23`, successful
  [run 39770297399](https://github.com/thuekx/Nostek-NMSOLT/actions/runs/39770297399),
  squash merge `f43baa3fa0f4959b8513011e01e79a6db8509f51`. Go, Vue,
  Laravel, Compose, both image builds and the fail-closed Nostek namespace
  validator passed. The PR branch was deleted.

Raw device identifiers, communities and screenshots containing customer/serial
data remain outside the public repository.

## Active and gated phases

- **NMS-07 IN PROGRESS:** audit and stabilize Nostek-NMSOLT functions, UI,
  packaging, read paths and performance. The active cleanup standardizes the
  product name as **Nostek-NMSOLT**, short name **Nostek-NMS**, internal
  `nos-*` namespace and `nos-snmp-poller`. The upstream MIT copyright notice
  remains unchanged.
- **NMS-08 BLOCKED on NMS-07:** API v1 read-only and OpenAPI.
- **NMS-09 BLOCKED on NMS-08 and separate Maung scope:** `/nosteknmsolt`
  thin consumer.
- **NMS-10 BLOCKED on NMS-08:** Nostek-AI Apps/tool adapter.
- **NMS-11 BLOCKED:** write governance; writes remain globally disabled.
- **NMS-12 BLOCKED:** release and production readiness.

See [NMS-07-12-GATED-READINESS.md](NMS-07-12-GATED-READINESS.md) for exact gates.

## Search audit alignment (PR #20)

PR #20 retains bounded snapshot loading, MAC-only navigation, query validation
and synthetic regression coverage. Its original head `02949e56e41967956938dcb716c7003c91edc957`
passed all five jobs in run `39715103922`. That evidence applies only to the
original head, not to this alignment with main `b0cbf36ac6f59552a3ef4f3a01f187997d221f86` (PR #22).
The earlier failed fixture run `39714702681` remains historical evidence.

Main's completed PR #21 identity cleanup and PR #22 Cloud checkpoint take
precedence over the older PR #20 baseline. This branch merges that main without
reverting the canonical Nostek-NMSOLT/Nostek-NMS, nos-* namespace or LICENSE.
The feature matrix and source inventory retain their original audit baseline;
new UI/package/performance work remains unverified.

Before merge: inspect the current PR head, require all five CI jobs on that
exact head, review the combined diff and obtain owner merge approval. Never use
the old green run as proof of the combined tree. No deployment or runtime/device
validation is implied. NMS-07 remains IN_PROGRESS and NMS-08–12 remain BLOCKED.

## Exact next action

Identity cleanup is complete and merged. Continue NMS-07 with unified GenieACS packaging, unified health/backup/upgrade/rollback, and the functional/performance matrix. Do not modify the retained LICENSE attribution.

For cross-device continuation, use [CLOUD-HANDOFF.md](CLOUD-HANDOFF.md). While PR #20 is open, read its current head as well as main; the search fixes are not yet merged.

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
