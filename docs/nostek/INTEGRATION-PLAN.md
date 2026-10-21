# NMSOLT provider and consumer integration plan

## Product boundary

NMSOLT is the standalone network platform and authoritative source for
OLT/PON/ONU/ODP telemetry, alarms, optical history, GenieACS association and
network-domain workflows. Maung and Nostek-AI consume versioned interfaces.

A future Maung `/nosteknmsolt` page may contain:

- API connection and integration settings;
- health, inventory, alarm and freshness summaries;
- search and quick customer/ONU details;
- deep links to full NMSOLT workflows.

It must not duplicate polling, drivers, GenieACS, history aggregation or device
credentials. It must not read the NMSOLT database directly.

## Source-of-truth responsibility

| Domain | Source of truth |
| --- | --- |
| OLT/PON/ONU/ODP telemetry and alarms | NMSOLT |
| ONT Inform/config state | GenieACS surfaced through NMSOLT |
| Customer/work-order identity | Maung systems, referenced by stable external IDs |
| AI queries and correlation | Nostek-AI consuming scoped NMSOLT tools |

## Planned sequence

1. Stabilize NMSOLT functions, UI and performance.
2. Publish read-only API v1 and OpenAPI with stable IDs, freshness, pagination,
   scopes, redaction and rate limits.
3. Build Maung `/nosteknmsolt` as a thin API consumer under a separate scoped PR.
4. Build Nostek-AI read-tool adapter over the same contracts.
5. Add high-risk write governance only after explicit approval and lab proof.
6. Complete release and production-readiness gates.

## Proposed API surface

`/api/v1/health`, `/olts`, `/pons`, `/onus`, `/odps`, `/alarms`,
`/optical/summary` and targeted detail endpoints. All consumer responses include
schema version, stable resource ID, source, observed-at and freshness metadata.
Legacy mutating APIs are excluded from read-only consumer scopes.
