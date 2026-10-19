# NMS-07 through NMS-12 — gated readiness pack

**Status:** preparation only. None of NMS-07 through NMS-12 is VERIFIED.
This document records the contracts, evidence and approvals required after the
earlier runtime gates. It authorizes no deployment, external write or device
action.

## NMS-07 — Maung SSO and /nmsolt routing

### Contract that must be agreed before implementation

- Maung remains the identity authority. NMSOLT validates a bearer/session token
  server-to-server; it never trusts browser-supplied role or feature claims.
- The validated principal must include an immutable subject, expiry, issued-at,
  issuer/audience, active/revoked state, roles/scopes, and the explicit
  app.nmsolt entitlement.
- Missing, expired, revoked, wrong-audience, malformed, unreachable-introspection
  and missing-entitlement cases fail closed.
- Any positive introspection cache must expire before the token and have a short
  bounded TTL; privileged access must be rechecked after logout/revocation.
- Local emergency administration, if retained, must be separately documented,
  restricted and audited rather than silently mapped from Maung roles.
- The exact Maung endpoint/schema, issuer/audience, role mapping, logout flow and
  service credential lifecycle remain owner/Maung-team decisions.

### Required evidence

Contract fixtures and negative tests; role/tenant matrix; session fixation,
Cookie path/Secure/SameSite and CSRF tests; asset and redirect tests under
/nmsolt; app launcher test; server-side introspection logs with tokens redacted;
reversible Caddy diff, config validation and rollback test. Do not change Caddy
until NMS-03/NMS-04 and a change approval are complete.

## NMS-08 — Maung WO/CRM integration

### Ownership and identifiers

| Domain | Owner | Canonical reference carried by NMSOLT |
| --- | --- | --- |
| User/role/tenant | Maung identity | immutable subject and tenant/site ID |
| Customer | Maung WO | customer ID; no copied credentials |
| Work order | Maung WO | work-order ID, status/version and event ID |
| Lead/sales | CRM | CRM reference only after read-only feasibility review |
| OLT/PON/ONU/ODP telemetry | NMSOLT | NMSOLT IDs plus normalized physical keys |
| ONT inform/config state | GenieACS | ACS device ID plus masked serial/MAC mapping |

Every exchange must include schema version, source system, event ID,
occurred-at/observed-at timestamps and correlation ID. Consumers must be
idempotent; duplicate/out-of-order events cannot create duplicate assets or
overwrite fresher state. No cross-database queries. Unknown mappings go to a
review queue rather than heuristic writes.

### Required evidence

Approved API/event schemas; sanitized contract fixtures; auth and tenant
negative tests; duplicate/out-of-order replay tests; provenance/freshness UI;
a synthetic customer → work order → asset handover → telemetry workflow. Maung
WO/CRM repositories and environments remain untouched until explicitly scoped.

## NMS-09 — controlled Maung production go-live

No production change is permitted until NMS-03 through NMS-08 are VERIFIED.
The approval record must name the exact image digest, migration set, time
window, owner, observers, capacity budget, ingress/egress allow-list, backup
artifact and rollback trigger.

Required rehearsal evidence: restore to an isolated database; fresh install and
upgrade migrations; queue/scheduler health; SSO and /nmsolt smoke tests;
read-only telemetry; alerting; load/resource ceiling; rollback to the previous
immutable image and database state. Stop on unexpected device writes, tenant
leakage, SSO bypass, unbounded resource pressure or missing backup verification.

## NMS-10 — second Nostek AI deployment

Select nostek-platform versus nostek-ops only from current capacity, network and
security evidence. The second installation uses the same approved immutable
application digest but distinct secrets, PostgreSQL, Redis, GenieACS/MongoDB,
volumes, project name, site/tenant identifiers, audit stream and backups. It
must not copy Maung customer data by default.

R0 integration exposes only allow-listed read tools through a broker. Zubair or
another agent receives no raw database, admin, SSH, Telnet, SNMP-write or ACS
write credentials. Required evidence includes the host decision record,
collision/capacity check, isolation tests, pinned digest and read-only tool
authorization tests.

## NMS-11 — high-risk action governance

High-risk means ONU/ONT reboot, rename, provision, delete, replace, factory
reset, firmware or Wi-Fi change; PON state; configuration save; SNMP SET;
Telnet/CLI mutation; or TR-069 parameter/task write.

Every action requires this state machine:

1. authenticated scoped requester and fresh target snapshot;
2. deterministic plan/impact preview with exact device/site;
3. policy decision and explicit owner/change approval;
4. single-use, short-lived authorization bound to action, target and parameters;
5. execution through an allow-listed broker, never an unrestricted shell;
6. independent postcondition verification;
7. append-only audit with secrets/PII redacted;
8. rollback or safe-stop result.

Replay, stale state, changed parameters, ambiguous identity, missing audit sink,
expired approval and verification failure must deny execution. Current
NMSOLT_DEVICE_WRITES_ENABLED=false remains the global fail-closed boundary.
Source-level negative tests do not authorize live actions.

## NMS-12 — release and commercial readiness

A release decision requires:

- exact supported vendor/model/firmware and GenieACS compatibility matrices;
- versioned migrations, upgrade/downgrade policy and tested backup/restore;
- SLOs, logs/metrics/alerts, capacity guidance and incident ownership;
- signed or otherwise verifiable immutable images, dependency/SBOM review and
  secret/PII scanning;
- MIT attribution preserved and GenieACS AGPL distribution obligations reviewed;
- tenant/site isolation tests and data retention/export/deletion decisions;
- customer-hosted versus hosted responsibility, support and security boundaries;
- release notes, known limitations and rollback instructions;
- explicit owner sign-off on residual risk.

Until these are evidenced, the product may have source-readiness artifacts but
must not be represented as production- or commercially-ready.

## Global continuation rule

When the first unmet prerequisite requires authorization or external evidence,
record it as BLOCKED and continue only independent source/design/test work.
A green CI run for this readiness pack proves document integrity and repository
regression safety; it does not satisfy any runtime, approval or production gate.
