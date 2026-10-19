# NMS-05 — GenieACS isolated lab commissioning matrix

**Status:** BLOCKED for runtime verification. Source-level readiness may be reviewed
and merged, but NMS-05 is not VERIFIED until the approved lab evidence below
exists.

## Safety boundary

- Use one specifically identified, owner-approved **lab ONT**. Never repoint a
  production/customer ONT.
- Keep device writes disabled. Inform, identity matching and compatible read
  parameters are the only allowed device interactions in this gate.
- CWMP ingress remains loopback-only until a separate network/TLS/authentication
  plan is reviewed and explicitly approved.
- GenieACS NBI, file service and MongoDB must remain private. UI access is through
  a loopback tunnel only.
- Never record CWMP credentials, UI JWT secrets, customer PII, full serials or
  routable management addresses in GitHub evidence.
- Wi-Fi, reboot, factory reset, firmware, provisioning and parameter writes are
  out of scope and require a separate NMS-11 approval.

## Approval record

Fill this section only after approval is issued. Redact sensitive identifiers.

| Field | Required evidence |
| --- | --- |
| Approval/change ID | Owner-issued identifier and approver |
| Time window | Start/end with timezone |
| Lab device | Vendor/model/firmware; masked serial and MAC |
| Network path | Approved source/destination, TLS/auth mode, rollback owner |
| Scope | Inform plus read-only parameter retrieval |
| Prohibited actions acknowledged | Explicit confirmation that no write/repoint/reset is allowed |

## Runtime evidence matrix

| Check | Pass condition | Evidence to retain |
| --- | --- | --- |
| MongoDB | Healthy, persistent lab volume, no published host port | Sanitized Compose/health output |
| CWMP | Healthy; approved lab ONT informs through approved path | Timestamped service log with identifiers masked |
| NBI | Reachable only from the private application network | Internal health query and negative external reachability check |
| File service | Private, healthy if required by the test | Internal health output |
| UI | Loopback/tunnel only; authentication succeeds | Sanitized access result |
| Identity match | Manufacturer/OUI/product class plus masked serial/MAC map to exactly one expected ONT | Matching record with ambiguity check |
| Read parameters | Approved parameter paths return compatible typed values | Redacted parameter-path/result matrix |
| Write boundary | Device writes remain disabled; prohibited actions return denial | Negative-test output and audit event |
| Recovery | Removing the temporary CWMP path restores the prior lab state | Rollback log and post-check |

## Read-only parameter compatibility

Record only the parameter paths actually observed. A missing vendor path is a
capability result, not permission to try a write.

| Capability | Candidate TR-069 path(s) | Observed type/value class | Result |
| --- | --- | --- | --- |
| Device identity | DeviceInfo manufacturer/model/software version | Text/version | PENDING |
| WAN status | WAN connection status/address class | Enum/address class, redact address | PENDING |
| Optical diagnostics | Vendor-supported optical RX/TX paths | Numeric plus unit | PENDING |
| Wi-Fi state | SSID/radio/security mode (read only) | Text/enum; redact SSID | PENDING |
| LAN clients | Host count/type, no customer identifiers | Count/type | PENDING |

## Stop and rollback conditions

Stop immediately on an unexpected production identifier, more than one matching
device, authentication failure loops, a requested write, a non-loopback/private
service exposure, resource pressure beyond the NMS-03 budget, or evidence that
cannot be sanitized. Remove the temporary approved CWMP path and preserve only
redacted logs.

## Verification rule

NMS-05 can be marked VERIFIED only when every applicable runtime row has a
timestamped PASS artifact tied to the exact deployed commit/image and approval
record. CI success for the source policy proves readiness only; it does not prove
an ONT inform, device compatibility or a safe runtime deployment.
