# Session handoff — Nostek-NMSOLT

**Canonical restart page:** https://github.com/thuekx/Nostek-NMSOLT/blob/main/docs/nostek/HANDOFF.md  
**Companion files:** [ROADMAP.md](ROADMAP.md), [STATUS-TREE.md](STATUS-TREE.md), [PROJECT-STATUS.yaml](PROJECT-STATUS.yaml).  
**Snapshot date:** 19 October 2026 (Asia/Jakarta). Refresh CI evidence before resuming.

## Mission

Finish an independently Dockerized Nostek-NMSOLT web application, GenieACS lab, Maung WO/CRM integration, and separately configured Nostek AI installation, through NMS-12 gates. Same source, separate tenant/site data and secrets; no new VM until resource check. User wants tasks to progress automatically between VERIFIED checkpoints **within each active chat** without repeated confirmations for safe work; user accepts explicit approvals for infrastructure changes and device writes.

## Current live state (verified)

- Public GitHub `thuekx/Nostek-NMSOLT`; main initial commit `24332a2b4144cff03f04f64e4711ce7938d992ae`, author `thuekx <thuekx@kolu.web.id>`. Initial import 678 files, mobile excluded, no staged web PNG/SVG/ICO. MIT upstream attribution remains.
- Windows clone `D:\Github Repo\Nostek-NMSOLT` and local backup copies exist; Windows source changes were pushed successfully by owner. **Do not assume local Windows state is accessible via GitHub or container.**
- Existing stack draft: Laravel 12 / Vue / Inertia + Go SNMP poller, PostgreSQL16, Redis7, Nginx/PHP-FPM/worker; `docker-compose.yml` binds web to `127.0.0.1:18080`. Optional `compose.acs.yaml`: GenieACS 1.2.16 + MongoDB7; CWMP and UI loopback, NBI/FS private. These are lab drafts, not running Maung services.
- Target Maung VM `maung-apps` historically at `192.168.48.99`; current CPU/RAM/storage/ports **not yet audited**. No authorized Docker deployment at Maung; do not alter other Maung services.
- Target second site: existing Nostek AI VM (choose after resource study, likely nostek-platform vs nostek-ops); no new VM presumed.
- User requested Flutter/mobile deferred because inherited logo resembled 'K'; the mobile files remain physically in Windows checkout but `/mobile/` is Git-ignored and absent in initial commit.
- Gitleaks 8.30.1 and PowerShell prepush static scan passed on Windows at initial commit. This does not equal a full third-party security audit.
- NMS-01 draft CI PR #1: https://github.com/thuekx/Nostek-NMSOLT/pull/1, branch `ci/nms-01-docker-gates`, last known head `1897c7facd45c431310eb60c11594c3d3c683f05`.
- Latest checked PR run: https://github.com/thuekx/Nostek-NMSOLT/actions/runs/39275735589 — four jobs pass: Compose base/ACS, Go poller, JS/Vue, both Docker builds. **Laravel job fails**, 98 failed/562 warnings/122 passed, 3524 assertions. Previous issue missing APP_KEY was addressed by ephemeral secret in workflow; remaining examples include `ViteManifestNotFoundException` and locale-based string mismatch in `tests/Unit/CDataGponPortWriteTest.php`, plus fixture/path warnings. Do not merge until all tests green.

## Next concrete actions without owner input

1. Fetch current open PR, CI runs and failing Laravel logs. Confirm SHA, don't assume checkpoint is fresh.
2. Amend **CI branch only** to install Node and build frontend for Laravel view tests or otherwise use properly isolated Vite testing. Avoid weakening tests or hiding errors. Diagnose failures after build assets exist, fix application bugs/fixture paths if needed. An initial `npm ci && npm run build` in PHP job is a credible next trial; repository lockfile exists and separate frontend job passes.
3. If needed open a **separate security PR** for production write-operation isolation/R0 restrictions; do not change OLT semantics without tests.
4. Re-run CI. Only after full VERIFIED mark NMS-01 and proceed NMS-02/NMS-03. Avoid merging draft PR without review and complete evidence.
5. Do Maung host inspection read-only, then produce resource/deployment plan. If connection unavailable request a single consolidated read-only collection command rather than many rounds. Never execute `docker compose down -v` against live data.
6. Later: optional isolated GenieACS lab, Maung SSO and /nmsolt, inventory/CRM/WO contracts, approved production, second Nostek Docker, broker approvals, commercial readiness per ROADMAP.

## Safety

**No production OLT/ONT writes.** Original code retains Telnet/SNMP SET, ONU provisioning and other mutating endpoints; not broker-governed yet. Lab only with explicit owner-approved targets. Do not expose CWMP to customer networks or default wildcard. All admin/DB/JWT secrets local/private; no GitHub plaintext secrets. Preserve upstream MIT copyright and evaluate GenieACS AGPL obligations before distribution. GitHub public repo explicitly user-approved, not an error to undo. Maung WO/CRM remain independent source repositories and are not modified implicitly.

## User style / interaction

Owner communicates Indonesian, prefers complete executable one-block scripts if host input is unavoidable; does not want to manually patch code. Checkpoint-driven continuous execution is expected **within active session**. Summaries and decisions should be durable in GitHub rather than only in chat. Do not claim work can happen autonomously between chat sessions. When session ends, share these file links and a concise new-chat prompt.

## New-session prompt to paste

> Lanjutkan proyek Nostek-NMSOLT dari checkpoint GitHub. Baca terlebih dahulu `https://github.com/thuekx/Nostek-NMSOLT/blob/main/docs/nostek/HANDOFF.md`, `ROADMAP.md`, `PROJECT-STATUS.yaml` dan `STATUS-TREE.md` dalam folder sama; cek langsung status PR #1 dan GitHub Actions terbaru sebelum mengubah apa pun. Ikuti tahapan NMS-00 sampai NMS-12; kerjakan otomatis task berikutnya ketika gate VERIFIED tanpa berhenti bertanya, selama tidak memerlukan akses/approval saya. Jangan sentuh Docker Maung produksi atau OLT/ONT live tanpa persetujuan. Seluruh perubahan source lewat branch dan PR, catat evidence dan update status/handoff. Mulai dari Laravel CI yang masih gagal. Bahasa Indonesia.
