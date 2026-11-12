#!/usr/bin/env bash

PATH=${PATH}:/opt/bin
DATE=$(date +%Y-%m-%d_%H_%M_%S)
LOCAL_ARCH=$(uname -m)
export LC_ALL=C

REMOTE_SSH_USER="${REMOTE_SSH_USER:-root}"
REMOTE_SSH_PORT="${REMOTE_SSH_PORT:-22}"
REMOTE_SSH_STRICT_HOST_KEY_CHECKING="${REMOTE_SSH_STRICT_HOST_KEY_CHECKING:-no}"

echo
echo "If this script is run automatically by cron or a timer AND you are using block-level snapshots on your backup destination, make sure both do not run at the same time."
echo "The snapshots of your backup destination should run AFTER the cold standby script finished to ensure consistent snapshots."
echo

function remote_shell() {
  ssh -o "StrictHostKeyChecking=${REMOTE_SSH_STRICT_HOST_KEY_CHECKING}" \
    -i "${REMOTE_SSH_KEY}" \
    -p "${REMOTE_SSH_PORT}" \
    -l "${REMOTE_SSH_USER}" \
    -- "${REMOTE_SSH_HOST}" \
    "$1"
}

function remote_privileged_shell() {
  local command="$1"
  local escaped_command

  if [[ "${REMOTE_SSH_USER}" == "root" ]]; then
    remote_shell "${command}"
    return
  fi

  printf -v escaped_command '%q' "${command}"
  remote_shell "sudo -n bash -lc ${escaped_command}"
}

function remote_rsync_path() {
  if [[ "${REMOTE_SSH_USER}" == "root" ]]; then
    printf 'rsync'
  else
    printf 'sudo -n rsync'
  fi
}

function remote_rsync_shell() {
  printf 'ssh -o %q -i %q -p %q -l %q' \
    "StrictHostKeyChecking=${REMOTE_SSH_STRICT_HOST_KEY_CHECKING}" \
    "${REMOTE_SSH_KEY}" \
    "${REMOTE_SSH_PORT}" \
    "${REMOTE_SSH_USER}"
}

function remote_rsync_target() {
  printf '%s:%s' "${REMOTE_SSH_HOST}" "$1"
}

function preflight_local_checks() {
  if [[ -z "${REMOTE_SSH_KEY:-}" ]]; then
    >&2 echo -e "\e[31mREMOTE_SSH_KEY is not set\e[0m"
    exit 1
  fi

  if [[ ! -s "${REMOTE_SSH_KEY}" ]]; then
    >&2 echo -e "\e[31mKeyfile ${REMOTE_SSH_KEY} is empty\e[0m"
    exit 1
  fi

  if [[ $(stat -c "%a" "${REMOTE_SSH_KEY}") -ne 600 ]]; then
    >&2 echo -e "\e[31mKeyfile ${REMOTE_SSH_KEY} has insecure permissions\e[0m"
    exit 1
  fi

  if [[ ! "${REMOTE_SSH_PORT}" =~ ^[0-9]+$ ]] \
    || [[ "${REMOTE_SSH_PORT}" -lt 1 ]] \
    || [[ "${REMOTE_SSH_PORT}" -gt 65535 ]]; then
    >&2 echo -e "\e[31mREMOTE_SSH_PORT must be an integer from 1 through 65535\e[0m"
    exit 1
  fi

  if [[ -z "${REMOTE_SSH_HOST:-}" ]]; then
    >&2 echo -e "\e[31mREMOTE_SSH_HOST cannot be empty\e[0m"
    exit 1
  fi

  if [[ -z "${REMOTE_SSH_USER}" ]] \
    || [[ "${REMOTE_SSH_USER}" =~ [[:space:][:cntrl:]] ]]; then
    >&2 echo -e "\e[31mREMOTE_SSH_USER cannot be empty or contain whitespace/control characters\e[0m"
    exit 1
  fi

  for bin in rsync docker grep cut ssh; do
    if ! command -v "${bin}" >/dev/null 2>&1; then
      >&2 echo -e "\e[31mCannot find ${bin} in local PATH, exiting...\e[0m"
      exit 1
    fi
  done

  if grep --help 2>&1 | head -n 1 | grep -q -i "busybox"; then
    echo -e "\e[31mBusyBox grep detected on local system, please install GNU grep\e[0m"
    exit 1
  fi
}

function preflight_remote_checks() {

  if ! remote_shell "rsync --version >/dev/null"; then
      >&2 echo -e "\e[31mCould not verify connection to ${REMOTE_SSH_HOST}\e[0m"
      >&2 echo -e "\e[31mPlease check the output above (is rsync >= 3.1.0 installed on the remote system?)\e[0m"
      exit 1
  fi

  if remote_shell "grep --help 2>&1" | head -n 1 | grep -q -i "busybox"; then
      >&2 echo -e "\e[31mBusyBox grep detected on remote system ${REMOTE_SSH_HOST}, please install GNU grep\e[0m"
      exit 1
  fi

  if [[ "${REMOTE_SSH_USER}" != "root" ]] && ! remote_shell "sudo -n true"; then
    >&2 echo -e "\e[31mRemote SSH user ${REMOTE_SSH_USER} requires root-equivalent non-interactive sudo for migration operations\e[0m"
    exit 1
  fi

  for bin in rsync docker; do
    if ! remote_privileged_shell "command -v ${bin} >/dev/null"; then
        >&2 echo -e "\e[31mCannot find ${bin} in remote PATH, exiting...\e[0m"
        exit 1
    fi
  done

  if remote_privileged_shell "docker compose version >/dev/null 2>&1"; then
    COMPOSE_COMMAND="docker compose"
    echo "INFO: Using native docker compose on remote"
  elif remote_privileged_shell "docker-compose version --short 2>/dev/null | grep -q '^2\\.'"; then
    COMPOSE_COMMAND="docker-compose"
    echo "INFO: Using standalone docker compose on remote"
  else
  echo -e "\e[31mCannot find any Docker Compose on remote, exiting...\e[0m"
  exit 1
  fi

  REMOTE_ARCH="$(remote_shell "uname -m")"
}

SCRIPT_DIR=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )
source "${SCRIPT_DIR}/../mailcow.conf"
COMPOSE_FILE="${SCRIPT_DIR}/../docker-compose.yml"
CMPS_PRJ=$(echo ${COMPOSE_PROJECT_NAME} | tr -cd '0-9A-Za-z-_')
SQLIMAGE=$(grep -iEo '(mysql|mariadb)\:.+' "${COMPOSE_FILE}")

preflight_local_checks
preflight_remote_checks

echo
echo -e "\033[1mFound compose project name ${CMPS_PRJ} for ${MAILCOW_HOSTNAME}\033[0m"
echo -e "\033[1mFound SQL ${SQLIMAGE}\033[0m"
echo

# Print Message if Local Arch and Remote Arch is not the same
if [[ $LOCAL_ARCH != $REMOTE_ARCH ]]; then
  echo
  echo -e "\e[1;33m!!!!!!!!!!!!!!!!!!!!!!!!!! CAUTION !!!!!!!!!!!!!!!!!!!!!!!!!!\e[0m"
  echo -e "\e[3;33mDetected Architecture missmatch from source to destination...\e[0m"
  echo -e "\e[3;33mYour backup is transferred but some volumes might be skipped!\e[0m"
  echo -e "\e[1;33m!!!!!!!!!!!!!!!!!!!!!!!!!! CAUTION !!!!!!!!!!!!!!!!!!!!!!!!!!\e[0m"
  echo
  sleep 2
fi

# Make sure destination exists, rsync can fail under some circumstances
echo -e "\033[1mPreparing remote...\033[0m"
if ! remote_privileged_shell "mkdir -p \"${SCRIPT_DIR}/../\""; then
    >&2 echo -e "\e[31m[ERR]\e[0m - Could not prepare remote for mailcow base directory transfer"
    exit 1
fi

# Syncing the mailcow base directory
echo -e "\033[1mSynchronizing mailcow base directory...\033[0m"
rsync --delete -aH -e "$(remote_rsync_shell)" \
  --rsync-path="$(remote_rsync_path)" \
  "${SCRIPT_DIR}/../" "$(remote_rsync_target "${SCRIPT_DIR}/../")"
ec=$?
if [ ${ec} -ne 0 ] && [ ${ec} -ne 24 ]; then
  >&2 echo -e "\e[31m[ERR]\e[0m - Could not transfer mailcow base directory to remote"
  exit 1
fi

# Let the remote side create all network, volumes and containers to prevent need for external:true #
echo -e "\e[33mCreating networks, volumes and containers on remote...\e[0m"

if ! remote_privileged_shell "cd \"${SCRIPT_DIR}/../\" && ${COMPOSE_COMMAND} create 2>&1"; then
    >&2 echo -e "\e[31m[ERR]\e[0m - Could not create networks, volumes and containers on remote"
fi

# Trigger a Redis save for a consistent Redis copy
echo -ne "\033[1mRunning redis-cli save... \033[0m"
docker exec $(docker ps -qf name=redis-mailcow) redis-cli -a ${REDISPASS} --no-auth-warning save

# Syncing volumes related to compose project
# Same here: make sure destination exists
for vol in $(docker volume ls -qf name="${CMPS_PRJ}"); do

  mountpoint="$(docker inspect ${vol} | grep Mountpoint | cut -d '"' -f4)"

  echo -e "\033[1mCreating remote mountpoint ${mountpoint} for ${vol}...\033[0m"

  remote_privileged_shell "mkdir -p \"${mountpoint}\""

  if [[ "${vol}" =~ "mysql-vol-1" ]]; then

    # Make sure a previous backup does not exist
    rm -rf "${SCRIPT_DIR}/../_tmp_mariabackup/"

    echo -e "\033[1mCreating consistent backup of MariaDB volume...\033[0m"
    if ! docker run --rm \
      --network $(docker network ls -qf name=${CMPS_PRJ}_) \
      -v $(docker volume ls -qf name=${CMPS_PRJ}_mysql-vol-1):/var/lib/mysql/:ro \
      --entrypoint= \
      -v "${SCRIPT_DIR}/../_tmp_mariabackup":/backup \
      ${SQLIMAGE} mariabackup --host mysql --user root --password ${DBROOT} --backup --target-dir=/backup 2>/dev/null ; then
        >&2 echo -e "\e[31m[ERR]\e[0m - Could not create MariaDB backup on source"
        rm -rf "${SCRIPT_DIR}/../_tmp_mariabackup/"
        exit 1
    fi

    if ! docker run --rm \
      --network $(docker network ls -qf name=${CMPS_PRJ}_) \
      --entrypoint= \
      -v "${SCRIPT_DIR}/../_tmp_mariabackup":/backup \
      ${SQLIMAGE} mariabackup --prepare --target-dir=/backup 2> /dev/null ; then
        >&2 echo -e "\e[31m[ERR]\e[0m - Could not transfer MariaDB backup to remote"
        rm -rf "${SCRIPT_DIR}/../_tmp_mariabackup/"
        exit 1
    fi

    chown -R 999:999 "${SCRIPT_DIR}/../_tmp_mariabackup"

    echo -e "\033[1mSynchronizing MariaDB backup...\033[0m"
    rsync --delete --info=progress2 -aH -e "$(remote_rsync_shell)" \
      --rsync-path="$(remote_rsync_path)" \
      "${SCRIPT_DIR}/../_tmp_mariabackup/" "$(remote_rsync_target "${mountpoint}")"
    ec=$?
    if [ ${ec} -ne 0 ] && [ ${ec} -ne 24 ]; then
      >&2 echo -e "\e[31m[ERR]\e[0m - Could not transfer MariaDB backup to remote"
      exit 1
    fi

    # Cleanup
    rm -rf "${SCRIPT_DIR}/../_tmp_mariabackup/"

  elif [[ "${vol}" =~ "rspamd-vol-1" ]]; then
    # Exclude rspamd-vol-1 if the Architectures are not the same on source and destination due to compatibility issues.
    if [[ $LOCAL_ARCH == $REMOTE_ARCH ]]; then
      echo -e "\033[1mSynchronizing ${vol} from local ${mountpoint}...\033[0m"
      rsync --delete --info=progress2 -aH -e "$(remote_rsync_shell)" \
        --rsync-path="$(remote_rsync_path)" \
        "${mountpoint}/" "$(remote_rsync_target "${mountpoint}")"
    else
      echo -e "\e[1;31mSkipping ${vol} from local maschine due to incompatiblity between different architecture...\e[0m"
      sleep 2
      continue
    fi

  else
    echo -e "\033[1mSynchronizing ${vol} from local ${mountpoint}...\033[0m"
    rsync --delete --info=progress2 -aH -e "$(remote_rsync_shell)" \
      --rsync-path="$(remote_rsync_path)" \
      "${mountpoint}/" "$(remote_rsync_target "${mountpoint}")"
    ec=$?
    if [ ${ec} -ne 0 ] && [ ${ec} -ne 24 ]; then
      >&2 echo -e "\e[31m[ERR]\e[0m - Could not transfer ${vol} from local ${mountpoint} to remote"
      exit 1
    fi
  fi

  echo -e "\e[32mCompleted\e[0m"

done

# Restart Dockerd on destination
echo -ne "\033[1mRestarting Docker daemon on remote to detect new volumes... \033[0m"
if ! remote_privileged_shell "systemctl restart docker"; then
    >&2 echo -e "\e[31m[ERR]\e[0m - Could not restart Docker daemon on remote"
    exit 1
fi
echo "OK"

  echo -e "\e[33mPulling images on remote...\e[0m"
  echo -e "\e[33mProcess is NOT stuck! Please wait...\e[0m"

  if ! remote_privileged_shell "cd \"${SCRIPT_DIR}/../\" && ${COMPOSE_COMMAND} pull --quiet 2>&1"; then
      >&2 echo -e "\e[31m[ERR]\e[0m - Could not pull images on remote"
  fi

echo -e "\033[1mExecuting update script and forcing garbage cleanup on remote...\033[0m"
if ! remote_privileged_shell "cd \"${SCRIPT_DIR}/../\" && ./update.sh -f --gc"; then
    >&2 echo -e "\e[31m[ERR]\e[0m - Could not cleanup old images on remote"
fi

echo -e "\e[32mDone\e[0m"
