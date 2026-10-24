# NMS-02 device-write security matrix

This document records the R0 boundary for Nostek-NMSOLT. It is a source-level
control and does not authorize any production or lab device change.

## Installation default

`NMSOLT_DEVICE_WRITES_ENABLED=false` is the default in both example environment
files and in application config. With that value, read-only monitoring and
script previews remain available, while OLT/ONT mutations and interactive
Telnet ticket issuance return HTTP 403.

Enabling the variable only opens the application-level gate. Existing session or
Sanctum authentication, role checks, tenant/OLT scopes, capability checks,
validation, throttles and audit rules continue to apply. A deployment approval
and an explicitly approved device target are still required outside the codebase.

## Admin switch (WRITE-UI-1, owner 2026-10-24)

The env value is the upper limit; it now takes three values:

| `NMSOLT_DEVICE_WRITES_ENABLED` | Meaning |
| --- | --- |
| `false` (default) | Hard off. The UI switch page is read-only and cannot lift it. |
| `admin` | Off until an admin switches writes on in Settings → Device writes. |
| `true` | Legacy always-on; the switches are ignored. `nmsolt-ops.sh config-check`/`status` FAIL on it. |

In `admin` mode `App\Support\DeviceWritePolicy` decides every write, most restrictive first:
global switch (with an optional auto-off timer of 15 min to 24 h) → channel (`olt` = SNMP set,
CLI and telnet through the OLT; `acs` = GenieACS/TR-069, declared as `device.write:acs` on the two
Wi-Fi routes) → per-OLT block. A missing row means off (global, channel) or "follow the global
switch" (OLT); a per-OLT row can only take writes away. Only the `admin` role reaches the page
(`role:admin`); every change needs a reason (5–500 characters) and writes a
`device_write_setting_changed` audit row with actor, time, before/after and reason. Blocked
requests record `gate_reason` (`installation`, `global`, `channel`, `olt`), `gate_mode`,
`write_channel` and `olt_id`. The request middleware, the Telegram reboot callback and both queued
jobs (re-read at run time; queue workers flush the cached switches before every job) use the
same decision. The browser receives the channel switches and blocked OLT ids so it can lock a
control before the request; the server still decides every request.

## Surface classification

| Surface | R0 behavior | Boundary |
| --- | --- | --- |
| Dashboard, inventory, alarms, reports, ODP/map reads | Allowed | Authentication plus model scopes |
| SNMP/CLI refresh and config backup capture | Allowed read-only | Authentication, OLT scope, capability/throttle |
| Provisioning previews and generated scripts with `execute=false` | Allowed; no device command | Validation plus conditional `device.write:execute` |
| ONU register/execute, reboot, delete, rename/state/config | Denied by default | `device.write` plus existing role/scope/capability checks |
| VLAN, uplink description/admin state, device config save | Denied by default | `device.write` plus existing role/scope/capability/throttle checks |
| GenieACS Wi-Fi mutation | Denied by default | `device.write:acs` (ACS channel) plus role and OLT/ACS scope |
| Interactive browser Telnet ticket | Denied by default | `device.write` plus secret-access authorization and one-time ticket |
| Telegram ONU reboot callback | Denied by default | In-handler gate recheck plus existing chat authorization, OLT scope and two-step confirmation |
| Copy ONU / bulk TR-069 dry-run | Allowed | Conditional gate with `execute=false` |
| Copy ONU / bulk TR-069 execution | Denied by default at request and worker time | Conditional route gate plus queued-job recheck |
| Profile catalog cache-only edits | Allowed | Conditional gate with `execute_cli=false` |
| Profile catalog CLI execution | Denied by default | Conditional `device.write:execute_cli` gate |
| Local-only metadata such as ODP/pin/color/photo and OLT inventory records | Allowed | Authentication, demo-write block, scopes and controller validation |

## Audit behavior

Every request that reaches the device-write boundary is recorded in
`audit_logs` as `device_write_allowed` or `device_write_blocked`, with actor,
route name, HTTP method and path. Request bodies, credentials and command text
are deliberately excluded. Existing operation-specific audit records remain in
place. The Telegram reboot callback applies the same decision audit without
recording the bot token or chat ID. Queue workers recheck the installation gate immediately before an
`execute=true` batch starts. After workers have loaded the disabled
configuration (for example, after the deployment's normal config-cache and
worker reload), queued executions that have not begun are stopped.

## Existing control inventory

- `PartnerOltScope` and `DemoScope` constrain OLT, alarm, ODP, map and related
  model queries. Route-model binding therefore returns 404 for an OLT outside
  the authenticated user's assignment instead of exposing its inventory.
- OLT SNMP communities and CLI passwords, ACS passwords, and global/partner
  Telegram tokens and webhook secrets use encrypted model casts and hidden
  serialization fields.
- Existing role/capability checks restrict secret access, connection edits,
  uplink writes and backup content. Demo accounts remain read-only for both
  session and token API requests.
- Existing audit serialization excludes OLT credentials, while ACS passwords
  are filled server-side and Telegram error redaction removes bot tokens.

The repository suite contains regression coverage in `PartnerRoleTest`,
`OperatorOltScopeTest`, `SecurityHardeningTest`, `OltAccessGateTest`,
`AuditLogTest`, and the device-write boundary tests below. Their presence is
not a PASS by itself; the branch's full CI run remains the verification gate.

## Threat boundary and residual risks

This control is an application kill switch, not a network firewall or a broker
approval system. Read-only polling and explicit refresh actions can still open
SNMP/CLI sessions to approved inventory targets. A changed environment value
only takes effect after the deployment's normal config-cache and long-running
worker reload. Direct database access, compromised application code, or a
separately privileged host process is outside this gate.

Per-operation impact previews, one-time authorization and external broker
approval remain NMS-11 work. Until those controls and lab evidence exist, an
enabled installation variable is necessary but not sufficient authorization
for a live OLT/ONT change.

## Verification

`tests/Feature/DeviceWriteBoundaryTest.php` verifies:

- a disabled installation rejects and audits a representative OLT command;
- preview/dry-run requests bypass the mutation gate;
- queued execution rechecks the gate before calling device services; and
- every known web/API device-mutation route carries the boundary middleware.

The complete repository suite and CI remain the release gate. No test in this
matrix contacts a real OLT, ONT or GenieACS instance.
