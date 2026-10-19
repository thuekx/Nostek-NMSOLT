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
- NMS-01 PR #1 https://github.com/thuekx/Nostek-NMSOLT/pull/1 is merged. Verified head `b20149cad1aff4087108f7dc4cd1433da307495b`; successful integration run https://github.com/thuekx/Nostek-NMSOLT/actions/runs/39278668150; squash merge `63f4b77b002fa5ef9b1189d4a4c982c0246a1354`.
- NMS-01 evidence: Compose base/ACS PASS, loopback publishing PASS, both images build without push, Go test/vet PASS, Vue 12 files/57 tests PASS, Laravel 122 tests/4,505 assertions/0 failures. The 660 non-fatal PHPUnit warnings and runner action deprecations remain visible backlog; they were not reported as clean output.
- NMS-02 draft PR #2 https://github.com/thuekx/Nostek-NMSOLT/pull/2 adds a default-off device-write boundary, audit decisions, queued-job rechecks and negative/inventory tests. Current head `ac425b7d827ae83ca96daa7a4a67289bf0da1b5a`; full CI is pending, so NMS-02 is IN_PROGRESS.
- NMS-03 draft PR #3 https://github.com/thuekx/Nostek-NMSOLT/pull/3 contains only a read-only Maung collector and evidence checklist. No host connection occurred. NMS-03 is BLOCKED on authorized execution and sanitized host evidence.

## Next concrete actions without owner input

1. Inspect all five jobs in PR #2 at its current head. Fix failures without weakening assertions; merge only with a green run and reviewed security evidence.
2. After PR #2 is merged, rebase/retarget PR #3 onto main and run CI/shell syntax validation. Keep the collector read-only.
3. To complete NMS-03, obtain authorized execution of the collector on the intended Maung host, review/sanitize the output, and record CPU/RAM/disk, port/network/Compose collisions, reverse-proxy ownership, backups and a bounded staging budget.
4. NMS-04 and later infrastructure stages remain blocked until NMS-03 evidence and any required human approval exist. Continue only source-level documentation, design, fixtures and tests that cannot affect production.
5. Never infer OLT/ONT or host state from historical details, and never mark a phase VERIFIED without the cited run/log/artifact.

## Safety

**No production OLT/ONT writes.** Original code retains Telnet/SNMP SET, ONU provisioning and other mutating endpoints; not broker-governed yet. Lab only with explicit owner-approved targets. Do not expose CWMP to customer networks or default wildcard. All admin/DB/JWT secrets local/private; no GitHub plaintext secrets. Preserve upstream MIT copyright and evaluate GenieACS AGPL obligations before distribution. GitHub public repo explicitly user-approved, not an error to undo. Maung WO/CRM remain independent source repositories and are not modified implicitly.

## User style / interaction

Owner communicates Indonesian, prefers complete executable one-block scripts if host input is unavoidable; does not want to manually patch code. Checkpoint-driven continuous execution is expected **within active session**. Summaries and decisions should be durable in GitHub rather than only in chat. Do not claim work can happen autonomously between chat sessions. When session ends, share these file links and a concise new-chat prompt.

## New-session prompt to paste

> Lanjutkan proyek Nostek-NMSOLT dari checkpoint GitHub. Baca `docs/nostek/HANDOFF.md`, `ROADMAP.md`, `PROJECT-STATUS.yaml`, dan `STATUS-TREE.md`; verifikasi `main`, PR #2, PR #3, serta CI terbaru. NMS-01 sudah VERIFIED dan merged sebagai `63f4b77`. Selesaikan review/CI NMS-02 tanpa melemahkan gate. Setelah NMS-02 VERIFIED, rebase PR #3 dan lanjutkan NMS-03 hanya dengan inventaris Maung read-only yang diotorisasi; jangan menyentuh produksi atau OLT/ONT live dan jangan melewati approval. Catat seluruh bukti dan blocker di GitHub. Bahasa Indonesia.
