# NMS-04 authorized Maung staging runbook

**Approval received:** 2026-10-20 (Asia/Jakarta) for a bounded NMS-04
staging deployment only. The approval excludes Caddy, maung_edge, Maung
repositories, production and OLT/ONT interaction.

## Runner

Use scripts/nostek/deploy-maung-staging.sh only after its exact-head CI passes.
The runner pins application source commit
0aea5e7832663dd20b4ab51b42cf28ce6bebea6f and creates a new workspace in the
invoking user's current directory. It never checks out or edits Maung WO/CRM.

Enforced boundaries:

- Compose project nmsolt-maung-staging;
- private subnet 172.30.30.0/24 after collision checks;
- application only at 127.0.0.1:18080;
- app 1.25 CPU / 1536 MiB;
- PostgreSQL 0.50 CPU / 1024 MiB;
- Redis 0.25 CPU / 512 MiB;
- aggregate 2 CPU / 3 GiB;
- NMSOLT_DEVICE_WRITES_ENABLED=false;
- no ACS overlay, Caddy edit or maung_edge attachment.

Preflight stops when available memory is below 8 GiB, free disk is below 30 GiB,
port 18080 is occupied, project resources already exist, or subnet overlap is
observed. After build, it stops if free disk falls below 20 GiB.

## Rehearsal evidence

The runner must prove:

1. rendered Compose policy passes;
2. the exact application commit builds;
3. app, PostgreSQL and Redis become healthy;
4. /up and /login respond locally;
5. migrations complete and supervised processes are running;
6. a staging-only PostgreSQL custom-format backup is non-empty;
7. that backup restores into a disposable database with matching public-table
   count;
8. stopping only the staging project leaves every previously running container
   running;
9. the staging stack becomes healthy again after restart.

On an error after startup, the runner stops only the staging project and
preserves its volumes and local logs for review.

## Sensitive local artifacts

The staging workspace contains .env, a generated admin password and a database
backup. Permissions are restricted to the invoking account. Never commit or
paste those artifacts. Share only the generated evidence text after reviewing
it for unexpected identifiers.

A successful script does not mark NMS-04 VERIFIED until its evidence is reviewed
against the approved limits and merged through PR/CI.
