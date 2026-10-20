# Session handoff — Nostek-NMSOLT

**Canonical restart page:** https://github.com/thuekx/Nostek-NMSOLT/blob/main/docs/nostek/HANDOFF.md  
**Companion files:** [ROADMAP.md](ROADMAP.md), [STATUS-TREE.md](STATUS-TREE.md),
[PROJECT-STATUS.yaml](PROJECT-STATUS.yaml).  
**Snapshot date:** 21 October 2026 (Asia/Jakarta). Always refresh main, open
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

- **NMS-03 VERIFIED:** preparation [PR #3](https://github.com/thuekx/Nostek-NMSOLT/pull/3)
  passed [run 39290385687](https://github.com/thuekx/Nostek-NMSOLT/actions/runs/39290385687)
  and merged f925529ef0b41026a08e8bd9639cedbe3ff6e22b. The owner ran the
  read-only collector at 2026-10-20T00:52:49+07:00. Raw artifact SHA-256 is
  0187497B9551BE5AB3E0C8598E8F12DE109D8700273EFA1C3C877C5661844ECB;
  raw output remains outside the public repo. Sanitized evidence records 8 vCPU,
  about 13 GiB available RAM, 43 GiB root free, existing Docker/port/network/
  proxy/backup inventory and a bounded base staging budget of 2 vCPU / 3 GiB /
  12 GiB.
- **NMS-04 VERIFIED:** source topology [PR #5](https://github.com/thuekx/Nostek-NMSOLT/pull/5)
  passed run 39291789107 and merged f3823666b78f6eb5bc840ff3f4217858b0c691d3.
  The authorized runner [PR #11](https://github.com/thuekx/Nostek-NMSOLT/pull/11)
  passed [run 39449952838](https://github.com/thuekx/Nostek-NMSOLT/actions/runs/39449952838)
  and merged 3ad09b818b80a73a913acb03a8c8bba7d54efcf2. A restrictive
  build-context permission incident was corrected by
  [PR #12](https://github.com/thuekx/Nostek-NMSOLT/pull/12), successful
  [run 39470245938](https://github.com/thuekx/Nostek-NMSOLT/actions/runs/39470245938),
  merge 4b7437c7169e98a2b42be599bc241ece0a7299e7. That exact permanent
  image is active. Loopback-only isolation, writes-disabled policy, migrations,
  five supervised processes, backup/restore with 38/38 public tables, bounded
  resources, rollback/restart and unchanged pre-existing containers all passed.
  See [sanitized runtime evidence](NMS-04-MAUNG-STAGING-EVIDENCE-2026-10-21.md).
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

NMS-05 requires a separate owner decision identifying one exact non-production
lab ONT and approving its network path to the isolated GenieACS lab. Do not use
a production ONT, expose CWMP publicly, change Caddy, enable device writes, or
perform write-parameter tests. Until that identity and boundary are supplied,
NMS-05 remains BLOCKED; source-readiness work is already complete.

## Continuation after NMS-04

1. Record the exact lab ONT identity/model and confirm it is non-production.
2. Approve only the isolated NMS-05 inform/read matrix and rollback boundary.
3. Deploy the private GenieACS/MongoDB overlay within the remaining approved
   incremental budget; expose only approved loopback/test-network endpoints.
4. Collect sanitized CWMP health, inform identity match, NBI/read-parameter and
   rollback evidence without publishing serials, MACs or credentials.
5. Continue NMS-06 read-only vendor validation. NMS-07 Caddy/SSO, NMS-08
   WO/CRM, production and every device write remain separately approval-gated.



## New-session prompt

> Lanjutkan thuekx/Nostek-NMSOLT dari GitHub. Baca HANDOFF.md, ROADMAP.md,
> PROJECT-STATUS.yaml dan STATUS-TREE.md; verifikasi main, PR dan CI terbaru.
> NMS-00..04 VERIFIED. Base staging NMS-04 aktif secara terisolasi pada image
> 4b7437c, loopback-only, writes disabled; bukti migrasi, proses, backup/restore,
> resource dan rollback tersimpan secara tersanitasi. NMS-05..12 BLOCKED.
> Lanjutkan pekerjaan aman secara berurutan, tetapi jangan menyentuh produksi,
> live OLT/ONT, Maung WO/CRM, Caddy atau rahasia, dan jangan melewati approval.
> Gate berikutnya adalah persetujuan satu ONT lab non-produksi yang spesifik
> untuk matriks inform/read NMS-05. Jangan mengaktifkan device writes atau
> mengekspos CWMP, dan jangan klaim VERIFIED tanpa bukti runtime yang diwajibkan.
