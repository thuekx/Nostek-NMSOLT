#!/usr/bin/env bash
#
# Ops integration test for scripts/nostek/nmsolt-ops.sh (NMS-07 unified package).
# Runs a throw-away Compose project with real PostgreSQL, Redis, MongoDB and
# GenieACS containers plus a small PHP stand-in for the app image, on this
# machine only (CI runner or developer workstation). It never contacts a
# device, a production host or a registry other than the image pulls.
#
#   bash tests/ops/integration.sh            # with-acs + platform overlay, then core
#   KEEP=1 bash tests/ops/integration.sh     # keep the project for inspection on FAIL
#
# Requires: docker with compose v2, python3, openssl, ss. The GenieACS image
# nmsolt-genieacs:1.2.16-local must exist (docker compose build genieacs-cwmp)
# unless NMSOLT_IT_BUILD_ACS=1 lets the test build it.
set -Eeuo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
OPS="${ROOT}/scripts/nostek/nmsolt-ops.sh"
STANDIN="${ROOT}/tests/ops/compose.standin-app.yaml"
WORK="$(mktemp -d "${TMPDIR:-/tmp}/nmsolt-opsit.XXXXXX")"
PASSED=0
FAILED=0
PROJECTS=()

pass() { PASSED=$((PASSED + 1)); printf 'PASS %s\n' "$*"; }
fail() { FAILED=$((FAILED + 1)); printf 'FAIL %s\n' "$*"; }

cleanup() {
    local p
    if [ "${FAILED}" -gt 0 ] && [ "${KEEP:-0}" = 1 ]; then
        printf 'KEEP=1: leaving projects %s and %s\n' "${PROJECTS[*]-}" "${WORK}"
        return
    fi
    for p in "${PROJECTS[@]+"${PROJECTS[@]}"}"; do
        docker compose -p "${p}" down -v --remove-orphans >/dev/null 2>&1 || true
    done
    # Bind-mounted database files belong to container users; remove them through a container.
    docker run --rm -v "${WORK}:/w" "${STANDIN_BASE_IMAGE:-php:8.3-fpm-bookworm}" sh -c 'rm -rf /w/*' >/dev/null 2>&1 || true
    rm -rf "${WORK}" 2>/dev/null || true
}
trap cleanup EXIT

free_port() { python3 -c 'import socket; s=socket.socket(); s.bind(("127.0.0.1", 0)); print(s.getsockname()[1]); s.close()'; }

# run NAME EXPECTED_EXIT -- args... : runs nmsolt-ops, keeps the log, checks exit code
run() {
    local name="$1" want="$2" rc=0
    shift 3
    "${OPS}" "$@" > "${WORK}/${name}.log" 2>&1 || rc=$?
    ALL_LOGS+=("${WORK}/${name}.log")
    if [ "${rc}" -eq "${want}" ]; then
        pass "${name} exit=${rc}"
    else
        fail "${name} exit=${rc} want=${want}"
        sed 's/^/    | /' "${WORK}/${name}.log" | tail -n 40
    fi
}
expect_line() { # expect_line NAME REGEX
    if grep -Eq "$2" "${WORK}/$1.log"; then pass "$1 has /$2/"; else fail "$1 lacks /$2/"; fi
}
ALL_LOGS=()

build_images() {
    local base="${STANDIN_BASE_IMAGE:-}"
    local args=()
    [ -n "${base}" ] && args+=(--build-arg "BASE=${base}")
    docker build -q "${args[@]+"${args[@]}"}" -t nmsolt-standin:v1 "${ROOT}/tests/ops/standin-app" >/dev/null
    docker tag nmsolt-standin:v1 nmsolt-standin:v2
    docker build -q "${args[@]+"${args[@]}"}" --build-arg STANDIN_BROKEN=1 -t nmsolt-standin:broken "${ROOT}/tests/ops/standin-app" >/dev/null
    if ! docker image inspect nmsolt-genieacs:1.2.16-local >/dev/null 2>&1; then
        if [ "${NMSOLT_IT_BUILD_ACS:-0}" = 1 ]; then
            docker build -q -t nmsolt-genieacs:1.2.16-local -f "${ROOT}/docker/genieacs/Dockerfile" "${ROOT}" >/dev/null
        else
            printf 'missing image nmsolt-genieacs:1.2.16-local (set NMSOLT_IT_BUILD_ACS=1)\n' >&2
            exit 2
        fi
    fi
}

write_env() { # write_env FILE PROJECT DATA_DIR
    local file="$1"
    {
        cat "${ROOT}/.env.docker.example"
        printf 'COMPOSE_PROJECT_NAME=%s\n' "$2"
        printf 'APP_PORT=%s\n' "$(free_port)"
        printf 'ACS_CWMP_PORT=%s\n' "$(free_port)"
        printf 'ACS_UI_PORT=%s\n' "$(free_port)"
        printf 'DB_PASSWORD=%s\n' "${SECRET_DB}"
        printf 'GENIEACS_UI_JWT_SECRET=%s\n' "${SECRET_JWT}"
        printf 'MONGO_ACS_ROOT_PASSWORD=%s\n' "${SECRET_MROOT}"
        printf 'MONGO_ACS_APP_PASSWORD=%s\n' "${SECRET_MAPP}"
        printf 'NMSOLT_DATA_DIR=%s\n' "$3"
        printf 'NMSOLT_IMAGE_REPO=nmsolt-standin\n'
        printf 'IMAGE_TAG=v1\n'
    } > "${file}"
    chmod 600 "${file}"
}

psql_exec() { # psql_exec PROJECT_ARGS... -- SQL on stdin
    docker compose "$@" exec -T db sh -c 'psql -X -At -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" -d "$POSTGRES_DB"'
}
mongo_exec() {
    docker compose "$@" exec -T mongo_acs sh -c 'f=$(mktemp); { echo "db.getSiblingDB(\"admin\").auth(process.env.MONGO_INITDB_ROOT_USERNAME, process.env.MONGO_INITDB_ROOT_PASSWORD);"; cat; } > "$f"; mongosh --quiet --norc genieacs "$f"; rc=$?; rm -f "$f"; exit $rc'
}

scenario_with_acs() {
    local project="nmsolt-opsit-acs-$$" env="${WORK}/acs.env" data="${WORK}/acs-data" backups="${WORK}/acs-backups"
    PROJECTS+=("${project}")
    write_env "${env}" "${project}" "${data}"
    local g=(--env-file "${env}" --profile with-acs --platform)
    local s=("${g[@]}" --override "${STANDIN}")
    local dc=(--project-directory "${ROOT}" --env-file "${env}" -p "${project}" -f "${ROOT}/docker-compose.yml"
        -f "${ROOT}/compose.acs.yaml" -f "${ROOT}/compose.platform.yaml" -f "${STANDIN}")
    export NMSOLT_STATE_DIR="${WORK}/acs-state"
    export NMSOLT_MIN_FREE_GB=1

    run acs-wrong-host 3 -- "${g[@]}" --expect-host not-this-host status
    expect_line acs-wrong-host '^❌ WRONG HOST: this is .* nothing done$'

    chmod 644 "${env}"
    run acs-config-perm 1 -- "${g[@]}" config-check
    expect_line acs-config-perm '^RESULT env_file=FAIL'
    chmod 600 "${env}"

    run acs-config-check 0 -- "${g[@]}" config-check
    expect_line acs-config-check '^RESULT topology=PASS'
    expect_line acs-config-check '^RESULT acs_policy=PASS'
    run acs-preflight-nodata 1 -- "${g[@]}" preflight
    expect_line acs-preflight-nodata '^RESULT data_dir_pg=FAIL missing'
    run acs-init-data 0 -- "${g[@]}" init-data
    run acs-preflight 0 -- "${g[@]}" preflight
    expect_line acs-preflight '^RESULT data_free=PASS'

    run acs-start 0 -- "${s[@]}" start --wait 240
    run acs-status 0 -- "${s[@]}" status
    expect_line acs-status '^RESULT service_genieacs-nbi=PASS running healthy'
    expect_line acs-status '^RESULT acs_nbi_from_app=PASS'
    expect_line acs-status '^RESULT device_writes=PASS'
    expect_line acs-status '^RESULT process_worker=PASS'

    # Data lands on the bind mounts, not in Docker volumes.
    if [ -n "$(ls -A "${data}/pg" 2>/dev/null)" ] && [ -n "$(ls -A "${data}/mongo" 2>/dev/null)" ]; then
        pass "acs data on bind mounts under ${data}"
    else
        fail "acs data dirs empty"
    fi

    # The app container never receives the other containers' secrets.
    if docker compose "${dc[@]}" exec -T app printenv | grep -Fq -e "${SECRET_MROOT}" -e "${SECRET_MAPP}" -e "${SECRET_JWT}"; then
        fail "app environment holds a foreign secret"
    else
        pass "app environment holds no MongoDB/GenieACS secret"
    fi
    # MongoDB refuses unauthenticated reads.
    if docker compose "${dc[@]}" exec -T mongo_acs mongosh --quiet --norc genieacs --eval 'db.presets.countDocuments({})' >/dev/null 2>&1; then
        fail "mongo allows unauthenticated reads"
    else
        pass "mongo refuses unauthenticated reads"
    fi

    # Seed synthetic data.
    psql_exec "${dc[@]}" <<'SQL' >/dev/null
create table it_olts (id serial primary key, name text);
insert into it_olts (name) select 'olt-' || g from generate_series(1, 25) g;
create table onu_rx_samples (id serial primary key, rx numeric);
insert into onu_rx_samples (rx) select -20 - random() * 8 from generate_series(1, 500);
SQL
    mongo_exec "${dc[@]}" <<'JS' >/dev/null
db.presets.insertMany([{_id: 'it-preset-1'}, {_id: 'it-preset-2'}]);
db.devices.insertMany([{_id: 'IT-DEVICE-1'}, {_id: 'IT-DEVICE-2'}, {_id: 'IT-DEVICE-3'}]);
db.cache.insertOne({_id: 'transient'});
JS
    docker compose "${dc[@]}" exec -T app sh -c 'mkdir -p /var/www/html/storage/app/public && echo photo > /var/www/html/storage/app/public/odp-1.txt && echo base64:not-a-real-key > /var/www/html/storage/app/.appkey'

    run acs-backup 0 -- "${s[@]}" backup --out "${backups}"
    local set
    set="$(sed -n 's/^RESULT backup_dir=PASS //p' "${WORK}/acs-backup.log")"
    if [ -d "${set}" ]; then pass "backup set ${set##*/}"; else fail "no backup set"; return; fi
    run acs-verify 0 -- "${s[@]}" verify-backup --from "${set}"
    if tar -tzf "${set}/app-storage.tar.gz" | grep -q 'odp-1.txt' && ! tar -tzf "${set}/app-storage.tar.gz" | grep -q 'appkey'; then
        pass "storage archive has uploads, no APP_KEY"
    else
        fail "storage archive content wrong"
    fi
    if grep -q $'^onu_rx_samples\t500$' "${set}/counts.postgres.tsv" && grep -q $'^it_olts\t25$' "${set}/counts.postgres.tsv"; then
        pass "postgres counts recorded"
    else
        fail "postgres counts missing"
    fi
    if grep -q $'^devices\t3$' "${set}/counts.mongo.tsv" && ! grep -q '^cache' "${set}/counts.mongo.tsv"; then
        pass "mongo counts recorded, transient cache excluded"
    else
        fail "mongo counts wrong"
    fi

    # Tampered copy must fail verification.
    cp -a "${set}" "${set}-tampered"
    printf 'x' >> "${set}-tampered/postgres.dump"
    run acs-verify-tampered 1 -- "${s[@]}" verify-backup --from "${set}-tampered"
    rm -rf "${set}-tampered"

    # Damage the live data, then restore.
    psql_exec "${dc[@]}" <<< 'delete from it_olts where id > 5;' >/dev/null
    mongo_exec "${dc[@]}" <<< 'db.devices.deleteMany({}); db.presets.deleteOne({_id: "it-preset-2"});' >/dev/null
    docker compose "${dc[@]}" exec -T app rm -f /var/www/html/storage/app/public/odp-1.txt
    run acs-restore-noconfirm 2 -- "${s[@]}" restore --from "${set}"
    run acs-restore 0 -- "${s[@]}" restore --from "${set}" --confirm RESTORE
    expect_line acs-restore '^RESULT restore_counts_postgres=PASS'
    expect_line acs-restore '^RESULT restore_counts_mongo=PASS'
    expect_line acs-restore '^RESULT backup_dir=PASS .*pre-restore'
    if [ "$(psql_exec "${dc[@]}" <<< 'select count(*) from it_olts;')" = 25 ]; then pass "postgres rows back"; else fail "postgres rows not restored"; fi
    if docker compose "${dc[@]}" exec -T app test -f /var/www/html/storage/app/public/odp-1.txt; then pass "upload back"; else fail "upload not restored"; fi
    if docker compose "${dc[@]}" exec -T app grep -q not-a-real-key /var/www/html/storage/app/.appkey; then pass "existing APP_KEY kept"; else fail "APP_KEY changed"; fi

    # Upgrade v1 -> v2, manual rollback, then a broken upgrade with auto-rollback.
    run acs-upgrade-missing 2 -- "${s[@]}" upgrade --tag v9 --backup-dir "${backups}"
    run acs-upgrade 0 -- "${s[@]}" upgrade --tag v2 --backup-dir "${backups}" --wait 120
    expect_line acs-upgrade '^RESULT upgrade=PASS running v2'
    if grep -q '^IMAGE_TAG=v2$' "${env}" && [ -n "$(find "${NMSOLT_STATE_DIR}/env-history" -type f -print -quit)" ]; then
        pass "IMAGE_TAG=v2, old env kept in history"
    else
        fail "env not switched or history missing"
    fi
    run acs-rollback-noconfirm 2 -- "${s[@]}" rollback
    run acs-rollback 0 -- "${s[@]}" rollback --confirm ROLLBACK
    expect_line acs-rollback '^RESULT rollback=PASS back on v1'
    run acs-upgrade-broken 1 -- "${s[@]}" upgrade --tag broken --backup-dir "${backups}" --wait 30
    expect_line acs-upgrade-broken '^RESULT upgrade=FAIL new version unhealthy; rolling back automatically'
    expect_line acs-upgrade-broken '^RESULT rollback=PASS back on v1 with pre-upgrade data'
    if grep -q '^IMAGE_TAG=v1$' "${env}"; then pass "IMAGE_TAG back to v1"; else fail "IMAGE_TAG not rolled back"; fi
    run acs-status-final 0 -- "${s[@]}" status

    run acs-stop 0 -- "${s[@]}" stop
}

scenario_core() {
    local project="nmsolt-opsit-core-$$" env="${WORK}/core.env" backups="${WORK}/core-backups"
    PROJECTS+=("${project}")
    write_env "${env}" "${project}" "${WORK}/core-data-unused"
    local s=(--env-file "${env}" --profile core --override "${STANDIN}")
    local dc=(--project-directory "${ROOT}" --env-file "${env}" -p "${project}" -f "${ROOT}/docker-compose.yml" -f "${STANDIN}")
    export NMSOLT_STATE_DIR="${WORK}/core-state"

    run core-config-check 0 -- --env-file "${env}" --profile core config-check
    run core-start 0 -- "${s[@]}" start --wait 120
    run core-status 0 -- "${s[@]}" status
    if grep -q 'mongo\|genieacs' "${WORK}/core-status.log"; then fail "core status mentions ACS"; else pass "core status has no ACS services"; fi
    psql_exec "${dc[@]}" <<< "create table it_core (id int); insert into it_core select generate_series(1, 7);" >/dev/null
    run core-backup 0 -- "${s[@]}" backup --out "${backups}"
    local set
    set="$(sed -n 's/^RESULT backup_dir=PASS //p' "${WORK}/core-backup.log")"
    if [ -f "${set}/mongo-genieacs.archive.gz" ]; then fail "core backup has a mongo dump"; else pass "core backup without mongo"; fi
    psql_exec "${dc[@]}" <<< "truncate it_core;" >/dev/null
    run core-restore 0 -- "${s[@]}" restore --from "${set}" --confirm RESTORE --no-safety-backup
    if [ "$(psql_exec "${dc[@]}" <<< 'select count(*) from it_core;')" = 7 ]; then pass "core rows back"; else fail "core rows not restored"; fi
}

SECRET_DB="it$(openssl rand -hex 16)"
SECRET_JWT="it$(openssl rand -hex 16)"
SECRET_MROOT="it$(openssl rand -hex 16)"
SECRET_MAPP="it$(openssl rand -hex 16)"

build_images
scenario_with_acs
scenario_core

# No generated secret may appear in any output of the tool.
if grep -F -l -e "${SECRET_DB}" -e "${SECRET_JWT}" -e "${SECRET_MROOT}" -e "${SECRET_MAPP}" "${ALL_LOGS[@]}" >/dev/null 2>&1; then
    fail "a generated secret appears in nmsolt-ops output"
else
    pass "no generated secret in ${#ALL_LOGS[@]} nmsolt-ops logs"
fi

printf 'RESULT ops-integration passed=%s failed=%s\n' "${PASSED}" "${FAILED}"
[ "${FAILED}" -eq 0 ]
