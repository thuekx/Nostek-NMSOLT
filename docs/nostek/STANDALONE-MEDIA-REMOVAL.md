# Nostek-NMSOLT: Standalone UI / Media Removal

- Root `/` redirects to `/dashboard`, and existing authentication middleware sends unauthenticated users to `/login`.
- Public promotional `Welcome.vue`, demonstration screenshots, original OLT model photos, external technology artwork and screenshot-based user-guide PDFs removed.
- Device detail remains usable as a text/model card without image network requests.
- Full operational dashboard, map, alarm, OLT/ONU and device-management pages are **not** removed.
- Android/mobile artwork remains in the separate `mobile/` tree and is not part of the Docker web build. **Do not distribute Android APKs before a separate asset/license/identity review.**
- Original MIT legal notice is preserved in `LICENSE` as required.
- Gitleaks 1 finding was reported on Windows source snapshot; **not yet resolved**. See `scripts/locate-gitleaks-finding.ps1`. Do not push until scan passes or a documented non-secret false positive is reviewed.
- No device commands, database migrations, Docker runtime or production OLT tests have been executed as part of this cleanup.
