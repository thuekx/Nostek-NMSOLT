# NMS-07 product stabilization — initial feature audit

**Date:** 2026-10-22  
**Branch:** `audit/nms07-product-stabilization`  
**Scope:** source-only review; no host, production, proxy or device mutation.

## Confirmed existing capability

| Area | Evidence | Initial state |
| --- | --- | --- |
| Web UI | Authenticated dashboard, alarms, reports, users, settings, OLT/PON/ONU/ODP and vendor pages are routed in `routes/web.php`. | Present; journey testing pending |
| REST API | `/api/v1` already exposes summary, search, OLT, ONU, alarm, ODP, map and GenieACS detail endpoints. | Present; consumer-hardening belongs to NMS-08 |
| GenieACS application integration | Services/controllers already query NBI, match ONU↔ACS devices, expose connected-device detail and contain gated Wi-Fi write paths. | Present; live writes remain disabled |
| Data growth controls | Raw RX samples default to 3 days and hourly aggregates to 45 days (400 on nostek-platform), daily 3 years, with aggregation/prune commands. | Present; B2 adds a daily rollup kept 3 years and fixes the aggregation stall after a polling gap; row size measured (~160 B), see NMS-07-DATA-GROWTH.md |
| Base package | `docker-compose.yml` contains app, PostgreSQL and Redis. | Healthy staging evidence exists |
| ACS package | `compose.acs.yaml` contains MongoDB plus CWMP/NBI/FS/UI and connects the app to the private ACS network. | Validated lab overlay; not yet a unified product install flow |

## Important correction

GenieACS is not absent from the application. NMSOLT already contains NBI integration
and the reviewed ACS container topology. The remaining product gap is packaging and
operations: the default instructions still describe ACS as an optional lab overlay,
and installation, health, backup, upgrade and rollback are not yet presented as one
managed NMSOLT package.

## NMS-07 work packages

0. **Identity and namespace cleanup**
   - canonical product name: `Nostek-NMSOLT`;
   - permitted short name: `Nostek-NMS`;
   - replace the inherited internal namespace with `nos-*`/`nos_`;
   - standardize the Go source and executable as
     `cmd/nos-snmp-poller` and `bin/nos-snmp-poller`;
   - keep the legally required upstream copyright line in `LICENSE` intact;
   - require Go, Vue, Laravel, Compose and image-build gates on the exact head.

1. **Unified package contract**
   - retain separate containers and data volumes;
   - provide explicit `core` and `with-acs` installation profiles, with
     `with-acs` as the recommended full product;
   - keep MongoDB/NBI/FS private, UI loopback-only, and publish CWMP only on an
     explicitly configured management address;
   - add health dependencies for all ACS services.
2. **Unified operations**
   - one preflight and configuration validator;
   - one status/health command;
   - documented backup/restore for PostgreSQL, application storage and MongoDB;
   - pinned image/version and reversible upgrade/rollback.
3. **Functional UI audit**
   - exercise dashboard → OLT → PON → ONU detail;
   - exercise ONU↔ACS association and read-only device detail;
   - verify unavailable, stale, partial and permission states;
   - ensure every mutating control visibly reflects the global write gate.
4. **Performance audit**
   - measure large ONU lists, alarm lists, search and optical history;
   - inspect query count, pagination, cache and queue/poller behavior;
   - verify retention commands prevent unbounded RX-history growth.
5. **API inventory**
   - record existing v1 shapes and legacy write endpoints;
   - defer consumer-specific scopes, OpenAPI, stable freshness metadata and
     compatibility guarantees to NMS-08.

## Safety and stop rules

No production deployment, Caddy/maung_edge change, Maung repository change,
credential retrieval, live OLT/ONT mutation or ACS write is authorized. Package
tests must use rendered Compose validation, unit/feature tests and isolated
synthetic fixtures until a separate runtime gate is approved.

## NMS-07-A result (2026-10-22)

Work package 1 (unified package contract) and the source half of work package 2
(unified operations) are implemented on branch `step/nms-07-a-unified-package`:
core/with-acs Compose, platform bind-mount overlay, MongoDB authentication, GenieACS
secrets out of command arguments, healthchecks for all services, resource limits,
digest-pinned images, `scripts/nostek/nmsolt-ops.sh` (config-check, preflight,
init-data, start/stop, status, build, backup, verify-backup, restore, upgrade with
automatic rollback, rollback) and `monitor/checks.json`. Workspace evidence: ops
integration 58/58 with real PostgreSQL/Redis/MongoDB/GenieACS and a stand-in app.
Defect found by the test: GenieACS listens on `::` by default and dies where Docker has
no IPv6 (`bind EAFNOSUPPORT :::7557`); fixed with explicit `0.0.0.0` interfaces.
Remaining: the real app image under the tool (first runtime = Nostek-AI NET-1-2) and
work packages 3–5. See [NMS-07-UNIFIED-PACKAGE.md](NMS-07-UNIFIED-PACKAGE.md).

## NMS-07-D-1 result (2026-10-22)

Part of work package 3 (UI journeys), "every mutating control visibly reflects the global
write gate", is implemented on `step/nms-07-d-ui-journey`:

- a read-only banner;
- a request guard;
- 56 locked controls;
- a drift test.

It was checked in a real browser. Stale, partial and permission states (D-2) are next. See
[NMS-07-UI-JOURNEY.md](NMS-07-UI-JOURNEY.md).

## NMS-07-C/E result (2026-10-22)

Work package 4 (performance audit), first part, is on branch `step/nms-07-c-perf-matrix`.
These were measured on synthetic data with a throwaway PostgreSQL:

- large ONU lists;
- global search;
- snapshot decode.

| ONUs | ONU list | payload | worst-case search |
| --- | --- | --- | --- |
| 4,500 | 154 ms | 2.4 MB | 98 ms |
| 10,200 | 303 ms | 5.5 MB | 223 ms |
| 51,000 | 1.7 s | 27.6 MB, 266 MB memory | 650 ms |

The planned `onu_state` table was measured and not adopted. It was 25 % slower than the
snapshot path.

Finding: the ONU monitor page and the v1 ONU list send every ONU in one response. That is
fine at the current size and the scale limit beyond about 50,000 ONUs. Server-side paging
(E2) is planned together with the journey audit (work package 3).

Not measured yet:

- alarm lists;
- optical history;
- query counts;
- queue and poller behaviour.

See [NMS-07-PERFORMANCE.md](NMS-07-PERFORMANCE.md).

## Current conclusion

NMS-07 remains **IN PROGRESS**. This audit establishes that the safest next
implementation is unified GenieACS packaging and health validation, followed by
the functional/performance matrix. Nothing in this document marks NMS-07 VERIFIED.

## Post-merge checkpoint

PR #20 search fixes are now merged as `1a38c6b`; all five CI jobs passed on
exact head `fc5ce840` in run `39773898154`. Identity cleanup remains complete.
NMS-07 is still IN_PROGRESS; implementation after that merge was paused by owner (resumed 2026-10-22).
Implementation resumed 2026-10-22; NMS-07-A above does not validate a host runtime.
