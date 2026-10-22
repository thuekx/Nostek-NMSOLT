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
| Data growth controls | Raw RX samples default to 3 days and hourly aggregates to 45 days, with aggregation/prune commands. | Present; scale measurements pending |
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

## Current conclusion

NMS-07 remains **IN PROGRESS**. This audit establishes that the safest next
implementation is unified GenieACS packaging and health validation, followed by
the functional/performance matrix. Nothing in this document marks NMS-07 VERIFIED.
