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

## D-2a — status honesty for OLT state (stale is never green)

**Branch:** `step/nms-07-d2-stale-states` (on top of D-1).

**Finding.** Every OLT status came from the last poll snapshot, with no notion of age. An
OLT whose poller had stopped hours ago still showed:

- "Up" on the dashboard;
- "Online 2 / Offline 0" in the dashboard cards;
- "OK" in green in the OLT list;
- "Online" on the OLT detail page;
- "Available" in green on the PON-port page;
- its ONUs counted as online.

**Rule.** `App\Support\Freshness` uses the same rule as the Control Center:

| State | When |
| --- | --- |
| STALE | the snapshot is older than 3 × the OLT poll interval (15 min at 5 min) |
| UNKNOWN | never polled |
| PAUSED | polling off |
| FRESH | otherwise |

Only FRESH may be green.

| Screen | Now |
| --- | --- |
| Dashboard OLT inventory | pill Up/Down only when fresh; otherwise Stale (amber) or Unknown/Polling off (grey); "Not current N" total |
| Dashboard cards | OLT and ONU online/offline count fresh OLTs only; "Not current" shows the rest |
| OLT list (4 layouts) | "OK" green only when fresh; "OK · Stale" amber, "OK · Polling off" / "OK · Unknown" grey |
| OLT detail | Online (fresh), Stale, Polling off or Unknown |
| PON-port ONU page | "Available" green only when the port data is fresh; relative age under the last refresh time |

**Kept on purpose.** API v1 `/summary` and `/public/status` and the Telegram summary keep
the legacy counts. Changing a consumer-visible contract silently is worse. NMS-08
(versioned API with freshness metadata) gets this as a requirement:
"stale OLTs must not count as online".

**Evidence.**

- `tests/Feature/FreshnessTest.php`, 4 tests:
  - the boundary at exactly 3 intervals;
  - unknown, paused and invalid dates;
  - dashboard list and cards;
  - the legacy counts kept for the API;
  - freshness on the OLT list, detail, port and dashboard pages.
- `tests/js/freshness.spec.js`: the status mapping, and the inventory list rendering a stale OLT as Stale.
- The `DashboardTest` fixture gained `last_polled_at` (an OLT never polled is not "Up").
- Real browser with two demo OLTs, polled 1 minute and 5 hours ago:
  - dashboard pills Up and Stale;
  - cards "Online 1 · Offline 0 · Not current 1";
  - OLT list "OK" and "OK · Stale".
- Laravel 817 passed, Vitest 71 passed, `vite build` passes.

## D-2b — ONU, map, alarm and vendor pages (stale is never green)

**Branch:** `step/nms-07-d2b-onu-states` (on top of D-2a).

| Journey | Before | After |
| --- | --- | --- |
| ONU monitor | rows of a stale OLT showed green "online" dots and a green online count | notice "data not current (state, last poll age)"; dots and the online count are neutral grey |
| ONU rows (inventory, API v1 `/onus`, map options) | no freshness | each row carries `stale` (additive field; `online` keeps the last known value) |
| Map | pins and ODP lines of a stale OLT green or red | grey pin and line, legend entry "not current", pin card "Online · Stale", online count excludes stale pins |
| Alarms | an OLT that is not polled any more has no new alarms, so the page looked healthy | notice naming each OLT whose alarms are not evaluated (stale, unknown, polling off) with the age |
| C-Data / HiOSO detail and port pages | green online counts and "Available" from any cache age | shared `DataNotCurrent` notice; status card shows the state; online count green only when fresh |
| ONU detail | live CLI fetch on open; failure shows the error | already honest; no change |
| Global search | shows no status | nothing to change |
| ONU ↔ ACS badge | own rule (last inform age, `acs_stale`) | unchanged |

Shared pieces: `resources/js/Components/Shell/DataNotCurrent.vue` (renders nothing when
fresh), `OnuInventoryService` `stale` per row (memo per OLT), C-Data and HiOSO `olt.freshness`.

### Evidence

- `tests/Feature/FreshnessTest.php`: 3 new tests (ONU rows, ONU monitor props and map pins
  of a stale OLT; alarm page names stale and paused OLTs; C-Data and HiOSO pages carry
  freshness).
- `tests/js/dataNotCurrent.spec.js` (5) and 2 new `PortOnuStats` cases.
- Real browser, two demo OLTs polled now and 5 hours ago: the ONU monitor shows the notice
  only for the stale OLT ("Stale, last poll 5 hours ago"), the online count is grey; the
  alarm page lists "OLT-DEMO-PATI · Stale · 5 hours ago".
- Laravel 820 passed, Vitest 78 passed, `vite build` passes.

## D-2c — partial and permission states

**Branch:** `step/nms-07-d2c-permission-states` (on top of D-2b).

**Partial state found.** A poll refreshes every PON port at once, so the ports of one OLT
never have different ages. The partial case is the RX read: when the ONU status poll works
but the RX read fails, the RX column keeps older values (`preserveExistingRx`) and only an
error line said so. The PON port page now also says how old the RX values are when they lag
the status by more than the freshness threshold (`rxLags()` in `resources/js/lib/freshness.js`),
or that RX was never read.

**Permission states, swept per role** (`tests/Feature/RoleJourneyTest.php`), for admin,
operator, partner and demo, each with an OLT it may see:

- every sidebar link of the role opens with 200; the test's sidebar list is compared with
  `AuthenticatedLayout.vue`, so a new link fails the test until it is classified;
- every signed-in GET page without parameters, and every page of the role's OLT, answers
  below 500 (more than 100 requests across the roles);
- a page a role is refused (403/404) is never in that role's sidebar;
- the "Add OLT" button (`add_olt`) and the ACS pin search (`manage_acs`) are shown exactly
  when the server allows them.

Result: no server error and no sidebar link into a refused page. The UI already hid the
refused controls (users, audit log, settings, partner Telegram, add OLT, ACS pin search),
so no code change was needed there; the test now keeps it that way. Mutation check: putting
`users.index` in the operator sidebar fails two tests.

### Evidence

- `RoleJourneyTest` 4 tests (243 assertions); `freshness.spec.js` +3 (`rxLags`).
- Laravel 824 passed, Vitest 81 passed, `vite build` passes.

## Next

E2 (ONU monitor scoped to one OLT) is done; see NMS-07-PERFORMANCE.md. Remaining NMS-07
UI work: none known from this audit. Remaining NMS-07 evidence: the rest of E and a real
app image on a host (owner-run package).
