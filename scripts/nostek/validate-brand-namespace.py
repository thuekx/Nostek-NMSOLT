#!/usr/bin/env python3
"""Enforce the public Nostek-NMSOLT identity and internal nos-* namespace."""

from __future__ import annotations

import subprocess
import sys
from pathlib import Path


ROOT = Path(__file__).resolve().parents[2]
LEGACY_PREFIX = "k" + "v"
FORBIDDEN = (
    f"{LEGACY_PREFIX}-snmp-poller",
    f"{LEGACY_PREFIX}-",
    f"{LEGACY_PREFIX}_",
    f"{LEGACY_PREFIX.upper()}_",
    "KUSUMA" + "NET",
)
TEXT_SUFFIXES = {
    ".bat", ".css", ".env", ".go", ".html", ".js", ".json", ".md", ".mjs",
    ".php", ".py", ".sh", ".ts", ".vue", ".yaml", ".yml",
}


def tracked_files() -> list[Path]:
    result = subprocess.run(
        ["git", "ls-files", "-z"],
        cwd=ROOT,
        check=True,
        capture_output=True,
    )
    return [
        ROOT / item.decode("utf-8")
        for item in result.stdout.split(b"\0")
        if item
    ]


def main() -> int:
    errors: list[str] = []

    if not (ROOT / "cmd" / "nos-snmp-poller" / "main.go").is_file():
        errors.append("missing cmd/nos-snmp-poller/main.go")
    legacy_poller = ROOT / "cmd" / f"{LEGACY_PREFIX}-snmp-poller"
    if legacy_poller.exists():
        errors.append("legacy poller source directory still exists")

    for path in tracked_files():
        relative = path.relative_to(ROOT).as_posix()
        if relative in {"LICENSE", "scripts/nostek/validate-brand-namespace.py"} or not path.is_file():
            continue
        if path.suffix.lower() not in TEXT_SUFFIXES and path.name not in {
            ".env.example", ".env.docker.example", ".gitignore", "Dockerfile",
        }:
            continue
        try:
            text = path.read_text(encoding="utf-8")
        except UnicodeDecodeError:
            continue
        for token in FORBIDDEN:
            if token in text:
                errors.append(f"{relative}: legacy token {token!r}")

    for required in ("Nostek-NMSOLT", "Nostek-NMS"):
        audit = (ROOT / "docs" / "nostek" / "NMS-07-PRODUCT-STABILIZATION-AUDIT.md").read_text(encoding="utf-8")
        if required not in audit:
            errors.append(f"audit is missing canonical name {required!r}")

    if errors:
        for error in errors:
            print(f"FAIL: {error}", file=sys.stderr)
        return 1

    print("Nostek brand namespace: PASS")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
