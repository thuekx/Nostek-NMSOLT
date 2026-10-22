#!/usr/bin/env python3
"""Fail-closed static checks for rendered NMSOLT Compose staging topology."""

from __future__ import annotations

import argparse
import json
import sys
from typing import Any


def fail(errors: list[str], message: str) -> None:
    errors.append(message)


def ports(service: dict[str, Any]) -> list[dict[str, Any]]:
    value = service.get("ports", [])
    return value if isinstance(value, list) else []


def volume_sources(service: dict[str, Any]) -> set[str]:
    result: set[str] = set()
    for mount in service.get("volumes", []) or []:
        if isinstance(mount, dict) and mount.get("type") == "volume":
            source = mount.get("source")
            if isinstance(source, str):
                result.add(source)
    return result


DIGEST_SUFFIX = "@sha256:"
APP_FOREIGN_SECRETS = ("MONGO_ACS_ROOT_PASSWORD", "MONGO_ACS_APP_PASSWORD", "GENIEACS_UI_JWT_SECRET")
DATA_VOLUMES = {
    "pgdata": "pg",
    "redisdata": "redis",
    "app_storage": "app-storage",
    "nmsolt_mongo_data": "mongo",
}


def is_false(value: Any) -> bool:
    return str(value).strip().lower() in {"", "0", "false", "no", "off", "none"}


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--profile", choices=("base", "acs", "core", "with-acs"), required=True)
    parser.add_argument("--approved-cwmp-host-ip")
    parser.add_argument(
        "--data-dir",
        help="platform overlay: every data volume must be a bind mount under this directory",
    )
    args = parser.parse_args()
    args.profile = {"core": "base", "with-acs": "acs"}.get(args.profile, args.profile)

    try:
        config = json.load(sys.stdin)
    except (json.JSONDecodeError, UnicodeDecodeError) as exc:
        print(f"invalid Compose JSON: {exc}", file=sys.stderr)
        return 2

    services = config.get("services")
    if not isinstance(services, dict):
        print("Compose JSON has no services map", file=sys.stderr)
        return 2

    errors: list[str] = []
    required = {"app", "db", "redis"}
    missing = sorted(required - services.keys())
    if missing:
        fail(errors, f"missing required services: {', '.join(missing)}")

    for name in sorted(required & services.keys()):
        if not isinstance(services[name], dict):
            fail(errors, f"service {name} is not an object")
            continue
        if "healthcheck" not in services[name]:
            fail(errors, f"service {name} has no healthcheck")

    built_images = {
        str(service.get("image"))
        for service in services.values()
        if isinstance(service, dict) and "build" in service and service.get("image")
    }
    for name, service in sorted(services.items()):
        if not isinstance(service, dict):
            continue
        if "healthcheck" not in service:
            fail(errors, f"service {name} has no healthcheck")
        if not service.get("mem_limit") or not service.get("cpus"):
            fail(errors, f"service {name} has no cpus/mem_limit resource limit")
        image = str(service.get("image", ""))
        if "build" not in service and image not in built_images and DIGEST_SUFFIX not in image:
            fail(errors, f"service {name} image {image or '<none>'} is not pinned by digest")
        command = service.get("command") or []
        if isinstance(command, list) and any(
            "secret" in str(arg).lower() or "password" in str(arg).lower() for arg in command
        ):
            fail(errors, f"service {name} passes a secret-looking command argument")

    allowed_publishers = {"app"}
    if args.profile == "acs":
        allowed_publishers |= {"genieacs-cwmp", "genieacs-ui"}

    for name, service in services.items():
        if not isinstance(service, dict):
            continue
        published = ports(service)
        if published and name not in allowed_publishers:
            fail(errors, f"service {name} unexpectedly publishes host ports")
        for port in published:
            host_ip = str(port.get("host_ip", ""))
            approved_host_ips = {"127.0.0.1", "::1"}
            if name == "genieacs-cwmp" and args.approved_cwmp_host_ip:
                approved_host_ips = {args.approved_cwmp_host_ip}
            if host_ip not in approved_host_ips:
                expected = ", ".join(sorted(approved_host_ips))
                fail(
                    errors,
                    f"service {name} publishes host IP {host_ip or '<wildcard>'}; expected {expected}",
                )

    for name in ("db", "redis"):
        if name in services and ports(services[name]):
            fail(errors, f"service {name} must not publish ports")

    app = services.get("app", {})
    environment = app.get("environment", {}) if isinstance(app, dict) else {}
    if not isinstance(environment, dict):
        fail(errors, "app environment is not a map")
    else:
        if not is_false(environment.get("NMSOLT_DEVICE_WRITES_ENABLED")):
            fail(errors, "NMSOLT_DEVICE_WRITES_ENABLED must remain false in staging")
        if not is_false(environment.get("APP_DEBUG")):
            fail(errors, "APP_DEBUG must remain false in staging")
        if environment.get("QUEUE_CONNECTION") != "redis":
            fail(errors, "QUEUE_CONNECTION must use redis")
        for secret in APP_FOREIGN_SECRETS:
            if str(environment.get(secret, "")):
                fail(errors, f"app must not receive {secret}")

    expected_mounts = {
        "app": "app_storage",
        "db": "pgdata",
        "redis": "redisdata",
    }
    for name, source in expected_mounts.items():
        service = services.get(name)
        if isinstance(service, dict) and source not in volume_sources(service):
            fail(errors, f"service {name} does not mount named volume {source}")

    declared_volumes = config.get("volumes", {})
    if not isinstance(declared_volumes, dict):
        fail(errors, "Compose JSON has no volumes map")
    else:
        for name in expected_mounts.values():
            definition = declared_volumes.get(name)
            if definition is None:
                fail(errors, f"named volume {name} is not declared")
            elif isinstance(definition, dict) and definition.get("external"):
                fail(errors, f"named volume {name} must not be external")

    if args.data_dir:
        root = args.data_dir.rstrip("/")
        volumes_map = declared_volumes if isinstance(declared_volumes, dict) else {}
        wanted = dict(DATA_VOLUMES)
        if args.profile != "acs":
            wanted.pop("nmsolt_mongo_data")
        for name, sub in sorted(wanted.items()):
            definition = volumes_map.get(name) or {}
            opts = definition.get("driver_opts", {}) if isinstance(definition, dict) else {}
            if not isinstance(opts, dict) or opts.get("type") != "none" or opts.get("o") != "bind":
                fail(errors, f"volume {name} is not a bind mount (platform overlay missing)")
            elif opts.get("device") != f"{root}/{sub}":
                fail(errors, f"volume {name} binds {opts.get('device')}; expected {root}/{sub}")

    if args.profile == "acs":
        acs_services = {"mongo_acs", "genieacs-cwmp", "genieacs-nbi", "genieacs-fs", "genieacs-ui"}
        missing_acs = sorted(acs_services - services.keys())
        if missing_acs:
            fail(errors, f"missing ACS services: {', '.join(missing_acs)}")
        mongo = services.get("mongo_acs")
        if isinstance(mongo, dict):
            if ports(mongo):
                fail(errors, "mongo_acs must not publish ports")
            if "healthcheck" not in mongo:
                fail(errors, "mongo_acs has no healthcheck")
            if "nmsolt_mongo_data" not in volume_sources(mongo):
                fail(errors, "mongo_acs does not mount nmsolt_mongo_data")
        for name in ("genieacs-nbi", "genieacs-fs"):
            service = services.get(name)
            if isinstance(service, dict) and ports(service):
                fail(errors, f"{name} must remain private")
        networks = config.get("networks", {})
        private = networks.get("acs_private", {}) if isinstance(networks, dict) else {}
        if not isinstance(private, dict) or private.get("internal") is not True:
            fail(errors, "acs_private network must be internal")
        for name in sorted(acs_services):
            service = services.get(name)
            attached = service.get("networks", {}) if isinstance(service, dict) else {}
            if "acs_private" not in attached:
                fail(errors, f"service {name} is not attached to acs_private")
        for name in ("genieacs-cwmp", "genieacs-ui"):
            service = services.get(name)
            attached = service.get("networks", {}) if isinstance(service, dict) else {}
            if "default" not in attached:
                fail(errors, f"published service {name} is not attached to the staging default network")
        for name in ("mongo_acs", "genieacs-nbi", "genieacs-fs"):
            service = services.get(name)
            attached = service.get("networks", {}) if isinstance(service, dict) else {}
            if "default" in attached:
                fail(errors, f"private service {name} must not join the staging default network")

    if errors:
        for error in errors:
            print(f"FAIL: {error}", file=sys.stderr)
        return 1

    print(f"NMSOLT {args.profile} staging topology: PASS")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
