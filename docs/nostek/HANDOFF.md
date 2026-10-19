# Session handoff — Nostek-NMSOLT

**Canonical restart page:** https://github.com/thuekx/Nostek-NMSOLT/blob/main/docs/nostek/HANDOFF.md  
**Companion files:** [ROADMAP.md](ROADMAP.md), [STATUS-TREE.md](STATUS-TREE.md), [PROJECT-STATUS.yaml](PROJECT-STATUS.yaml).  
**Snapshot date:** 20 October 2026 (Asia/Jakarta). Refresh CI evidence before resuming.

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
- NMS-02 PR #2 https://github.com/thuekx/Nostek-NMSOLT/pull/2 is merged and VERIFIED. Verified head `0fcb962347c20873f75ba54273e346247db5c613`; successful run https://github.com/thuekx/Nostek-NMSOLT/actions/runs/39284353892; squash merge `732debb348ca62d74df256a7f233c3601d48710a`. Evidence: all five jobs passed; Vue 57 tests; Laravel 122 passed / 4,603 assertions / 665 non-fatal warnings / 0 failures; default-off route/queue/Telegram device-write boundary reviewed.
- NMS-03 readiness PR #3 https://github.com/thuekx/Nostek-NMSOLT/pull/3 is merged as `f925529ef0b41026a08e8bd9639cedbe3ff6e22b`. Head `61e6ca45c20504e12f3ada619e52e4638eaa9534` passed run https://github.com/thuekx/Nostek-NMSOLT/actions/runs/39290385687, including `bash -n` for the read-only collector. No host connection occurred. NMS-03 remains BLOCKED on authorized execution and sanitized host evidence.

## Next concrete actions without owner input

1. To complete NMS-03, obtain owner authorization to run `scripts/nostek/collect-maung-r0.sh` on the intended Maung host as an ordinary account. Do not use sudo, inspect secrets, or change host state.
2. Review and sanitize the output, then record CPU/RAM/disk, port/network/Compose collisions, reverse-proxy ownership, backups and a bounded staging budget.
3. NMS-04 and later infrastructure stages remain blocked until NMS-03 evidence and any required human approval exist. Continue only source-level documentation, design, fixtures and tests that cannot affect production.
4. Never infer OLT/ONT or host state from historical details, and never mark a phase VERIFIED without the cited run/log/artifact.

## Safety

**No production OLT/ONT writes.** Original code retains Telnet/SNMP SET, ONU provisioning and other mutating endpoints; not broker-governed yet. Lab only with explicit owner-approved targets. Do not expose CWMP to customer networks or default wildcard. All admin/DB/JWT secrets local/private; no GitHub plaintext secrets. Preserve upstream MIT copyright and evaluate GenieACS AGPL obligations before distribution. GitHub public repo explicitly user-approved, not an error to undo. Maung WO/CRM remain independent source repositories and are not modified implicitly.

## User style / interaction

Owner communicates Indonesian, prefers complete executable one-block scripts if host input is unavoidable; does not want to manually patch code. Checkpoint-driven continuous execution is expected **within active session**. Summaries and decisions should be durable in GitHub rather than only in chat. Do not claim work can happen autonomously between chat sessions. When session ends, share these file links and a concise new-chat prompt.

## New-session prompt to paste

> Lanjutkan proyek Nostek-NMSOLT dari checkpoint GitHub. Baca `docs/nostek/HANDOFF.md`, `ROADMAP.md`, `PROJECT-STATUS.yaml`, dan `STATUS-TREE.md`; verifikasi `main` serta CI terbaru. NMS-01 dan NMS-02 sudah VERIFIED (`63f4b77`, `732debb`). Readiness NMS-03 sudah merged sebagai `f925529`, tetapi fase tetap BLOCKED sampai inventaris Maung read-only diotorisasi dan hasilnya disanitasi. Jangan menyentuh produksi atau OLT/ONT live dan jangan melewati approval. Lanjutkan pekerjaan source-level aman serta catat seluruh bukti dan blocker di GitHub. Bahasa Indonesia.
