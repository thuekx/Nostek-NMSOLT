# Nostek-NMSOLT — Master Roadmap (canonical)

**Owner:** thuekx <thuekx@kolu.web.id>  
**Repository:** https://github.com/thuekx/Nostek-NMSOLT  
**Execution policy:** Continue automatically from a VERIFIED gate to the next non-destructive task **within an active session**. Never claim asynchronous work will continue after the session ends. Never mark a test PASS unless backed by actual execution/evidence.  
**Status:** consult [PROJECT-STATUS.yaml](PROJECT-STATUS.yaml) before each action.  
**Handoff:** [HANDOFF.md](HANDOFF.md).  
**Human-readable tree:** [STATUS-TREE.md](STATUS-TREE.md).  
**Current checkpoint (2026-10-22):** NMS-00 through NMS-06 are VERIFIED. The isolated Maung staging base remains healthy with device writes disabled. ZTE F660 TR-069 and ZTE C320 read-only FTTH inventory/optical validation passed with sanitized evidence and exact-head CI. NMS-07 is the active product-stabilization gate. NMSOLT remains the network platform/API provider; Maung and Nostek-AI are consumers. Production and device writes remain approval-gated.

## Product contract

- **One NMSOLT platform per environment** owns network telemetry and operations. Maung and Nostek-AI consume versioned NMSOLT APIs/tools; they do not duplicate polling, drivers, databases or device credentials. A second deployment is optional only when capacity, site or security isolation requires it.
- A Docker **stack** consists of multiple containers; do not equate "one Docker deployment" with a single container.
- NMSOLT remains domain owner for OLT/PON/ONU/ODP optical monitoring, state, topology and alarm information.
- **GenieACS required for desired ONT/TR-069 capabilities**, but its runtime is added in an isolated lab first. The GenieACS NBI, UI, MongoDB and file service are private; device CWMP ingress requires approved network policy.
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
| **NMS-07** | Product stabilization | Audit every function/page; repair broken and misleading UI paths; enforce write-gate visibility; profile large ONU/alarm pages, polling and queries; add regression/performance evidence | Feature matrix, tests, measured before/after results, PR |
| **NMS-08** | Read-only API v1 and tool contract | Versioned schemas, stable IDs, freshness/provenance, pagination/filtering, scoped service tokens, rate limits, redaction, OpenAPI and negative contract tests | OpenAPI spec, API/authorization tests, compatibility policy |
| **NMS-09** | Maung `/nosteknmsolt` consumer page | Thin Maung page for API settings, summaries, alarms, search, customer/ONU quick detail and deep links; graceful timeout/failure; no duplicated polling, cross-database access or device credentials | Consumer UI/API contract and synthetic E2E fixtures; Maung implementation remains separately scoped |
| **NMS-10** | Nostek-AI Apps/tool adapter | Read-only allow-listed tools backed by NMSOLT API; no raw database/admin/device credentials; identity, tenant, audit, timeout and result-size controls | Tool schema, broker tests, redacted synthetic conversations |
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
- [NMS-07-12-GATED-READINESS.md](NMS-07-12-GATED-READINESS.md): SSO, WO/CRM, go-live, second deployment, action governance and release gates.

## Stop/continue rule

On every session start: retrieve this file, status YAML and HANDOFF; validate GitHub main and open PR/CI; update the evidence before changing statuses. When a task passes, move to the next unblocked safe task in the same session. When work is blocked, work on independent safe tasks, not unrelated production systems. Never modify Maung-Wo-Inv/maung-crm or Nostek runtime without scoped approval/review. Do not fabricate tests or handoffs.
