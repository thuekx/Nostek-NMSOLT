# NMS-07-A — unified package (core / with-acs) and operations tool

**Date:** 2026-10-22 · **Branch:** `step/nms-07-a-unified-package` · **State:** source PASS in an
isolated workspace; NMS-07 stays IN PROGRESS. No host, production, Caddy, Maung or device action.

## Why

The owner chose nostek-platform as the NMSOLT runtime (Nostek-AI decisions NMS-AI-Q1…Q9,
2026-10-22) and to migrate the Maung staging data there. The platform install is a
Nostek-AI host package (NET-1-2: PREFLIGHT → APPLY → VERIFY with host and sha gates).
That package needs one reviewed product stack and one non-interactive tool it can wrap.
This step provides both.

## Package layout

| File | Role |
| --- | --- |
| `docker-compose.yml` | **core** profile: app, PostgreSQL 16, Redis 7 |
| `compose.acs.yaml` | **with-acs** profile (recommended full product): + MongoDB 7.0 and GenieACS 1.2.16 CWMP/NBI/FS/UI |
| `compose.platform.yaml` | overlay: all data volumes become bind mounts under `NMSOLT_DATA_DIR` (`pg`, `redis`, `app-storage`, `mongo`) |
| `docker/mongo/init-genieacs-user.js` | first start only: least-privilege `genieacs` account (readWrite on `genieacs`) |
| `docker/genieacs/health.js` | HTTP healthcheck used by all four GenieACS services |
| `scripts/nostek/nmsolt-ops.sh` | one operations tool (below) |
| `monitor/checks.json` | `nostek-checks/1` for the Nostek host watcher: compose project healthy |
| `tests/ops/` | integration test with real PostgreSQL/Redis/MongoDB/GenieACS and a PHP stand-in app |

### What changed against the NMS-05 lab overlay

| Item | Before | Now |
| --- | --- | --- |
| MongoDB | no authentication | `--auth`; root account for backup/restore only, `genieacs` app account for GenieACS |
| GenieACS UI JWT secret | command argument (`--ui-jwt-secret`, visible in `ps`/inspect) | environment variable `GENIEACS_UI_JWT_SECRET` |
| GenieACS health | none (only `service_started`) | HTTP healthcheck on CWMP 7547, NBI 7557, FS 7567, UI 3000 |
| Listen address | GenieACS default `::` (fails where Docker has no IPv6: `bind EAFNOSUPPORT :::7557`, found in this test) | explicit `0.0.0.0` inside the container; publishing rules unchanged |
| Third-party images | tags (`postgres:16-alpine`, `redis:7-alpine`, `mongo:7.0`, `node:22-bookworm-slim`) | same tags pinned by digest (checked against Docker Hub 2026-10-22) |
| Resource limits | only in the Maung runner override | every service, "S" defaults (≤ 2 OLT, ≤ 2k ONU), tunable in `.env` |
| App environment | the whole `.env` reached the app, including other containers' secrets | MongoDB and GenieACS secrets are blanked for the app |
| Data on the platform | named volumes (in `/srv/docker`, excluded by the platform backup) | bind mounts under `/srv/data/nmsolt` via `compose.platform.yaml` |

Publishing stays as reviewed: app and GenieACS UI on loopback only; MongoDB, NBI and FS never
published; CWMP on loopback unless `ACS_CWMP_BIND_IP` names one approved address (the tool
then prints a WARN that a firewall source allow-list is required). Device writes stay off.

## Operations tool

`scripts/nostek/nmsolt-ops.sh [--env-file F] [--profile core|with-acs] [--platform] [--override F] [--expect-host H] <command>`

| Command | What it does |
| --- | --- |
| `config-check` | env file mode 600, required secrets set (≥ 16 chars, MongoDB charset), write gate false, `APP_DEBUG` false, rendered topology and GenieACS policy validators |
| `preflight` | config-check + Docker/compose present, data directories present (not symlinks), free space (default ≥ 20 GiB), ports free. Read-only |
| `init-data` | create missing data directories (mode 0750); existing ones are left as they are |
| `start` / `stop` | start without building and wait until every service is healthy (refuses before `compose up` when a locally built image is missing, A2) / stop containers, keep data |
| `status` | per service running + healthy, app `/up`, write gate in the running app, worker and scheduler processes, NBI reachable from the app over the private network |
| `build --tag T` | build the app image `<NMSOLT_IMAGE_REPO>:T`; with-acs also the local GenieACS image `nmsolt-genieacs:1.2.16-local` (A2) |
| `backup --out DIR` | `pg_dump -Fc` (raw `onu_rx_samples` data excluded unless `--include-raw-rx`), `mongodump` of `genieacs` without transient `cache`/`tasks`/`faults`/`operations`, app storage tarball **without APP_KEY** (`storage/app/.appkey`), logs and framework caches; per-table and per-collection counts; `MANIFEST.sha256`. A failed set stays as `*.partial` |
| `verify-backup --from DIR` | checksums and profile |
| `restore --from DIR --confirm RESTORE` | verify, safety backup of the current data, stop the app, `pg_restore --clean`, `mongorestore --drop`, storage extract (existing APP_KEY kept), start, compare counts |
| `upgrade --tag T --backup-dir DIR` | refuses if the image is missing; pre-upgrade backup; rollback point; switch `IMAGE_TAG` (old env copy kept); start; wait healthy. On FAIL: automatic rollback to the previous tag **with the pre-upgrade data** (Laravel migrations may have run) |
| `rollback --confirm ROLLBACK [--with-data]` | return to the tag before the last upgrade |

Output: `RESULT <name>=PASS|FAIL|WARN|UNKNOWN <detail>`, `PROGRESS k/n` on long steps,
`REFUSE …` for refused input, final `RESULT nmsolt-ops <command>=PASS|FAIL`; exit 0 only on
PASS. `--expect-host` prints `❌ WRONG HOST: …; nothing done` to stdout, stderr and the
terminal and exits 3. Secret values are never printed; MongoDB tools read the password from a
temporary config file or the container environment, never from a command argument.

## Evidence (workspace, 2026-10-22)

`bash tests/ops/integration.sh` with real `postgres:16-alpine`, `redis:7-alpine`, `mongo:7.0`
and the GenieACS 1.2.16 image, PHP stand-in app (the full app image is built by the CI
`docker-build` job): **RESULT ops-integration passed=58 failed=0**. Covered: wrong host,
env mode, config-check, preflight before/after `init-data`, start, status (all 8 services
healthy, NBI from the app, write gate), data on bind mounts, no foreign secret in the app
environment, MongoDB refuses unauthenticated reads, backup content (uploads in, APP_KEY out,
raw RX excluded, transient collections excluded), tampered backup refused, restore without
confirm refused, restore with counts equal, upgrade refused for a missing image, upgrade
v1→v2, manual rollback, broken upgrade with automatic data rollback, core-only profile,
and no generated secret in any of the 25 tool logs. The same test runs in CI job
`ops-integration`.

A2 (2026-10-22): **RESULT ops-integration passed=69 failed=0** after adding the missing-image
checks (preflight WARN, start refused before `compose up`) and the with-acs build of the GenieACS image.

Not verified here: the real app image under this tool (CI builds it; first runtime is the
NET-1-2 install), performance, and any host. The Maung runners
`scripts/nostek/deploy-maung-*.sh` are historical NMS-04/NMS-05 evidence: do not re-run them
against this revision (MongoDB now needs authentication); the migration path is NET-1.

## Platform use (for NET-1-2, owner-run package in Nostek-AI)

1. `.env` (mode 600) with `NMSOLT_DATA_DIR=/srv/data/nmsolt`, generated secrets, write gate false.
2. `nmsolt-ops.sh --platform --expect-host nostek-platform preflight` → `init-data` → `build --tag <commit>` → `start` → `status`.
3. Nightly before the platform restic run: `backup --out /srv/data/nmsolt/dumps`; the platform
   backup excludes the live `pg/`, `mongo/`, `redis/` directories (Nostek-AI backup 1.2).
4. APP_KEY is kept by the owner (password manager), not in any backup.
