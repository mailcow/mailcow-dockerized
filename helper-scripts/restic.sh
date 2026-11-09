#!/usr/bin/env bash
# Restore of mailcow from a restic snapshot of the backup feature (System > Configuration > Backup).
#
#   helper-scripts/restic.sh snapshots                    list the snapshots of the repository
#   helper-scripts/restic.sh restore [id]                 full restore (default: the latest mailcow snapshot): the mailcow
#                                                         directory with its version and configuration, all data volumes
#                                                         and the database, as they were when the snapshot was made
#   helper-scripts/restic.sh restore --data-only [id]     only the data volumes and the database (optionally data/conf and
#                                                         mailcow.conf) into this mailcow, e.g. to move them into a newer one
#
# Snapshots made with RESTIC_BACKUP_MODE=data (of the mailcow directory only mailcow.conf) or config (without mails) are
# restored with --data-only; a config snapshot leaves mails, mail index, Rspamd and SOGo data as they are.
#
# A full restore moves this directory aside (<dir>.before-restore-<date>) and restores the snapshot's directory in its
# place, then starts that version with its images. On a new server only Docker, a clone of mailcow and this script are
# needed (no generate_config.sh, no first start).
#
# The repository settings come from the mailcow database when mailcow runs, else from the environment:
#   RESTIC_REPOSITORY   e.g. s3:https://s3.example.com/bucket/path, sftp:user@host:/path, rest:https://host:8000/path,
#                       /repository/<path> for a local repository (folder RESTIC_LOCAL_PATH, else the volume restic-repo-vol-1)
#   RESTIC_PASSWORD     asked for when not set
#   AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_DEFAULT_REGION (S3), RESTIC_REST_USERNAME, RESTIC_REST_PASSWORD (REST)
#   RESTIC_OPTIONS      extra options, e.g. "-o s3.bucket-lookup=path"
#   RESTIC_SSH_KEY      private key file for SFTP (the host key is accepted on first use)
#   RESTIC_LOCAL_PATH   host folder of a local repository (as in mailcow.conf; empty: the volume restic-repo-vol-1)

# A full restore replaces the directory of this script: it runs from a copy
if [[ "${BASH_SOURCE[0]}" == "${0}" && -z ${RESTIC_SH_COPY} ]]; then
  RESTIC_SH_COPY=$(mktemp -d)
  cp "${BASH_SOURCE[0]}" "${RESTIC_SH_COPY}/restic.sh"
  export RESTIC_SH_COPY RESTIC_SH_MAILCOW_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." && pwd -P )"
  exec bash "${RESTIC_SH_COPY}/restic.sh" "$@"
fi

MAILCOW_DIR="${RESTIC_SH_MAILCOW_DIR:-$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." && pwd -P )}"
# the copy goes when the script ends (load_config adds its temp folder to this)
[[ "${BASH_SOURCE[0]}" == "${0}" && -n ${RESTIC_SH_COPY} ]] && trap 'rm -rf "${RESTIC_SH_COPY}"' EXIT
# volumes of a snapshot: path in the snapshot => compose volume
declare -A SNAPSHOT_VOLUMES=(
  [/var/vmail]=vmail-vol-1
  [/var/vmail_index]=vmail-index-vol-1
  [/mail_crypt]=crypt-vol-1
  [/redis]=redis-vol-1
  [/rspamd]=rspamd-vol-1
  [/sogo_backup]=sogo-userdata-backup-vol-1
)
declare -A MANIFEST

# docker compose in a mailcow directory with its .env (mailcow.conf): the files of COMPOSE_FILE, else
# docker-compose.yml and docker-compose.override.yml
function compose() {
  local dir=${1}
  shift
  local files=()
  if [[ -z ${COMPOSE_FILE} ]]; then
    files=(-f "${dir}/docker-compose.yml")
    [[ -f ${dir}/docker-compose.override.yml ]] && files+=(-f "${dir}/docker-compose.override.yml")
  fi
  ( cd "${dir}" && ${COMPOSE_COMMAND} --project-directory "${dir}" "${files[@]}" --env-file "${dir}/.env" "$@" )
}

# Runs restic in its image with the repository of RESTIC_ENV_FILE; docker options before --, restic arguments after it
function restic_run() {
  local docker_args=()
  while [[ $# -gt 0 && ${1} != "--" ]]; do
    docker_args+=("${1}")
    shift
  done
  shift
  # the network of mailcow if it runs (targets in it), the cache and the SSH key of the runner if they exist
  local network= cache= repo=()
  if [[ -n ${CMPS_PRJ} ]]; then
    network=$(docker network ls -qf name=^${CMPS_PRJ}_mailcow-network$)
    cache=$(docker volume ls -qf name=^${CMPS_PRJ}_restic-vol-1$)
  fi
  # a local repository: the folder RESTIC_LOCAL_PATH, else the volume of mailcow
  if [[ -n ${RESTIC_LOCAL_DIR} ]]; then
    mkdir -p "${RESTIC_LOCAL_DIR}"
    repo=(-v "${RESTIC_LOCAL_DIR}:/repository")
  elif [[ -n ${CMPS_PRJ} && -n $(docker volume ls -qf name=^${CMPS_PRJ}_restic-repo-vol-1$) ]]; then
    repo=(-v "${CMPS_PRJ}_restic-repo-vol-1:/repository")
  fi
  docker run --rm --security-opt label=disable ${network:+--network ${network}} -e TZ=${TZ} --env-file "${RESTIC_ENV_FILE}" \
    ${cache:+-v ${cache}:/cache} \
    "${repo[@]}" \
    ${RESTIC_SSH_KEY:+-v "${RESTIC_SSH_KEY}":/run/ssh_key:ro} \
    "${docker_args[@]}" --entrypoint /usr/bin/python3 ${RESTIC_IMAGE} -c "
import json, os, sys
opts = json.loads(os.environ.pop('MAILCOW_RESTIC_OPTS', '[]'))
os.execvp('restic', ['restic'] + opts + sys.argv[1:])" "$@"
}

function project_name() {
  echo "${1}" | tr -cd "[0-9A-Za-z-_]"
}

function load_config() {
  if [[ ! -f ${MAILCOW_DIR}/docker-compose.yml ]]; then
    echo -e "\e[31mdocker-compose.yml not found in ${MAILCOW_DIR}\e[0m"
    exit 1
  fi
  # mailcow.conf is missing in a fresh clone (new server)
  CMPS_PRJ=
  if [[ -f ${MAILCOW_DIR}/mailcow.conf ]]; then
    source "${MAILCOW_DIR}/mailcow.conf"
    CMPS_PRJ=$(project_name "${COMPOSE_PROJECT_NAME}")
  fi
  if docker compose version > /dev/null 2>&1; then
    COMPOSE_COMMAND="docker compose"
  elif command -v docker-compose > /dev/null; then
    COMPOSE_COMMAND="docker-compose"
  else
    echo -e "\e[31mNeither docker compose nor docker-compose found\e[0m"
    exit 1
  fi
  RESTIC_IMAGE=$(grep -oE 'ghcr.io/mailcow/restic:[^[:space:]"]+' "${MAILCOW_DIR}/docker-compose.yml" | head -n 1)
  if [[ -z ${RESTIC_IMAGE} ]]; then
    echo -e "\e[31mCould not find the restic image in ${MAILCOW_DIR}/docker-compose.yml\e[0m"
    exit 1
  fi
  # schema of this checkout, DDMMYYYY_HHMM
  CHECKOUT_DB_VERSION=$(grep -oE 'DB_VERSION=[0-9]{8}_[0-9]{4}' "${MAILCOW_DIR}/docker-compose.yml" | head -n 1 | cut -d= -f2)
  # a local repository folder: absolute, or ./relative to the mailcow directory; empty: the volume restic-repo-vol-1
  RESTIC_LOCAL_DIR=${RESTIC_LOCAL_PATH}
  if [[ -n ${RESTIC_LOCAL_DIR} ]]; then
    if [[ ! ${RESTIC_LOCAL_DIR} =~ ^(/|\./) || /${RESTIC_LOCAL_DIR}/ == */../* ]]; then
      echo -e "\e[31mRESTIC_LOCAL_PATH must be an absolute path (or ./relative to the mailcow directory) without ..\e[0m"
      exit 1
    fi
    [[ ${RESTIC_LOCAL_DIR} =~ ^/ ]] || RESTIC_LOCAL_DIR="${MAILCOW_DIR}/${RESTIC_LOCAL_DIR#./}"
  fi
  # repository settings with secrets: only for this run, readable by root only
  RESTIC_TMP=$(mktemp -d)
  chmod 700 "${RESTIC_TMP}"
  trap 'rm -rf "${RESTIC_TMP}" ${RESTIC_SH_COPY:+"${RESTIC_SH_COPY}"}' EXIT
  RESTIC_ENV_FILE="${RESTIC_TMP}/restic.env"
  touch "${RESTIC_ENV_FILE}"
  chmod 600 "${RESTIC_ENV_FILE}"
}

function mysql_container() {
  docker ps -qf name=mysql-mailcow -f label=com.docker.compose.project=${CMPS_PRJ} | head -n 1
}

# Repository settings: from the environment, else from the backup settings in the database
function repository_env() {
  local cache=
  [[ -n ${CMPS_PRJ} ]] && cache=$(docker volume ls -qf name=^${CMPS_PRJ}_restic-vol-1$)
  if [[ -z ${RESTIC_REPOSITORY} ]]; then
    if [[ -n ${CMPS_PRJ} && -n $(mysql_container) ]]; then
      # the runner code builds repository, options and credentials from backup_setting (secrets never in argv)
      DBPASS="${DBPASS}" docker run --rm --security-opt label=disable -e DBNAME=${DBNAME} -e DBUSER=${DBUSER} -e DBPASS -e MAILCOW_HOSTNAME=${MAILCOW_HOSTNAME} \
        -v ${CMPS_PRJ}_mysql-socket-vol-1:/var/run/mysqld/ ${cache:+-v ${cache}:/cache} \
        --entrypoint /usr/bin/python3 ${RESTIC_IMAGE} -c "
import json, sys
sys.path.insert(0, '/usr/local/bin')
import restic_runner as rr
try:
  s = rr.settings()
  repo = rr.Repository(s)
  repo.prepare(s)
except Exception as e:
  sys.exit('%s' % e)
for key, value in sorted(repo.env.items()):
  if key.startswith(('RESTIC_', 'AWS_')) and '\n' not in value:
    print('%s=%s' % (key, value))
print('MAILCOW_RESTIC_OPTS=' + json.dumps(repo.options))" > "${RESTIC_ENV_FILE}" 2> "${RESTIC_TMP}/error"
      if [[ $? -eq 0 ]]; then
        echo "Using the repository of the backup settings in the mailcow database."
        return 0
      fi
      echo -e "\e[33mThe backup settings in the database cannot be used: $(cat "${RESTIC_TMP}/error")\e[0m"
    fi
    echo "Pass the repository in the environment (see the top of this script), e.g. RESTIC_REPOSITORY=sftp:user@host:/path"
    read -ep "Repository: " RESTIC_REPOSITORY
    [[ -z ${RESTIC_REPOSITORY} ]] && exit 1
  fi
  if [[ -z ${RESTIC_PASSWORD} ]]; then
    IFS= read -rsp "Repository password: " RESTIC_PASSWORD
    echo
  fi
  local opts=()
  read -ra opts <<< "${RESTIC_OPTIONS}"
  if [[ -n ${RESTIC_SSH_KEY} ]]; then
    if [[ ! -f ${RESTIC_SSH_KEY} ]]; then
      echo -e "\e[31m${RESTIC_SSH_KEY} not found\e[0m"
      exit 1
    fi
    RESTIC_SSH_KEY=$(readlink -f "${RESTIC_SSH_KEY}")
    opts+=(-o "sftp.args=-i /run/ssh_key -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new -o UserKnownHostsFile=/tmp/known_hosts")
  fi
  {
    for var in RESTIC_REPOSITORY RESTIC_PASSWORD AWS_ACCESS_KEY_ID AWS_SECRET_ACCESS_KEY AWS_DEFAULT_REGION RESTIC_REST_USERNAME RESTIC_REST_PASSWORD; do
      [[ -n ${!var} ]] && echo "${var}=${!var}"
    done
    echo "RESTIC_CACHE_DIR=/cache/restic"
    printf 'MAILCOW_RESTIC_OPTS=['
    local sep=
    for opt in "${opts[@]}"; do
      printf '%s"%s"' "${sep}" "$(echo "${opt}" | sed 's/\\/\\\\/g; s/"/\\"/g')"
      sep=,
    done
    echo ']'
  } > "${RESTIC_ENV_FILE}"
}

function snapshots() {
  restic_run -- snapshots
}

# The newest snapshot made by mailcow, or the full id of the given one
function snapshot_id() {
  if [[ -z ${1} ]]; then
    set -- --tag mailcow latest
  fi
  restic_run -- snapshots --json "$@" | grep -oE '"id":"[0-9a-f]{64}"' | head -n 1 | cut -d'"' -f4
}

# The manifest of a snapshot into MANIFEST[...] (hostname, db_version, arch, instance, domains, mode, commit, branch,
# version, changed_count) and MANIFEST_CHANGED (locally changed files of the mailcow directory)
function read_manifest() {
  local key value
  MANIFEST=()
  MANIFEST_CHANGED=()
  while IFS=$'\t' read -r key value; do
    if [[ ${key} == "changed" ]]; then
      MANIFEST_CHANGED+=("${value}")
    elif [[ -n ${key} ]]; then
      MANIFEST[${key}]=${value}
    fi
  done < <(restic_run -- dump ${1} /mailcow/manifest.json 2> /dev/null | docker run --rm -i --security-opt label=disable --entrypoint /usr/bin/python3 ${RESTIC_IMAGE} -c '
import json, sys
try:
  m = json.load(sys.stdin)
except ValueError:
  sys.exit(0)
i = m.get("install") or {}
clean = lambda v: " ".join(str(v).split())
for key, value in (("hostname", m.get("hostname")), ("db_version", m.get("db_version")), ("arch", m.get("arch")),
    ("instance", m.get("instance")), ("domains", len(m.get("domains") or [])), ("mode", m.get("mode") or "all"), ("commit", i.get("commit")),
    ("branch", i.get("branch")), ("version", i.get("version")), ("changed_count", i.get("changed_count")),
    ("install", int(bool(i)))):
  if value not in (None, ""):
    print("%s\t%s" % (key, clean(value)))
for line in i.get("changed") or []:
  print("changed\t%s" % clean(line))')
}

function show_manifest() {
  echo "Snapshot of ${MANIFEST[hostname]}: database schema ${MANIFEST[db_version]}, ${MANIFEST[domains]:-0} domains"
  [[ ${MANIFEST[mode]} != "all" ]] && echo "Backup mode ${MANIFEST[mode]}: $(mode_text ${MANIFEST[mode]})"
  if [[ -n ${MANIFEST[commit]} ]]; then
    echo "mailcow ${MANIFEST[version]:-?}, branch ${MANIFEST[branch]:-?}, commit ${MANIFEST[commit]:0:12}"
    if [[ ${MANIFEST[changed_count]:-0} -gt 0 ]]; then
      echo "Locally changed or added files (${MANIFEST[changed_count]}):"
      printf '  %s\n' "${MANIFEST_CHANGED[@]:0:30}"
      [[ ${MANIFEST[changed_count]} -gt 30 ]] && echo "  ..."
    fi
  fi
}

function mode_text() {
  case ${1} in
    data) echo "of the mailcow directory only mailcow.conf" ;;
    config) echo "without mails, mail index, Rspamd and SOGo data" ;;
    *) echo "complete" ;;
  esac
}

# DDMMYYYY_HHMM as YYYYMMDDHHMM
function version_key() {
  [[ ${1} =~ ^([0-9]{2})([0-9]{2})([0-9]{4})_([0-9]{4})$ ]] && echo "${BASH_REMATCH[3]}${BASH_REMATCH[2]}${BASH_REMATCH[1]}${BASH_REMATCH[4]}"
}

# The dump of the snapshot is complete (read through, no database needed)
function check_dump() {
  echo "Checking the database dump of the snapshot..."
  restic_run -- dump ${1} /mailcow/mysql/mailcow.sql | tail -c 300 | grep -q "Dump completed"
  if [[ ${PIPESTATUS[0]} -ne 0 || ${PIPESTATUS[2]} -ne 0 ]]; then
    echo -e "\e[31mThe database dump of the snapshot could not be read or is incomplete, nothing was changed\e[0m"
    return 1
  fi
}

# Instance id of the backup container of a project on this server (empty on a new server)
function local_instance() {
  local volume=$(docker volume ls -qf name=^${1}_restic-vol-1$)
  [[ -n ${volume} ]] && docker run --rm --security-opt label=disable -v ${volume}:/cache:ro --entrypoint cat ${RESTIC_IMAGE} /cache/instance_id 2> /dev/null
}

# Scheduled backups go on only on the server that made the snapshot: two servers must not back up into one repository
function schedule_decision() {
  local here=$(local_instance ${1})
  if [[ -n ${MANIFEST[instance]} && ${here} == "${MANIFEST[instance]}" ]]; then
    KEEP_SCHEDULE=y
    SCHEDULE_NOTE="The snapshot was made by this server, the backup schedule stays as it was in the snapshot."
  else
    KEEP_SCHEDULE=n
    SCHEDULE_NOTE="The snapshot was made by another mailcow (or this one lost its backup volume): scheduled backups are switched off, so two servers do not back up into the same repository. Switch them on again in the backup settings when the old server no longer backs up."
  fi
}

function restore_volumes() {
  local snapshot=${1} manifest_arch=${2} includes=() mounts=() restored=() path volume json paths
  # the backed up paths of the snapshot, one per line; without them --delete below would act on the whole target /
  if ! json=$(restic_run -- snapshots --json ${snapshot}); then
    echo -e "\e[31mThe paths of the snapshot could not be read\e[0m"
    return 1
  fi
  paths=$(grep -oE '"paths":\[[^]]*\]' <<< "${json}" | grep -oE '"/[^"]*"' | tr -d '"')
  if [[ -z ${paths} ]]; then
    echo -e "\e[31mThe snapshot lists no paths\e[0m"
    return 1
  fi
  for path in "${!SNAPSHOT_VOLUMES[@]}"; do
    volume=$(docker volume ls -qf name=^${CMPS_PRJ}_${SNAPSHOT_VOLUMES[${path}]}$)
    if [[ -z ${volume} ]]; then
      echo -e "\e[31mVolume ${CMPS_PRJ}_${SNAPSHOT_VOLUMES[${path}]} not found\e[0m"
      return 1
    fi
    if ! grep -qx "${path}" <<< "${paths}"; then
      if [[ ${path} == "/var/vmail_index" ]] && grep -qx /var/vmail <<< "${paths}"; then
        echo "The snapshot has no mail index, emptying the index volume (Dovecot rebuilds it)..."
        docker run --rm --security-opt label=disable -v ${volume}:/clean --entrypoint /bin/sh ${RESTIC_IMAGE} -c 'find /clean -mindepth 1 -delete'
        NO_INDEX=y
      else
        echo -e "\e[33m${path} is not in the snapshot, ${volume} is left as it is\e[0m"
      fi
      continue
    fi
    if [[ ${path} == "/rspamd" && -n ${manifest_arch} && ${manifest_arch} != $(uname -m) ]]; then
      echo -e "\e[33mThe snapshot was made on ${manifest_arch}, this is $(uname -m): Rspamd data is skipped\e[0m"
      continue
    fi
    includes+=(--include ${path})
    mounts+=(-v ${volume}:${path})
    restored+=(${path})
  done
  if [[ ${#includes[@]} -eq 0 ]]; then
    echo -e "\e[31mNone of the volumes is in the snapshot\e[0m"
    return 1
  fi
  echo "Restoring the volumes (${restored[*]})..."
  # --delete: the volumes get exactly the content of the snapshot
  restic_run "${mounts[@]}" -- restore ${snapshot} --target / --delete "${includes[@]}"
}

# Copies the dump of the snapshot into the database container (a running MariaDB is waited for)
function fetch_dump() {
  local snapshot=${1} mysql=${MYSQL_CONTAINER:-$(mysql_container)}
  local i=0
  # over TCP: MariaDB initializing a new volume listens only on its socket
  until MYSQL_PWD="${DBROOT}" docker exec -e MYSQL_PWD ${mysql} mariadb -uroot -h 127.0.0.1 --protocol=TCP -e 'SELECT 1' > /dev/null 2>&1; do
    if [[ ${i} -ge 150 ]]; then
      echo -e "\e[31mThe database does not answer\e[0m"
      return 1
    fi
    sleep 2
    i=$((i + 1))
  done
  echo "Reading the database dump from the snapshot..."
  # the client misreads long lines from a pipe: the dump goes to a file in the container first
  restic_run -- dump ${snapshot} /mailcow/mysql/mailcow.sql | docker exec -i ${mysql} sh -c 'umask 077; cat > /tmp/mailcow-restore.sql'
  if [[ ${PIPESTATUS[0]} -ne 0 || ${PIPESTATUS[1]} -ne 0 ]] \
    || ! docker exec ${mysql} sh -c 'tail -c 300 /tmp/mailcow-restore.sql | grep -q "Dump completed"'; then
    docker exec ${mysql} rm -f /tmp/mailcow-restore.sql
    echo -e "\e[31mThe database dump of the snapshot could not be read or is incomplete\e[0m"
    return 1
  fi
}

function restore_database() {
  local mysql=${MYSQL_CONTAINER:-$(mysql_container)}
  echo "Importing the database dump into ${DBNAME}..."
  MYSQL_PWD="${DBROOT}" docker exec -i -e MYSQL_PWD ${mysql} mariadb -uroot \
    -e "DROP DATABASE IF EXISTS \`${DBNAME}\`; CREATE DATABASE \`${DBNAME}\`;" || return 1
  MYSQL_PWD="${DBROOT}" docker exec -e MYSQL_PWD ${mysql} sh -c 'mariadb -uroot "${1}" < /tmp/mailcow-restore.sql; rc=$?; rm -f /tmp/mailcow-restore.sql; exit ${rc}' sh ${DBNAME}
  if [[ $? -ne 0 ]]; then
    echo -e "\e[31mThe database import failed\e[0m"
    return 1
  fi
  # jobs that were queued or running when the snapshot was made must not run again
  local sql="UPDATE backup_job SET state = 'failed', error = 'interrupted: restored from a snapshot', finished = NOW() WHERE state IN ('queued', 'running');"
  # the schedule counts from now: no catch-up backup of the days since the snapshot
  sql+=" INSERT INTO backup_setting (name, value) VALUES ('state_schedule_since', UNIX_TIMESTAMP()) ON DUPLICATE KEY UPDATE value = VALUES(value);"
  [[ ${KEEP_SCHEDULE} != "y" ]] && sql+=" UPDATE backup_setting SET value = '0' WHERE name = 'enabled';"
  MYSQL_PWD="${DBROOT}" docker exec -i -e MYSQL_PWD ${mysql} mariadb -uroot ${DBNAME} -e "${sql}"
}

# data/conf (in place, or into a side folder when the snapshot is of another mailcow version) and data/assets/ssl
function restore_config() {
  local snapshot=${1} conf_dir=${2}
  if [[ ${conf_dir} == "${MAILCOW_DIR}/data/conf" ]]; then
    echo "Restoring data/conf and data/assets/ssl (files of the snapshot overwrite the current ones, newer files stay)..."
  else
    echo "Restoring data/conf into ${conf_dir} and data/assets/ssl..."
    mkdir -p "${conf_dir}"
  fi
  restic_run -v "${conf_dir}":/mailcow/install/data/conf -v "${MAILCOW_DIR}/data/assets/ssl":/mailcow/install/data/assets/ssl -- \
    restore ${snapshot} --target / --include /mailcow/install/data/conf --include /mailcow/install/data/assets/ssl
}

# mailcow.conf of the snapshot; database credentials and project name stay those of this installation
function restore_mailcow_conf() {
  local snapshot=${1} backup="${MAILCOW_DIR}/mailcow.conf.before-restore-$(date +%Y%m%d-%H%M%S)" var
  restic_run -- dump ${snapshot} /mailcow/install/mailcow.conf > "${RESTIC_TMP}/mailcow.conf" || return 1
  for var in DBNAME DBUSER DBPASS DBROOT COMPOSE_PROJECT_NAME; do
    if grep -q "^${var}=" "${RESTIC_TMP}/mailcow.conf"; then
      VALUE="${!var}" awk -v name="${var}" 'index($0, name "=") == 1 { print name "=" ENVIRON["VALUE"]; next } { print }' \
        "${RESTIC_TMP}/mailcow.conf" > "${RESTIC_TMP}/mailcow.conf.new" && mv "${RESTIC_TMP}/mailcow.conf.new" "${RESTIC_TMP}/mailcow.conf"
    fi
  done
  cp -p "${MAILCOW_DIR}/mailcow.conf" "${backup}"
  cat "${RESTIC_TMP}/mailcow.conf" > "${MAILCOW_DIR}/mailcow.conf"
  echo "mailcow.conf restored (DBNAME, DBUSER, DBPASS, DBROOT and COMPOSE_PROJECT_NAME kept), the old one is ${backup}"
}

# Project name in the mailcow.conf of a snapshot
function snapshot_project() {
  project_name "$(restic_run -- dump ${1} /mailcow/install/mailcow.conf 2> /dev/null | grep -m 1 '^COMPOSE_PROJECT_NAME=' | cut -d= -f2-)"
}

# Stops the containers of a project (graceful, no volumes are removed)
function stop_project() {
  local ids=$(docker ps -aq --filter label=com.docker.compose.project=${1})
  [[ -z ${ids} ]] && return 0
  echo "Stopping the containers of ${1}..."
  docker stop -t 60 ${ids} > /dev/null
  docker rm ${ids} > /dev/null
}

# After the directory was restored: its mailcow.conf (project, database credentials) and compose files
function load_restored_config() {
  if [[ ! -f ${MAILCOW_DIR}/mailcow.conf ]]; then
    echo -e "\e[31mThe restored directory has no mailcow.conf\e[0m"
    return 1
  fi
  source "${MAILCOW_DIR}/mailcow.conf"
  CMPS_PRJ=$(project_name "${COMPOSE_PROJECT_NAME}")
}

function failed_after_move() {
  echo -e "\e[31mThe restore stopped. The previous mailcow directory is ${ASIDE_DIR}.\e[0m"
  echo "To go back to it: stop the stack, remove ${MAILCOW_DIR}, move ${ASIDE_DIR} back to ${MAILCOW_DIR}"
  echo "and start it with docker compose up -d (data volumes restored so far stay restored)."
  if [[ -n ${OLD_DUMP} ]]; then
    echo "The database as it was before the restore: ${ASIDE_DIR}/${OLD_DUMP} (import it with mariadb into ${DBNAME:-the database})."
  elif [[ ${DB_REMOVED} == "y" ]]; then
    echo "The database volume was recreated and the database before the restore was not saved: it is gone."
  fi
  exit 1
}

function failed_data_only() {
  echo -e "\e[31mThe restore stopped, mailcow is stopped.\e[0m"
  echo "Fix the problem shown above and run the restore again, or start mailcow as it is with"
  echo "  ${COMPOSE_COMMAND} up -d"
  echo "(volumes and database restored so far stay restored)."
  if [[ -n ${OLD_DUMP} ]]; then
    echo "The database as it was before the restore: ${MAILCOW_DIR}/${OLD_DUMP} (import it with mariadb into ${DBNAME:-the database})."
  fi
  exit 1
}

# The mailcow directory is renamed for a full restore: not possible for a mount point or without write access to its parent
function movable() {
  ! awk -v dir="${1}" '$5 == dir { found = 1 } END { exit !found }' /proc/self/mountinfo && [[ -w $(dirname "${1}") ]]
}

# The snapshots made by mailcow; asks which one to restore (Enter: the newest)
function choose_snapshot() {
  local latest choice
  restic_run -- snapshots --tag mailcow --compact >&2
  latest=$(snapshot_id)
  [[ -z ${latest} ]] && return 1
  read -ep "Snapshot to restore (ID, Enter for the newest one, ${latest:0:8}): " choice >&2
  if [[ -z ${choice} ]]; then
    echo "${latest}"
  elif [[ ${choice} =~ ^[0-9a-f]{8,64}$ ]]; then
    snapshot_id "${choice}"
  fi
}

# The database before a restore, into the mailcow directory (a full restore moves it aside; needs the running database)
function dump_current_database() {
  local mysql=$(docker ps -qf name=mysql-mailcow -f label=com.docker.compose.project=${1} | head -n 1)
  if [[ -z ${mysql} || -z ${DBROOT} || -z ${DBNAME} ]]; then
    confirm "WARNING: the current database cannot be saved before the restore (MariaDB does not run).
The restore deletes it, there is no way back to it."
    return 0
  fi
  OLD_DUMP="mailcow-before-restore-$(date +%Y%m%d-%H%M%S).sql"
  echo "Saving the current database to ${OLD_DUMP}..."
  ( umask 077; MYSQL_PWD="${DBROOT}" docker exec -e MYSQL_PWD ${mysql} mariadb-dump -uroot --single-transaction --routines \
    --triggers --events --hex-blob "${DBNAME}" > "${MAILCOW_DIR}/${OLD_DUMP}" )
  if [[ $? -ne 0 ]]; then
    echo -e "\e[31mThe current database could not be saved, nothing was changed\e[0m"
    rm -f "${MAILCOW_DIR}/${OLD_DUMP}"
    exit 1
  fi
}

function confirm() {
  echo
  echo -e "\e[31m${1}\e[0m"
  echo
  read -p "Type 'yes' to restore: " CONFIRM
  [[ ${CONFIRM} == "yes" ]] || exit 1
}

# Full restore: the mailcow directory of the snapshot (version, configuration, local changes), its images, all data
# volumes and the database
function restore_full() {
  local snapshot snap_prj repo_rel= other
  if [[ -n ${1} ]]; then
    snapshot=$(snapshot_id ${1})
  else
    snapshot=$(choose_snapshot)
  fi
  if [[ -z ${snapshot} ]]; then
    echo -e "\e[31mSnapshot ${1:-latest} not found\e[0m"
    exit 1
  fi
  restic_run -- snapshots ${snapshot}
  read_manifest ${snapshot}
  if [[ -z ${MANIFEST[db_version]} ]]; then
    echo -e "\e[31mThe snapshot has no mailcow manifest, it was not made by the mailcow backup\e[0m"
    exit 1
  fi
  if [[ ${MANIFEST[mode]} != "all" ]]; then
    echo -e "\e[31mThe snapshot was made with RESTIC_BACKUP_MODE=${MANIFEST[mode]} ($(mode_text ${MANIFEST[mode]})), it has no complete"
    echo -e "mailcow directory. Install mailcow (git clone, generate_config.sh) and use: restore --data-only ${snapshot:0:8}\e[0m"
    exit 1
  fi
  snap_prj=$(snapshot_project ${snapshot})
  if [[ ${MANIFEST[install]} != "1" || -z ${snap_prj} ]]; then
    echo -e "\e[31mThe snapshot has no mailcow directory (made before full backups), use: restore --data-only ${snapshot:0:8}\e[0m"
    exit 1
  fi
  show_manifest
  # another installation of this project on this server (other directory) is not touched
  other=$(docker ps -a --filter label=com.docker.compose.project=${snap_prj} --format '{{.Label "com.docker.compose.project.working_dir"}}' \
    | sort -u | grep -vxF "${MAILCOW_DIR}" | head -n 1)
  if [[ -n ${other} ]]; then
    echo -e "\e[31mThe project ${snap_prj} of the snapshot runs from ${other}, not from ${MAILCOW_DIR}. Restore it there.\e[0m"
    exit 1
  fi
  schedule_decision ${snap_prj}
  ASIDE_DIR="${MAILCOW_DIR}.before-restore-$(date +%Y%m%d-%H%M)"
  [[ -e ${ASIDE_DIR} ]] && ASIDE_DIR="${ASIDE_DIR}$(date +%S)"
  if ! movable "${MAILCOW_DIR}"; then
    echo -e "\e[31m${MAILCOW_DIR} cannot be moved aside for the restore (it is a mount point or its parent folder is not writable)\e[0m"
    exit 1
  fi
  confirm "WARNING: this restores mailcow completely as it was in snapshot ${snapshot:0:8}: the mailcow directory
(version and configuration), all mails, the mail index and crypt keys, Redis, Rspamd, the SOGo backup files and the
database. ${MAILCOW_DIR} is moved to ${ASIDE_DIR}, the database volume is recreated.
Everything newer than the snapshot is lost. mailcow is stopped during the restore."
  check_dump ${snapshot} || exit 1

  # nothing was changed until here
  OLD_DUMP=
  [[ -n ${CMPS_PRJ} ]] && dump_current_database ${CMPS_PRJ}
  if [[ -n ${CMPS_PRJ} ]]; then
    echo "Stopping mailcow..."
    compose "${MAILCOW_DIR}" down
  fi
  stop_project ${snap_prj}
  echo "Moving ${MAILCOW_DIR} to ${ASIDE_DIR}..."
  mv "${MAILCOW_DIR}" "${ASIDE_DIR}" || exit 1
  mkdir "${MAILCOW_DIR}"
  chmod --reference="${ASIDE_DIR}" "${MAILCOW_DIR}"
  chown --reference="${ASIDE_DIR}" "${MAILCOW_DIR}"
  # a local repository inside the directory moved with it
  if [[ ${RESTIC_LOCAL_DIR} == "${MAILCOW_DIR}"/* ]]; then
    repo_rel=${RESTIC_LOCAL_DIR#${MAILCOW_DIR}/}
    RESTIC_LOCAL_DIR="${ASIDE_DIR}/${repo_rel}"
  fi
  echo "Restoring the mailcow directory..."
  restic_run -v "${MAILCOW_DIR}":/mailcow/install -- restore ${snapshot} --target / --include /mailcow/install || failed_after_move
  if [[ -n ${repo_rel} ]]; then
    mkdir -p "$(dirname "${MAILCOW_DIR}/${repo_rel}")"
    rmdir "${MAILCOW_DIR}/${repo_rel}" 2> /dev/null
    mv "${ASIDE_DIR}/${repo_rel}" "${MAILCOW_DIR}/${repo_rel}" && RESTIC_LOCAL_DIR="${MAILCOW_DIR}/${repo_rel}"
  fi
  load_restored_config || failed_after_move

  echo "Fetching the images of the restored version..."
  compose "${MAILCOW_DIR}" pull || failed_after_move
  echo "Creating volumes and containers, the database volume is recreated..."
  compose "${MAILCOW_DIR}" down
  docker volume rm ${CMPS_PRJ}_mysql-vol-1 > /dev/null 2>&1 && DB_REMOVED=y
  compose "${MAILCOW_DIR}" up --no-start || failed_after_move
  restore_volumes ${snapshot} "${MANIFEST[arch]}" || failed_after_move
  echo "Starting the database..."
  compose "${MAILCOW_DIR}" up -d mysql-mailcow || failed_after_move
  fetch_dump ${snapshot} || failed_after_move
  restore_database || failed_after_move
  echo "Starting mailcow..."
  compose "${MAILCOW_DIR}" up -d || failed_after_move

  echo
  echo -e "\e[32mRestore of snapshot ${snapshot:0:8} finished.\e[0m"
  echo "Next steps:"
  echo "- ${SCHEDULE_NOTE}"
  echo "- The previous mailcow directory is ${ASIDE_DIR}, remove it when everything works."
  echo "- New server or new IP address: update the DNS records (A/AAAA of ${MAILCOW_HOSTNAME}, MX, SPF, PTR)."
  echo "- SFTP target on a new server: the backup creates its own SSH key, add its public key (backup settings) to the"
  echo "  SFTP server, or copy the old key into the volume ${CMPS_PRJ}_restic-vol-1 (ssh/id_ed25519)."
  if [[ ${NO_INDEX} == "y" ]]; then
    echo "- The snapshot has no mail index: with full text search enabled, rebuild it:"
  else
    echo "- If the full text search index was excluded from the backups, rebuild it:"
  fi
  echo "    ${COMPOSE_COMMAND} exec dovecot-mailcow doveadm fts rescan -A"
  echo "- If a mailbox shows wrong content, resync it; quotas:"
  echo "    ${COMPOSE_COMMAND} exec dovecot-mailcow doveadm force-resync -A '*'"
  echo "    ${COMPOSE_COMMAND} exec dovecot-mailcow doveadm quota recalc -A"
}

# Data only: volumes and database into this mailcow (same or newer version), optionally data/conf and mailcow.conf
function restore_data_only() {
  local snapshot conf_dir
  if [[ -z ${CMPS_PRJ} ]]; then
    echo -e "\e[31mmailcow.conf not found: a data-only restore needs an installed mailcow (or use a full restore)\e[0m"
    exit 1
  fi
  if [[ -n ${1} ]]; then
    snapshot=$(snapshot_id ${1})
  else
    snapshot=$(choose_snapshot)
  fi
  if [[ -z ${snapshot} ]]; then
    echo -e "\e[31mSnapshot ${1:-latest} not found\e[0m"
    exit 1
  fi
  restic_run -- snapshots ${snapshot}
  read_manifest ${snapshot}
  if [[ -z ${MANIFEST[db_version]} ]]; then
    echo -e "\e[31mThe snapshot has no mailcow manifest, it was not made by the mailcow backup\e[0m"
    exit 1
  fi
  show_manifest
  if [[ -z $(version_key "${MANIFEST[db_version]}") || -z $(version_key "${CHECKOUT_DB_VERSION}") ]]; then
    echo -e "\e[31mThe database schema of the snapshot (${MANIFEST[db_version]}) or of this checkout (${CHECKOUT_DB_VERSION}) is unknown\e[0m"
    exit 1
  fi
  if [[ $(version_key "${MANIFEST[db_version]}") > $(version_key "${CHECKOUT_DB_VERSION}") ]]; then
    echo -e "\e[31mThe snapshot was made with a newer mailcow (database schema ${MANIFEST[db_version]}, this checkout has ${CHECKOUT_DB_VERSION})."
    echo -e "Update this mailcow first (./update.sh) or use a full restore.\e[0m"
    exit 1
  fi
  schedule_decision ${CMPS_PRJ}
  if [[ ${MANIFEST[mode]} == "config" ]]; then
    confirm "WARNING: this replaces the mail crypt keys, Redis and the whole database of this mailcow with the content of
snapshot ${snapshot:0:8}. The snapshot holds no mails: mails, mail index, Rspamd and SOGo data stay as they are.
Everything newer than the snapshot is lost. mailcow is stopped during the restore."
  else
    confirm "WARNING: this replaces all mails, the mail index, the mail crypt keys, Redis, Rspamd and SOGo backup data
and the whole database of this mailcow with the content of snapshot ${snapshot:0:8}.
Everything newer than the snapshot is lost. mailcow is stopped during the restore."
  fi
  RESTORE_CONF=
  if [[ ${MANIFEST[install]} == "1" ]]; then
    # data snapshots have only mailcow.conf of the mailcow directory
    if [[ ${MANIFEST[mode]} != "data" ]]; then
      conf_dir="${MAILCOW_DIR}/data/conf"
      if [[ ${MANIFEST[db_version]} == "${CHECKOUT_DB_VERSION}" ]]; then
        read -p "Also restore data/conf and data/assets/ssl from the snapshot? [y|N] " RESTORE_CONF
      else
        # the configuration of another mailcow version may not fit this checkout
        conf_dir="${MAILCOW_DIR}/data/conf.from-snapshot-$(date +%Y%m%d-%H%M%S)"
        echo "The snapshot was made with another mailcow version, its data/conf is restored into ${conf_dir} to compare."
        read -p "Restore data/conf into that folder and data/assets/ssl? [y|N] " RESTORE_CONF
      fi
    fi
    read -p "Also restore mailcow.conf from the snapshot (the current one is kept as a copy)? [y|N] " RESTORE_MAILCOW_CONF
  fi
  check_dump ${snapshot} || exit 1
  OLD_DUMP=
  dump_current_database ${CMPS_PRJ}

  echo "Stopping mailcow..."
  compose "${MAILCOW_DIR}" down
  echo "Starting the database..."
  compose "${MAILCOW_DIR}" up -d mysql-mailcow
  if ! fetch_dump ${snapshot}; then
    [[ -n ${OLD_DUMP} ]] && rm -f "${MAILCOW_DIR}/${OLD_DUMP}"
    echo "Nothing was changed, starting mailcow again..."
    compose "${MAILCOW_DIR}" up -d
    exit 1
  fi
  restore_volumes ${snapshot} "${MANIFEST[arch]}" || failed_data_only
  restore_database || failed_data_only
  [[ ${RESTORE_CONF,,} =~ ^(yes|y)$ ]] && { restore_config ${snapshot} "${conf_dir}" || failed_data_only; }
  [[ ${RESTORE_MAILCOW_CONF,,} =~ ^(yes|y)$ ]] && { restore_mailcow_conf ${snapshot} || failed_data_only; }

  echo "Starting mailcow..."
  compose "${MAILCOW_DIR}" up -d
  echo
  echo -e "\e[32mRestore of snapshot ${snapshot:0:8} finished.\e[0m"
  echo "Next steps:"
  echo "- ${SCHEDULE_NOTE}"
  echo "- When Dovecot runs, check the mailboxes. If a mailbox shows wrong content, resync it, and recalculate the quotas:"
  echo "    ${COMPOSE_COMMAND} exec dovecot-mailcow doveadm force-resync -A '*'"
  echo "    ${COMPOSE_COMMAND} exec dovecot-mailcow doveadm quota recalc -A"
  if [[ ${NO_INDEX} == "y" ]]; then
    echo "- The snapshot has no mail index: with full text search enabled, rebuild it:"
  else
    echo "- If the full text search index was excluded from the backups, rebuild it:"
  fi
  echo "    ${COMPOSE_COMMAND} exec dovecot-mailcow doveadm fts rescan -A"
  echo "- Run an \"init\" job in the backup settings, it refreshes the list of snapshots."
  if [[ -n ${OLD_DUMP} ]]; then
    echo "- The database before the restore is ${MAILCOW_DIR}/${OLD_DUMP}, delete it when you no longer need it."
  fi
  if [[ ${conf_dir} != "${MAILCOW_DIR}/data/conf" && ${RESTORE_CONF,,} =~ ^(yes|y)$ ]]; then
    echo "- Compare ${conf_dir} with data/conf and take over what you need."
  fi
}

if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
  # restore [--data-only] [id], the option before or after the id
  if [[ ${1} == "restore" ]]; then
    SNAPSHOT_ARG=
    for arg in "${@:2}"; do
      if [[ ${arg} == "--data-only" ]]; then
        MODE=data
      elif [[ -z ${SNAPSHOT_ARG} && ${arg} =~ ^[0-9a-f]{8,64}$ ]]; then
        SNAPSHOT_ARG=${arg}
      else
        echo "Unknown argument: ${arg}"
        exit 1
      fi
    done
    set -- restore "${SNAPSHOT_ARG}"
  fi
  if [[ ! ${1} =~ ^(snapshots|restore)$ ]]; then
    echo "Usage: ${0##*/} snapshots | restore [snapshot id] | restore --data-only [snapshot id]"
    exit 1
  fi
  load_config
  repository_env
  case ${1} in
    snapshots) snapshots ;;
    restore)
      if [[ ${MODE} == "data" ]]; then
        restore_data_only ${2}
      else
        restore_full ${2}
      fi
    ;;
  esac
fi
