# Nostek-NMSOLT — Pre-push baseline review

Import source manually into Windows Git checkout. Do not copy original `.git` from an archived ZIP. This package intentionally excludes all `.git` metadata and developer-assistant prompt files.

## Legal

The source LICENSE is MIT and names the original copyright holder. A legacy code comment inaccurately asserted a proprietary restriction on changing footer branding; it has been corrected. Keep the unchanged original `LICENSE` notice. Treat all upstream assets, icons, third-party images and bundled PDFs as needing separate clearance if distributed commercially.

## Confirmed static observations

- Backend Laravel 12, Vue/Inertia, PostgreSQL, Redis, Go poller. Native Docker Compose currently places nginx/PHP-FPM/worker/scheduler inside the app image.
- Docker service credentials previously allowed a weak database password fallback; this baseline requires explicit `DB_PASSWORD` in Compose.
- Original API token routes support read and write using Laravel Sanctum; fine-grained scoped service tokens and broker verification are not yet built.
- Some OLT write paths use Telnet and SNMP SET. No existing code should be called safe for unapproved live mutations.
- Existing ONU state is largely a JSON snapshot, not a complete durable business asset registry.
- Maung authentication, prefix `/nmsolt`, SSO and CRM/WO event adapters are future work.

## Checks not performed

No full Composer/npm test suite, Docker build, firmware-level OLT test, GenieACS lab test, application penetration test or Maung live integration is asserted here. Docker was not available in this preparation environment.

## Follow-up gates

1. Run `gitleaks dir . --redact` on Windows.
2. Review marketing screenshots, mobile app icons, SVGs, all PDFs and all files for historic personal/customer information.
3. Execute `docker compose config`, isolated Docker build and simulated provisioning tests.
4. Introduce scoped read-only service tokens and high-risk write-action approval paths.
5. Test Maung SSO as backend-validated server-side principal and no stale admin authorization.
6. Keep the original archive offline for comparisons; never commit it.
