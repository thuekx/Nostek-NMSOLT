#!/usr/bin/env python3
"""Fail-closed static policy checks for the isolated GenieACS lab candidate."""

from __future__ import annotations

import argparse
import json
import re
import sys
from pathlib import Path
from typing import Any


EXPECTED_IMAGE = "nmsolt-genieacs:1.2.16-local"
EXPECTED_MONGO_URL = "mongodb://mongo_acs:27017/genieacs"
EXPECTED_COMMANDS = {
    "genieacs-cwmp": "genieacs-cwmp",
    "genieacs-nbi": "genieacs-nbi",
    "genieacs-fs": "genieacs-fs",
    "genieacs-ui": "genieacs-ui",
}


def fail(errors: list[str], message: str) -> None:
    errors.append(message)


def published_ports(service: dict[str, Any]) -> list[dict[str, Any]]:
    value = service.get("ports", [])
    return value if isinstance(value, list) else []


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--dockerfile", type=Path, required=True)
    parser.add_argument("--compose-source", type=Path, required=True)
    parser.add_argument("--approved-cwmp-host-ip")
    args = parser.parse_args()

    try:
        config = json.load(sys.stdin)
        dockerfile = args.dockerfile.read_text(encoding="utf-8")
        compose_source = args.compose_source.read_text(encoding="utf-8")
    except (json.JSONDecodeError, OSError, UnicodeDecodeError) as exc:
        print(f"unable to read validation input: {exc}", file=sys.stderr)
        return 2

    services = config.get("services")
    if not isinstance(services, dict):
        print("Compose JSON has no services map", file=sys.stderr)
        return 2

    errors: list[str] = []

    if not re.search(r"(?m)^FROM node:22-bookworm-slim\s*$", dockerfile):
        fail(errors, "GenieACS lab base image must remain node:22-bookworm-slim")
    if "genieacs@1.2.16" not in dockerfile:
        fail(errors, "GenieACS npm package must remain pinned to 1.2.16")
    if not re.search(r"(?m)^USER node\s*$", dockerfile):
        fail(errors, "GenieACS image must run as the non-root node user")
    if re.search(r"(?m)^USER\s+(?:0|root)\s*$", dockerfile):
        fail(errors, "GenieACS image must not switch to root")
    if "${GENIEACS_UI_JWT_SECRET:?set GENIEACS_UI_JWT_SECRET in local .env}" not in compose_source:
        fail(errors, "GenieACS UI secret must be required through local environment interpolation")

    for name, expected_command in EXPECTED_COMMANDS.items():
        service = services.get(name)
        if not isinstance(service, dict):
            fail(errors, f"missing GenieACS service: {name}")
            continue
        if service.get("image") != EXPECTED_IMAGE:
            fail(errors, f"{name} must use {EXPECTED_IMAGE}")
        command = service.get("command", [])
        if not isinstance(command, list) or not command or command[0] != expected_command:
            fail(errors, f"{name} has an unexpected command")
        environment = service.get("environment", {})
        if not isinstance(environment, dict) or environment.get("GENIEACS_MONGODB_CONNECTION_URL") != EXPECTED_MONGO_URL:
            fail(errors, f"{name} must use the private mongo_acs database URL")

    mongo = services.get("mongo_acs")
    if not isinstance(mongo, dict) or mongo.get("image") != "mongo:7.0":
        fail(errors, "mongo_acs must remain pinned to the reviewed 7.0 lab tag")

    for name in ("mongo_acs", "genieacs-nbi", "genieacs-fs"):
        service = services.get(name)
        if isinstance(service, dict) and published_ports(service):
            fail(errors, f"{name} must not publish a host port")

    expected_ports = {
        "genieacs-cwmp": 7547,
        "genieacs-ui": 3000,
    }
    for name, target in expected_ports.items():
        service = services.get(name)
        ports = published_ports(service) if isinstance(service, dict) else []
        if len(ports) != 1:
            fail(errors, f"{name} must publish exactly one loopback-only lab port")
            continue
        port = ports[0]
        allowed_host_ips = {"127.0.0.1", "::1"}
        if name == "genieacs-cwmp" and args.approved_cwmp_host_ip:
            allowed_host_ips = {args.approved_cwmp_host_ip}
        if port.get("host_ip") not in allowed_host_ips or int(port.get("target", -1)) != target:
            expected = ", ".join(sorted(allowed_host_ips))
            fail(errors, f"{name} must publish container port {target} on {expected}")

    if errors:
        for error in errors:
            print(f"FAIL: {error}", file=sys.stderr)
        return 1

    print("GenieACS isolated lab source policy: PASS")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
