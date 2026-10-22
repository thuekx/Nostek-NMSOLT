# Nostek-NMSOLT — Codex Cloud handoff

This handoff starts from the completed NMS-07 identity checkpoint on GitHub main. A Cloud
task starts from GitHub; it cannot see unpushed files, the Maung VM, private
management networks or credentials stored on the original PC.

## Cloud task setup

1. Create/select a Codex Cloud environment for `thuekx/Nostek-NMSOLT`.
2. Start from the repository default branch `main` after refreshing it.
3. Do not add production, Maung, OLT/ONT, SNMP, ACS or Caddy credentials.
4. Allow only repository dependency installation and isolated CI-equivalent tests.
5. Use a feature branch and GitHub PR for every change.

## Canonical context

- NMS-00 through NMS-06 are VERIFIED.
- PR #19 merged the provider-centric roadmap as `62b414f04ac4b5e4f024669e431be29f4d0845d1`.
- PR #21 passed all five gates in run 39770297399 and merged the canonical identity/namespace as `f43baa3fa0f4959b8513011e01e79a6db8509f51`.
- Nostek-NMSOLT is the standalone network platform and data/API provider.
- Maung and Nostek-AI are consumers. A future Maung `/nosteknmsolt` page is a
  thin API/settings/quick-view surface, not a polling or database layer.
- GenieACS is a bundled Nostek-NMSOLT subsystem implemented as isolated
  containers; it is not intended to require a separately managed product server.
- Laravel/Vue/Inertia and the Go poller remain until profiling proves a rewrite
  is justified.
- Device writes, production, Caddy, `maung_edge`, Maung/CRM/WO repositories and
  secrets remain outside Cloud scope.

## PR #20 alignment and resume rules

The search-audit branch `work/nms-07-search-audit` is aligned with PR #22 main
`b0cbf36ac6f59552a3ef4f3a01f187997d221f86`. Search changes are pending merge, not part of main.
Fetch main and PR #20 before deciding where to resume. Do not recreate or discard
the search fixes. Require exact-head CI and review after alignment; run
`39715103922` verifies only original search head `02949e5`. Owner merge approval
is still required for PR #20. Do not merge or delete branches merely because
historical CI is green.

## Active work

NMS-07 is IN PROGRESS:

1. preserve the completed identity standard: `Nostek-NMSOLT`, short name
   `Nostek-NMS`, internal `nos-*` namespace and `nos-snmp-poller`;
2. preserve the existing LICENSE attribution required by the inherited MIT code;
3. unify the full installation path for app/PostgreSQL/Redis plus
   GenieACS/MongoDB with private internal services and explicitly bound CWMP;
4. add unified health, preflight, backup/restore, upgrade and rollback evidence;
5. audit functions, UI journeys, stale/partial states and performance;
6. proceed to NMS-08 only when NMS-07 has complete evidence.

## Required first actions in Cloud

Read `docs/nostek/HANDOFF.md`, `ROADMAP.md`, `PROJECT-STATUS.yaml`,
`STATUS-TREE.md`, `NMS-07-PRODUCT-STABILIZATION-AUDIT.md` and this file. Verify
the current `main` SHA, open PRs and exact-head CI. Never infer VERIFIED from a
plan or a green documentation-only test.

## Copy-ready Cloud prompt

> Pelajari dan lanjutkan thuekx/Nostek-NMSOLT dari GitHub terkini. Baca enam
> file kanonis: HANDOFF.md, ROADMAP.md, PROJECT-STATUS.yaml, STATUS-TREE.md,
> NMS-07-PRODUCT-STABILIZATION-AUDIT.md dan CLOUD-HANDOFF.md di docs/nostek.
> Periksa main, PR terbuka, current head SHA, CI exact-head dan review. Baseline
> penyelarasan PR #20 adalah main b0cbf36 (PR #22); refresh karena bisa berubah.
> PR #21 identity cleanup sudah merged. Pertahankan perbaikan search, validasi
> input, MAC navigation dan regression tests PR #20; jangan menganggapnya sudah
> ada di main sebelum memverifikasi merge. Baca feature matrix dan source inventory.
> PR #20 memerlukan CI pada head hasil penyelarasan, review diff dan approval
> owner sebelum merge; jangan gunakan run lama sebagai bukti head baru.
> NMS-00–06 tercatat VERIFIED; NMS-07 IN_PROGRESS; NMS-08–12 BLOCKED.
> Lanjutkan pekerjaan source yang aman setelah pemeriksaan: unified full package
> app/PostgreSQL/Redis + GenieACS/MongoDB, health/preflight, backup/restore,
> upgrade/rollback, audit UI/write visibility dan profiling terukur. NMSOLT
> provider; Maung dan Nostek-AI consumer lewat API/tools, tanpa akses DB langsung.
> GenieACS bundled dengan isolasi internal. Pertahankan Laravel/Vue/Go,
> Nostek-NMSOLT/Nostek-NMS, namespace nos-* dan cmd/nos-snmp-poller; LICENSE
> attribution wajib dipertahankan. Jangan akses produksi, Maung, Caddy,
> maung_edge, CRM/WO, secrets atau live OLT/ONT. Device writes tetap disabled.
> Gunakan branch, synthetic tests, PR review dan bukti exact-head; langkah yang
> butuh akses eksternal/approval dicatat BLOCKED. Sinkronkan checkpoint dan
> handoff; jangan klaim runtime VERIFIED dari source tests. Tidak boleh merge
> atau hapus branch tanpa seluruh gate dan approval yang berlaku.
