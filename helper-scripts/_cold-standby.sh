#!/usr/bin/env bash

PATH=${PATH}:/opt/bin
DATE=$(date +%Y-%m-%d_%H_%M_%S)
export LC_ALL=C

echo
echo "If this script is run automatically by cron or a timer AND you are using block-level snapshots on your backup destination, make sure both do not run at the same time."
echo "The snapshots of your backup destination should run AFTER the cold standby script finished to ensure consistent snapshots."
echo

# Detects the Linux distribution name and version
detect_distro() {
  local distro
  local version

  if [ -f /etc/os-release ]; then
    # freedesktop.org and systemd
    . /etc/os-release
    distro=$NAME
    version=$VERSION_ID
  elif type lsb_release >/dev/null 2>&1; then
    # linuxbase.org
    distro=$(lsb_release -si)
    version=$(lsb_release -sr)
  elif [ -f /etc/lsb-release ]; then
    # For some versions of Debian/Ubuntu without lsb_release command
    . /etc/lsb-release
    distro=$DISTRIB_ID
    version=$DISTRIB_RELEASE
  elif [ -f /etc/debian_version ]; then
    # Older Debian/Ubuntu/etc.
    distro=Debian
    version=$(cat /etc/debian_version)
  elif [ -f /etc/SuSe-release ]; then
    # Older SuSE/etc.
    distro=$(cat /etc/SuSe-release)
    version=""
  elif [ -f /etc/redhat-release ]; then
    # Older Red Hat, CentOS, etc.
    distro=$(cat /etc/redhat-release)
    version=""
  else
    distro=$(uname -s)
    version=$(uname -r)
  fi

  # echo "Detected distribution: $distro $version"
}
detect_distro

# Function to execute a command on the remote host via SSH
# Arguments:
#   1. command: The command to execute
execute_ssh_command() {
  local command=$1
  local ssh_output
  local ssh_exit_status

  # Execute the command and capture the output
  ssh_output=$(ssh -o StrictHostKeyChecking=no -i "${REMOTE_SSH_KEY}" "${REMOTE_SSH_HOST}" -p "${REMOTE_SSH_PORT}" "${command}" 2>&1)
  ssh_exit_status=$?

  # If the output is non-empty, print it (this could be a success message or an error message)
  if [[ -n "$ssh_output" ]]; then
    echo "$ssh_output"
  fi

  # Return the exit status of the SSH command
  return $ssh_exit_status
}

# Function to synchronize directories using rsync over SSH
# Arguments:
#   1. source: The source directory
#   2. destination: The destination directory
sync_directories() {
  local source=$1
  local destination=$2
  local rsync_output
  local rsync_exit_status

  rsync_output=$(rsync --delete -aH -e "ssh -o StrictHostKeyChecking=no -i \"${REMOTE_SSH_KEY}\" -p ${REMOTE_SSH_PORT}" "${source}" "root@${REMOTE_SSH_HOST}:${destination}" 2>&1)
  rsync_exit_status=$?

  # If the output is non-empty, print it (this could be a success message or an error message)
  if [[ -n "$rsync_output" ]]; then
    echo "$rsync_output"
  fi

  # Return the exit status of the rsync command
  return $rsync_exit_status
}

docker_garbage() {
  local compose_file="${SCRIPT_DIR}/../docker-compose.yml"
  local repository tag v_main v_sub existing_tags existing_tag
  local v_main_existing v_sub_existing
  local imgs_to_delete=() # Use lowercase for non-environment variables

  # Ensure docker-compose.yml exists
  if [[ ! -f "$compose_file" ]]; then
    printf '%b\n' "\e[31mThe docker-compose.yml file does not exist at $compose_file\e[0m" >&2
    exit 1
  fi

  # Read image names from docker-compose.yml
  while IFS= read -r container; do
    repository=${container%:*}
    tag=${container#*:}
    v_main=${tag%%.*}
    v_sub=${tag##*.}
    existing_tags=$(docker images "$repository" --format "{{.Tag}}")

    for existing_tag in $existing_tags; do
      v_main_existing=${existing_tag%%.*}
      v_sub_existing=${existing_tag##*.}

      # Skip tags that are not version numbers
      if ! [[ $v_main_existing =~ ^[0-9]+$ ]] || ! [[ $v_sub_existing =~ ^[0-9]+$ ]]; then
        continue
      fi

      if [[ $v_main_existing == "latest" ]]; then
        printf 'Found deprecated label "latest" for repository %s, it should be deleted.\n' "$repository"
        imgs_to_delete+=("$repository:$existing_tag")
      elif [[ $v_main_existing -lt $v_main ]] || [[ $v_sub_existing -lt $v_sub ]]; then
        printf 'Found tag %s for %s, which is older than the current tag %s and should be deleted.\n' "$existing_tag" "$repository" "$tag"
        imgs_to_delete+=("$repository:$existing_tag")
      fi
    done
  done < <(grep -oP "image: \Kmailcow.+" "$compose_file")

  # Remove old images
  if ((${#imgs_to_delete[@]} > 0)); then
    printf 'Deleting old images...\n'
    docker rmi "${imgs_to_delete[@]}"
  fi
}

preflight_local_checks() {
  local keyfile="${REMOTE_SSH_KEY:-}"
  local ssh_port="${REMOTE_SSH_PORT:-}"

  if [[ -z "$keyfile" ]]; then
    printf '%b\n' "\e[31mREMOTE_SSH_KEY is not set\e[0m" >&2
    exit 1
  fi

  if [[ ! -s "$keyfile" ]]; then
    printf '%b\n' "\e[31mKeyfile $keyfile is empty\e[0m" >&2
    exit 1
  fi

  if [[ $(stat -c "%a" "$keyfile") != "600" ]]; then
    printf '%b\n' "\e[31mKeyfile $keyfile has insecure permissions\e[0m" >&2
    exit 1
  fi

  if [[ -n "$ssh_port" && ! "$ssh_port" =~ ^[0-9]+$ ]] || [[ "$ssh_port" -gt 65535 ]]; then
    printf '%b\n' "\e[31mREMOTE_SSH_PORT is set but not a valid port number\e[0m" >&2
    exit 1
  fi

  local required_bins=(rsync docker grep cut)
  for bin in "${required_bins[@]}"; do
    if ! command -v "${bin}" &>/dev/null; then
      printf '%b\n' "\e[31mCannot find ${bin} in local PATH, exiting...\e[0m" >&2
      exit 1
    fi
  done

  if grep --help 2>&1 | head -n 1 | grep -q -i "busybox"; then
    printf '%b\n' "\e[31mBusyBox grep detected on local system, please install GNU grep\e[0m" >&2
    exit 1
  fi
}

preflight_remote_checks() {
  # Check rsync version on the remote host
  if ! ssh -o StrictHostKeyChecking=no -i "${REMOTE_SSH_KEY}" \
    "${REMOTE_SSH_HOST}" -p "${REMOTE_SSH_PORT}" rsync --version &>/dev/null; then
    printf '%b\n' "\e[31mCould not verify connection to ${REMOTE_SSH_HOST} or rsync >= 3.1.0 is not installed.\e[0m" >&2
    exit 1
  fi

  # Check for BusyBox grep on the remote host
  if ssh -o StrictHostKeyChecking=no -i "${REMOTE_SSH_KEY}" \
    "${REMOTE_SSH_HOST}" -p "${REMOTE_SSH_PORT}" grep --help 2>&1 | head -n 1 | grep -q -i "busybox"; then
    printf '%b\n' "\e[31mBusyBox grep detected on remote system ${REMOTE_SSH_HOST}, please install GNU grep.\e[0m" >&2
    exit 1
  fi

  # Check for required binaries on the remote host
  local required_bins=(rsync docker)
  for bin in "${required_bins[@]}"; do
    if ! ssh -o StrictHostKeyChecking=no -i "${REMOTE_SSH_KEY}" \
      "${REMOTE_SSH_HOST}" -p "${REMOTE_SSH_PORT}" command -v "${bin}" &>/dev/null; then
      printf '%b\n' "\e[31mCannot find ${bin} in remote PATH, exiting...\e[0m" >&2
      exit 1
    fi
  done

  # Determine the Docker Compose command on the remote host
  local compose_command
  if ssh -o StrictHostKeyChecking=no -i "${REMOTE_SSH_KEY}" \
    "${REMOTE_SSH_HOST}" -p "${REMOTE_SSH_PORT}" docker compose &>/dev/null; then
    compose_command="docker compose"
  elif ssh -o StrictHostKeyChecking=no -i "${REMOTE_SSH_KEY}" \
    "${REMOTE_SSH_HOST}" -p "${REMOTE_SSH_PORT}" docker-compose version --short | grep "^2." &>/dev/null; then
    compose_command="docker-compose"
  else
    printf '%b\n' "\e[31mCannot find any Docker Compose on remote, exiting...\e[0m" >&2
    exit 1
  fi

  printf 'Using %s for Docker Compose on remote.\n' "$compose_command"
  COMPOSE_COMMAND=$compose_command # Exporting for use in the rest of the script
}

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
source "${SCRIPT_DIR}/../mailcow.conf"
COMPOSE_FILE="${SCRIPT_DIR}/../docker-compose.yml"
CMPS_PRJ=$(echo "${COMPOSE_PROJECT_NAME}" | tr -cd 'A-Za-z-_')
SQLIMAGE=$(grep -iEo '(mysql|mariadb)\:.+' "${COMPOSE_FILE}")

preflight_local_checks
preflight_remote_checks

echo
echo -e "\033[1mFound compose project name ${CMPS_PRJ} for ${MAILCOW_HOSTNAME}\033[0m"
echo -e "\033[1mFound SQL ${SQLIMAGE}\033[0m"
echo

# Make sure destination exists, rsync can fail under some circumstances
echo -e "\033[1mPreparing remote...\033[0m"
if ! execute_ssh_command "mkdir -p \"${SCRIPT_DIR}/../\""; then
  echo >&2 -e "\e[31m[ERR]\e[0m - Could not prepare remote for mailcow base directory transfer"
  exit 1
fi

# Syncing the mailcow base directory
echo -e "\033[1mSynchronizing mailcow base directory...\033[0m"
if ! sync_directories "${SCRIPT_DIR}/../" "${SCRIPT_DIR}/../"; then
  echo >&2 -e "\e[31m[ERR]\e[0m - Could not transfer mailcow base directory to remote"
  exit 1
fi

# Trigger a Redis save for a consistent Redis copy
echo -ne "\033[1mRunning redis-cli save... \033[0m"
docker exec "$(docker ps -qf name=redis-mailcow)" redis-cli save

# Syncing volumes related to compose project
# Same here: make sure destination exists
for vol in $(docker volume ls -qf name="${CMPS_PRJ}"); do
  mountpoint="$(docker inspect "${vol}" | grep Mountpoint | cut -d '"' -f4)"
  echo -e "\033[1mCreating remote mountpoint ${mountpoint} for ${vol}...\033[0m"
  if ! execute_ssh_command "mkdir -p \"${mountpoint}\""; then
    echo >&2 -e "\e[31m[ERR]\e[0m - Could not create remote mountpoint for ${vol}"
    exit 1
  fi

  if [[ "${vol}" =~ "mysql-vol-1" ]]; then

    # Make sure a previous backup does not exist
    rm -rf "${SCRIPT_DIR}/../_tmp_mariabackup/"

    echo -e "\033[1mCreating consistent backup of MariaDB volume...\033[0m"
    if ! docker run --rm \
      --network "$(docker network ls -qf name="${CMPS_PRJ}"_)" \
      -v "$(docker volume ls -qf name="${CMPS_PRJ}"_mysql-vol-1)":/var/lib/mysql/:ro \
      --entrypoint= \
      -v "${SCRIPT_DIR}/../_tmp_mariabackup":/backup \
      "${SQLIMAGE}" mariabackup --host mysql --user root --password "${DBROOT}" --backup --target-dir=/backup 2>/dev/null; then
      echo >&2 -e "\e[31m[ERR]\e[0m - Could not create MariaDB backup on source"
      rm -rf "${SCRIPT_DIR}/../_tmp_mariabackup/"
      exit 1
    fi

    if ! docker run --rm \
      --network "$(docker network ls -qf name="${CMPS_PRJ}"_)" \
      --entrypoint= \
      -v "${SCRIPT_DIR}/../_tmp_mariabackup":/backup \
      "${SQLIMAGE}" mariabackup --prepare --target-dir=/backup 2>/dev/null; then
      echo >&2 -e "\e[31m[ERR]\e[0m - Could not transfer MariaDB backup to remote"
      rm -rf "${SCRIPT_DIR}/../_tmp_mariabackup/"
      exit 1
    fi

    chown -R 999:999 "${SCRIPT_DIR}/../_tmp_mariabackup"

    echo -e "\033[1mSynchronizing MariaDB backup...\033[0m"
    if ! sync_directories "${SCRIPT_DIR}/../_tmp_mariabackup/" "${mountpoint}"; then
      echo >&2 -e "\e[31m[ERR]\e[0m - Could not transfer MariaDB backup to remote"
      exit 1
    fi

    # Cleanup
    rm -rf "${SCRIPT_DIR}/../_tmp_mariabackup/"
  else
    echo -e "\033[1mSynchronizing ${vol} from local ${mountpoint}...\033[0m"
    if ! sync_directories "${mountpoint}/" "${mountpoint}"; then
      echo >&2 -e "\e[31m[ERR]\e[0m - Could not transfer ${vol} from local ${mountpoint} to remote"
      exit 1
    fi
  fi

  echo -e "\e[32mCompleted\e[0m"
done


# Restart Dockerd on destination
echo -ne "\033[1mRestarting Docker daemon on remote to detect new volumes... \033[0m"
if [[ "${distro}" =~ ^(Ubuntu|Debian|CentOS|Red) ]]; then
  if ! execute_ssh_command "systemctl restart docker"; then
    echo >&2 -e "\e[31m[ERR]\e[0m - Could not restart Docker daemon on remote"
    exit 1
  fi
else
  if ! execute_ssh_command "service docker restart"; then
    echo >&2 -e "\e[31m[ERR]\e[0m - Could not restart Docker daemon on remote"
    exit 1
  fi
fi

echo "OK"

echo -e "\e[33mPulling images on remote...\e[0m"
echo -e "\e[33mProcess is NOT stuck! Please wait...\e[0m"
if ! execute_ssh_command "${COMPOSE_COMMAND} -f \"${SCRIPT_DIR}/../docker-compose.yml\" pull --no-parallel --quiet"; then
  echo >&2 -e "\e[31m[ERR]\e[0m - Could not pull images on remote"
fi

echo -e "\033[1mExecuting update script and forcing garbage cleanup on remote...\033[0m"
if ! execute_ssh_command "${SCRIPT_DIR}/../update.sh -f --gc"; then
  echo >&2 -e "\e[31m[ERR]\e[0m - Could not cleanup old images on remote"
fi

echo -e "\e[32mDone\e[0m"