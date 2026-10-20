# NMS-04 Maung isolated staging evidence — 2026-10-21

## Decision

**NMS-04 VERIFIED.** The owner-authorized base staging stack was deployed on the
Maung VM within the NMS-03 budget. The raw runtime evidence, database dump,
environment file and credentials remain only on the host. This public record is
sanitized and contains no secret, customer, OLT or ONT data.

## Scope and isolation

- Compose project: `nmsolt-maung-staging`.
- Private network: `nmsolt-maung-staging-private`, planned subnet
  `172.30.30.0/24`; preflight collision validation passed.
- Published application endpoint: loopback-only `127.0.0.1:18080`.
- PostgreSQL and Redis publish no host ports.
- Aggregate limits: 2 CPU and 3 GiB memory.
- Device writes: disabled.
- Caddy: unchanged.
- `maung_edge`: not joined.
- Maung WO/CRM repositories, production and live OLT/ONT: not touched.

## Exact source and CI evidence

- Authorized runner: [PR #11](https://github.com/thuekx/Nostek-NMSOLT/pull/11),
  head `983ef45e6c4d7918897bd7237dbf0caf1851f08b`, successful
  [run 39449952838](https://github.com/thuekx/Nostek-NMSOLT/actions/runs/39449952838),
  merge `3ad09b818b80a73a913acb03a8c8bba7d54efcf2`.
- Initial runtime exposed restrictive build-context permissions: application
  files were root-owned mode 0600/0700, causing FastCGI 404 responses and
  supervised Laravel processes to fail as `www-data`.
- Permanent correction: [PR #12](https://github.com/thuekx/Nostek-NMSOLT/pull/12),
  head `27ec84e6d96de8a75bca1b640c37440fb0b65d9a`, successful
  [run 39470245938](https://github.com/thuekx/Nostek-NMSOLT/actions/runs/39470245938),
  merge `4b7437c7169e98a2b42be599bc241ece0a7299e7`.
- Final deployed image:
  `ghcr.io/thuekx/nostek-nmsolt:4b7437c7169e98a2b42be599bc241ece0a7299e7`.
- The image was built on the target host from the exact detached source commit;
  the post-build filesystem retained approximately 37.9 GiB free.

## Runtime acceptance

| Check | Sanitized result |
| --- | --- |
| Rendered base Compose topology | PASS |
| Application health `/up` | PASS |
| Login route | HTTP 200 |
| Empty-database migrations | All listed migrations ran |
| nginx | RUNNING |
| php-fpm | RUNNING |
| queue worker | RUNNING |
| scheduler | RUNNING |
| telnet proxy | RUNNING |
| Runtime source readable by `www-data` | PASS |
| PostgreSQL health | PASS |
| Redis health | PASS |
| PostgreSQL backup creation | PASS |
| Restore into disposable database | PASS |
| Source/restored public table count | 38 / 38 |
| Pre-existing containers after staging stop | Unchanged and running |
| Restart after rollback stop | PASS |
| Permanent image retained after rollback | PASS |

Observed steady snapshot before rollback:

- application: approximately 0.50% CPU and 171 MiB of its 1.5 GiB limit;
- PostgreSQL: approximately 0.05% CPU and 74.54 MiB of its 1 GiB limit;
- Redis: approximately 1.51% CPU and 4.27 MiB of its 512 MiB limit.

These are point-in-time staging observations, not production capacity claims.

## Local-only retained artifacts

The owner retains these on the Maung host:

- sanitized/full execution evidence:
  `~/nostek-nmsolt-staging/evidence/nms04-20261021-003137.txt`;
- staging-only PostgreSQL dump under
  `~/nostek-nmsolt-staging/backups/`;
- environment and generated credentials in the mode-0600 staging `.env`.

None of those files are committed. Do not publish the environment, dump,
passwords or raw logs.

## Remaining boundary

This evidence verifies only NMS-04 base staging. It does not authorize or verify
GenieACS/CWMP, an ONT inform, Caddy or SSO integration, Maung WO/CRM integration,
production deployment, or any OLT/ONT write. NMS-05 requires separate approval
for one exact lab ONT and a sanitized inform/read/rollback evidence set.
