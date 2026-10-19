# Session handoff — Nostek-NMSOLT

**Canonical restart page:** https://github.com/thuekx/Nostek-NMSOLT/blob/main/docs/nostek/HANDOFF.md  
**Companion files:** [ROADMAP.md](ROADMAP.md), [STATUS-TREE.md](STATUS-TREE.md),
[PROJECT-STATUS.yaml](PROJECT-STATUS.yaml).  
**Snapshot date:** 20 October 2026 (Asia/Jakarta). Always refresh main, open
PRs and CI before resuming.

## Mission and safety boundary

Continue through NMS-12 using dependency gates and GitHub PR/CI evidence. Safe,
independent source work may continue automatically. Do not deploy to production,
touch a live OLT/ONT, inspect unauthorized secrets, modify Maung WO/CRM, change
Caddy, expose CWMP, or bypass an approval. A source-readiness PASS is not runtime
verification.

## Verified phases

- **NMS-00 VERIFIED:** initial source commit
  [24332a2](https://github.com/thuekx/Nostek-NMSOLT/commit/24332a2b4144cff03f04f64e4711ce7938d992ae);
  public source baseline, mobile excluded, MIT attribution retained.
- **NMS-01 VERIFIED:** [PR #1](https://github.com/thuekx/Nostek-NMSOLT/pull/1),
  exact head b20149cad1aff4087108f7dc4cd1433da307495b, successful
  [run 39278668150](https://github.com/thuekx/Nostek-NMSOLT/actions/runs/39278668150),
  merge 63f4b77b002fa5ef9b1189d4a4c982c0246a1354. Five jobs passed:
  Compose base/ACS, both image builds, Go test/vet, Vue 57, Laravel 122 /
  4,505 assertions / 660 non-fatal warnings / 0 failures.
- **NMS-02 VERIFIED:** [PR #2](https://github.com/thuekx/Nostek-NMSOLT/pull/2),
  exact head 0fcb962347c20873f75ba54273e346247db5c613, successful
  [run 39284353892](https://github.com/thuekx/Nostek-NMSOLT/actions/runs/39284353892),
  merge 732debb348ca62d74df256a7f233c3601d48710a. Default-off request,
  queue and Telegram device-write boundaries passed; Laravel 122 / 4,603
  assertions / 665 non-fatal warnings / 0 failures.

## Blocked phases and completed safe preparation

- **NMS-03 BLOCKED:** [PR #3](https://github.com/thuekx/Nostek-NMSOLT/pull/3)
  passed [run 39290385687](https://github.com/thuekx/Nostek-NMSOLT/actions/runs/39290385687)
  and merged f925529ef0b41026a08e8bd9639cedbe3ff6e22b. The read-only
  collector and sanitization checklist exist. No host connection occurred.
  Blocker: owner-authorized execution on the intended Maung host and sanitized
  CPU/RAM/disk/ports/Docker/proxy/backup evidence.
- **NMS-04 BLOCKED:** [PR #5](https://github.com/thuekx/Nostek-NMSOLT/pull/5),
  head a548131e3aa51901727a5105865e91363f695793, successful
  [run 39291789107](https://github.com/thuekx/Nostek-NMSOLT/actions/runs/39291789107),
  merge f3823666b78f6eb5bc840ff3f4217858b0c691d3. Rendered base and
  ACS topology policies passed. No staging deployment occurred.
- **NMS-05 BLOCKED:** [PR #6](https://github.com/thuekx/Nostek-NMSOLT/pull/6),
  head 97377e53d7532e6d02e257e315b302b6d47215b7, successful
  [run 39294335613](https://github.com/thuekx/Nostek-NMSOLT/actions/runs/39294335613),
  merge f5917c06b05b10a1f727a79dd2b5ce0a4b120008. GenieACS lab
  source policy passed. No ONT was contacted; approved inform/read evidence is
  still required.
- **NMS-06 BLOCKED:** [PR #7](https://github.com/thuekx/Nostek-NMSOLT/pull/7),
  head 2a565b0e5647cf65d1ca0a98f387149c886ec24f, successful
  [run 39295106373](https://github.com/thuekx/Nostek-NMSOLT/actions/runs/39295106373),
  merge 9c6722c218e1e7d6cd0d581b3f8f29ad0fbffdfe. Reviewed OLT family
  routing and unsupported-vendor fail-closed tests executed; Laravel 122 /
  4,620 assertions / 667 non-fatal warnings / 0 failures. Per-model lab
  polling/RX/stale/alarm/ODP/UI evidence is still required.
- **NMS-07 through NMS-12 BLOCKED:** [PR #8](https://github.com/thuekx/Nostek-NMSOLT/pull/8),
  head e31300ab9e2a6030be6541d5a23435e12ff6fb6b, successful
  [run 39296021006](https://github.com/thuekx/Nostek-NMSOLT/actions/runs/39296021006),
  merge 22d609a49cab92d5eb4a2e7303303d6bbc98f3a9. Contracts, approvals,
  negative evidence and stop rules are in
  [NMS-07-12-GATED-READINESS.md](NMS-07-12-GATED-READINESS.md). All remain
  blocked on upstream gates, external contracts, runtime evidence and explicit
  approvals. No Maung, CRM/WO, production or device mutation was performed.

## Exact next action requiring owner authority

Authorize execution of scripts/nostek/collect-maung-r0.sh on the intended Maung
host as an ordinary account. The executor must not use sudo, read environment
secrets, deploy, restart services, change firewall/proxy configuration, or
contact devices. Sanitize the output before it enters GitHub, then record the
bounded staging resource budget and collisions.

Historical target information is maung-apps / 192.168.48.99, but current state
is unverified and must not be inferred from history.

## After NMS-03 evidence

1. Review the sanitized inventory and set a bounded NMSOLT staging budget.
2. Obtain an explicit staging deployment window; execute NMS-04 deploy,
   migration, health, backup/restore and rollback rehearsal.
3. Obtain separate approval for one exact lab ONT; execute only the NMS-05
   inform/read matrix.
4. Continue NMS-06 read-only lab validation, then negotiate the Maung SSO and
   WO/CRM contracts before NMS-07/NMS-08.
5. Production and every device write remain independently approval-gated.

## New-session prompt

> Lanjutkan thuekx/Nostek-NMSOLT dari GitHub. Baca HANDOFF.md, ROADMAP.md,
> PROJECT-STATUS.yaml dan STATUS-TREE.md; verifikasi main, PR dan CI terbaru.
> NMS-00..02 VERIFIED. NMS-03..12 BLOCKED dengan source-readiness sampai NMS-06
> dan kontrak gate NMS-07..12 sudah terdokumentasi. Kerjakan semua langkah aman
> secara berurutan, tetapi jangan menyentuh produksi/live OLT/ONT, Maung WO/CRM,
> Caddy atau rahasia, dan jangan melewati approval. Tahap berikut yang membutuhkan
> otorisasi adalah collector read-only NMS-03 pada host Maung yang dimaksud;
> sanitasi hasil sebelum GitHub. Jangan klaim VERIFIED tanpa bukti exact-head CI
> dan artifact runtime yang diwajibkan.
