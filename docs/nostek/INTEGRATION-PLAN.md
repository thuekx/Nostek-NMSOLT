# One source, two independent deployments

- Git repository: `thuekx/Nostek-NMSOLT`
- Docker Maung: own PostgreSQL, Redis, configuration, (optional) GenieACS/MongoDB; joins `maung_edge` **only after** Caddy path and SSO tests, with service routes restricted.
- Docker Nostek: identical signed/pinned image digest at a separately approved release; distinct credentials/DB/site inventory and R0 agent API only. Host selection deferred until VM capacity review.
- No new VM assumed; measure memory, CPU, volume capacity and database backup impact first.

## Source-of-truth responsibility

- Maung WO/CRM: customer, lead, work order, billing references, installed inventory transactions.
- NMSOLT: OLT/PON/ONU/ODP optical telemetry and provisioning state.
- GenieACS: TR-069 discovered ONT properties and device inform/config management.
- Nostek: incident correlation, read-only monitoring query, approved action broker.

## Roadmap (not yet implemented)

1. Static audit, license and brand review, sensitive-data scanning.
2. Isolated Docker smoke test and monitoring read paths, no live OLT writes.
3. Maung SSO backend JWT introspection, `app.nmsolt` permission, one-domain `/nmsolt` routing and rollback.
4. Stable external-id asset mapping, `customerId`, `workOrderId`, `oltId`, `ponPort`, `onuSn`, `odpId`, polling freshness metadata.
5. Read-only evidence propagation to WO and CRM, no direct cross-database reads.
6. GenieACS lab ONT commissioning and validation of CWMP, NBI and vendor support.
7. Nostek integration R0, no raw admin token to an agent.
8. Explicit broker authorization, one-time action execution and verification before any live writes.

## API sketch (proposed, not existing)

`/api/nostek/v1/health`, `/olts`, `/onus`, `/odps`, `/alarms`, `/optical/summary` using delegated service credentials, schema and cursor pagination. Do not expose legacy mutating API to read-only integrations.
