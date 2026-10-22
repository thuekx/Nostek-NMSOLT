# Nostek-NMSOLT — Master Roadmap (canonical)

**Owner:** thuekx <thuekx@kolu.web.id>  
**Repository:** https://github.com/thuekx/Nostek-NMSOLT  
**Execution policy:** Continue automatically from a VERIFIED gate to the next non-destructive task **within an active session**. Never claim asynchronous work will continue after the session ends. Never mark a test PASS unless backed by actual execution/evidence.  
**Status:** consult [PROJECT-STATUS.yaml](PROJECT-STATUS.yaml) before each action.  
**Handoff:** [HANDOFF.md](HANDOFF.md).  
**Human-readable tree:** [STATUS-TREE.md](STATUS-TREE.md).  
**Current checkpoint (2026-10-22):** NMS-00 through NMS-06 are VERIFIED. **Target runtime: Nostek-AI VM `nostek-platform`** (owner decisions NMS-AI-Q1…Q9, 2026-10-22: read-only device credentials only, data under `/srv/data/nmsolt`, CWMP only from approved ONT management subnets behind a DNS name, Maung staging data migrated with write credentials blanked, NMSOLT Telegram off, Maung polling off first). The Maung `maung-apps` staging is historical evidence and is retired after migration (Nostek-AI track NET-1). NMS-07-A unified package: [NMS-07-UNIFIED-PACKAGE.md](NMS-07-UNIFIED-PACKAGE.md). The isolated Maung staging base remains healthy with device writes disabled. ZTE F660 TR-069 and ZTE C320 read-only FTTH inventory/optical validation passed with sanitized evidence and exact-head CI. NMS-07 is the active product-stabilization gate. NMSOLT remains the network platform/API provider; Maung and Nostek-AI are consumers. Production and device writes remain approval-gated.

## Product contract

- **One NMSOLT platform per environment** owns network telemetry and operations. Maung and Nostek-AI consume versioned NMSOLT APIs/tools; they do not duplicate polling, drivers, databases or device credentials. A second deployment is optional only when capacity, site or security isolation requires it.
- A Docker **stack** consists of multiple containers; do not equate "one Docker deployment" with a single container.
- NMSOLT remains domain owner for OLT/PON/ONU/ODP optical monitoring, state, topology and alarm information.
- **GenieACS is a bundled NMSOLT subsystem**, not a separately operated product requirement. The supported installer/Compose package provides app, PostgreSQL, Redis, GenieACS CWMP/NBI/FS/UI and MongoDB as one managed product stack. Containers and data remain isolated internally; only approved CWMP ingress is published, while NBI/UI/Mongo stay private or loopback. NMSOLT owns health, upgrade, backup and rollback orchestration.
- Maung may expose a thin `/nosteknmsolt` consumer page, similar to its CRM integration, for API settings, network summaries, alarms, search and quick customer/ONU detail. The page calls versioned NMSOLT APIs; it never duplicates polling/drivers, queries the NMSOLT database or stores device credentials. CRM remains separately scoped.
- Nostek AI/Zubair starts with read-only broker tools; no raw admin API tokens and no unrestricted CLI/SSH/SNMP write.
- Backend stays Laravel 12/Vue/Inertia + Go SNMP poller for first validation. An alternate language/framework requires benchmarks and a separate migration decision; do not replace speculatively.
- Main branch and Maung/Nostek production are protected from experimental changes; use branches, tests, PRs and evidence.
- User deliberately keeps repositories public: enforce secret/PII scanning; retain legally required MIT attribution in LICENSE while user-facing product identity is Nostek-NMSOLT.

## Status vocabulary

- **VERIFIED**: observable evidence covers all acceptance criteria; cite commit, run, log, test summary.
- **IN_PROGRESS**: implementation underway; some checks may pass but gate incomplete.
- **BLOCKED**: concrete failing verification, missing prerequisite, or necessary human boundary.
- **PENDING**: not started.
- **DEFERRED**: deliberately postponed with explanation.

## Execution stages and gate requirements

| ID | Stage | Definition of done / hard gate | Evidence |
| --- | --- | --- | --- |
| **NMS-00** | Git baseline, identity and source hygiene | Correct public repo and author; upstream legal notice retained; mobile excluded; initial checks/Gitleaks clear; no secrets/dumps pushed | Initial commit SHA, Windows validation |
| **NMS-01** | Repeatable CI and image build | GitHub CI: Compose, base + ACS Docker builds, Go test/vet, Vue test, Laravel test all green on one commit; no skipped/falsely masked failures | Workflow URL, full test counts, PR |
| **NMS-02** | Standalone hardening / R0 boundary | Inventory endpoints are scoped/secured, legacy write routes not exposed to unapproved actors; secret storage, request audit, safe polling and auth tests; secure defaults, threat model | Security matrix, negative tests, PR |
| **NMS-03** | Maung VM discovery (read-only) | Measure available CPU/RAM/disk, running Docker services/networks, ports, storage, backups, reverse proxy; identify collisions and safe resource budget | Sanitized read-only inventory |
| **NMS-04** | Maung isolated staging Docker | Compose separate project/volumes/network, no accidental published DB/Redis ports, no live OLT write; healthchecks, app login, queue/scheduler, migrations from empty DB, backup/restore rehearsal | Deploy and rollback logs, resource data |
| **NMS-05** | GenieACS/MongoDB isolated lab | CWMP, NBI, MongoDB health, a specifically approved test ONT "inform", serial/MAC match, compatible read parameters; no production ONT repointing, no unsupported Wi-Fi action | Lab device matrix and logs |
| **NMS-06** | FTTH operations validation | Supported OLT models, per-PON/ONU snapshots, optical RX, stale data, alarm debounce, ODP mapping, user-facing read-only UI tests pass with fixtures/lab | Vendor capability matrix, fixtures, screenshots generated in lab only (not necessarily committed) |
| **NMS-07** | Product stabilization and unified package | **A** unified package: core/with-acs (recommended) Compose, platform bind-mount overlay, MongoDB auth, no secret in argv, healthchecks on every service, resource limits, digest-pinned images, one ops tool (config-check, preflight, status, backup/restore, upgrade/rollback) and `monitor/checks.json`; **B** data growth: retention for polling/alarm/audit/config-backup tables, duplicate RX index, separate poll worker pool; **C** `onu_state` table instead of the per-OLT JSON snapshot for lists; **D** audit every function/page, repair broken and misleading UI paths, stale/partial/unavailable/permission states, write-gate visibility; **E** measured ONU/alarm/search/optical/query/memory/queue/poller performance on synthetic data | Feature matrix, package/topology and ops integration tests, regression tests, measured before/after results, PR per work package |
| **NMS-08** | Read-only API v1 and tool contract | Versioned schemas, stable IDs, freshness/provenance, pagination/filtering, scoped service tokens, rate limits, redaction, OpenAPI and negative contract tests; tenant keys `org_id` + `org_slug` and `site_key` on OLT rows, token bound to org/site **server-side only** (never from request parameters), negative test: token of site A reading an OLT of site B by a valid id → 403/404; GET-only consumer route group (no live-walk refresh routes); any vendor/support access follows the Control Center support contract (ADR-14: staff identity, customer consent, read-only default, expiry, instant revoke, audit receipt) | OpenAPI spec, API/authorization tests, compatibility policy |
| **NMS-09** | Maung `/nosteknmsolt` consumer page | Thin Maung page for API settings, summaries, alarms, search, customer/ONU quick detail and deep links; graceful timeout/failure; no duplicated polling, cross-database access or device credentials | Consumer UI/API contract and synthetic E2E fixtures; Maung implementation remains separately scoped |
| **NMS-10** | Nostek-AI tool adapter | Nostek-AI host broker + stdio MCP stub `nms_*` on agentic-ai (owner decision: all `nms_*` owner-only), GET-only allow-listed API v1 paths with one service token; no raw database/admin/device credentials; identity, tenant, audit, timeout and result-size controls. Owner alerts come from Nostek-AI (Zubair) in the readable alert format; the NMSOLT Telegram bot stays off on the platform deployment. Built in the Nostek-AI repo (track NET-1-5) | Tool schema, broker tests, redacted synthetic conversations |
| **NMS-11** | High-risk changes governance | Any ONU reboot/rename/provision/delete, PON state, TR-069 write requires scoped identity, plan/impact preview, broker approval, one-time authorization, freshness, verification and audit. No live operations until explicit device/change approval | Threat model, negative tests, approvals, lab evidence |
| **NMS-12** | Release readiness / commercial extension | Supported-vendor/version matrix, upgrades, DR, observability, release process, tenant/site isolation, customer-hosted/hosted design, license attribution/compliance and SBOM review | Release checklist and product decisions |

## Dependencies / recommended critical path

NMS-00 → NMS-01 → NMS-02 → NMS-03 → NMS-04 → NMS-05 → NMS-06 → NMS-07 → NMS-08 → NMS-09 → NMS-10 → NMS-11 → NMS-12.

Non-destructive parallel work is allowed (documentation, API design, unit tests, capacity-readiness scripts). Stages are not permission to bypass the earlier safety gates or deploy to production. For resource/capacity checks, obtain environment access or have the owner run a read-only collector only when necessary.

## Readiness artifacts

- [NMS-03-MAUNG-DISCOVERY.md](NMS-03-MAUNG-DISCOVERY.md): read-only host collector and evidence checklist.
- [NMS-03-MAUNG-EVIDENCE-2026-10-20.md](NMS-03-MAUNG-EVIDENCE-2026-10-20.md): sanitized capacity, collision and bounded-budget decision.
- [NMS-04-STAGING-READINESS.md](NMS-04-STAGING-READINESS.md): isolated Compose deployment/rollback plan.
- [NMS-04-MAUNG-STAGING-EVIDENCE-2026-10-21.md](NMS-04-MAUNG-STAGING-EVIDENCE-2026-10-21.md): sanitized deploy, health, backup/restore, resource and rollback evidence.
- [NMS-05-GENIEACS-LAB-MATRIX.md](NMS-05-GENIEACS-LAB-MATRIX.md): approved lab-ONT matrix and stop rules.
- [NMS-05-GENIEACS-RUNTIME-EVIDENCE-2026-10-21.md](NMS-05-GENIEACS-RUNTIME-EVIDENCE-2026-10-21.md): sanitized Inform/read/rollback evidence.
- [NMS-06-FTTH-CAPABILITY-MATRIX.md](NMS-06-FTTH-CAPABILITY-MATRIX.md): verified vendor fixture/runtime coverage.
- [NMS-06-ZTE-C320-RUNTIME-EVIDENCE-2026-10-22.md](NMS-06-ZTE-C320-RUNTIME-EVIDENCE-2026-10-22.md): sanitized C320 inventory, optical and UI evidence.
- [NMS-07-12-GATED-READINESS.md](NMS-07-12-GATED-READINESS.md): product packaging, API/consumer/tool, action-governance and release gates.

## Stop/continue rule

On every session start: retrieve this file, status YAML and HANDOFF; validate GitHub main and open PR/CI; update the evidence before changing statuses. When a task passes, move to the next unblocked safe task in the same session. When work is blocked, work on independent safe tasks, not unrelated production systems. Never modify Maung-Wo-Inv/maung-crm or Nostek runtime without scoped approval/review. Do not fabricate tests or handoffs.

## NMS-07-A unified package checkpoint

Branch `step/nms-07-a-unified-package`: core/with-acs/platform Compose, `scripts/nostek/nmsolt-ops.sh`, `monitor/checks.json` and the ops integration test (workspace 58/58 PASS with real PostgreSQL/Redis/MongoDB/GenieACS and a stand-in app). NMS-07 stays IN_PROGRESS until work packages B–E and the whole-phase evidence exist. See [NMS-07-UNIFIED-PACKAGE.md](NMS-07-UNIFIED-PACKAGE.md).

## NMS-07-B data growth checkpoint

Branch `step/nms-07-b-data-growth`: retention command `history:prune`, duplicate RX index dropped, dedicated poll worker pool. See [NMS-07-DATA-GROWTH.md](NMS-07-DATA-GROWTH.md).

## NMS-07-E2 ONU monitor scope checkpoint

Branch `step/nms-07-e2-onu-monitor-scope`: the ONU monitor reads and sends only the selected OLT's ONUs (was every ONU: 27.6 MB at 51k). See [NMS-07-PERFORMANCE.md](NMS-07-PERFORMANCE.md).

## NMS-07-D-2c partial and permission checkpoint

Branch `step/nms-07-d2c-permission-states`: role sweep (admin, operator, partner, demo) with no server error and no sidebar link into a refused page; RX values lagging the ONU status are labelled. See [NMS-07-UI-JOURNEY.md](NMS-07-UI-JOURNEY.md).

## NMS-07-D-2b stale ONU/map/alarm checkpoint

Branch `step/nms-07-d2b-onu-states`: ONU monitor, map, alarms and C-Data/HiOSO pages never show a stale OLT's last known ONU state as live; the alarm page names OLTs whose alarms are not evaluated. See [NMS-07-UI-JOURNEY.md](NMS-07-UI-JOURNEY.md).

## NMS-07-D-2a status honesty checkpoint

Branch `step/nms-07-d2-stale-states`: stale (> 3× poll interval), unknown and paused OLT data is never shown green on the dashboard, OLT list, detail or port page. NMS-08 must add freshness to API v1 (summary/public status still count stale OLTs as online). See [NMS-07-UI-JOURNEY.md](NMS-07-UI-JOURNEY.md).

## NMS-07-D-1 write-gate visibility checkpoint

Branch `step/nms-07-d-ui-journey`: read-only banner, request guard and locked controls driven by the server's gated-route table; drift test. See [NMS-07-UI-JOURNEY.md](NMS-07-UI-JOURNEY.md).

## NMS-07-B2 daily RX rollup checkpoint

Branch `step/nms-07-b2-rx-daily`: daily RX rollup kept 3 years (owner Q5 A), 90d/1y/3y chart ranges, fix for the hourly aggregation stall after a polling gap. See [NMS-07-DATA-GROWTH.md](NMS-07-DATA-GROWTH.md).

## NMS-07-C/E ONU read path checkpoint

Branch `step/nms-07-c-perf-matrix`: `onu_state` table measured and not adopted (25 % slower than the snapshot path); performance harness `tests/perf/run-onu-list-bench.sh`; next is E2 server-side ONU list paging with D. See [NMS-07-PERFORMANCE.md](NMS-07-PERFORMANCE.md).

## Post-merge search checkpoint

PR #20 merged as `1a38c6b309a84f07db89398f2f26d727d92c3128` after owner approval.
Exact head `fc5ce840bcc548479a308e1de16fc292f1d21e49` passed all five jobs in
[run 39773898154](https://github.com/thuekx/Nostek-NMSOLT/actions/runs/39773898154). Merge tree matches that tested
head; no separate merge-SHA run or runtime verification is claimed. Identity
cleanup and search fixes are in main. NMS-07 remains incomplete; subsequent
implementation was paused by owner and resumed on 2026-10-22 (owner prompt). See HANDOFF and CLOUD-HANDOFF before resuming.
