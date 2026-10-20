#!/usr/bin/env bash
# Authorized NMS-05 isolated GenieACS lab overlay deployment.
# Scope: one owner-approved non-production ZTE F660 on a ZTE C320 path.
# No ONT configuration, device write, Caddy, maung_edge or production changes.
set -Eeuo pipefail
umask 077

PROJECT="nmsolt-maung-staging"
STAGING_ROOT="${NMSOLT_STAGING_ROOT:-${HOME}/nostek-nmsolt-staging}"
SOURCE_DIR="${STAGING_ROOT}/source"
ENV_FILE="${SOURCE_DIR}/.env"
BASE_OVERRIDE="${STAGING_ROOT}/compose.nms04.yaml"
ACS_OVERRIDE="${STAGING_ROOT}/compose.nms05.yaml"
EVIDENCE_DIR="${STAGING_ROOT}/evidence"
RUN_ID="$(date +%Y%m%d-%H%M%S)"
EVIDENCE_FILE="${EVIDENCE_DIR}/nms05-runtime-${RUN_ID}.txt"
CWMP_BIND_IP="${NMSOLT_CWMP_BIND_IP:?set NMSOLT_CWMP_BIND_IP to the approved private Maung address}"
LAB_ONT_IP="${NMSOLT_LAB_ONT_IP:?set NMSOLT_LAB_ONT_IP to the approved lab ONT management address}"
DEPLOYMENT_STARTED=0

say() { printf '\n==> %s\n' "$*"; }
die() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }
record() { printf '%s\n' "$*" >> "${EVIDENCE_FILE}"; }

compose() {
    docker compose --project-name "${PROJECT}" \
        --env-file "${ENV_FILE}" \
        --file "${SOURCE_DIR}/docker-compose.yml" \
        --file "${BASE_OVERRIDE}" \
        --file "${SOURCE_DIR}/compose.acs.yaml" \
        --file "${ACS_OVERRIDE}" "$@"
}

on_error() {
    code=$?
    if [ "${DEPLOYMENT_STARTED}" -eq 1 ]; then
        printf 'NMS-05 overlay failed; stopping only ACS services.\n' >&2
        compose stop genieacs-ui genieacs-fs genieacs-nbi genieacs-cwmp mongo_acs >/dev/null 2>&1 || true
    fi
    printf 'Base NMS-04 services and all pre-existing Maung projects were not targeted.\n' >&2
    exit "${code}"
}
trap on_error ERR

for command_name in docker curl openssl python3 ss ip awk df grep; do
    command -v "${command_name}" >/dev/null 2>&1 ||
        die "required command is unavailable: ${command_name}"
done
[ "$(id -u)" -ne 0 ] || die "run as the ordinary Docker-enabled account, not root"
[ -f "${ENV_FILE}" ] || die "NMS-04 environment is missing"
[ -f "${BASE_OVERRIDE}" ] || die "NMS-04 override is missing"
[ -f "${SOURCE_DIR}/compose.acs.yaml" ] || die "ACS overlay source is missing"

case "${CWMP_BIND_IP}" in
    10.*|192.168.*|172.1[6-9].*|172.2[0-9].*|172.3[01].*) ;;
    *) die "CWMP bind address must be an explicitly approved RFC1918 address" ;;
esac
case "${LAB_ONT_IP}" in
    10.*|192.168.*|172.1[6-9].*|172.2[0-9].*|172.3[01].*) ;;
    *) die "lab ONT address must be RFC1918" ;;
esac

ip -4 -o address show | awk '{print $4}' | cut -d/ -f1 |
    grep -Fxq "${CWMP_BIND_IP}" ||
    die "approved CWMP bind address is not assigned to this host"

ip route get "${LAB_ONT_IP}" >/dev/null
ping -c 2 -W 2 "${LAB_ONT_IP}" >/dev/null ||
    die "approved lab ONT did not answer the bounded reachability preflight"

available_kib="$(awk '/MemAvailable:/ {print $2}' /proc/meminfo)"
[ "${available_kib}" -ge 8388608 ] ||
    die "less than 8 GiB memory is currently available"
free_kib="$(df -Pk "${STAGING_ROOT}" | awk 'NR==2 {print $4}')"
[ "${free_kib}" -ge 26214400 ] ||
    die "less than 25 GiB is free before the ACS build"

if ss -ltnH | awk '{print $4}' | grep -Eq "(^|:|\\])17547$"; then
    die "TCP port 17547 is already listening"
fi
if ss -ltnH | awk '{print $4}' | grep -Eq "(^|:|\\])13000$"; then
    die "TCP port 13000 is already listening"
fi

docker ps --filter "label=com.docker.compose.project=${PROJECT}" \
    --filter "label=com.docker.compose.service=app" --format '{{.Names}}' |
    grep -q . || die "verified NMS-04 base app is not running"
curl --fail --silent --max-time 5 http://127.0.0.1:18080/up >/dev/null ||
    die "verified NMS-04 base health is unavailable"

set_env_value() {
    key="$1"
    value="$2"
    if grep -q "^${key}=" "${ENV_FILE}"; then
        sed -i "s|^${key}=.*|${key}=${value}|" "${ENV_FILE}"
    else
        printf '%s=%s\n' "${key}" "${value}" >> "${ENV_FILE}"
    fi
}
set_env_value ACS_CWMP_BIND_IP "${CWMP_BIND_IP}"
set_env_value ACS_CWMP_PORT "17547"
set_env_value ACS_UI_PORT "13000"
if ! grep -Eq '^GENIEACS_UI_JWT_SECRET=.+$' "${ENV_FILE}"; then
    set_env_value GENIEACS_UI_JWT_SECRET "$(openssl rand -hex 32)"
fi
chmod 600 "${ENV_FILE}"

cat > "${ACS_OVERRIDE}" <<'YAML'
services:
  mongo_acs:
    cpus: 0.75
    mem_limit: 1024m
  genieacs-cwmp:
    cpus: 0.50
    mem_limit: 512m
  genieacs-nbi:
    cpus: 0.25
    mem_limit: 384m
  genieacs-fs:
    cpus: 0.25
    mem_limit: 384m
  genieacs-ui:
    cpus: 0.25
    mem_limit: 768m
YAML
chmod 600 "${ACS_OVERRIDE}"

mkdir -p "${EVIDENCE_DIR}"
{
    printf 'NMS-05 isolated GenieACS lab deployment evidence\n'
    printf 'run_id=%s\n' "${RUN_ID}"
    printf 'started_at=%s\n' "$(date --iso-8601=seconds)"
    printf 'source_commit=%s\n' "$(git -C "${SOURCE_DIR}" rev-parse HEAD)"
    printf 'project=%s\n' "${PROJECT}"
    printf 'approved_device=ZTE-F660\n'
    printf 'approved_olt=ZTE-C320\n'
    printf 'network_route=PASS\n'
    printf 'cwmp_port=17547\n'
    printf 'ui_endpoint=127.0.0.1:13000\n'
    printf 'budget_cpu=2.00\n'
    printf 'budget_memory=3072MiB\n'
    printf 'device_writes=false\n'
    printf 'caddy_changed=false\n'
    printf 'maung_edge_joined=false\n'
    printf 'preflight_available_memory_kib=%s\n' "${available_kib}"
    printf 'preflight_free_disk_kib=%s\n' "${free_kib}"
} > "${EVIDENCE_FILE}"

say "Validate exact approved topology"
compose config --quiet
compose config --format json |
    python3 "${SOURCE_DIR}/scripts/nostek/validate-staging-compose.py" \
        --profile acs --approved-cwmp-host-ip "${CWMP_BIND_IP}" |
    tee -a "${EVIDENCE_FILE}"
compose config --format json |
    python3 "${SOURCE_DIR}/scripts/nostek/validate-genieacs-lab.py" \
        --dockerfile "${SOURCE_DIR}/docker/genieacs/Dockerfile" \
        --compose-source "${SOURCE_DIR}/compose.acs.yaml" \
        --approved-cwmp-host-ip "${CWMP_BIND_IP}" >/dev/null

say "Build reviewed GenieACS image"
COMPOSE_PARALLEL_LIMIT=1 compose build genieacs-cwmp
post_build_free_kib="$(df -Pk "${STAGING_ROOT}" | awk 'NR==2 {print $4}')"
[ "${post_build_free_kib}" -ge 20971520 ] ||
    die "build left less than the 20 GiB safety floor"
record "post_build_free_disk_kib=${post_build_free_kib}"

say "Start isolated ACS services"
DEPLOYMENT_STARTED=1
compose up --detach --no-build mongo_acs genieacs-cwmp genieacs-nbi genieacs-fs genieacs-ui

say "Wait for MongoDB and local endpoints"
for _ in $(seq 1 60); do
    mongo_status="$(compose ps --format json mongo_acs 2>/dev/null || true)"
    if printf '%s' "${mongo_status}" | grep -q '"Health":"healthy"'; then
        break
    fi
    sleep 5
done
compose ps mongo_acs | grep -q '(healthy)' || die "MongoDB did not become healthy"

cwmp_code="000"
ui_code="000"
for _ in $(seq 1 60); do
    cwmp_code="$(curl --silent --max-time 5 --output /dev/null --write-out '%{http_code}' "http://${CWMP_BIND_IP}:17547/" || true)"
    ui_code="$(curl --silent --max-time 5 --output /dev/null --write-out '%{http_code}' http://127.0.0.1:13000/ || true)"
    if [ "${cwmp_code}" != "000" ] && [ "${ui_code}" != "000" ]; then
        break
    fi
    sleep 5
done
[ "${cwmp_code}" != "000" ] || die "CWMP endpoint is unreachable"
[ "${ui_code}" != "000" ] || die "UI endpoint is unreachable"

compose exec -T genieacs-nbi node -e \
    "fetch('http://127.0.0.1:7557/devices?limit=1').then(r=>{if(!r.ok)process.exit(1);console.log('NBI_INTERNAL=PASS')})" |
    tee -a "${EVIDENCE_FILE}"

record "mongo_health=PASS"
record "cwmp_listener=PASS(http_${cwmp_code})"
record "ui_loopback=PASS(http_${ui_code})"
record "acs_overlay_deployment=PASS"
compose ps >> "${EVIDENCE_FILE}"
acs_container_ids="$(compose ps -q mongo_acs genieacs-cwmp genieacs-nbi genieacs-fs genieacs-ui)"
docker stats --no-stream --format '{{.Name}} cpu={{.CPUPerc}} memory={{.MemUsage}}' \
    ${acs_container_ids} >> "${EVIDENCE_FILE}"
record "completed_at=$(date --iso-8601=seconds)"

trap - ERR
printf '\nNMS05_ACS_OVERLAY=PASS\n'
printf 'CWMP URL for the approved lab ONT: http://%s:17547/\n' "${CWMP_BIND_IP}"
printf 'UI (SSH tunnel only): http://127.0.0.1:13000/\n'
printf 'Evidence file: %s\n' "${EVIDENCE_FILE}"
printf 'Do not publish .env, secrets, full identifiers or raw ACS logs.\n'
