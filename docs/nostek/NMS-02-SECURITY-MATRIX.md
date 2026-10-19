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

## Surface classification

| Surface | R0 behavior | Boundary |
| --- | --- | --- |
| Dashboard, inventory, alarms, reports, ODP/map reads | Allowed | Authentication plus model scopes |
| SNMP/CLI refresh and config backup capture | Allowed read-only | Authentication, OLT scope, capability/throttle |
| Provisioning previews and generated scripts with `execute=false` | Allowed; no device command | Validation plus conditional `device.write:execute` |
| ONU register/execute, reboot, delete, rename/state/config | Denied by default | `device.write` plus existing role/scope/capability checks |
| VLAN, uplink description/admin state, device config save | Denied by default | `device.write` plus existing role/scope/capability/throttle checks |
| GenieACS Wi-Fi mutation | Denied by default | `device.write` plus role and OLT/ACS scope |
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

## Verification

`tests/Feature/DeviceWriteBoundaryTest.php` verifies:

- a disabled installation rejects and audits a representative OLT command;
- preview/dry-run requests bypass the mutation gate;
- queued execution rechecks the gate before calling device services; and
- every known web/API device-mutation route carries the boundary middleware.

The complete repository suite and CI remain the release gate. No test in this
matrix contacts a real OLT, ONT or GenieACS instance.
