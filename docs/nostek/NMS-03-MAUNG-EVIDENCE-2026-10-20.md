# NMS-03 — sanitized Maung discovery evidence

**Collection time:** 2026-10-20T00:52:49+07:00  
**Method:** scripts/nostek/collect-maung-r0.sh from repository commit
6981999a8c8f55cfb1dc345fd17cb52ebe6c37f2  
**Raw artifact SHA-256:**
0187497B9551BE5AB3E0C8598E8F12DE109D8700273EFA1C3C877C5661844ECB  
**Raw artifact handling:** retained outside the public repository. It contains
internal addressing, account, container and path metadata and must not be
committed.

## Safety attestation

The collector ran as an ordinary account without sudo. It did not read container
environment variables, secrets, database rows or configuration-file contents.
It did not start, stop or modify services, containers, networks, volumes,
firewall rules, routes or files on the VM.

## Sanitized findings

| Area | Observation | Decision |
| --- | --- | --- |
| Compute | 8 virtual CPUs; load below 1 during collection | Capacity exists for a bounded staging allocation |
| Memory | 15 GiB total; about 13 GiB available; 4 GiB swap effectively unused | Capacity exists; preserve at least 8 GiB available before deployment |
| Root storage | 78 GiB mounted filesystem; 43 GiB available at collection time | Enough for bounded staging, but enforce a 20 GiB free-space stop floor |
| Block storage | Backing virtual disk is larger than the mounted logical volume | Do not assume unallocated space is usable without a separate approved storage change |
| Docker | Current engine healthy and accessible; 12 running containers across 3 Compose projects | Use a separate Compose project, network and volumes |
| Docker storage | Images about 9.8 GB; build cache about 15.3 GB, mostly reclaimable | Do not prune automatically; cleanup requires separate owner approval |
| Host ports | 80/443 are owned by an existing containerized reverse proxy; 9001 and 20022 are also occupied | Never bind these ports; planned loopback 18080 was free |
| ACS lab ports | Planned loopback 17547 and 13000 were free | Availability is point-in-time only; recheck immediately before deployment |
| Networks | Existing Docker bridges occupy consecutive private ranges through the current Maung networks | Verify the new subnet immediately before creation; do not join maung_edge before NMS-07 |
| Reverse proxy | Host proxy services are inactive; an existing WO Compose project owns the active Caddy container and 80/443 | NMS-04 stays loopback-only; no Caddy edit until NMS-07 approval |
| Persistence | Existing workloads use named volumes; separate backup-named volumes exist for WO and CRM | Backup presence is identified, but backup contents and restore success are not asserted |
| OS | Current Ubuntu VM and Docker engine were recorded in the raw evidence | Recheck compatibility during the NMS-04 preflight |

No public evidence includes the account name, exact internal/global addresses,
Docker IDs, raw routes, full image identifiers or host filesystem paths.

## Bounded staging budget

The following is an upper bound, not authorization to deploy:

| Candidate | CPU ceiling | Memory ceiling | Initial disk budget |
| --- | ---: | ---: | ---: |
| NMS-04 base NMSOLT staging | 2 vCPU | 3 GiB | 12 GiB |
| Later NMS-05 ACS lab increment | 2 vCPU | 3 GiB | 8 GiB |
| Combined maximum | 4 vCPU | 6 GiB | 20 GiB |

Pre-deployment stop conditions:

- available host memory below 8 GiB;
- root free space below 30 GiB before deployment or projected below 20 GiB;
- loopback port 18080 already occupied;
- an overlapping Docker subnet;
- inability to use separate named volumes/project credentials;
- missing rollback/backup plan;
- any requirement to modify the existing Caddy owner, maung_edge, WO or CRM
  during NMS-04.

## Collision-free NMS-04 constraints

- Compose project name must be unique, for example nmsolt-maung-staging.
- Application publishing remains 127.0.0.1:18080 only.
- PostgreSQL and Redis publish no host ports.
- Use separate project-local networks and named volumes.
- Do not attach to maung_edge or alter the existing reverse proxy.
- Keep NMSOLT_DEVICE_WRITES_ENABLED=false.
- Re-run port, memory, disk and Docker-network checks immediately before any
  approved deployment because this evidence is point-in-time.

## Gate decision

NMS-03 is VERIFIED by the timestamped read-only inventory, raw-artifact hash,
sanitized findings, collision analysis and bounded resource budget.

This decision does not verify backup recovery, authorize NMS-04, or prove that
the point-in-time capacity remains unchanged. NMS-04 remains BLOCKED pending an
explicit staging deployment window and a reviewed deploy/health/migration/
backup/restore/rollback plan.
