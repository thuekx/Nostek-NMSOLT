#!/usr/bin/env bash
#
# nmsolt-ops.sh — one operations entry point for the Nostek-NMSOLT stack
# (NMS-07 unified package). Profiles: core (app + PostgreSQL + Redis) and
# with-acs (core + GenieACS + MongoDB, recommended). --platform adds the
# bind-mount overlay (data under NMSOLT_DATA_DIR).
#
# Output contract: one "RESULT <name>=PASS|FAIL|WARN|UNKNOWN <detail>" line per
# check, "PROGRESS k/n <what>" on long steps, and a final
# "RESULT nmsolt-ops <command>=PASS|FAIL". Exit 0 only on PASS. Secret values
# are never printed and never passed as command arguments.
#
# This tool runs on the host that owns the stack. Host packages (for example
# Nostek-AI NET-1-2) wrap it with their own PREFLIGHT -> APPLY -> VERIFY gates.
# Usage: nmsolt-ops.sh --help
set -Eeuo pipefail
umask 077

SELF_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SOURCE_DIR="$(cd "${SELF_DIR}/../.." && pwd)"
ENV_FILE=""
PROJECT=""
PROFILE="with-acs"
PLATFORM=0
EXPECT_HOST=""
OVERRIDES=()
DOCKER="${NMSOLT_DOCKER:-docker}"
MIN_FREE_GB="${NMSOLT_MIN_FREE_GB:-20}"

FAILS=0
WARNS=0

usage() {
    cat <<'EOF'
Usage: nmsolt-ops.sh [global options] <command> [command options]

Global options:
  --source DIR        repository checkout holding the Compose files (default: this repo)
  --env-file FILE     environment file (default: <source>/.env)
  --project NAME      Compose project name (default: COMPOSE_PROJECT_NAME from the env file)
  --profile P         core | with-acs (default: with-acs)
  --platform          add compose.platform.yaml (bind mounts under NMSOLT_DATA_DIR)
  --override FILE     extra Compose file, may repeat (host-specific limits, binds)
  --expect-host NAME  refuse to run unless the hostname is NAME

Commands:
  config-check                      static checks: env keys, write gate, rendered topology
  preflight                         config-check + Docker, data dir, free space, ports (read-only)
  init-data                         create the data directories (--platform only)
  start [--wait SEC]                start the stack (no build) and wait until every service is healthy
  stop                              stop the stack (containers kept, data untouched)
  status                            per-service state and health, app /up, write gate, workers
  build --tag TAG                   build the app image <NMSOLT_IMAGE_REPO>:TAG
  backup --out DIR [--include-raw-rx]
                                    PostgreSQL dump, MongoDB dump (with-acs), app storage
  verify-backup --from DIR          check a backup's manifest checksums
  restore --from DIR --confirm RESTORE [--no-safety-backup]
                                    restore a backup into this stack (takes a safety backup first)
  upgrade --tag TAG --backup-dir DIR [--wait SEC] [--no-data-rollback]
                                    backup, switch IMAGE_TAG, start, wait healthy; auto-rollback on FAIL
  rollback --confirm ROLLBACK [--with-data]
                                    return to the image tag (and optionally data) before the last upgrade
EOF
}

# --------------------------------------------------------------------------- output
result() { # result NAME STATUS DETAIL...
    local name="$1" status="$2"
    shift 2
    printf 'RESULT %s=%s %s\n' "${name}" "${status}" "$*"
    case "${status}" in
        FAIL|UNKNOWN) FAILS=$((FAILS + 1)) ;;
        WARN) WARNS=$((WARNS + 1)) ;;
    esac
}
progress() { printf 'PROGRESS %s\n' "$*"; }
note() { printf '    %s\n' "$*"; }
die() {
    printf 'REFUSE %s\n' "$*"
    printf 'REFUSE %s\n' "$*" >&2
    exit 2
}
finish() { # finish COMMAND
    if [ "${FAILS}" -eq 0 ]; then
        printf 'RESULT nmsolt-ops %s=PASS warnings=%s\n' "$1" "${WARNS}"
        exit 0
    fi
    printf 'RESULT nmsolt-ops %s=FAIL failures=%s warnings=%s\n' "$1" "${FAILS}" "${WARNS}"
    exit 1
}

# --------------------------------------------------------------------------- env file
# env_get KEY -> value of the last KEY= line of the env file (quotes stripped).
env_get() {
    local key="$1" line value=""
    while IFS= read -r line || [ -n "${line}" ]; do
        line="${line%$'\r'}"
        case "${line}" in
            "${key}="*) value="${line#*=}" ;;
        esac
    done < "${ENV_FILE}"
    value="${value%\"}"; value="${value#\"}"
    value="${value%\'}"; value="${value#\'}"
    printf '%s' "${value}"
}

# env_set KEY VALUE: rewrite the env file atomically; the old copy is kept in the
# state directory (never deleted).
env_set() {
    local key="$1" value="$2" tmp keep
    tmp="$(mktemp "${ENV_FILE}.nmsolt-ops.XXXXXX")"
    keep="${STATE_DIR}/env-history/$(basename "${ENV_FILE}").$(date -u +%Y%m%dT%H%M%SZ).$$"
    mkdir -p "${STATE_DIR}/env-history"
    cp -p "${ENV_FILE}" "${keep}"
    awk -v k="${key}" -v v="${value}" '
        BEGIN { done = 0 }
        index($0, k "=") == 1 { if (!done) { print k "=" v; done = 1 }; next }
        { print }
        END { if (!done) print k "=" v }
    ' "${ENV_FILE}" > "${tmp}"
    chmod --reference="${ENV_FILE}" "${tmp}" 2>/dev/null || chmod 600 "${tmp}"
    mv -f "${tmp}" "${ENV_FILE}"
}

# --------------------------------------------------------------------------- compose
compose_files() {
    COMPOSE_ARGS=(--project-directory "${SOURCE_DIR}" --env-file "${ENV_FILE}" -p "${PROJECT}"
        -f "${SOURCE_DIR}/docker-compose.yml")
    if [ "${PROFILE}" = "with-acs" ]; then
        COMPOSE_ARGS+=(-f "${SOURCE_DIR}/compose.acs.yaml")
    fi
    if [ "${PLATFORM}" -eq 1 ]; then
        COMPOSE_ARGS+=(-f "${SOURCE_DIR}/compose.platform.yaml")
    fi
    local extra
    for extra in "${OVERRIDES[@]+"${OVERRIDES[@]}"}"; do
        COMPOSE_ARGS+=(-f "${extra}")
    done
}
compose() { NMSOLT_ENV_FILE="${ENV_FILE}" "${DOCKER}" compose "${COMPOSE_ARGS[@]}" "$@"; }

expected_services() {
    if [ "${PROFILE}" = "with-acs" ]; then
        printf '%s\n' app db redis mongo_acs genieacs-cwmp genieacs-nbi genieacs-fs genieacs-ui
    else
        printf '%s\n' app db redis
    fi
}

# --------------------------------------------------------------------------- checks
check_host() {
    [ -n "${EXPECT_HOST}" ] || return 0
    local here
    here="$(hostname -s 2>/dev/null || hostname)"
    if [ "${here}" != "${EXPECT_HOST}" ]; then
        local line="❌ WRONG HOST: this is ${here} — run nmsolt-ops on ${EXPECT_HOST}; nothing done"
        printf '%s\n' "${line}"
        printf '%s\n' "${line}" >&2
        { printf '%s\n' "${line}" > /dev/tty; } 2>/dev/null || true
        exit 3
    fi
}

check_env() {
    local mode key value
    if [ ! -f "${ENV_FILE}" ]; then
        result env_file FAIL "missing ${ENV_FILE}"
        return
    fi
    mode="$(stat -c '%a' "${ENV_FILE}")"
    if [ $((8#${mode} & 8#077)) -ne 0 ]; then
        result env_file FAIL "mode ${mode}; must not be readable by group/other (chmod 600)"
    else
        result env_file PASS "mode ${mode}"
    fi

    local required=(DB_PASSWORD)
    if [ "${PROFILE}" = "with-acs" ]; then
        required+=(GENIEACS_UI_JWT_SECRET MONGO_ACS_ROOT_PASSWORD MONGO_ACS_APP_PASSWORD)
    fi
    if [ "${PLATFORM}" -eq 1 ]; then
        required+=(NMSOLT_DATA_DIR)
    fi
    for key in "${required[@]}"; do
        value="$(env_get "${key}")"
        if [ -z "${value}" ]; then
            result "env_${key}" FAIL "not set"
        elif [ "${key}" != NMSOLT_DATA_DIR ] && [ "${#value}" -lt 16 ]; then
            result "env_${key}" FAIL "set but shorter than 16 characters"
        else
            result "env_${key}" PASS "set"
        fi
    done
    if [ "${PROFILE}" = "with-acs" ]; then
        for key in MONGO_ACS_ROOT_PASSWORD MONGO_ACS_APP_PASSWORD; do
            value="$(env_get "${key}")"
            if [ -n "${value}" ] && ! [[ "${value}" =~ ^[A-Za-z0-9._~-]+$ ]]; then
                result "env_${key}_charset" FAIL "use only A-Z a-z 0-9 . _ ~ - (it is placed in a MongoDB URL)"
            fi
        done
    fi

    value="$(env_get NMSOLT_DEVICE_WRITES_ENABLED)"
    case "${value,,}" in
        ""|0|false|no|off) result device_writes PASS "disabled" ;;
        *) result device_writes FAIL "NMSOLT_DEVICE_WRITES_ENABLED must stay false (NMS-11 gate)" ;;
    esac
    value="$(env_get APP_DEBUG)"
    case "${value,,}" in
        ""|0|false|no|off) result app_debug PASS "off" ;;
        *) result app_debug FAIL "APP_DEBUG must be false" ;;
    esac
    if [ "${PLATFORM}" -eq 1 ]; then
        value="$(env_get NMSOLT_DATA_DIR)"
        if [ -n "${value}" ] && [[ "${value}" != /* ]]; then
            result env_NMSOLT_DATA_DIR_absolute FAIL "must be an absolute path"
        fi
    fi
}

check_render() {
    local rendered profile_arg
    if ! rendered="$(compose config --format json 2>&1)"; then
        result compose_render FAIL "$(printf '%s' "${rendered}" | head -n 1)"
        return
    fi
    result compose_render PASS "profile=${PROFILE} platform=${PLATFORM}"
    profile_arg=(--profile "${PROFILE}")
    local cwmp_ip
    cwmp_ip="$(env_get ACS_CWMP_BIND_IP)"
    if [ -n "${cwmp_ip}" ] && [ "${cwmp_ip}" != 127.0.0.1 ]; then
        profile_arg+=(--approved-cwmp-host-ip "${cwmp_ip}")
        result cwmp_bind WARN "CWMP published on ${cwmp_ip}; firewall source allow-list required"
    fi
    if [ "${PLATFORM}" -eq 1 ]; then
        profile_arg+=(--data-dir "$(env_get NMSOLT_DATA_DIR)")
    fi
    local out
    if out="$(printf '%s' "${rendered}" | python3 "${SOURCE_DIR}/scripts/nostek/validate-staging-compose.py" "${profile_arg[@]}" 2>&1)"; then
        result topology PASS "$(printf '%s' "${out}" | tail -n 1)"
    else
        result topology FAIL "$(printf '%s' "${out}" | tr '\n' ';' | cut -c1-400)"
    fi
    if [ "${PROFILE}" = "with-acs" ]; then
        local acs_args=(--dockerfile "${SOURCE_DIR}/docker/genieacs/Dockerfile"
            --compose-source "${SOURCE_DIR}/compose.acs.yaml")
        if [ -n "${cwmp_ip}" ] && [ "${cwmp_ip}" != 127.0.0.1 ]; then
            acs_args+=(--approved-cwmp-host-ip "${cwmp_ip}")
        fi
        if out="$(printf '%s' "${rendered}" | python3 "${SOURCE_DIR}/scripts/nostek/validate-genieacs-lab.py" "${acs_args[@]}" 2>&1)"; then
            result acs_policy PASS "$(printf '%s' "${out}" | tail -n 1)"
        else
            result acs_policy FAIL "$(printf '%s' "${out}" | tr '\n' ';' | cut -c1-400)"
        fi
    fi
}

check_docker() {
    if ! "${DOCKER}" info >/dev/null 2>&1; then
        result docker FAIL "Docker daemon not reachable"
        return
    fi
    result docker PASS "$("${DOCKER}" version --format '{{.Server.Version}}' 2>/dev/null || echo unknown)"
    if "${DOCKER}" compose version >/dev/null 2>&1; then
        result compose PASS "$("${DOCKER}" compose version --short 2>/dev/null || echo v2)"
    else
        result compose FAIL "docker compose v2 plugin missing"
    fi
}

check_data_dir() {
    [ "${PLATFORM}" -eq 1 ] || return 0
    local dir sub free_kb
    dir="$(env_get NMSOLT_DATA_DIR)"
    [ -n "${dir}" ] || return 0
    local subs=(pg redis app-storage dumps)
    [ "${PROFILE}" = "with-acs" ] && subs+=(mongo)
    for sub in "${subs[@]}"; do
        if [ -L "${dir}/${sub}" ]; then
            result "data_dir_${sub}" FAIL "${dir}/${sub} is a symlink"
        elif [ -d "${dir}/${sub}" ]; then
            result "data_dir_${sub}" PASS "present"
        else
            result "data_dir_${sub}" FAIL "missing (run init-data)"
        fi
    done
    local probe="${dir}"
    while [ ! -e "${probe}" ] && [ "${probe}" != / ]; do probe="$(dirname "${probe}")"; done
    free_kb="$(df -Pk "${probe}" | awk 'NR == 2 { print $4 }')"
    if [ -n "${free_kb}" ] && [ "${free_kb}" -ge $((MIN_FREE_GB * 1024 * 1024)) ]; then
        result data_free PASS "$((free_kb / 1024 / 1024)) GiB free on ${probe}"
    else
        result data_free FAIL "$((${free_kb:-0} / 1024 / 1024)) GiB free on ${probe}; need ${MIN_FREE_GB} GiB"
    fi
}

check_ports() {
    local port ip spec ours
    local specs=("127.0.0.1:$(env_get APP_PORT)")
    if [ "${PROFILE}" = "with-acs" ]; then
        specs+=("$(env_get ACS_CWMP_BIND_IP):$(env_get ACS_CWMP_PORT)" "127.0.0.1:$(env_get ACS_UI_PORT)")
    fi
    ours="$(compose ps -q 2>/dev/null | wc -l)"
    for spec in "${specs[@]}"; do
        ip="${spec%%:*}"; port="${spec##*:}"
        [ -n "${ip}" ] || ip=127.0.0.1
        [ -n "${port}" ] || continue
        if ss -Hltn "( sport = :${port} )" 2>/dev/null | grep -q .; then
            if [ "${ours}" -gt 0 ]; then
                result "port_${port}" PASS "in use (stack already running)"
            else
                result "port_${port}" FAIL "${ip}:${port} already in use by another process"
            fi
        else
            result "port_${port}" PASS "free"
        fi
    done
}

# --------------------------------------------------------------------------- status
service_states() { # prints "service state health" per line
    compose ps --all --format json 2>/dev/null | python3 -c '
import json, sys
raw = sys.stdin.read().strip()
items = []
if raw.startswith("["):
    items = json.loads(raw)
else:
    items = [json.loads(line) for line in raw.splitlines() if line.strip()]
for item in items:
    print(item.get("Service", "?"), item.get("State", "?") or "?", item.get("Health", "") or "-")
'
}

cmd_status() {
    local states svc line state health
    states="$(service_states || true)"
    while IFS= read -r svc; do
        line="$(printf '%s\n' "${states}" | awk -v s="${svc}" '$1 == s { print; exit }')"
        if [ -z "${line}" ]; then
            result "service_${svc}" FAIL "not created"
            continue
        fi
        state="$(printf '%s' "${line}" | awk '{ print $2 }')"
        health="$(printf '%s' "${line}" | awk '{ print $3 }')"
        if [ "${state}" = running ] && [ "${health}" = healthy ]; then
            result "service_${svc}" PASS "running healthy"
        elif [ "${state}" = running ] && [ "${health}" = starting ]; then
            result "service_${svc}" UNKNOWN "running, health starting"
        else
            result "service_${svc}" FAIL "state=${state} health=${health}"
        fi
    done < <(expected_services)

    local code
    code="$(compose exec -T app curl -s -o /dev/null -w '%{http_code}' http://localhost/up 2>/dev/null || true)"
    if [ "${code}" = 200 ]; then result app_up PASS "http 200"; else result app_up FAIL "http ${code:-none}"; fi

    local writes
    writes="$(compose exec -T app printenv NMSOLT_DEVICE_WRITES_ENABLED 2>/dev/null || true)"
    case "${writes,,}" in
        ""|0|false|no|off) result device_writes PASS "disabled in the running app" ;;
        *) result device_writes FAIL "enabled in the running app" ;;
    esac

    local procs program
    procs="$(compose exec -T app supervisorctl status 2>/dev/null || true)"
    for program in nostek-nmsolt-worker nostek-nmsolt-scheduler; do
        if printf '%s\n' "${procs}" | grep -Eq "^${program}[[:space:]]+RUNNING"; then
            result "process_${program#nostek-nmsolt-}" PASS "RUNNING"
        elif [ -z "${procs}" ]; then
            result "process_${program#nostek-nmsolt-}" UNKNOWN "supervisorctl not reachable"
        else
            result "process_${program#nostek-nmsolt-}" FAIL "not RUNNING"
        fi
    done

    if [ "${PROFILE}" = "with-acs" ]; then
        code="$(compose exec -T app curl -s -o /dev/null -w '%{http_code}' \
            'http://genieacs-nbi:7557/devices/?projection=_id&limit=1' 2>/dev/null || true)"
        if [ "${code}" = 200 ]; then
            result acs_nbi_from_app PASS "http 200 over the private network"
        else
            result acs_nbi_from_app FAIL "http ${code:-none}"
        fi
    fi
}

# --------------------------------------------------------------------------- backup
sha_manifest() { # sha_manifest DIR -> writes DIR/MANIFEST.sha256
    (cd "$1" && find . -maxdepth 1 -type f ! -name MANIFEST.sha256 -printf '%f\n' | LC_ALL=C sort |
        xargs -r sha256sum > MANIFEST.sha256)
}

pg_counts() {
    local sql
    sql="select table_name, (xpath('/row/c/text()', query_to_xml(format('select count(*) as c from %I.%I', table_schema, table_name), false, true, '')))[1]::text from information_schema.tables where table_schema = 'public' and table_type = 'BASE TABLE' order by 1"
    printf '%s\n' "${sql}" | compose exec -T db sh -c 'psql -X -At -F "	" -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" -d "$POSTGRES_DB"'
}

MONGO_EXCLUDE=(cache tasks faults operations)

mongo_shell() { # mongo_shell < script.js ; authenticates as root from the container env, no argv secret
    compose exec -T mongo_acs sh -c '
set -eu
f=$(mktemp)
trap "rm -f \"$f\"" EXIT
{
    printf "%s\n" "db.getSiblingDB(\"admin\").auth(process.env.MONGO_INITDB_ROOT_USERNAME, process.env.MONGO_INITDB_ROOT_PASSWORD);"
    cat
} > "$f"
mongosh --quiet --norc genieacs "$f"
'
}

mongo_counts() {
    printf '%s\n' "db.getCollectionNames().sort().forEach(function (c) { print(c + '\t' + db.getCollection(c).countDocuments({})); });" |
        mongo_shell | grep -v -E "^($(IFS='|'; printf '%s' "${MONGO_EXCLUDE[*]}"))	" || true
}

# mongo_tool TOOL ARGS... : run mongodump/mongorestore with a --config file that
# holds the password, so the secret never appears in any argv.
mongo_tool() {
    compose exec -T mongo_acs sh -c '
set -eu
tool="$1"; shift
d=$(mktemp -d)
trap "rm -rf \"$d\"" EXIT
printf "password: \"%s\"\n" "$MONGO_INITDB_ROOT_PASSWORD" > "$d/cfg.yaml"
"$tool" --quiet --config "$d/cfg.yaml" --username "$MONGO_INITDB_ROOT_USERNAME" \
    --authenticationDatabase admin "$@"
' sh "$@"
}

do_backup() { # do_backup OUT_DIR LABEL INCLUDE_RAW -> prints final dir on fd 3
    local out="$1" label="$2" include_raw="$3" stamp final work steps=3 step=0
    stamp="$(date -u +%Y%m%dT%H%M%SZ)"
    final="${out}/nmsolt-backup-${stamp}-${label}"
    work="${final}.partial"
    [ "${PROFILE}" = "with-acs" ] && steps=4
    mkdir -p "${work}"

    step=$((step + 1)); progress "${step}/${steps} PostgreSQL dump"
    local pg_excl=(--exclude-table-data=onu_rx_samples)
    [ "${include_raw}" -eq 1 ] && pg_excl=()
    if compose exec -T db sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Fc "$@"' sh "${pg_excl[@]+"${pg_excl[@]}"}" > "${work}/postgres.dump" &&
        [ -s "${work}/postgres.dump" ]; then
        result backup_postgres PASS "$(du -h "${work}/postgres.dump" | cut -f1) raw_rx=$([ "${include_raw}" -eq 1 ] && echo included || echo excluded)"
    else
        result backup_postgres FAIL "pg_dump failed"
    fi
    pg_counts > "${work}/counts.postgres.tsv" 2>/dev/null || result backup_postgres_counts FAIL "count query failed"

    if [ "${PROFILE}" = "with-acs" ]; then
        step=$((step + 1)); progress "${step}/${steps} MongoDB (GenieACS) dump"
        local excl=() c
        for c in "${MONGO_EXCLUDE[@]}"; do excl+=("--excludeCollection=${c}"); done
        if mongo_tool mongodump --db genieacs "${excl[@]}" --archive --gzip > "${work}/mongo-genieacs.archive.gz" &&
            [ -s "${work}/mongo-genieacs.archive.gz" ]; then
            result backup_mongo PASS "$(du -h "${work}/mongo-genieacs.archive.gz" | cut -f1) transient collections excluded"
        else
            result backup_mongo FAIL "mongodump failed"
        fi
        mongo_counts > "${work}/counts.mongo.tsv" 2>/dev/null || result backup_mongo_counts FAIL "count query failed"
    fi

    step=$((step + 1)); progress "${step}/${steps} application storage"
    # APP_KEY (storage/app/.appkey) stays out of every backup: keep it in the
    # owner's password manager. Logs and framework caches are rebuildable.
    if compose exec -T app tar -C /var/www/html/storage \
        --exclude=./app/.appkey --exclude=./logs --exclude=./framework/cache \
        --exclude=./framework/sessions --exclude=./framework/views \
        -czf - . > "${work}/app-storage.tar.gz"; then
        result backup_storage PASS "$(du -h "${work}/app-storage.tar.gz" | cut -f1) APP_KEY excluded"
    else
        result backup_storage FAIL "storage archive failed"
    fi

    step=$((step + 1)); progress "${step}/${steps} manifest"
    {
        printf 'schema=nostek-nmsolt-backup/1\n'
        printf 'created_utc=%s\n' "${stamp}"
        printf 'label=%s\n' "${label}"
        printf 'profile=%s\n' "${PROFILE}"
        printf 'project=%s\n' "${PROJECT}"
        printf 'image_tag=%s\n' "$(env_get IMAGE_TAG)"
        printf 'source_commit=%s\n' "$(git -C "${SOURCE_DIR}" rev-parse HEAD 2>/dev/null || echo unknown)"
        printf 'raw_rx=%s\n' "$([ "${include_raw}" -eq 1 ] && echo included || echo excluded)"
    } > "${work}/backup.meta"
    sha_manifest "${work}"
    if [ "${FAILS}" -eq 0 ]; then
        mv "${work}" "${final}"
        result backup_dir PASS "${final}"
        BACKUP_DIR_RESULT="${final}"
    else
        # FAILED != ERASED: the partial set stays for inspection.
        result backup_dir FAIL "incomplete set kept at ${work}"
        BACKUP_DIR_RESULT=""
    fi
}

verify_backup() { # verify_backup DIR
    local dir="$1"
    if [ ! -f "${dir}/MANIFEST.sha256" ] || [ ! -f "${dir}/backup.meta" ]; then
        result backup_manifest FAIL "${dir} has no MANIFEST.sha256/backup.meta"
        return
    fi
    if (cd "${dir}" && sha256sum --quiet -c MANIFEST.sha256 >/dev/null 2>&1); then
        result backup_manifest PASS "$(wc -l < "${dir}/MANIFEST.sha256") files match"
    else
        result backup_manifest FAIL "checksum mismatch in ${dir}"
    fi
    local want
    want="$(awk -F= '$1 == "profile" { print $2 }' "${dir}/backup.meta")"
    if [ "${want}" = with-acs ] && [ "${PROFILE}" != with-acs ]; then
        result backup_profile FAIL "backup is with-acs; run with --profile with-acs"
    else
        result backup_profile PASS "${want}"
    fi
}

compare_counts() { # compare_counts NAME EXPECTED_FILE ACTUAL_TEXT [skip-table]
    local name="$1" expected="$2" actual="$3" skip="${4:-}"
    local diff
    diff="$(diff <(grep -v -E "^${skip:-__none__}	" "${expected}" | LC_ALL=C sort) \
        <(printf '%s\n' "${actual}" | grep -v -E "^${skip:-__none__}	" | grep . | LC_ALL=C sort) || true)"
    if [ -z "${diff}" ]; then
        result "restore_counts_${name}" PASS "$(grep -c . "${expected}") tables/collections equal"
    else
        result "restore_counts_${name}" FAIL "$(printf '%s' "${diff}" | grep -E '^[<>]' | head -n 5 | tr '\n' ';')"
    fi
}

wait_healthy() { # wait_healthy SECONDS
    local limit="$1" waited=0 svc ok
    while [ "${waited}" -le "${limit}" ]; do
        ok=1
        local states
        states="$(service_states || true)"
        while IFS= read -r svc; do
            printf '%s\n' "${states}" | awk -v s="${svc}" '$1 == s && $2 == "running" && $3 == "healthy" { f = 1 } END { exit !f }' || ok=0
        done < <(expected_services)
        if [ "${ok}" -eq 1 ]; then
            result wait_healthy PASS "all services healthy after ${waited}s"
            return 0
        fi
        progress "waiting for healthy services ${waited}/${limit}s"
        sleep 5
        waited=$((waited + 5))
    done
    result wait_healthy FAIL "services not healthy within ${limit}s"
    return 1
}

do_restore() { # do_restore DIR
    local dir="$1" steps=4 step=0
    [ -f "${dir}/mongo-genieacs.archive.gz" ] && steps=5

    step=$((step + 1)); progress "${step}/${steps} stop the app (no writers during restore)"
    compose stop app >/dev/null 2>&1 || true
    compose up -d --no-build db redis >/dev/null
    [ "${PROFILE}" = "with-acs" ] && compose up -d --no-build mongo_acs >/dev/null

    step=$((step + 1)); progress "${step}/${steps} PostgreSQL restore"
    if compose exec -T db sh -c 'pg_restore --clean --if-exists --no-owner --exit-on-error -U "$POSTGRES_USER" -d "$POSTGRES_DB"' < "${dir}/postgres.dump"; then
        result restore_postgres PASS "pg_restore done"
    else
        result restore_postgres FAIL "pg_restore failed"
    fi

    if [ -f "${dir}/mongo-genieacs.archive.gz" ]; then
        step=$((step + 1)); progress "${step}/${steps} MongoDB restore"
        if mongo_tool mongorestore --drop --nsInclude 'genieacs.*' --archive --gzip < "${dir}/mongo-genieacs.archive.gz"; then
            result restore_mongo PASS "mongorestore done"
        else
            result restore_mongo FAIL "mongorestore failed"
        fi
    fi

    step=$((step + 1)); progress "${step}/${steps} application storage"
    compose up -d --no-build app >/dev/null
    if compose exec -T app tar -C /var/www/html/storage -xzf - < "${dir}/app-storage.tar.gz" &&
        compose exec -T app chown -R www-data:www-data /var/www/html/storage; then
        result restore_storage PASS "extracted (existing APP_KEY kept)"
    else
        result restore_storage FAIL "storage extract failed"
    fi

    step=$((step + 1)); progress "${step}/${steps} verify"
    compose up -d --no-build >/dev/null
    local raw
    raw="$(awk -F= '$1 == "raw_rx" { print $2 }' "${dir}/backup.meta")"
    compare_counts postgres "${dir}/counts.postgres.tsv" "$(pg_counts 2>/dev/null || true)" \
        "$([ "${raw}" = included ] || echo onu_rx_samples)"
    if [ -f "${dir}/counts.mongo.tsv" ]; then
        compare_counts mongo "${dir}/counts.mongo.tsv" "$(mongo_counts 2>/dev/null || true)"
    fi
}

# --------------------------------------------------------------------------- commands
cmd_config_check() { check_env; check_render; }

cmd_preflight() {
    check_env
    check_render
    check_docker
    check_data_dir
    check_ports
}

cmd_init_data() {
    [ "${PLATFORM}" -eq 1 ] || die "init-data needs --platform"
    local dir sub
    dir="$(env_get NMSOLT_DATA_DIR)"
    [ -n "${dir}" ] && [[ "${dir}" == /* ]] || die "NMSOLT_DATA_DIR must be an absolute path in ${ENV_FILE}"
    local subs=(pg redis app-storage dumps)
    [ "${PROFILE}" = "with-acs" ] && subs+=(mongo)
    for sub in "${subs[@]}"; do
        if [ -L "${dir}/${sub}" ]; then
            result "data_dir_${sub}" FAIL "${dir}/${sub} is a symlink; refusing"
        elif [ -d "${dir}/${sub}" ]; then
            result "data_dir_${sub}" PASS "already present (left as is)"
        else
            mkdir -p "${dir}/${sub}"
            chmod 0750 "${dir}/${sub}"
            result "data_dir_${sub}" PASS "created"
        fi
    done
}

image_repo() {
    local repo
    repo="$(env_get NMSOLT_IMAGE_REPO)"
    printf '%s' "${repo:-ghcr.io/thuekx/nostek-nmsolt}"
}

cmd_start() {
    local wait_s="$1"
    progress "1/2 start containers"
    if compose up -d --no-build >/dev/null; then
        result start PASS "containers started"
    else
        result start FAIL "compose up failed"
        return 0
    fi
    progress "2/2 wait for healthy services"
    wait_healthy "${wait_s}" || true
}

cmd_stop() {
    if compose stop >/dev/null; then
        result stop PASS "containers stopped, data kept"
    else
        result stop FAIL "compose stop failed"
    fi
}

cmd_build() {
    local tag="$1"
    [[ "${tag}" =~ ^[A-Za-z0-9._-]{1,64}$ ]] || die "--tag must match [A-Za-z0-9._-]{1,64}"
    progress "1/1 build app image tag ${tag}"
    if IMAGE_TAG="${tag}" compose build app; then
        result build PASS "$(image_repo):${tag}"
    else
        result build FAIL "image build failed"
    fi
}

cmd_backup() {
    local out="$1" include_raw="$2"
    [ -n "${out}" ] || die "backup needs --out DIR"
    mkdir -p "${out}"
    do_backup "${out}" manual "${include_raw}"
}

cmd_restore() {
    local from="$1" confirm="$2" safety="$3"
    [ "${confirm}" = RESTORE ] || die "restore needs --confirm RESTORE (it replaces the data of project ${PROJECT})"
    [ -d "${from}" ] || die "no backup directory ${from}"
    verify_backup "${from}"
    [ "${FAILS}" -eq 0 ] || return 0
    if [ "${safety}" -eq 1 ]; then
        do_backup "$(dirname "${from}")" pre-restore 1
        [ "${FAILS}" -eq 0 ] || { result restore SKIPPED "safety backup failed; nothing restored"; FAILS=$((FAILS + 1)); return 0; }
    fi
    do_restore "${from}"
    wait_healthy 300 || true
}

cmd_upgrade() {
    local tag="$1" backup_dir="$2" wait_s="$3" data_rollback="$4"
    [[ "${tag}" =~ ^[A-Za-z0-9._-]{1,64}$ ]] || die "--tag must match [A-Za-z0-9._-]{1,64}"
    [ -n "${backup_dir}" ] || die "upgrade needs --backup-dir DIR"
    local image previous
    image="$(image_repo):${tag}"
    if ! "${DOCKER}" image inspect "${image}" >/dev/null 2>&1; then
        die "image ${image} is not present; run: nmsolt-ops.sh build --tag ${tag}"
    fi
    previous="$(env_get IMAGE_TAG)"
    previous="${previous:-local}"
    [ "${previous}" != "${tag}" ] || die "IMAGE_TAG is already ${tag}"
    result upgrade_plan PASS "from ${previous} to ${tag}"

    progress "1/4 pre-upgrade backup"
    mkdir -p "${backup_dir}"
    do_backup "${backup_dir}" "pre-upgrade-${previous}" 1
    if [ "${FAILS}" -ne 0 ]; then
        result upgrade FAIL "pre-upgrade backup failed; nothing changed"
        return 0
    fi

    progress "2/4 record rollback point"
    mkdir -p "${STATE_DIR}"
    {
        printf 'previous_tag=%s\n' "${previous}"
        printf 'new_tag=%s\n' "${tag}"
        printf 'backup=%s\n' "${BACKUP_DIR_RESULT}"
        printf 'created_utc=%s\n' "$(date -u +%Y%m%dT%H%M%SZ)"
    } > "${STATE_DIR}/rollback-point.tmp"
    mv -f "${STATE_DIR}/rollback-point.tmp" "${STATE_DIR}/rollback-point"
    result rollback_point PASS "${STATE_DIR}/rollback-point"

    progress "3/4 switch IMAGE_TAG and start"
    env_set IMAGE_TAG "${tag}"
    compose up -d --no-build >/dev/null 2>&1 || true

    progress "4/4 wait for healthy services"
    if wait_healthy "${wait_s}"; then
        result upgrade PASS "running ${tag}; rollback point kept"
        return 0
    fi
    result upgrade FAIL "new version unhealthy; rolling back automatically"
    rollback_to_point "${data_rollback}"
}

rollback_to_point() { # rollback_to_point WITH_DATA
    local with_data="$1" previous backup
    [ -f "${STATE_DIR}/rollback-point" ] || die "no rollback point in ${STATE_DIR}"
    previous="$(awk -F= '$1 == "previous_tag" { print $2 }' "${STATE_DIR}/rollback-point")"
    backup="$(awk -F= '$1 == "backup" { print $2 }' "${STATE_DIR}/rollback-point")"
    env_set IMAGE_TAG "${previous}"
    if [ "${with_data}" -eq 1 ]; then
        # Migrations of the newer version may have changed the schema: restore the
        # pre-upgrade backup taken by `upgrade`.
        local before="${FAILS}"
        verify_backup "${backup}"
        if [ "${FAILS}" -eq "${before}" ]; then
            do_restore "${backup}"
        fi
    else
        compose up -d --no-build >/dev/null 2>&1 || true
    fi
    local before_wait="${FAILS}"
    if wait_healthy 300; then
        result rollback PASS "back on ${previous}$([ "${with_data}" -eq 1 ] && echo ' with pre-upgrade data')"
    else
        result rollback FAIL "still unhealthy on ${previous}; stop here and inspect"
    fi
    : "${before_wait}"
}

cmd_rollback() {
    local confirm="$1" with_data="$2"
    [ "${confirm}" = ROLLBACK ] || die "rollback needs --confirm ROLLBACK"
    rollback_to_point "${with_data}"
}

# --------------------------------------------------------------------------- main
main() {
    local command=""
    while [ $# -gt 0 ]; do
        case "$1" in
            --source) SOURCE_DIR="$(cd "$2" && pwd)"; shift 2 ;;
            --env-file) ENV_FILE="$2"; shift 2 ;;
            --project) PROJECT="$2"; shift 2 ;;
            --profile) PROFILE="$2"; shift 2 ;;
            --platform) PLATFORM=1; shift ;;
            --override) OVERRIDES+=("$2"); shift 2 ;;
            --expect-host) EXPECT_HOST="$2"; shift 2 ;;
            -h|--help) usage; exit 0 ;;
            -*) die "unknown option $1" ;;
            *) command="$1"; shift; break ;;
        esac
    done
    [ -n "${command}" ] || { usage; exit 2; }
    case "${PROFILE}" in core|with-acs) ;; *) die "--profile must be core or with-acs" ;; esac

    check_host
    ENV_FILE="${ENV_FILE:-${SOURCE_DIR}/.env}"
    [ -f "${ENV_FILE}" ] || die "env file ${ENV_FILE} not found"
    ENV_FILE="$(cd "$(dirname "${ENV_FILE}")" && pwd)/$(basename "${ENV_FILE}")"
    if [ -z "${PROJECT}" ]; then
        PROJECT="$(env_get COMPOSE_PROJECT_NAME)"
        PROJECT="${PROJECT:-nostek-nmsolt-lab}"
    fi
    [[ "${PROJECT}" =~ ^[a-z0-9][a-z0-9_-]{0,62}$ ]] || die "invalid project name ${PROJECT}"
    STATE_DIR="${NMSOLT_STATE_DIR:-$(dirname "${ENV_FILE}")/.nmsolt-state/${PROJECT}}"
    compose_files

    local out="" from="" tag="" confirm="" include_raw=0 safety=1 wait_s=300 data_rollback=1 with_data=0
    while [ $# -gt 0 ]; do
        case "$1" in
            --out) out="$2"; shift 2 ;;
            --from) from="$2"; shift 2 ;;
            --tag) tag="$2"; shift 2 ;;
            --backup-dir) out="$2"; shift 2 ;;
            --confirm) confirm="$2"; shift 2 ;;
            --include-raw-rx) include_raw=1; shift ;;
            --no-safety-backup) safety=0; shift ;;
            --wait) wait_s="$2"; shift 2 ;;
            --no-data-rollback) data_rollback=0; shift ;;
            --with-data) with_data=1; shift ;;
            *) die "unknown argument $1 for ${command}" ;;
        esac
    done
    [[ "${wait_s}" =~ ^[0-9]{1,4}$ ]] || die "--wait must be seconds"

    printf 'nmsolt-ops %s profile=%s platform=%s project=%s\n' "${command}" "${PROFILE}" "${PLATFORM}" "${PROJECT}"
    case "${command}" in
        config-check) cmd_config_check ;;
        preflight) cmd_preflight ;;
        init-data) cmd_init_data ;;
        status) cmd_status ;;
        start) cmd_start "${wait_s}" ;;
        stop) cmd_stop ;;
        build) [ -n "${tag}" ] || die "build needs --tag"; cmd_build "${tag}" ;;
        backup) cmd_backup "${out}" "${include_raw}" ;;
        verify-backup) [ -n "${from}" ] || die "verify-backup needs --from"; verify_backup "${from}" ;;
        restore) [ -n "${from}" ] || die "restore needs --from"; cmd_restore "${from}" "${confirm}" "${safety}" ;;
        upgrade) [ -n "${tag}" ] || die "upgrade needs --tag"; cmd_upgrade "${tag}" "${out}" "${wait_s}" "${data_rollback}" ;;
        rollback) cmd_rollback "${confirm}" "${with_data}" ;;
        *) die "unknown command ${command}" ;;
    esac
    finish "${command}"
}

main "$@"
