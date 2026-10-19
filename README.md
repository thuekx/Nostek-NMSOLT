# Nostek-NMSOLT

**FTTH / OLT / ONU Network Management**
Product identity: **thuekx** (`thuekx@kolu.web.id`)

> **Status:** baseline rebranding and integration planning. Not production-certified. No claim of verified OLT, ONT, or VM deployment is made by this repository snapshot.

## Purpose

Nostek-NMSOLT provides OLT/ONU monitoring, optical power, ODP mapping and FTTH operations. The legacy implementation uses Laravel 12, Vue/Inertia, PostgreSQL, Redis and a Go SNMP poller. Existing OLT device-write operations are still present in source: **do not expose this app to production OLTs or untrusted users until hardening and approvals are implemented**.

It is intended to be installed as independent Docker deployments:

- **Maung**: its own database and integration adapter for WO, CRM and customer inventory.
- **Nostek AI**: separate deployment, data and credentials, with read-only agent tooling initially.

No new VM is required by design, subject to capacity measurements.

## Repository status

- Rebranded application identity and package/service names from initial source import.
- Docker Compose baseline with PostgreSQL and Redis; web port binds to loopback for staging.
- Optional `compose.acs.yaml` **lab-only** draft for GenieACS 1.2.16 with MongoDB. Not included in the base Compose deployment.
- Original copyright notice preserved in `LICENSE` as required by MIT terms.
- Maung SSO, `/nmsolt` reverse-proxy routing, Nostek tool registry, SNMPv3, write-approval broker and production hardening are **not implemented in this baseline**.
- Demonstration screenshots and PDF user guides have been removed. Android/mobile launcher assets remain excluded from the web Docker build and require a separate identity/legal review before APK release.

## Before first push

1. Inspect every changed file and `git status`; keep your Windows `.git` directory intact.
2. Run secret scanning (`gitleaks dir . --redact`) and review demo/sample content for customer data.
3. Resolve all Gitleaks findings; run PHP syntax checks, the available unit tests, and a Docker build in a disposable lab.
4. Do **not** use `docker compose down -v` on servers with persistent data.
5. Keep the first release staged, with no management-network access to live OLTs.

## Docker staging

For Windows PowerShell copy the example file with `Copy-Item .env.docker.example .env`, then set a random strong `DB_PASSWORD` and an admin password outside source control.

```sh
docker compose config
docker compose up -d --build
```

The default port is `127.0.0.1:18080` for local-only staging. The app may perform database migrations at startup; run only against disposable staging volumes.

**GenieACS is not part of the default deployment.** See `docs/nostek/GENIEACS-STAGING.md` before using the optional Compose overlay.

## License and provenance

See `LICENSE`. The original MIT copyright holder remains credited there. The UI/application identity `thuekx` does not replace or erase third-party copyright ownership. Independent GenieACS components are AGPLv3-licensed; do not bundle or distribute them without satisfying their license obligations.

## Development

See `docs/nostek/INTEGRATION-PLAN.md` and `docs/nostek/BASELINE-REVIEW.md`. Existing vendor-specific reference docs under `docs/` are reference material; verify real device firmware/OID/CLI before using any write action.
