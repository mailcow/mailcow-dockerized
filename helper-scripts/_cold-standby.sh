#!/usr/bin/env bash

PATH=${PATH}:/opt/bin
DATE=$(date +%Y-%m-%d_%H_%M_%S)
export LC_ALL=C

echo
echo "If this script is run automatically by cron or a timer AND you are using block-level snapshots on your backup destination, make sure both do not run at the same time."
echo "The snapshots of your backup destination should run AFTER the cold standby script finished to ensure consistent snapshots."
echo

function docker_garbage() {
  IMGS_TO_DELETE=()

  for container in $(grep -oP "image: \Kmailcow.+" docker-compose.yml); do

    REPOSITORY=${container/:*}
    TAG=${container/*:}
    V_MAIN=${container/*.}
    V_SUB=${container/*.}
    EXISTING_TAGS=$(docker images | grep ${REPOSITORY} | awk '{ print $2 }')

    for existing_tag in ${EXISTING_TAGS[@]}; do

      V_MAIN_EXISTING=${existing_tag/*.}
      V_SUB_EXISTING=${existing_tag/*.}

      # Not an integer
      [[ ! ${V_MAIN_EXISTING} =~ ^[0-9]+$ ]] && continue
      [[ ! ${V_SUB_EXISTING} =~ ^[0-9]+$ ]] && continue

      if [[ ${V_MAIN_EXISTING} == "latest" ]]; then
        echo "Found deprecated label \"latest\" for repository ${REPOSITORY}, it should be deleted."
        IMGS_TO_DELETE+=(${REPOSITORY}:${existing_tag})
      elif [[ ${V_MAIN_EXISTING} -lt ${V_MAIN} ]]; then
        echo "Found tag ${existing_tag} for ${REPOSITORY}, which is older than the current tag ${TAG} and should be deleted."
        IMGS_TO_DELETE+=(${REPOSITORY}:${existing_tag})
      elif [[ ${V_SUB_EXISTING} -lt ${V_SUB} ]]; then
        echo "Found tag ${existing_tag} for ${REPOSITORY}, which is older than the current tag ${TAG} and should be deleted."
        IMGS_TO_DELETE+=(${REPOSITORY}:${existing_tag})
      fi

    done

  done

  if [[ ! -z ${IMGS_TO_DELETE[*]} ]]; then
    docker rmi ${IMGS_TO_DELETE[*]}
  fi
}

function preflight_local_checks() {
  if [[ -z "${REMOTE_SSH_KEY}" ]]; then
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

  if [[ ! -z "${REMOTE_SSH_PORT}" ]]; then
    if [[ ${REMOTE_SSH_PORT} != ?(-)+([0-9]) ]] || [[ ${REMOTE_SSH_PORT} -gt 65535 ]]; then
      >&2 echo -e "\e[31mREMOTE_SSH_PORT is set but not an integer < 65535\e[0m"
      exit 1
    fi
  fi

  if [[ -z "${REMOTE_SSH_HOST}" ]]; then
    >&2 echo -e "\e[31mREMOTE_SSH_HOST cannot be empty\e[0m"
    exit 1
  fi

  for bin in rsync docker grep cut; do
    if [[ -z $(which ${bin}) ]]; then
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

  if ! ssh -o StrictHostKeyChecking=no \
    -i "${REMOTE_SSH_KEY}" \
    ${REMOTE_SSH_HOST} \
    -p ${REMOTE_SSH_PORT} \
    rsync --version > /dev/null ; then
      >&2 echo -e "\e[31mCould not verify connection to ${REMOTE_SSH_HOST}\e[0m"
      >&2 echo -e "\e[31mPlease check the output above (is rsync >= 3.1.0 installed on the remote system?)\e[0m"
      exit 1
  fi

  if ssh -o StrictHostKeyChecking=no \
    -i "${REMOTE_SSH_KEY}" \
    ${REMOTE_SSH_HOST} \
    -p ${REMOTE_SSH_PORT} \
    grep --help 2>&1 | head -n 1 | grep -q -i "busybox" ; then
      >&2 echo -e "\e[31mBusyBox grep detected on remote system ${REMOTE_SSH_HOST}, please install GNU grep\e[0m"
      exit 1
  fi

  for bin in rsync docker; do
    if ! ssh -o StrictHostKeyChecking=no \
      -i "${REMOTE_SSH_KEY}" \
      ${REMOTE_SSH_HOST} \
      -p ${REMOTE_SSH_PORT} \
      which ${bin} > /dev/null ; then
        >&2 echo -e "\e[31mCannot find ${bin} in remote PATH, exiting...\e[0m"
        exit 1
    fi
  done

  ssh -o StrictHostKeyChecking=no \
      -i "${REMOTE_SSH_KEY}" \
      ${REMOTE_SSH_HOST} \
      -p ${REMOTE_SSH_PORT} \
      "bash -s" << "EOF"
if docker compose > /dev/null 2>&1; then
	exit 0
elif docker-compose version --short | grep "^2." > /dev/null 2>&1; then
	exit 1
else
exit 2
fi
EOF

if [ $? = 0 ]; then
  COMPOSE_COMMAND="docker compose"
  echo "DEBUG: Using native docker compose on remote"

elif [ $? = 1 ]; then
  COMPOSE_COMMAND="docker-compose"
  echo "DEBUG: Using standalone docker compose on remote"

else
  echo -e "\e[31mCannot find any Docker Compose on remote, exiting...\e[0m"
  exit 1
fi
}

SCRIPT_DIR=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )
source "${SCRIPT_DIR}/../mailcow.conf"
COMPOSE_FILE="${SCRIPT_DIR}/../docker-compose.yml"
CMPS_PRJ=$(echo ${COMPOSE_PROJECT_NAME} | tr -cd 'A-Za-z-_')
SQLIMAGE=$(grep -iEo '(mysql|mariadb)\:.+' "${COMPOSE_FILE}")

preflight_local_checks
preflight_remote_checks

echo
echo -e "\033[1mFound compose project name ${CMPS_PRJ} for ${MAILCOW_HOSTNAME}\033[0m"
echo -e "\033[1mFound SQL ${SQLIMAGE}\033[0m"
echo

# Make sure destination exists, rsync can fail under some circumstances
echo -e "\033[1mPreparing remote...\033[0m"
if ! ssh -o StrictHostKeyChecking=no \
  -i "${REMOTE_SSH_KEY}" \
  ${REMOTE_SSH_HOST} \
  -p ${REMOTE_SSH_PORT} \
  mkdir -p "${SCRIPT_DIR}/../" ; then
    >&2 echo -e "\e[31m[ERR]\e[0m - Could not prepare remote for mailcow base directory transfer"
    exit 1
fi

# Syncing the mailcow base directory
echo -e "\033[1mSynchronizing mailcow base directory...\033[0m"
rsync --delete -aH -e "ssh -o StrictHostKeyChecking=no \
  -i \"${REMOTE_SSH_KEY}\" \
  -p ${REMOTE_SSH_PORT}" \
  "${SCRIPT_DIR}/../" root@${REMOTE_SSH_HOST}:"${SCRIPT_DIR}/../"
ec=$?
if [ ${ec} -ne 0 ] && [ ${ec} -ne 24 ]; then
  >&2 echo -e "\e[31m[ERR]\e[0m - Could not transfer mailcow base directory to remote"
  exit 1
fi

# Trigger a Redis save for a consistent Redis copy
echo -ne "\033[1mRunning redis-cli save... \033[0m"
docker exec $(docker ps -qf name=redis-mailcow) redis-cli save

# Syncing volumes related to compose project
# Same here: make sure destination exists
for vol in $(docker volume ls -qf name="${CMPS_PRJ}"); do

  mountpoint="$(docker inspect ${vol} | grep Mountpoint | cut -d '"' -f4)"

  echo -e "\033[1mCreating remote mountpoint ${mountpoint} for ${vol}...\033[0m"

  ssh -o StrictHostKeyChecking=no \
    -i "${REMOTE_SSH_KEY}" \
    ${REMOTE_SSH_HOST} \
    -p ${REMOTE_SSH_PORT} \
    mkdir -p "${mountpoint}"

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
    rsync --delete --info=progress2 -aH -e "ssh -o StrictHostKeyChecking=no \
      -i \"${REMOTE_SSH_KEY}\" \
      -p ${REMOTE_SSH_PORT}" \
      "${SCRIPT_DIR}/../_tmp_mariabackup/" root@${REMOTE_SSH_HOST}:"${mountpoint}"
    ec=$?
    if [ ${ec} -ne 0 ] && [ ${ec} -ne 24 ]; then
      >&2 echo -e "\e[31m[ERR]\e[0m - Could not transfer MariaDB backup to remote"
      exit 1
    fi

    # Cleanup
    rm -rf "${SCRIPT_DIR}/../_tmp_mariabackup/"

  else

    echo -e "\033[1mSynchronizing ${vol} from local ${mountpoint}...\033[0m"
    rsync --delete --info=progress2 -aH -e "ssh -o StrictHostKeyChecking=no \
      -i \"${REMOTE_SSH_KEY}\" \
      -p ${REMOTE_SSH_PORT}" \
      "${mountpoint}/" root@${REMOTE_SSH_HOST}:"${mountpoint}"
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
if ! ssh -o StrictHostKeyChecking=no \
  -i "${REMOTE_SSH_KEY}" \
  ${REMOTE_SSH_HOST} \
  -p ${REMOTE_SSH_PORT} \
  systemctl restart docker ; then
    >&2 echo -e "\e[31m[ERR]\e[0m - Could not restart Docker daemon on remote"
    exit 1
fi
echo "OK"

  echo -e "\e[33mPulling images on remote...\e[0m"
  echo -e "\e[33mProcess is NOT stuck! Please wait...\e[0m"

  if ! ssh -o StrictHostKeyChecking=no \
    -i "${REMOTE_SSH_KEY}" \
    ${REMOTE_SSH_HOST} \
    -p ${REMOTE_SSH_PORT} \
    ${COMPOSE_COMMAND} -f "${SCRIPT_DIR}/../docker-compose.yml" pull --no-parallel --quiet 2>&1 ; then
      >&2 echo -e "\e[31m[ERR]\e[0m - Could not pull images on remote"
  fi

echo -e "\033[1mExecuting update script and forcing garbage cleanup on remote...\033[0m"
if ! ssh -o StrictHostKeyChecking=no \
  -i "${REMOTE_SSH_KEY}" \
  ${REMOTE_SSH_HOST} \
  -p ${REMOTE_SSH_PORT} \
  ${SCRIPT_DIR}/../update.sh -f --gc ; then
    >&2 echo -e "\e[31m[ERR]\e[0m - Could not cleanup old images on remote"
fi

echo -e "\e[32mDone\e[0m"