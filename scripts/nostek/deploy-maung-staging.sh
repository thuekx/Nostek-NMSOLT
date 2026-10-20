#!/usr/bin/env bash
# Authorized NMS-04 isolated staging runner for the Maung VM.
# It does not modify Maung repositories, Caddy, maung_edge, production data,
# firewall rules, OLTs or ONTs.
set -Eeuo pipefail
umask 077

SOURCE_REPO="https://github.com/thuekx/Nostek-NMSOLT.git"
SOURCE_COMMIT="0aea5e7832663dd20b4ab51b42cf28ce6bebea6f"
PROJECT_NAME="nmsolt-maung-staging"
APP_PORT="18080"
STAGING_SUBNET="172.30.30.0/24"
STAGING_NETWORK="nmsolt-maung-staging-private"
STAGING_ROOT="${NMSOLT_STAGING_ROOT:-${PWD}/nostek-nmsolt-staging}"
SOURCE_DIR="${STAGING_ROOT}/source"
OVERRIDE_FILE="${STAGING_ROOT}/compose.nms04.yaml"
ENV_FILE="${SOURCE_DIR}/.env"
EVIDENCE_DIR="${STAGING_ROOT}/evidence"
BACKUP_DIR="${STAGING_ROOT}/backups"
RUN_ID="$(date +%Y%m%d-%H%M%S)"
EVIDENCE_FILE="${EVIDENCE_DIR}/nms04-${RUN_ID}.txt"
EXISTING_CONTAINERS="${STAGING_ROOT}/existing-containers.before"
RESTORE_DB=""
DEPLOYMENT_STARTED=0

say() { printf '\n==> %s\n' "$*"; }
die() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }
compose() {
    docker compose --project-name "${PROJECT_NAME}" \
        --env-file "${ENV_FILE}" \
        --file "${SOURCE_DIR}/docker-compose.yml" \
        --file "${OVERRIDE_FILE}" "$@"
}
record() { printf '%s\n' "$*" >> "${EVIDENCE_FILE}"; }

cleanup_restore_db() {
    if [ -n "${RESTORE_DB}" ] && [ "${DEPLOYMENT_STARTED}" -eq 1 ]; then
        compose exec -T db dropdb --if-exists -U nostek_nmsolt "${RESTORE_DB}" >/dev/null 2>&1 || true
    fi
}

on_error() {
    code=$?
    cleanup_restore_db
    if [ "${DEPLOYMENT_STARTED}" -eq 1 ]; then
        printf '\nDeployment failed; stopping only Compose project %s. Volumes are preserved.\n' "${PROJECT_NAME}" >&2
        compose stop >/dev/null 2>&1 || true
    fi
    printf 'Review local logs under %s. Existing Maung projects were not targeted.\n' "${STAGING_ROOT}" >&2
    exit "${code}"
}
trap on_error ERR
trap cleanup_restore_db EXIT

say "Preflight: required commands"
for command_name in git docker curl openssl python3 ss ip awk df grep sort; do
    command -v "${command_name}" >/dev/null 2>&1 || die "required command is unavailable: ${command_name}"
done
docker compose version >/dev/null
docker info >/dev/null

[ "$(id -u)" -ne 0 ] || die "run this script as the ordinary Docker-enabled account, not root"
[ ! -e "${STAGING_ROOT}" ] || die "target already exists: ${STAGING_ROOT}; do not overwrite it"

available_kib="$(awk '/MemAvailable:/ {print $2}' /proc/meminfo)"
[ "${available_kib}" -ge 8388608 ] || die "less than 8 GiB memory is currently available"

free_kib="$(df -Pk "${PWD}" | awk 'NR==2 {print $4}')"
[ "${free_kib}" -ge 31457280 ] || die "less than 30 GiB is currently free on the target filesystem"

if ss -ltnH | awk '{print $4}' | grep -Eq '(^|:|\])18080$'; then
    die "TCP port 18080 is already listening"
fi

if docker ps -aq --filter "label=com.docker.compose.project=${PROJECT_NAME}" | grep -q .; then
    die "Compose project ${PROJECT_NAME} already has containers"
fi
if docker network ls --format '{{.Name}}' | grep -Fxq "${STAGING_NETWORK}"; then
    die "network ${STAGING_NETWORK} already exists"
fi
if docker volume ls --format '{{.Name}}' | grep -Eq "^${PROJECT_NAME}[_-]"; then
    die "volumes for ${PROJECT_NAME} already exist"
fi

say "Preflight: subnet collision"
network_json="$(mktemp)"
route_json="$(mktemp)"
trap 'rm -f -- "${network_json}" "${route_json}"' RETURN
docker network inspect $(docker network ls -q) > "${network_json}"
ip -j route show > "${route_json}"
python3 - "${STAGING_SUBNET}" "${network_json}" "${route_json}" <<'PY'
import ipaddress
import json
import sys

candidate = ipaddress.ip_network(sys.argv[1], strict=False)
with open(sys.argv[2], encoding="utf-8") as handle:
    networks = json.load(handle)
for network in networks:
    for config in network.get("IPAM", {}).get("Config", []) or []:
        subnet = config.get("Subnet")
        if subnet and candidate.overlaps(ipaddress.ip_network(subnet, strict=False)):
            raise SystemExit(f"candidate {candidate} overlaps Docker network {network.get('Name')}: {subnet}")

with open(sys.argv[3], encoding="utf-8") as handle:
    routes = json.load(handle)
for route in routes:
    destination = route.get("dst")
    if destination and destination != "default":
        try:
            existing = ipaddress.ip_network(destination, strict=False)
        except ValueError:
            continue
        if candidate.overlaps(existing):
            raise SystemExit(f"candidate {candidate} overlaps host route {destination}")

print(f"Subnet {candidate} has no observed collision: PASS")
PY
rm -f -- "${network_json}" "${route_json}"
trap - RETURN

say "Create isolated staging workspace"
mkdir -p "${SOURCE_DIR}" "${EVIDENCE_DIR}" "${BACKUP_DIR}"
chmod 700 "${STAGING_ROOT}" "${SOURCE_DIR}" "${EVIDENCE_DIR}" "${BACKUP_DIR}"
docker ps --format '{{.Names}}' | sort > "${EXISTING_CONTAINERS}"

git -C "${SOURCE_DIR}" init --quiet
git -C "${SOURCE_DIR}" remote add origin "${SOURCE_REPO}"
git -C "${SOURCE_DIR}" fetch --quiet --depth=1 origin "${SOURCE_COMMIT}"
git -C "${SOURCE_DIR}" checkout --quiet --detach FETCH_HEAD
actual_commit="$(git -C "${SOURCE_DIR}" rev-parse HEAD)"
[ "${actual_commit}" = "${SOURCE_COMMIT}" ] || die "source commit verification failed"

read -r -p "Email admin staging: " ADMIN_EMAIL
case "${ADMIN_EMAIL}" in
    *"@"*.*) ;;
    *) die "admin email is not valid" ;;
esac
case "${ADMIN_EMAIL}" in
    *[$'\r\n ']* ) die "admin email contains whitespace" ;;
esac

DB_PASSWORD="$(openssl rand -hex 24)"
ADMIN_PASSWORD="$(openssl rand -hex 18)"

install -m 600 /dev/null "${ENV_FILE}"
{
    printf 'COMPOSE_PROJECT_NAME=%s\n' "${PROJECT_NAME}"
    printf 'IMAGE_TAG=%s\n' "${SOURCE_COMMIT}"
    printf 'APP_PORT=%s\n' "${APP_PORT}"
    printf 'APP_NAME=Nostek-NMSOLT-Staging\n'
    printf 'APP_URL=http://127.0.0.1:%s\n' "${APP_PORT}"
    printf 'APP_KEY=\n'
    printf 'NMSOLT_DEVICE_WRITES_ENABLED=false\n'
    printf 'DB_DATABASE=nostek_nmsolt\n'
    printf 'DB_USERNAME=nostek_nmsolt\n'
    printf 'DB_PASSWORD=%s\n' "${DB_PASSWORD}"
    printf 'ACS_URL=\nACS_USERNAME=\nACS_PASSWORD=\n'
    printf 'ADMIN_NAME=Staging-Administrator\n'
    printf 'ADMIN_EMAIL=%s\n' "${ADMIN_EMAIL}"
    printf 'ADMIN_PASSWORD=%s\n' "${ADMIN_PASSWORD}"
    printf 'GENIEACS_UI_JWT_SECRET=\n'
    printf 'ACS_CWMP_PORT=17547\nACS_UI_PORT=13000\n'
} > "${ENV_FILE}"

cat > "${OVERRIDE_FILE}" <<YAML
services:
  app:
    cpus: 1.25
    mem_limit: 1536m
  db:
    cpus: 0.50
    mem_limit: 1024m
  redis:
    cpus: 0.25
    mem_limit: 512m
networks:
  default:
    name: ${STAGING_NETWORK}
    ipam:
      config:
        - subnet: ${STAGING_SUBNET}
YAML
chmod 600 "${OVERRIDE_FILE}"

{
    printf 'NMS-04 isolated staging evidence\n'
    printf 'run_id=%s\n' "${RUN_ID}"
    printf 'started_at=%s\n' "$(date --iso-8601=seconds)"
    printf 'source_commit=%s\n' "${SOURCE_COMMIT}"
    printf 'project=%s\n' "${PROJECT_NAME}"
    printf 'published_endpoint=127.0.0.1:%s\n' "${APP_PORT}"
    printf 'network=%s\n' "${STAGING_NETWORK}"
    printf 'subnet=%s\n' "${STAGING_SUBNET}"
    printf 'budget_cpu=2.00\n'
    printf 'budget_memory=3072MiB\n'
    printf 'device_writes=false\n'
    printf 'caddy_changed=false\n'
    printf 'maung_edge_joined=false\n'
    printf 'preflight_available_memory_kib=%s\n' "${available_kib}"
    printf 'preflight_free_disk_kib=%s\n' "${free_kib}"
} > "${EVIDENCE_FILE}"

say "Validate rendered Compose policy"
compose config --quiet
compose config --format json |
    python3 "${SOURCE_DIR}/scripts/nostek/validate-staging-compose.py" --profile base |
    tee -a "${EVIDENCE_FILE}"

say "Build exact application commit"
COMPOSE_PARALLEL_LIMIT=1 compose build app

post_build_free_kib="$(df -Pk "${STAGING_ROOT}" | awk 'NR==2 {print $4}')"
[ "${post_build_free_kib}" -ge 20971520 ] || die "build left less than the 20 GiB safety floor"
record "post_build_free_disk_kib=${post_build_free_kib}"

say "Start isolated base stack"
DEPLOYMENT_STARTED=1
compose up --detach --no-build

say "Wait for application health"
healthy=0
for _ in $(seq 1 60); do
    if curl --fail --silent --show-error "http://127.0.0.1:${APP_PORT}/up" >/dev/null 2>&1; then
        healthy=1
        break
    fi
    sleep 5
done
if [ "${healthy}" -ne 1 ]; then
    compose ps >&2
    compose logs --tail=100 app >&2
    die "application did not become healthy"
fi
record "application_health=PASS"

login_status="$(curl --silent --output /dev/null --write-out '%{http_code}' "http://127.0.0.1:${APP_PORT}/login")"
case "${login_status}" in
    200|302) record "login_route=PASS(http_${login_status})" ;;
    *) die "login route returned HTTP ${login_status}" ;;
esac

say "Verify migrations and supervised processes"
compose exec -T app php artisan migrate:status --no-ansi >> "${EVIDENCE_FILE}"
compose exec -T app supervisorctl status >> "${EVIDENCE_FILE}"

say "Create and restore-test a staging-only PostgreSQL backup"
BACKUP_FILE="${BACKUP_DIR}/nms04-${RUN_ID}.dump"
compose exec -T db pg_dump -U nostek_nmsolt -d nostek_nmsolt -Fc > "${BACKUP_FILE}"
[ -s "${BACKUP_FILE}" ] || die "database backup is empty"
chmod 600 "${BACKUP_FILE}"
sha256sum "${BACKUP_FILE}" > "${BACKUP_FILE}.sha256"
RESTORE_DB="nmsolt_restore_check_${RUN_ID//-/}"
compose exec -T db createdb -U nostek_nmsolt "${RESTORE_DB}"
compose exec -T db pg_restore -U nostek_nmsolt -d "${RESTORE_DB}" --no-owner --no-privileges < "${BACKUP_FILE}"
source_tables="$(compose exec -T db psql -U nostek_nmsolt -d nostek_nmsolt -Atc "select count(*) from pg_tables where schemaname='public'")"
restore_tables="$(compose exec -T db psql -U nostek_nmsolt -d "${RESTORE_DB}" -Atc "select count(*) from pg_tables where schemaname='public'")"
[ "${source_tables}" -gt 0 ] || die "source database has no public tables"
[ "${source_tables}" = "${restore_tables}" ] || die "restored table count differs from source"
record "backup_restore=PASS"
record "public_table_count=${source_tables}"
record "backup_sha256=$(awk '{print $1}' "${BACKUP_FILE}.sha256")"
compose exec -T db dropdb -U nostek_nmsolt "${RESTORE_DB}"
RESTORE_DB=""

say "Capture bounded runtime usage"
compose ps >> "${EVIDENCE_FILE}"
docker stats --no-stream --format '{{.Name}} cpu={{.CPUPerc}} memory={{.MemUsage}}'     $(compose ps -q) >> "${EVIDENCE_FILE}"

say "Exercise rollback stop and prove existing containers remain running"
compose stop
while IFS= read -r existing_name; do
    [ -n "${existing_name}" ] || continue
    running="$(docker inspect --format '{{.State.Running}}' "${existing_name}" 2>/dev/null || printf false)"
    [ "${running}" = "true" ] || die "pre-existing container is not running after staging stop: ${existing_name}"
done < "${EXISTING_CONTAINERS}"
record "rollback_stop=PASS"
record "preexisting_containers_unchanged=PASS"

say "Restart staging after rollback rehearsal"
compose up --detach --no-build
healthy=0
for _ in $(seq 1 60); do
    if curl --fail --silent "http://127.0.0.1:${APP_PORT}/up" >/dev/null 2>&1; then
        healthy=1
        break
    fi
    sleep 5
done
[ "${healthy}" -eq 1 ] || die "application did not recover after rollback rehearsal"
record "post_rollback_restart=PASS"
record "completed_at=$(date --iso-8601=seconds)"

cat > "${STAGING_ROOT}/stop-staging.sh" <<STOP
#!/usr/bin/env bash
set -euo pipefail
docker compose --project-name "${PROJECT_NAME}" \
  --env-file "${ENV_FILE}" \
  --file "${SOURCE_DIR}/docker-compose.yml" \
  --file "${OVERRIDE_FILE}" stop
STOP
chmod 700 "${STAGING_ROOT}/stop-staging.sh"

cat > "${STAGING_ROOT}/start-staging.sh" <<START
#!/usr/bin/env bash
set -euo pipefail
docker compose --project-name "${PROJECT_NAME}" \
  --env-file "${ENV_FILE}" \
  --file "${SOURCE_DIR}/docker-compose.yml" \
  --file "${OVERRIDE_FILE}" up --detach --no-build
START
chmod 700 "${STAGING_ROOT}/start-staging.sh"

trap - ERR
trap - EXIT

printf '\nNMS-04 staging rehearsal completed successfully.\n'
printf 'Application: http://127.0.0.1:%s\n' "${APP_PORT}"
printf 'Admin email: %s\n' "${ADMIN_EMAIL}"
printf 'Admin password: %s\n' "${ADMIN_PASSWORD}"
printf 'Evidence file: %s\n' "${EVIDENCE_FILE}"
printf 'Backup file: %s\n' "${BACKUP_FILE}"
printf 'Stop command: %s\n' "${STAGING_ROOT}/stop-staging.sh"
printf '\nDo not publish .env, backup, raw logs or the admin password.\n'
