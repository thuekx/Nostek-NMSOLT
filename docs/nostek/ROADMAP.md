# Nostek-NMSOLT — Master Roadmap (canonical)

**Owner:** thuekx <thuekx@kolu.web.id>  
**Repository:** https://github.com/thuekx/Nostek-NMSOLT  
**Execution policy:** Continue automatically from a VERIFIED gate to the next non-destructive task **within an active session**. Never claim asynchronous work will continue after the session ends. Never mark a test PASS unless backed by actual execution/evidence.  
**Status:** consult [PROJECT-STATUS.yaml](PROJECT-STATUS.yaml) before each action.  
**Handoff:** [HANDOFF.md](HANDOFF.md).  
**Human-readable tree:** [STATUS-TREE.md](STATUS-TREE.md).  
**Current checkpoint (2026-10-21):** NMS-00 through NMS-04 are VERIFIED. The owner-authorized NMS-04 base staging stack is isolated on loopback port 18080 with device writes disabled; migrations, application processes, backup/restore, resource limits and rollback passed on permanent image commit `4b7437c7169e98a2b42be599bc241ece0a7299e7`. NMS-05 through NMS-12 remain BLOCKED on lab-device, contract, production and approval gates.

## Product contract

- **One source repository, two independent Docker Compose deployments**: first Maung, second Nostek AI. Each installation has independent app config, PostgreSQL, Redis, GenieACS/MongoDB, credentials, and operational data. No new VM assumed without resource evidence.
- A Docker **stack** consists of multiple containers; do not equate "one Docker deployment" with a single container.
- NMSOLT remains domain owner for OLT/PON/ONU/ODP optical monitoring, state, topology and alarm information.
- **GenieACS required for desired ONT/TR-069 capabilities**, but its runtime is added in an isolated lab first. The GenieACS NBI, UI, MongoDB and file service are private; device CWMP ingress requires approved network policy.
- Maung WO owns users/roles, customers, work orders and physical inventory workflow; CRM owns leads and sales. NMSOLT reads/writes only via explicitly authorized contracts, **not cross-database access**.
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
| **NMS-07** | Maung authentication and /nmsolt routing | Server-side Maung token introspection/feature gate app.nmsolt, no stale privileged auth, route/asset/Cookie/CSRF test on /nmsolt, Caddy reversible, app launcher | SSO/RBAC/API/caddy tests and rollback |
| **NMS-08** | Maung WO/CRM integration | Canonical external IDs for customers, ONT serials, OLT/PON/ODP, work orders; CRM feasibility read-only; operational handover with provenance and freshness; idempotent deduplicated events; no cross-DB coupling | Contract tests and E2E synthetic workflow |
| **NMS-09** | Controlled Maung production go-live | Staging acceptance, backups/restore proof, observability, capacity/load budget, explicit owner change approval; restricted ingress/egress; rollback drill | Approved change ticket & production verification |
| **NMS-10** | Second Nostek AI Docker deployment | Decide nostek-platform vs nostek-ops from actual resource/security evidence; same immutable application version, distinct secrets/DB/ACS/volumes; R0 tool registration and Zubair mapping | Host decision record, pinned image, read-only integration tests |
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
- [NMS-06-FTTH-CAPABILITY-MATRIX.md](NMS-06-FTTH-CAPABILITY-MATRIX.md): reviewed vendor fixture coverage and lab gaps.
- [NMS-07-12-GATED-READINESS.md](NMS-07-12-GATED-READINESS.md): SSO, WO/CRM, go-live, second deployment, action governance and release gates.

## Stop/continue rule

On every session start: retrieve this file, status YAML and HANDOFF; validate GitHub main and open PR/CI; update the evidence before changing statuses. When a task passes, move to the next unblocked safe task in the same session. When work is blocked, work on independent safe tasks, not unrelated production systems. Never modify Maung-Wo-Inv/maung-crm or Nostek runtime without scoped approval/review. Do not fabricate tests or handoffs.
