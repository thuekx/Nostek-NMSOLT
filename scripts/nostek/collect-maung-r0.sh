#!/usr/bin/env bash
# Read-only host inventory for NMS-03. It does not use sudo, inspect container
# environments, read config contents, or change Docker/system state.

set -u

section() {
    printf '\n## %s\n' "$1"
}

run() {
    printf '\n$'
    printf ' %q' "$@"
    printf '\n'
    "$@" 2>&1 || printf '[unavailable or not permitted]\n'
}

section "Collection metadata"
run date --iso-8601=seconds
run hostname
run id

section "Operating system and kernel"
run uname -a
if [ -r /etc/os-release ]; then
    run grep -E '^(NAME|VERSION|ID|VERSION_ID)=' /etc/os-release
fi

section "CPU and memory"
run lscpu
run free -h
run uptime

section "Storage"
run df -hT
run lsblk -o NAME,TYPE,SIZE,FSTYPE,MOUNTPOINTS

section "Network addresses and routes"
run ip -brief address
run ip route show

section "Listening TCP and UDP ports"
run ss -lntu

section "Docker engine"
if command -v docker >/dev/null 2>&1; then
    run docker version --format '{{json .}}'
    run docker info --format 'CPUs={{.NCPU}} Memory={{.MemTotal}} Driver={{.Driver}} Root={{.DockerRootDir}}'
    run docker ps --format 'table {{.Names}}\t{{.Image}}\t{{.Status}}\t{{.Ports}}'
    run docker compose ls
    run docker network ls
    run docker volume ls
    run docker system df
else
    printf '\nDocker CLI is not installed or not on PATH.\n'
fi

section "Reverse proxy service state"
if command -v systemctl >/dev/null 2>&1; then
    for service in caddy nginx apache2 httpd traefik; do
        printf '%-12s ' "$service"
        systemctl is-active "$service" 2>/dev/null || true
    done
fi

section "Backup path metadata only"
for path in /srv/backups /var/backups /opt/backups; do
    if [ -d "$path" ]; then
        run find "$path" -maxdepth 2 -type f -printf '%TY-%Tm-%TdT%TH:%TM %10s %p\n'
    fi
done

section "Safety attestation"
printf '%s\n' \
    'No sudo was used.' \
    'No container environment, secret, database row, or config file content was read.' \
    'No service, container, network, volume, firewall, route, or file was changed.'
