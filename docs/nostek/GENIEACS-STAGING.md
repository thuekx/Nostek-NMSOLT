# GenieACS — optional Docker lab overlay

GenieACS is required for CWMP/TR-069 ONT management, NOT for basic SNMP OLT/ONU monitoring. No ACS device write may be performed without an authorized lab-device approval. GenieACS upstream stable 1.2.16 (reviewed Oct 2026); the main development branch is not a stable production branch. GenieACS is AGPLv3-licensed. See its upstream LICENSE and release notes for distribution requirements.

## Optional stage only

1. Set a strong `DB_PASSWORD` and `GENIEACS_UI_JWT_SECRET` in local `.env`, never commit it.
2. Run `docker compose -f docker-compose.yml -f compose.acs.yaml config` and review exposed ports, then `docker compose -f docker-compose.yml -f compose.acs.yaml up -d --build` on **disposable staging only**.
3. CWMP port 7547 is bound to host loopback 17547; the ONT cannot reach this without a separate, approved network/TLS plan.
4. GenieACS UI binds only to loopback 13000 for SSH tunnel; NBI port 7557, FS 7567 and MongoDB are private and never published.
5. Configure NMSOLT GenieACS NBI host to `http://genieacs-nbi:7557` **only in isolated staging** once its application adapter and controls are validated.
6. Do not use existing customer ONTs, factory reset devices, or change production ACS URL.
7. Before production: choose pinned base image digest, review MongoDB authorization/ACL, network segmentation, credential lifecycle, CWMP authentication, external DNS/TLS and ingress, backup/restore, health metrics and dependency advisories.

## Existing features

NMSOLT has an optional NBI integration in its original architecture. If NBI host isn't set, its matching process remains idle. This Compose overlay is a deployment candidate, not a successfully tested runtime. No existing ONT or OLT inventory is auto-imported.
