# UI-F1: organisations and sites in NMSOLT (design v0.2, DECIDED)

Status: DECIDED by the owner 2026-10-25 01:02 WIB: D1 = a, D2 = a, D3 = a with RLS done right after the
app scope (not deferred, before launch), D4 = a. Plain-language explanation given to the owner:
`notes/nmsolt/ui-f1/penjelasan-d1-d4.md` (project files).
Rule PRODUCT-1: design for real customers now (hosted and on-prem, many ISPs, devices on customer
networks), not only the LIV LAN.

## 1. Why
The redesign list has UI-F1 "pick org and site" in the header. NMSOLT has no tenant model today:

| Today | How it works | Limit |
|---|---|---|
| Roles | `admin`, `operator`, `partner`, `demo` (`App\Enums\UserRole`) | One installation = one ISP |
| Private OLT | `snmp_olts.owner_user_id` (partner owns it) | Ownership is per user, not per company |
| Assignment | pivot `olt_user` (operator/partner see listed OLTs) | No grouping by area or POP |
| Isolation | `PartnerOltScope` + `DemoScope` on `SnmpOlt` and every `snmp_olt_id` model | App-level only, no DB guard |

The Control Center already has `organization` + `site` (`Nostek-AI docs/nostekai/ui/DATA-MODEL.md` §2–3,
PostgreSQL RLS on `org_id`), and UI-D3 says NMSOLT is federated into CC by **`org_id` + `site_key`**.
NMSOLT should use the same two keys so that link works without re-architecture.

## 2. Proposal
### 2.1 Data
```text
organizations(id uuid, slug unique, name, kind [isp|reseller|demo], cc_org_id uuid NULL, status)
sites(id uuid, organization_id, key, name, kind [pop|datacenter|area], region, timezone,
      UNIQUE(organization_id, key))            -- key = CC site_key (e.g. 'juwana', 'pati')
organization_user(organization_id, user_id, role [org_admin|operator|technician|viewer])
snmp_olts.organization_id NOT NULL, snmp_olts.site_id NULL
```
- Every row that hangs off an OLT (ODP, pins, alarms, polling, registrations, RX history) stays keyed by
  `snmp_olt_id`. The organisation comes through the OLT, so no second column can drift.
- Migration backfill: one organisation (the install's own, e.g. `liv`) gets every existing OLT and user.
  LIV sees no change.

### 2.2 Isolation
- New `OrganizationScope` (global scope) on `SnmpOlt`, and through the OLT on every `snmp_olt_id` model.
  It filters to the session's current organisation, and it is applied AND-ed with the existing scopes.
- Another organisation's id returns **404, never 403** (same rule as the Control Center).
- Every org-scoped endpoint gets a negative test, as in `tests/test_tenant_isolation.py` in CC.
- PostgreSQL RLS on `snmp_olts.organization_id` as a second guard, right after the app scope (D3, F1d).

### 2.3 Site
- A site is a **filter and a grouping**, not a security boundary in v1. The header picker reads
  "Organisation ▸ Site". "All sites" is the default, and the choice is remembered per user.
- The Dashboard, Network tree, ONU monitor, Map, Alarms and Field console all filter by the chosen
  site. OLT forms get a Site field.
- Site-scoped roles (a technician sees only their area) come in a later step, using the same
  `scope_kind = site` idea as CC `role_binding`.

### 2.4 UI
- The header picker shows only when the user belongs to more than one organisation or the org has more
  than one site. A single-ISP install looks the same as today.
- The field console and the mobile API take the organisation from the session or token. There is no
  picker on phones unless it is needed.

## 3. Decisions for the owner
| # | Question | Options | Recommendation |
|---|---|---|---|
| D1 | What is a "partner" now? | a) its own organisation (kind reseller) with its OLTs moved in; b) stays an OLT-level grant inside one org | **a**: clean isolation for hosted customers; `olt_user` stays for operators inside an org |
| D2 | Source of truth for orgs/sites | a) NMSOLT local tables, linked to CC by `cc_org_id`/`site.key`; b) read from CC API | **a**: NMSOLT must run standalone (on-prem without CC); CC links by keys |
| D3 | DB guard | a) app scope now, RLS in phase 2; b) RLS from the start | **a**: one change at a time; RLS after the scope and its tests are proven |
| D4 | First step size | a) F1a schema + backfill + scope (no visible change); b) everything in one PR | **a**: small PRs; the picker follows in F1b |

### 3.1 Owner decisions (2026-10-25)
| # | Decision | Note |
|---|---|---|
| D1 | **a** | A partner that owns OLTs becomes its own organisation (F1c). A partner that is only assigned OLTs of the ISP stays a user of the ISP organisation. |
| D2 | **a** | NMSOLT keeps its own `organizations`/`sites`; CC links by `cc_org_id` + `site.key`. CC gets links, a per-site summary API and later SSO, never ONU lists or customer data. |
| D3 | **a, no gap** | App scope first, then RLS straight after F1a–F1c, before launch. "Launch soon after the product is done, so do not leave RLS as a big PR for later." RLS is a launch gate. |
| D4 | **a** | F1a first: schema, backfill, scope, negative tests; no visible change. |

## 4. Steps after the decision
1. **F1a**: migrations plus backfill to one organisation, `OrganizationScope`, and negative tests on
   every OLT-keyed endpoint. No UI change. The owner merges (security change).
2. **F1b**: header picker, the site field on OLT forms, and site filters on the main pages.
3. **F1c**: partners become organisations (if D1 = a), with a one-shot data migration package.
4. **F1d**: PostgreSQL RLS, started right after F1c (owner D3, 2026-10-25); a launch gate, not a later phase.

Each step is a NMSOLT PR, and every host change is a package the owner runs (PREFLIGHT → APPLY → VERIFY).
