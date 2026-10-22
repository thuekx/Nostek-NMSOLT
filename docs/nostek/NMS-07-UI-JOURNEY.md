# NMS-07-D — UI journey audit: write-gate visibility first

**Date:** 2026-10-22 · **Branch:** `step/nms-07-d-ui-journey` (on top of NMS-07-B2) ·
**State:** D-1 source PASS. It was verified in a real browser against a local demo build;
no host and no device was touched. D-2 (stale/partial/permission states) and E2 (ONU list
paging) are next. NMS-07 stays IN PROGRESS.

## D-1 — the write gate was invisible

**Finding.** `NMSOLT_DEVICE_WRITES_ENABLED` defaults to false, and
`EnsureDeviceWritesEnabled` refuses 43 routes (web and API). The browser was never told.
Every mutating control looked active, for example:

- reboot, enable/disable and delete ONU;
- register and execute;
- profiles with CLI;
- VLAN and port admin state;
- Wi-Fi via ACS;
- Telnet.

A click ended in a 403. The audit log recorded a "blocked" event for each click, but the
operator saw no reason.

**Fix (the server stays the gate).**

| Layer | What |
| --- | --- |
| Shared prop `device_writes` | `{enabled, gated}` for signed-in users. While writes are off, `gated` lists every gated route as method + path regex + optional condition field. The list is built from the route table (`App\Support\DeviceWriteGate`), so a new gated route is covered without UI work. |
| Banner | `AuthenticatedLayout` shows a read-only banner on every page while writes are off. |
| Request guard | `resources/js/lib/deviceWrites.js` hooks the Inertia router `before` event and an axios request interceptor. A request the server would refuse never leaves the browser; the operator gets a toast: "Nothing was sent to the device". Preview requests of conditional routes (`execute`, `execute_cli` false) still go through. |
| `v-device-write` | 56 controls in 17 screens show as disabled, with a "Locked: …" tooltip. On preview-or-execute screens, only the execute control is locked; preview stays usable. On profiles and ONU copy, the "also run on the OLT" checkbox is locked instead, so local-only saves keep working. |
| Drift guard | `DeviceWriteGateUiTest::test_every_screen_calling_a_gated_route_marks_its_controls` fails when a Vue file calls a gated route but has no `v-device-write`. |

**Evidence.**

- `tests/Feature/DeviceWriteGateUiTest.php`, 5 tests:
  - the shared payload matches the route table (43 entries, conditions kept);
  - enabled means no guard list, and guests get none;
  - every gated URI matches its own regex and nothing longer;
  - the drift guard;
  - the server still answers 403 and writes the audit row.
- `tests/js/deviceWrites.spec.js`, 9 tests:
  - matching on absolute and relative URLs;
  - reads, other methods and longer paths are ignored;
  - JSON, form-encoded and FormData conditions;
  - an Inertia visit is cancelled with a toast;
  - axios is rejected with a 403-shaped error;
  - the directive locks and unlocks.
- Real browser (Playwright + Chromium) against `php artisan serve` with the demo seeder:
  - with writes off: the banner is shown, 18 controls are locked on the ZTE PON-port ONU
    page, and an axios reboot is blocked with the toast and **no request sent to the
    server**;
  - with writes on: no banner and 0 locked controls.
- Full suites: Laravel 813 passed, Vitest 69 passed, and `vite build` passes.

## Test-suite warning (found while auditing)

Every Laravel run in the workspace reported about 680 warnings. The cause is
`file_get_contents(.env)` in phpdotenv when the checkout has no `.env` file. CI copies
`.env.docker.example` to `.env` first, so CI never sees the warnings. With an empty `.env`
the suite shows 813 passed and 0 warnings. This is not a product defect; the workspace
note is recorded here.

## Next (D-2, E2)

1. **Stale, partial and unavailable states.** For each journey, check what the operator
   sees when the snapshot is old, a poll half failed, or ACS/SNMP is down:
   - dashboard → OLT → PON → ONU detail;
   - ONU ↔ ACS;
   - alarms;
   - map;
   - search.

   Status honesty rule: stale or unknown is never shown green.
2. **Permission states.** Check each role's view against `auth.can`: admin, operator,
   partner, demo. Partner isolation is already tested server-side; the check is that the
   UI hides or explains, rather than failing with a 403.
3. **E2 — server-side ONU list paging.** The ONU monitor page and `/api/v1/onus` send every
   ONU in one response: 27.6 MB at 51,000 ONUs (NMS-07-PERFORMANCE.md).
