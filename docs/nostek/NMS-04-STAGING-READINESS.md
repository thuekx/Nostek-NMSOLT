# NMS-04 isolated staging readiness plan

This document prepares the source-level controls for NMS-04. It does not
authorize a deployment and does not claim that the Maung host has sufficient
capacity. NMS-04 remains dependency-blocked until NMS-03 produces authorized,
sanitized host evidence and an explicit staging resource budget.

## Required inputs from NMS-03

Before any `docker compose up` command is approved, record:

- available CPU, RAM and disk after reserving capacity for existing workloads;
- existing Compose project, container, network, volume and port names;
- reverse-proxy ownership and a reversible route plan;
- backup location, retention evidence and a safe restore-test target; and
- the approved application port, Compose project name and resource limits.

Do not substitute historical host details for current evidence.

## Source-level isolation contract

The rendered Compose model must satisfy all of these rules:

1. `app` is the only base-stack service with a published port, bound to
   loopback.
2. PostgreSQL and Redis publish no host ports.
3. The application, PostgreSQL and Redis have healthchecks and independent
   named volumes scoped by a unique Compose project name.
4. `APP_DEBUG=false`, Redis backs queues, and
   `NMSOLT_DEVICE_WRITES_ENABLED=false`.
5. With the optional ACS overlay, MongoDB, NBI and file service remain private;
   CWMP and UI bind only to loopback; `acs_private` is an internal network.
6. No image is pushed and no production container is started by CI.

CI enforces this contract against the fully rendered Compose JSON using
`scripts/nostek/validate-staging-compose.py`.

## Future authorized rehearsal

Only after NMS-03 and the staging change approval:

1. Pin the reviewed image/commit and create a unique local environment file
   containing generated secrets outside Git.
2. Render and archive the sanitized Compose plan.
3. Start the isolated project without the ACS overlay.
4. Verify health, migrations from an empty database, login, queue worker,
   scheduler and local-only ingress.
5. Create a PostgreSQL backup, restore it into a separate disposable database,
   and compare schema/application checks.
6. Capture resource usage and stop if the agreed budget is exceeded.
7. Exercise rollback by stopping the project and proving existing Maung
   workloads and routes are unchanged.

The exact project name, port, limits, backup target and proxy route are
deliberately unresolved until the authorized NMS-03 evidence exists.
