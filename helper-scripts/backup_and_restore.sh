#!/usr/bin/env bash

# ----------------- Start Functions -----------------

RED_COLOR="\e[31m"
GREEN_COLOR="\e[32m"
YELLOW_COLOR="\e[33m"
BLUE_COLOR="\e[34m"
BOLD="\e[1m"
ITALIC="\e[3m"
RESET="\e[0m"

function print_usage() {
  echo -e "${BOLD}Usage:${RESET} ${0} [command] [command_options]${RESET}"
  echo
  echo -e "${BOLD}Commands:${RESET}"
  echo -e "\t${GREEN_COLOR}-h, --help${RESET}\t\t\t${ITALIC}Print this help message${RESET}"
  echo -e "\t${GREEN_COLOR}-b, --backup${RESET}\t${ITALIC}<${GREEN_COLOR}path${RESET}${ITALIC}>\t\tBackup one or more components of mailcow (example: ${0} -b /path/to/backup/folder -c vmail -c crypt)${RESET}"
  echo -e "\t${GREEN_COLOR}-r, --restore${RESET}\t${ITALIC}<${GREEN_COLOR}path${RESET}${ITALIC}>\t\tRestore the full mailcow configuration from a backup${RESET}"
  echo -e "\t${GREEN_COLOR}-d, --delete${RESET}\t${ITALIC}<${GREEN_COLOR}path${RESET}${ITALIC}> <${RESET}${GREEN_COLOR}days${RESET}${ITALIC}>\tDelete backups older than X days in the given path${RESET}"
  echo
  echo -e "${BOLD}Command Options:${RESET}"
  echo -e "\t${GREEN_COLOR}-t, --threads${RESET}\t${ITALIC}<${GREEN_COLOR}num${RESET}${ITALIC}>\t\tSet the thread count for backup and restore operations${RESET}"
  echo -e "\t${GREEN_COLOR}-c, --component${RESET}\t${ITALIC}<${GREEN_COLOR}component${RESET}${ITALIC}>\tSet the component(s) to backup or restore [vmail, crypt, redis, rspamd, postfix, mysql and all]${RESET}"
  echo
}

function check_required_tools() {
  # Add the required tools to the array
  local required_tools=("docker")

  for bin in "${required_tools[@]}"; do
    if [[ -z $(which ${bin}) ]]; then
      echo -e "\e[31mCannot find ${bin} in local PATH, exiting...\e[0m"
      exit 1
    fi
  done

  if grep --help 2>&1 | head -n 1 | grep -q -i "busybox"; then
    echo -e "\e[31mBusyBox grep detected on local system, please install GNU grep\e[0m"
    exit 1
  fi
}

# declare_restore_point declares the `RESTORE_POINT`
# globally which is the path to the backup folder.
#
# If the function succeeds, the variable `RESTORE_POINT`
# will be declared globally.
# If the function fails, it will exit with a status of 1.
function declare_restore_point() {
  local BACKUP_LOCATION="${1}"

  # Check subfolders inside BACKUP_LOCATION
  if [[ $(find "${BACKUP_LOCATION}"/mailcow-* -maxdepth 1 -type d 2> /dev/null| wc -l) -lt 1 ]]; then
    echo -e "\e[31mSelected backup location has no subfolders\e[0m"
    exit 1
  fi

  local i=0
  local -A FOLDER_SELECTION

  # Loop through the folders inside BACKUP_LOCATION,
  # and print them to stdout, for the user to choose.
  for folder in $(ls -d "${BACKUP_LOCATION}"/mailcow-*/); do
    ((i++))
    echo "[ ${i} ] - ${folder}"
    FOLDER_SELECTION[${i}]="${folder}"
  done

  echo

  # Prompt the user to choose what to restore.
  local input_sel=0
  while [[ ! "${input_sel}" =~ ^[0-9]+$ ]] || [[ ${input_sel} -lt 1 ||  ${input_sel} -gt ${i} ]]; do
    read -p "Select a restore point: " input_sel
  done

  echo

  # Declare the RESTORE_POINT variable globally,
  # to be used outside the function.
  RESTORE_POINT="${FOLDER_SELECTION[${input_sel}]}"
  if [[ -z $(find "${FOLDER_SELECTION[${input_sel}]}" -maxdepth 1 \( -type d -o -type f \) -regex ".*\(redis\|rspamd\|mariadb\|mysql\|crypt\|vmail\|postfix\).*") ]]; then
    echo -e "\e[31mNo datasets found\e[0m"
    exit 1
  fi
}

# declare_restore_components declares the `RESTORE_COMPONENTS`
# globally which is an array contains the components that should
# be restored from the given `RESTORE_POINT`.
# `RESTORE_POINT` folder (which components to restore from).
#
# If the function succeeds, the variable `RESTORE_COMPONENTS`
# will be declared globally.
# If the function fails, it will exit with a status of 1.
function declare_restore_components() {
  local RESTORE_POINT="$1"

  local i=0
  RESTORE_COMPONENTS=()

  # find all files in folder with *.gz extension, print their base names, remove backup_, remove .tar (if present), remove .gz
  for file in $(find "${RESTORE_POINT}" -maxdepth 1 \( -type d -o -type f \) \( -name '*.gz' -o -name 'mysql' \) -printf '%f\n' | sed 's/backup_*//' | sed 's/\.[^.]*$//' | sed 's/\.[^.]*$//'); do
    if [[ " ${MAILCOW_BACKUP_COMPONENTS[*]} " =~ " ${file} " ]] || [[ " ${MAILCOW_BACKUP_COMPONENTS[*]} " =~ " all " ]]; then
      RESTORE_COMPONENTS+=("${file}")
    fi
  done

  echo

  # Print the available files to restore
  echo -e "\e[32mMatching available components to restore:\e[0m"

  local i=0
  for component in "${RESTORE_COMPONENTS[@]}"; do
    ((i++))
    echo "[ ${i} ] - ${component}"
  done

  echo
}

# restore_docker_component restores components used in
# docker compose project name to the specified path.
#
# Parameters:
#   1. RESTORE_LOCATION
#   2. DOCKER_COMPOSE_PROJECT_NAME
#   3. DOCKER_IMAGE_NAME
#   4. COMPONENT_NAME
#
# If the function succeeds, will return with no value.
# If the function fails, it will exit with a status of 1.
function restore_docker_component() {
  local RESTORE_LOCATION="${1}"
  local DOCKER_COMPOSE_PROJECT_NAME="${2}"
  # Example: dovecot-mailcow, postfix-mailcow, redis-mailcow
  local DOCKER_IMAGE_NAME="${3}"
  # Example: vmail, redis, crypt, rspamd and postfix
  local COMPONENT_NAME="${4}"

  local CONTAINER_ID
  local DOCKER_VOLUME_NAME

  CONTAINER_ID="$(docker ps -qf name="${DOCKER_IMAGE_NAME}")"
  DOCKER_VOLUME_NAME="$(docker volume ls -qf name=^${DOCKER_COMPOSE_PROJECT_NAME}_${COMPONENT_NAME}-vol-1$)"

  if [[ -z "${DOCKER_VOLUME_NAME}" ]]; then
    echo
    echo -e "\e[31mFatal Error: Docker volume for [${DOCKER_COMPOSE_PROJECT_NAME}_${DOCKER_IMAGE_NAME}] not found!\e[0m"
    exit 1
  fi

  if [[ ! -f "${RESTORE_LOCATION}/backup_${COMPONENT_NAME}.tar.gz" ]]; then
    echo
    echo -e "\e[33mWarning: ${RESTORE_LOCATION} does not contains a backup for [${COMPONENT_NAME}]!\e[0m" >&2
    echo -e "\e[33mSkipping restore for [${COMPONENT_NAME}]...\e[0m" >&2
    return 1
  fi

  # Restoring Process

  docker stop "${CONTAINER_ID}"

  docker run -i --name mailcow-backup --rm \
    -v "${RESTORE_LOCATION}:/backup:z" \
    -v "${DOCKER_VOLUME_NAME}:/${COMPONENT_NAME}:z" \
    ${DEBIAN_DOCKER_IMAGE} /bin/tar --use-compress-program="pigz -d -p ${MAILCOW_BACKUP_RESTORE_THREADS}" -Pxvf /backup/backup_"${COMPONENT_NAME}".tar.gz

  docker start "${CONTAINER_ID}"
}

function check_valid_backup_directory() {
  BACKUP_LOCATION="${1}"

  if [[ -z "${BACKUP_LOCATION}" ]]; then
    echo
    echo -e "\e[31mFatal Error: Backup location not specified!\e[0m"
    exit 1
  fi

  if [[ ! -e "${BACKUP_LOCATION}" ]]; then
    echo -e "\e[33m${BACKUP_LOCATION} is not exist\e[0m"
    read -p "Create it now? [y|N] " CREATE_BACKUP_LOCATION
    if ! [[ ${CREATE_BACKUP_LOCATION,,} =~ ^(yes|y)$ ]]; then
      echo -e "\e[31mexiting...\e[0m"
      exit 0
    fi

    mkdir -p "${BACKUP_LOCATION}"
    chmod 755 "${BACKUP_LOCATION}"
    if [[ ! "${?}" -eq 0 ]]; then
      echo -e "\e[31mFailed, check the error above!\e[0m"
      exit 1
    fi
  fi

  if [[ -d "${BACKUP_LOCATION}" ]]; then
    if [[ -z $(echo $(stat -Lc %a "${BACKUP_LOCATION}") | grep -oE '[0-9][0-9][5-7]') ]]; then
      echo -e "\e[31m${BACKUP_LOCATION} is not writable!\e[0m"
      echo -e "\e[33mTry: chmod 755 ${BACKUP_LOCATION}\e[0m"
      exit 1
    fi
  else
    echo -e "\e[31m${BACKUP_LOCATION} is not a valid path! Maybe a file or a symbolic?\e["
    exit 1
  fi
}

function check_valid_restore_directory() {
  RESTORE_LOCATION="${1}"

  if [[ -z "${RESTORE_LOCATION}" ]]; then
    echo
    echo -e "\e[31mFatal Error: restore location not specified!\e[0m"
    exit 1
  fi

  if [[ ! -e "${RESTORE_LOCATION}" ]]; then
    echo -e "\e[31m${RESTORE_LOCATION} is not exist\e[0m"
    exit 1
  fi

  if [[ ! -d "${RESTORE_LOCATION}" ]]; then
    echo -e "\e[31m${RESTORE_LOCATION} is not a valid path! Maybe a file or a symbolic?\e[0m"
    exit 1
  fi
}

function delete_old_backups() {
  if [[ -z $(find "${MAILCOW_DELETE_LOCATION}"/mailcow-* -maxdepth 0 -mmin +$((${MAILCOW_DELETE_DAYS}*60*24))) ]]; then
    echo -e "\e[33mNo backups to delete found.\e[0m"
    exit 0
  fi

  echo -e "\e[34mBackups scheduled for deletion:\e[0m"
  find "${MAILCOW_DELETE_LOCATION}"/mailcow-* -maxdepth 0 -mmin +$((${MAILCOW_DELETE_DAYS}*60*24)) -exec printf "\e[33m- %s\e[0m\n" {} \;
  read -p "$(echo -e "\e[1m\e[31mAre you sure you want to delete the above backups? type YES in capital letters to delete, else to skip: \e[0m")" DELETE_CONFIRM
  if [[ "${DELETE_CONFIRM}" == "YES" ]]; then
    find "${MAILCOW_DELETE_LOCATION}"/mailcow-* -maxdepth 0 -mmin +$((${MAILCOW_DELETE_DAYS}*60*24)) -exec rm -rvf {} \;
  else
    echo -e "\e[33mOK, skipped.\e[0m"
  fi
}

# ----------------- End Functions -----------------

check_required_tools

DEBIAN_DOCKER_IMAGE="mailcow/backup:latest"

# This duplicated assignment is to address
# issue in `https://github.com/mailcow/mailcow-dockerized/issues/957`
if [[ ! -z "${MAILCOW_BACKUP_LOCATION}" ]]; then
  BACKUP_LOCATION="${MAILCOW_BACKUP_LOCATION}"
fi

if [[ $# -eq 0 ]]; then
  print_usage
  exit 0
fi

MAILCOW_BACKUP_COMPONENTS=()

# These variables can be set using flags or environment variables
# Such as `./script.sh -b /path/to/backup`
# Or `MAILCOW_BACKUP_LOCATION=/path/to/backup ./script.sh`
MAILCOW_BACKUP_LOCATION="${MAILCOW_BACKUP_LOCATION:-}"
MAILCOW_RESTORE_LOCATION="${MAILCOW_RESTORE_LOCATION:-}"
MAILCOW_DELETE_LOCATION="${MAILCOW_DELETE_LOCATION:-}"
MAILCOW_DELETE_DAYS="${MAILCOW_DELETE_DAYS:-}"
MAILCOW_BACKUP_RESTORE_THREADS=${MAILCOW_BACKUP_RESTORE_THREADS:-1}

# Check for required files
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
COMPOSE_FILE=${SCRIPT_DIR}/../docker-compose.yml
ENV_FILE=${SCRIPT_DIR}/../.env
ARCH=$(uname -m)

if [ ! -f ${COMPOSE_FILE} ]; then
  echo -e "\e[31mCompose file not found\e[0m"
  exit 1
fi

if [ ! -f ${ENV_FILE} ]; then
  echo -e "\e[31mEnvironment file not found\e[0m"
  exit 1
fi

# Parse arguments
while [[ $# -gt 0 ]]; do
  case "${1}" in
    -h|--help)
      print_usage
      exit 0
      ;;
    -b|--backup)
      if ! [[ $# -gt 1 ]]; then
        echo -e "\e[31mInvalid Option: -b/--backup requires an argument\e[0m" >&2
        exit 1
      fi

      if ! [[ "${2}" =~ ^/ ]]; then
        echo -e "\e[31mInvalid Option: -b/--backup requires an absolute path\e[0m" >&2
        exit 1
      fi

      MAILCOW_BACKUP_LOCATION=$(echo "${2}" | sed 's#/$##')
      shift 2
      ;;
    -r|--restore)
      if ! [[ $# -gt 1 ]]; then
        echo -e "\e[31mInvalid Option: -r/--restore requires an argument\e[0m" >&2
        exit 1
      fi

      if ! [[ "${2}" =~ ^/ ]]; then
        echo -e "\e[31mInvalid Option: -r/--restore requires an absolute path\e[0m" >&2
        exit 1
      fi

      MAILCOW_RESTORE_LOCATION=$(echo "${2}" | sed 's#/$##')
      shift 2
      ;;
    -d|--delete-days|--delete)
      if ! [[ $# -gt 2 ]]; then
        echo -e "\e[31mInvalid Option: -d/--delete-days requires <path> <days>\e[0m" >&2
        exit 1
      fi

      if ! [[ "${2}" =~ ^/ ]]; then
        echo -e "\e[31mInvalid Option: -d/--delete-days requires an absolute path\e[0m" >&2
        exit 1
      fi

      if ! [[ "${3}" =~ ^[0-9]+$ ]]; then
        echo -e "\e[31mInvalid Option: -d/--delete-days requires a number\e[0m" >&2
        exit 1
      fi

      MAILCOW_DELETE_LOCATION=$(echo "${2}" | sed 's#/$##')
      MAILCOW_DELETE_DAYS="${3}"
      shift 3
      ;;
    -c|--component)
      if ! [[ $# -gt 1 ]]; then
        echo -e "\e[31mInvalid Option: -c/--component requires an argument\e[0m" >&2
        exit 1
      fi

      if ! [[ "${2}" =~ ^(crypt|vmail|redis|rspamd|postfix|mysql|all)$ ]]; then
        echo -e "\e[31mInvalid Option: -c/--component requires one of the following: crypt|vmail|redis|rspamd|postfix|mysql|all\e[0m" >&2
        exit 1
      fi

      # Do not allow duplicate components
      if [[ ! " ${MAILCOW_BACKUP_COMPONENTS[*]} " =~ " ${2} " ]]; then
        MAILCOW_BACKUP_COMPONENTS+=("${2}")
      fi
      shift 2
      ;;
    -t|--threads)
      if ! [[ $# -gt 1 ]]; then
        echo -e "\e[31mInvalid Option: -t/--threads requires an argument\e[0m" >&2
        exit 1
      fi

      if ! [[ "${2}" =~ ^[1-9][0-9]*$ ]]; then
        echo -e "\e[31mInvalid Option: -t/--threads requires a positive number\e[0m" >&2
        exit 1
      fi

      echo -e "\e[32mUsing ${THREADS} thread(s) for this run.\e[0m"
      MAILCOW_BACKUP_RESTORE_THREADS="${2}"
      shift 2
      ;;
    --)
      shift
      break
      ;;
    *)
      echo -e "${RED_COLOR}Invalid Option: ${1}${RESET}" >&2
      exit 1
      ;;
  esac
done

# Prevent passing --restore , --backup or --delete together
declare -i OPTION_COUNT=0

if [[ ! -z "${MAILCOW_DELETE_LOCATION}" ]]; then
  OPTION_COUNT=$((OPTION_COUNT+1))
fi

if [[ ! -z "${MAILCOW_BACKUP_LOCATION}" ]]; then
  OPTION_COUNT=$((OPTION_COUNT+1))
fi

if [[ ! -z "${MAILCOW_RESTORE_LOCATION}" ]]; then
  OPTION_COUNT=$((OPTION_COUNT+1))
fi

if [[ "${OPTION_COUNT}" -gt 1 ]] || [[ "${OPTION_COUNT}" -eq 0 ]]; then
  echo -e "\e[31mYou should pass one of the following options: \e[33m-b/--backup\e[0m, \e[33m-r/--restore\e[0m or \e[33m-d/--delete\e[0m"
  exit 1
fi

# Merge backup components if all is passed
if [[ ! -z "${MAILCOW_BACKUP_LOCATION}" ]] || [[ ! -z "${MAILCOW_RESTORE_LOCATION}" ]]; then
  if [[ " ${MAILCOW_BACKUP_COMPONENTS[*]} " =~ " all " ]]; then
    # MAILCOW_BACKUP_COMPONENTS=("crypt" "vmail" "redis" "rspamd" "postfix" "mysql")
    MAILCOW_BACKUP_COMPONENTS=("all")
  fi
fi

source ${SCRIPT_DIR}/../mailcow.conf

if [[ -z ${COMPOSE_PROJECT_NAME} ]]; then
  echo -e "\e[31mCould not determine compose project name\e[0m"
  exit 1
else
  echo -e "\e[32mFound project name ${COMPOSE_PROJECT_NAME}\e[0m"
  CMPS_PRJ=$(echo ${COMPOSE_PROJECT_NAME} | tr -cd "[0-9A-Za-z-_]")
fi


function backup() {
  DATE=$(date +"%Y-%m-%d-%H-%M-%S")
  mkdir -p "${MAILCOW_BACKUP_LOCATION}/mailcow-${DATE}"
  chmod 755 "${MAILCOW_BACKUP_LOCATION}/mailcow-${DATE}"
  cp "${SCRIPT_DIR}/../mailcow.conf" "${MAILCOW_BACKUP_LOCATION}/mailcow-${DATE}"
  touch "${MAILCOW_BACKUP_LOCATION}/mailcow-${DATE}/.$ARCH"

  echo -e "\e[32mUsing ${MAILCOW_BACKUP_RESTORE_THREADS} thread(s) for this backup.\e[0m"

  while (( "$#" )); do
    case "$1" in
    vmail|all)
      docker run --name mailcow-backup --rm \
        -v "${MAILCOW_BACKUP_LOCATION}"/mailcow-${DATE}:/backup:z \
        -v $(docker volume ls -qf name=^${CMPS_PRJ}_vmail-vol-1$):/vmail:ro,z \
        ${DEBIAN_DOCKER_IMAGE} /bin/tar --warning='no-file-ignored' --use-compress-program="pigz --rsyncable -p ${MAILCOW_BACKUP_RESTORE_THREADS}" -Pcvpf /backup/backup_vmail.tar.gz /vmail
      ;;&
    crypt|all)
      docker run --name mailcow-backup --rm \
        -v "${BACKUP_LOCATION}"/mailcow-${DATE}:/backup:z \
        -v $(docker volume ls -qf name=^${CMPS_PRJ}_crypt-vol-1$):/crypt:ro,z \
        ${DEBIAN_DOCKER_IMAGE} /bin/tar --warning='no-file-ignored' --use-compress-program="pigz --rsyncable -p ${MAILCOW_BACKUP_RESTORE_THREADS}" -Pcvpf /backup/backup_crypt.tar.gz /crypt
      ;;&
    redis|all)
      docker exec $(docker ps -qf name=redis-mailcow) redis-cli save
      docker run --name mailcow-backup --rm \
        -v "${BACKUP_LOCATION}"/mailcow-${DATE}:/backup:z \
        -v $(docker volume ls -qf name=^${CMPS_PRJ}_redis-vol-1$):/redis:ro,z \
        ${DEBIAN_DOCKER_IMAGE} /bin/tar --warning='no-file-ignored' --use-compress-program="pigz --rsyncable -p ${MAILCOW_BACKUP_RESTORE_THREADS}" -Pcvpf /backup/backup_redis.tar.gz /redis
      ;;&
    rspamd|all)
      docker run --name mailcow-backup --rm \
        -v "${BACKUP_LOCATION}"/mailcow-${DATE}:/backup:z \
        -v $(docker volume ls -qf name=^${CMPS_PRJ}_rspamd-vol-1$):/rspamd:ro,z \
        ${DEBIAN_DOCKER_IMAGE} /bin/tar --warning='no-file-ignored' --use-compress-program="pigz --rsyncable -p ${MAILCOW_BACKUP_RESTORE_THREADS}" -Pcvpf /backup/backup_rspamd.tar.gz /rspamd
      ;;&
    postfix|all)
      docker run --name mailcow-backup --rm \
        -v "${BACKUP_LOCATION}"/mailcow-${DATE}:/backup:z \
        -v $(docker volume ls -qf name=^${CMPS_PRJ}_postfix-vol-1$):/postfix:ro,z \
        ${DEBIAN_DOCKER_IMAGE} /bin/tar --warning='no-file-ignored' --use-compress-program="pigz --rsyncable -p ${MAILCOW_BACKUP_RESTORE_THREADS}" -Pcvpf /backup/backup_postfix.tar.gz /postfix
      ;;&
    mysql|all)
      SQLIMAGE=$(grep -iEo '(mysql|mariadb):.+' ${COMPOSE_FILE})
      if [[ -z "${SQLIMAGE}" ]]; then
        echo -e "\e[31mCould not determine SQL image version, skipping backup...\e[0m"
        shift
        continue
      else
        echo -e "\e[32mUsing SQL image ${SQLIMAGE}, starting...\e[0m"
        docker run --name mailcow-backup --rm \
          --network $(docker network ls -qf name=^${CMPS_PRJ}_mailcow-network$) \
          -v $(docker volume ls -qf name=^${CMPS_PRJ}_mysql-vol-1$):/var/lib/mysql/:ro,z \
          -t --entrypoint= \
          --sysctl net.ipv6.conf.all.disable_ipv6=1 \
          -v "${BACKUP_LOCATION}"/mailcow-${DATE}:/backup:z \
          ${SQLIMAGE} /bin/sh -c "mariabackup --host mysql --user root --password ${DBROOT} --backup --rsync --target-dir=/backup_mariadb ; \
          mariabackup --prepare --target-dir=/backup_mariadb ; \
          chown -R 999:999 /backup_mariadb ; \
          /bin/tar --warning='no-file-ignored' --use-compress-program='gzip --rsyncable' -Pcvpf /backup/backup_mariadb.tar.gz /backup_mariadb ;"
      fi
      ;;&
    esac
    shift
  done
}

function restore() {
  if [ "${DOCKER_COMPOSE_VERSION}" == "native" ]; then
    COMPOSE_COMMAND="docker compose"
  elif [ "${DOCKER_COMPOSE_VERSION}" == "standalone" ]; then
    COMPOSE_COMMAND="docker-compose"
  else
    echo -e "\e[31mCan not read DOCKER_COMPOSE_VERSION variable from mailcow.conf! Is your mailcow up to date? Exiting...\e[0m"
    exit 1
  fi

  echo
  echo -e "\e[33mStopping watchdog-mailcow...\e[0m"
  docker stop $(docker ps -qf name=watchdog-mailcow)
  echo
  RESTORE_LOCATION="${1}"
  shift
  for component in ${RESTORE_COMPONENTS[@]}; do
    echo
    echo -e "\n\e[32mRestoring ${component}...\e[0m"
    sleep 1
    case ${component} in
      vmail)
        restore_docker_component "${RESTORE_LOCATION}" "${CMPS_PRJ}" "dovecot-mailcow" "vmail"

        echo
        echo "In most cases it is not required to run a full resync, you can run the command printed below at any time after testing wether the restore process broke a mailbox:"
        echo
        echo "docker exec $(docker ps -qf name=dovecot-mailcow) doveadm force-resync -A '*'"
        echo
        read -p "Force a resync now? [y|N] " FORCE_RESYNC
        if [[ ${FORCE_RESYNC,,} =~ ^(yes|y)$ ]]; then
          docker exec $(docker ps -qf name=dovecot-mailcow) doveadm force-resync -A '*'
        else
          echo "OK, skipped."
        fi
        ;;
      redis)
        restore_docker_component "${RESTORE_LOCATION}" "${CMPS_PRJ}" "redis-mailcow" "redis"
        ;;
      crypt)
        restore_docker_component "${RESTORE_LOCATION}" "${CMPS_PRJ}" "dovecot-mailcow" "crypt"
        ;;
      rspamd)
        if [[ $(find "${RESTORE_LOCATION}" \( -name '*x86*' -o -name '*aarch*' \) -exec basename {} \; | sed 's/^\.//' | sed 's/^\.//') == "" ]]; then
          echo -e "\e[33mCould not find a architecture signature of the loaded backup... Maybe the backup was done before the multiarch update?"
          sleep 2
          echo -e "Continuing anyhow. If rspamd is crashing opon boot try remove the rspamd volume with docker volume rm ${CMPS_PRJ}_rspamd-vol-1 after you've stopped the stack.\e[0m"
          sleep 2
          restore_docker_component "${RESTORE_LOCATION}" "${CMPS_PRJ}" "rspamd-mailcow" "rspamd"

        elif [[ $ARCH != $(find "${RESTORE_LOCATION}" \( -name '*x86*' -o -name '*aarch*' \) -exec basename {} \; | sed 's/^\.//' | sed 's/^\.//') ]]; then
          echo -e "\e[31mThe Architecture of the backed up mailcow OS is different then your restoring mailcow OS..."
          sleep 2
          echo -e "Skipping rspamd due to compatibility issues!\e[0m"
        else
          restore_docker_component "${RESTORE_LOCATION}" "${CMPS_PRJ}" "rspamd-mailcow" "rspamd"
        fi
        ;;
      postfix)
        restore_docker_component "${RESTORE_LOCATION}" "${CMPS_PRJ}" "postfix-mailcow" "postfix"
        ;;
      mysql|mariadb)
        SQLIMAGE=$(grep -iEo '(mysql|mariadb):.+' ${COMPOSE_FILE})
        if [[ -z "${SQLIMAGE}" ]]; then
          echo -e "\e[31mCould not determine SQL image version, skipping restore...\e[0m"
          shift
          continue
        elif [ ! -f "${RESTORE_LOCATION}/mailcow.conf" ]; then
          echo -e "\e[31mCould not find the corresponding mailcow.conf in ${RESTORE_LOCATION}, skipping restore.\e[0m"
          echo "If you lost that file, copy the last working mailcow.conf file to ${RESTORE_LOCATION} and restart the restore process."
          shift
          continue
        else
          read -p "mailcow will be stopped and the currently active mailcow.conf will be modified to use the DB parameters found in ${RESTORE_LOCATION}/mailcow.conf - do you want to proceed? [Y|n] " MYSQL_STOP_MAILCOW
          if [[ ${MYSQL_STOP_MAILCOW,,} =~ ^(no|n|N)$ ]]; then
            echo "OK, skipped."
            shift
            continue
          else
            echo "Stopping mailcow..."
            ${COMPOSE_COMMAND} -f ${COMPOSE_FILE} --env-file ${ENV_FILE} down
          fi
          #docker stop $(docker ps -qf name=mysql-mailcow)
          if [[ -d "${RESTORE_LOCATION}/mysql" ]]; then
          docker run --name mailcow-backup --rm \
            -v $(docker volume ls -qf name=^${CMPS_PRJ}_mysql-vol-1$):/var/lib/mysql/:rw,z \
            --entrypoint= \
            -v ${RESTORE_LOCATION}/mysql:/backup:z \
            ${SQLIMAGE} /bin/bash -c "shopt -s dotglob ; /bin/rm -rf /var/lib/mysql/* ; rsync -avh --usermap=root:mysql --groupmap=root:mysql /backup/ /var/lib/mysql/"
          elif [[ -f "${RESTORE_LOCATION}/backup_mysql.gz" ]]; then
          docker run \
            -i --name mailcow-backup --rm \
            -v $(docker volume ls -qf name=^${CMPS_PRJ}_mysql-vol-1$):/var/lib/mysql/:z \
            --entrypoint= \
            -u mysql \
            -v ${RESTORE_LOCATION}:/backup:z \
            ${SQLIMAGE} /bin/sh -c "mysqld --skip-grant-tables & \
            until mysqladmin ping; do sleep 3; done && \
            echo Restoring... && \
            gunzip < backup/backup_mysql.gz | mysql -uroot && \
            mysql -uroot -e SHUTDOWN;"
          elif [[ -f "${RESTORE_LOCATION}/backup_mariadb.tar.gz" ]]; then
          docker run --name mailcow-backup --rm \
            -v $(docker volume ls -qf name=^${CMPS_PRJ}_mysql-vol-1$):/backup_mariadb/:rw,z \
            --entrypoint= \
            -v ${RESTORE_LOCATION}:/backup:z \
            ${SQLIMAGE} /bin/bash -c "shopt -s dotglob ; \
              /bin/rm -rf /backup_mariadb/* ; \
              /bin/tar -Pxvzf /backup/backup_mariadb.tar.gz"
          fi
          echo "Modifying mailcow.conf..."
          source ${RESTORE_LOCATION}/mailcow.conf
          sed -i --follow-symlinks "/DBNAME/c\DBNAME=${DBNAME}" ${SCRIPT_DIR}/../mailcow.conf
          sed -i --follow-symlinks "/DBUSER/c\DBUSER=${DBUSER}" ${SCRIPT_DIR}/../mailcow.conf
          sed -i --follow-symlinks "/DBPASS/c\DBPASS=${DBPASS}" ${SCRIPT_DIR}/../mailcow.conf
          sed -i --follow-symlinks "/DBROOT/c\DBROOT=${DBROOT}" ${SCRIPT_DIR}/../mailcow.conf
          source ${SCRIPT_DIR}/../mailcow.conf
          echo "Starting mailcow..."
          ${COMPOSE_COMMAND} -f ${COMPOSE_FILE} --env-file ${ENV_FILE} up -d
          #docker start $(docker ps -aqf name=mysql-mailcow)
        fi
        ;;
    esac
    shift
  done
  echo
  echo "Starting watchdog-mailcow..."
  docker start $(docker ps -aqf name=watchdog-mailcow)
}

#
# Backup Process
#
if [[ ! -z "${MAILCOW_BACKUP_LOCATION}" ]]; then

  check_valid_backup_directory "${MAILCOW_BACKUP_LOCATION}"

  echo -e "\e[32mUsing ${MAILCOW_BACKUP_LOCATION} as backup location...\e[0m"

  # If you didn't specify any backup components, then exit
  if [[ ${#MAILCOW_BACKUP_COMPONENTS[@]} -eq 0 ]]; then
    echo -e "\e[31mNo components specified for the backup, please see --help\e[0m"
    exit 1
  fi

  backup "${MAILCOW_BACKUP_COMPONENTS[@]}"
fi

#
# Restore Process
#
if [[ ! -z "${MAILCOW_RESTORE_LOCATION}" ]]; then

  check_valid_restore_directory "${MAILCOW_RESTORE_LOCATION}"

  # If you didn't specify any backup components for the restore, then exit
  if [[ ${#MAILCOW_BACKUP_COMPONENTS[@]} -eq 0 ]]; then
    echo -e "\e[31mNo components specified for the restore, please see --help\e[0m"
    exit 1
  fi

  echo -e "\e[32mUsing ${MAILCOW_RESTORE_LOCATION} as restore location...\e[0m"

  # Calling `declare_restore_point` will
  # declare `RESTORE_POINT` globally.
  declare_restore_point "${MAILCOW_RESTORE_LOCATION}"

  # Calling `declare_restore_components` will
  # declare `RESTORE_COMPONENTS` globally.
  declare_restore_components "${RESTORE_POINT}"

  echo -e "\n\e[32mRestoring will start in \e[1m5 seconds\e[0m. Press \e[1mCtrl+C\e[0m to stop.\n\e[0m"
  sleep 5

  echo "Restoring ${MAILCOW_BACKUP_COMPONENTS[*]} from ${RESTORE_POINT}..."
  restore "${RESTORE_POINT}"
fi

#
# Delete Process
#
if [[ ! -z "${MAILCOW_DELETE_LOCATION}" ]]; then
  echo "Deleting backups older than ${MAILCOW_DELETE_DAYS} days in ${MAILCOW_DELETE_LOCATION}"

  delete_old_backups
fi
