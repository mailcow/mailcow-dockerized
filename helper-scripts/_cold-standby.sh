#!/usr/bin/env bash

#— enforce & export our SSH vars
: "${REMOTE_SSH_USER:?Need to set REMOTE_SSH_USER}"
: "${REMOTE_SSH_HOST:?Need to set REMOTE_SSH_HOST}"
: "${REMOTE_SSH_PORT:=22}"
: "${REMOTE_SSH_KEY:?Need to set REMOTE_SSH_KEY}"
export REMOTE_SSH_USER REMOTE_SSH_HOST REMOTE_SSH_PORT REMOTE_SSH_KEY

echo "DEBUG: SSH → ${REMOTE_SSH_USER}@${REMOTE_SSH_HOST}  key=${REMOTE_SSH_KEY}"

PATH=${PATH}:/opt/bin
DATE=$(date +%Y-%m-%d_%H_%M_%S)
LOCAL_ARCH=$(uname -m)
export LC_ALL=C

echo
echo "If this script is run by cron/timer AND you use block‐level snapshots, ensure snapshots run AFTER this script."
echo

function preflight_local_checks() {
  [[ -s "${REMOTE_SSH_KEY}" ]] || { >&2 echo "Keyfile ${REMOTE_SSH_KEY} missing or empty"; exit 1; }
  [[ $(stat -c "%a" "${REMOTE_SSH_KEY}") -eq 600 ]] || { >&2 echo "Keyfile ${REMOTE_SSH_KEY} must be 600"; exit 1; }
  [[ "${REMOTE_SSH_PORT}" =~ ^[0-9]+$ ]] && (( REMOTE_SSH_PORT <= 65535 )) || { >&2 echo "REMOTE_SSH_PORT invalid"; exit 1; }
  for bin in rsync docker grep cut tar scp ssh; do
    command -v "$bin" &>/dev/null || { >&2 echo "Cannot find $bin in PATH"; exit 1; }
  done
}

function preflight_remote_checks() {
  ssh -o StrictHostKeyChecking=no -i "${REMOTE_SSH_KEY}" \
    -p ${REMOTE_SSH_PORT} ${REMOTE_SSH_USER}@${REMOTE_SSH_HOST} \
    "command -v tar scp ssh docker" &>/dev/null \
    || { >&2 echo "Remote missing required binaries"; exit 1; }

  COMPOSE_COMMAND=$(
    ssh -o StrictHostKeyChecking=no -i "${REMOTE_SSH_KEY}" \
      -p ${REMOTE_SSH_PORT} ${REMOTE_SSH_USER}@${REMOTE_SSH_HOST} bash -s <<'EOF'
if command -v docker-compose &>/dev/null; then
  echo docker-compose
elif docker compose version &>/dev/null; then
  echo "docker compose"
else
  exit 1
fi
EOF
  ) || { >&2 echo "Cannot find Docker Compose on remote"; exit 1; }
  echo "INFO: Using '${COMPOSE_COMMAND}' on remote"
}

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
source "${SCRIPT_DIR}/../mailcow.conf"
COMPOSE_FILE="${SCRIPT_DIR}/../docker-compose.yml"
CMPS_PRJ=$(echo "${COMPOSE_PROJECT_NAME}" | tr -cd 'A-Za-z-_')
SQLIMAGE=$(grep -iEo '(mysql|mariadb)\:.+' "${COMPOSE_FILE}")

preflight_local_checks
preflight_remote_checks

echo
echo "Found compose project name ${CMPS_PRJ} for ${MAILCOW_HOSTNAME}"
echo "Found SQL image ${SQLIMAGE}"
echo

# Ensure remote base dir exists
echo -n "Preparing remote... "
ssh -o StrictHostKeyChecking=no -i "${REMOTE_SSH_KEY}" \
  -p ${REMOTE_SSH_PORT} ${REMOTE_SSH_USER}@${REMOTE_SSH_HOST} \
  mkdir -p "${SCRIPT_DIR}/../" \
  && echo OK || { >&2 echo "FAIL"; exit 1; }

# Sync mailcow base directory (configs, docker-compose.yml, etc.)
echo "Synchronizing mailcow base directory..."
rsync -aH --delete \
  -e "ssh -i ${REMOTE_SSH_KEY} -p ${REMOTE_SSH_PORT} -o StrictHostKeyChecking=no" \
  "${SCRIPT_DIR}/../" \
  ${REMOTE_SSH_USER}@${REMOTE_SSH_HOST}:"${SCRIPT_DIR}/../" \
  || { >&2 echo "[ERR] Could not transfer base directory"; exit 1; }

# Create networks, volumes and containers on remote
echo "Creating networks, volumes and containers on remote..."
ssh -o StrictHostKeyChecking=no -i "${REMOTE_SSH_KEY}" \
  -p ${REMOTE_SSH_PORT} ${REMOTE_SSH_USER}@${REMOTE_SSH_HOST} \
  ${COMPOSE_COMMAND} -f "${COMPOSE_FILE}" create

# Trigger redis save
echo -n "Running redis-cli save... "
REDIS_CTR=$(docker compose -f "${COMPOSE_FILE}" ps -q redis-mailcow)
docker exec "${REDIS_CTR}" redis-cli -a "${REDISPASS}" --no-auth-warning save \
  && echo OK || { >&2 echo "FAIL"; exit 1; }

# Sync each Docker volume via tar+scp
for vol in $(docker volume ls -qf name="${CMPS_PRJ}"); do
  mountpoint=$(docker inspect "${vol}" | grep Mountpoint | cut -d '"' -f4)
  archive="/tmp/${vol}.tar.gz"

  echo
  echo "Syncing volume: ${vol} → ${mountpoint}"
  echo " • Ensuring remote mountpoint..."
  ssh -o StrictHostKeyChecking=no -i "${REMOTE_SSH_KEY}" \
    -p ${REMOTE_SSH_PORT} ${REMOTE_SSH_USER}@${REMOTE_SSH_HOST} \
    sudo mkdir -p "${mountpoint}"

  if [[ "${vol}" =~ "mysql-vol-1" ]]; then
    # MariaDB backup flow
    echo " • Preparing MariaDB backup..."
    rm -rf "${SCRIPT_DIR}/../_tmp_mariabackup/"
    docker run --rm \
      --network $(docker network ls -qf name=${CMPS_PRJ}_) \
      -v $(docker volume ls -qf name=${CMPS_PRJ}_mysql-vol-1):/var/lib/mysql/:ro \
      --entrypoint= \
      -v "${SCRIPT_DIR}/../_tmp_mariabackup":/backup \
      ${SQLIMAGE} mariabackup --host mysql --user root --password ${DBROOT} --backup --target-dir=/backup
    docker run --rm \
      --network $(docker network ls -qf name=${CMPS_PRJ}_) \
      --entrypoint= \
      -v "${SCRIPT_DIR}/../_tmp_mariabackup":/backup \
      ${SQLIMAGE} mariabackup --prepare --target-dir=/backup
    chown -R 999:999 "${SCRIPT_DIR}/../_tmp_mariabackup"

    echo " • Archiving MariaDB backup..."
    tar -czf "${archive}" -C "${SCRIPT_DIR}/../_tmp_mariabackup" . \
      || { >&2 echo "[ERR] tar failed"; exit 1; }

  elif [[ "${vol}" =~ "rspamd-vol-1" ]]; then
    # Rspamd only if architectures match
    if [[ $LOCAL_ARCH != $REMOTE_ARCH ]]; then
      echo "Skipping ${vol} due to architecture mismatch"
      continue
    fi
    echo " • Archiving rspamd volume..."
    tar -czf "${archive}" -C "${mountpoint}" . \
      || { >&2 echo "[ERR] tar failed"; exit 1; }

  else
    # Generic volume
    echo " • Archiving volume data..."
    tar -czf "${archive}" -C "${mountpoint}" . \
      || { >&2 echo "[ERR] tar failed"; exit 1; }
  fi

  echo " • Copying archive to remote..."
  scp -i "${REMOTE_SSH_KEY}" -P ${REMOTE_SSH_PORT} "${archive}" \
    ${REMOTE_SSH_USER}@${REMOTE_SSH_HOST}:/tmp/ \
    || { >&2 echo "[ERR] scp failed"; exit 1; }

  echo " • Extracting on remote..."
  ssh -i "${REMOTE_SSH_KEY}" -p ${REMOTE_SSH_PORT} ${REMOTE_SSH_USER}@${REMOTE_SSH_HOST} bash -s <<EOF
sudo tar -xzf /tmp/${vol}.tar.gz -C "${mountpoint}"
sudo rm /tmp/${vol}.tar.gz
EOF

  echo " • Cleaning up local archive..."
  rm -f "${archive}"
  echo -e "\e[32mCompleted ${vol}\e[0m"
done

# Restart Docker on remote
echo -n "Restarting Docker daemon on remote... "
ssh -o StrictHostKeyChecking=no -i "${REMOTE_SSH_KEY}" \
  -p ${REMOTE_SSH_PORT} ${REMOTE_SSH_USER}@${REMOTE_SSH_HOST} \
  sudo systemctl restart docker && echo OK || { >&2 echo "FAIL"; exit 1; }

# Pull images on remote
echo "Pulling images on remote..."
ssh -o StrictHostKeyChecking=no -i "${REMOTE_SSH_KEY}" \
  -p ${REMOTE_SSH_PORT} ${REMOTE_SSH_USER}@${REMOTE_SSH_HOST} \
  ${COMPOSE_COMMAND} -f "${COMPOSE_FILE}" pull --quiet

echo "Executing update.sh and garbage cleanup on remote as root..."
ssh -o StrictHostKeyChecking=no -i "${REMOTE_SSH_KEY}" \
  -p ${REMOTE_SSH_PORT} ${REMOTE_SSH_USER}@${REMOTE_SSH_HOST} \
  "sudo bash -lc '\"${SCRIPT_DIR}/../update.sh\" -f --gc'"

echo -e "\e[32mDone\e[0m"
