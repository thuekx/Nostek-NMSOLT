# NMS-07 through NMS-12 — provider-centric gated readiness

NMSOLT is the standalone network platform and source of network truth. Maung and
Nostek-AI consume its APIs/tools. This document authorizes no production change,
external write, Caddy change or live device action.

## NMS-07 — product stabilization

**State: IN PROGRESS.** Inventory routes, screens, jobs and read APIs; exercise
core user journeys; repair broken or misleading read behavior; profile expensive
queries/polling; and add regression tests. Evidence requires a feature matrix,
reproducible defects, before/after results and green exact-head CI. Work stays in
source and isolated staging. No Maung repository or device mutation is required.

## NMS-08 — read-only API v1 and OpenAPI

Blocked on NMS-07. Define stable resources for health, OLTs, PONs, ONUs, ODPs,
alarms and optical summaries. Every response must carry stable IDs, observed-at
and freshness/staleness semantics. Require cursor pagination, bounded filters,
service scopes, tenant/site isolation, field redaction, rate limits, consistent
errors and an OpenAPI contract. Contract, auth-negative, pagination and stale-data
tests are required. Legacy mutating endpoints are not part of this consumer API.
Control Center conditions (Nostek-AI baseline v1, 2026-10-22): OLT rows carry `org_id`,
`org_slug` and `site_key`; org/site context comes only from the server-verified token,
never from request parameters; a token bound to site A reading an OLT of site B by a
valid id returns 403/404 (negative contract test); vendor/support access follows the
Control Center support contract (ADR-14).
Freshness (NMS-07-D-2a finding): the legacy API v1 `/summary` and `/public/status` count an
OLT whose last poll is older than 3 poll intervals as online. The NMS-08 API must use
`App\Support\Freshness` and return the state (FRESH / STALE / UNKNOWN / PAUSED), the
observed-at time and the age; a stale OLT and its ONUs never count as online or offline.

## NMS-09 — Maung /nosteknmsolt thin consumer

Blocked on NMS-08 and a separately scoped Maung change. The page may provide API
connection/settings, health summaries, alarms, search, and quick customer/ONU
details or deep links. Maung must not run NMSOLT polling/drivers, query NMSOLT's
database, store OLT/ONT credentials or become the telemetry source of truth.
Required evidence: consumer contract tests, scoped credentials, redaction,
unavailable/stale states, tenant boundaries, performance budget and rollback.
Caddy and Maung production remain separately approval-gated.

## NMS-10 — Nostek-AI tool adapter

Blocked on NMS-08. Built in the Nostek-AI repo (track NET-1-5) as a host broker plus
stdio MCP stub `nms_*` on agentic-ai; all `nms_*` tools are owner-only (owner decision
NMS-AI-Q6 = B). Owner alerts come from Nostek-AI (Zubair) in the readable alert format;
the NMSOLT Telegram bot stays off on the platform deployment. Expose only allow-listed,
read-only tools backed by API v1.
The adapter receives no raw database, admin, SSH, Telnet, SNMP-write or ACS-write
credentials. Required evidence: tool schemas, least-privilege scopes, tenant and
redaction negative tests, bounded output/pagination, rate limiting and audit
correlation. A second NMSOLT deployment is optional only for evidenced capacity,
tenant or security isolation.

## NMS-11 — high-risk action governance

Writes remain globally disabled. Reboot, provisioning, deletion, replacement,
firmware/Wi-Fi change, PON state, SNMP SET, Telnet/CLI mutation and TR-069 writes
require: fresh target snapshot; deterministic preview; explicit owner/change
approval; single-use authorization bound to target and parameters; allow-listed
broker execution; independent postcondition verification; redacted append-only
audit; and rollback or safe-stop. Replay, stale state, ambiguous identity,
expired approval or missing verification must deny execution.

## NMS-12 — release and production readiness

Requires supported vendor/model/firmware matrices; versioned migrations and
tested backup/restore; SLOs and observability; capacity guidance; immutable image
and dependency/SBOM review; secret/PII scanning; MIT attribution and GenieACS
license review; isolation and retention decisions; responsibility/support
boundaries; release notes; rollback instructions; and explicit residual-risk
sign-off. Green source CI alone is not production verification.

## Global continuation rule

When an unmet prerequisite requires external evidence or authorization, mark
that item BLOCKED and continue independent source/design/test work. Never claim
VERIFIED without exact evidence.
