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
- **NMS-05 VERIFIED:** source policy [PR #6](https://github.com/thuekx/Nostek-NMSOLT/pull/6);
  authorized lab runner and explicit private CWMP binding
  [PR #14](https://github.com/thuekx/Nostek-NMSOLT/pull/14), merged
  a6daef774f46d5aaeadcbba1529258fef90b29b6; published-service network repair
  [PR #15](https://github.com/thuekx/Nostek-NMSOLT/pull/15), merged
  08a57e6be31fbf00bcc9fe4cd5cb8f87bfa6a61f; Maung host guard
  [PR #16](https://github.com/thuekx/Nostek-NMSOLT/pull/16), successful run
  39591657569, merged 2a1547cd7d06e4d07d6526862ea615a105366cd5.
  One approved non-production ZTE F660 completed Bootstrap and Periodic Inform.
  Serial identity matched locally without disclosure; a read-only
  getParameterValues task completed on Periodic Inform; 0 faults, 0 pending
  tasks and 0 presets. ACS stop/start preserved the device record while the base
  app, PostgreSQL and Redis remained healthy. No OLT/ONT parameter write,
  production change, Caddy change or maung_edge attachment occurred. See
  [sanitized runtime evidence](NMS-05-GENIEACS-RUNTIME-EVIDENCE-2026-10-21.md).
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

## Exact next action

NMS-06 is now active. Register the owner-approved ZTE C320 using only an
RO community and manual Test/Refresh. Collect sanitized system, PON, ONU, RX,
stale-state, alarm-debounce, ODP mapping and read-only UI evidence. Keep
automated polling, Telnet, SNMP write community and all device mutations
disabled. The owner needs to supply the management address and retain the RO
community locally; neither value belongs in public evidence.

## Continuation after NMS-05

1. Record the approved C320 management path locally; do not publish its address or community.
2. Add the C320 in SmartOLT with SNMP v2c RO only; leave write community and CLI blank.
3. Run manual Test SNMP and one bounded Refresh; collect sanitized system/PON/ONU/RX evidence.
4. Exercise stale-state, alarm debounce, ODP mapping and UI paths using lab data/fixtures without device mutation.
5. Mark NMS-06 VERIFIED only when its complete matrix passes. NMS-07 Caddy/SSO,
   NMS-08 WO/CRM, production and every device write remain separately gated.



## New-session prompt

> Lanjutkan thuekx/Nostek-NMSOLT dari GitHub. Baca HANDOFF.md, ROADMAP.md,
> PROJECT-STATUS.yaml dan STATUS-TREE.md; verifikasi main, PR dan CI terbaru.
> NMS-00..05 VERIFIED. Base staging is isolated with device writes disabled.
> One approved ZTE F660 completed Bootstrap, Periodic Inform, local serial
> match, compatible read-only task and ACS stop/start persistence; sanitized
> evidence is committed without device identifiers. NMS-06 is the active gate:
> validate the approved ZTE C320 using SNMP v2c read-only, manual Test/Refresh
> and sanitized PON/ONU/RX/stale/alarm/ODP/UI evidence. Keep automated polling,
> Telnet, write community, Caddy, Maung WO/CRM, production and all device writes
> disabled or separately approval-gated. Never claim VERIFIED without evidence.
