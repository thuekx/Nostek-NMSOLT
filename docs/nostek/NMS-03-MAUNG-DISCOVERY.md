# NMS-03 Maung read-only discovery

NMS-03 remains blocked until current facts are collected from the intended
Maung host. Do not deploy, restart services, pull images, change firewall rules,
or inspect secrets as part of this stage.

## Collector

Run `scripts/nostek/collect-maung-r0.sh` as an ordinary account that can read
the required host and Docker metadata. Redirect its standard output to a local
text file if a durable attachment is needed. Review the output before sharing;
the script intentionally excludes container environments and config contents.

The collector records:

- operating system, kernel, CPU, memory and load;
- filesystems, mount points and block-device capacity;
- host addresses, routes and listening ports;
- Docker engine capacity, active containers, published ports, Compose projects,
  networks, volumes and disk usage;
- reverse-proxy service state; and
- backup file metadata in common backup roots, without reading file contents.

## Gate decisions after collection

The sanitized result must support all of these decisions before NMS-03 can be
VERIFIED:

1. Reserve CPU, memory and storage for existing Maung workloads first.
2. Identify port, network, volume and Compose project-name collisions.
3. Confirm the current reverse-proxy owner and a reversible `/nmsolt` routing
   plan without editing it.
4. Identify the backup system, recent backup evidence and a restore-test target.
5. Define a bounded resource budget for an isolated NMSOLT staging stack.
6. Record any missing permission or unverifiable item as a blocker rather than
   inferring it from historical host details.

No OLT, ONT, GenieACS CWMP endpoint or production application is contacted by
this collector.
