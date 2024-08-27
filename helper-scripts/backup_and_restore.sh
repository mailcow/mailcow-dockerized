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
      echo -e "${RED_COLOR}Cannot find ${bin} in local PATH, exiting...${RESET}"
      exit 1
    fi
  done

  if grep --help 2>&1 | head -n 1 | grep -q -i "busybox"; then
    echo -e "${RED_COLOR}BusyBox grep detected on local system, please install GNU grep${RESET}"
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
    echo -e "${RED_COLOR}Selected backup location has no subfolders${RESET}"
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
    echo -e "${RED_COLOR}No datasets found${RESET}"
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

  # Fix mysql component is stored as `backup_mariadb.tar.gz`
  if [[ " ${MAILCOW_BACKUP_COMPONENTS[*]} " =~ " mysql " ]]; then
    MAILCOW_BACKUP_COMPONENTS+=("mariadb")
  fi

  # find all files in folder with *.gz extension, print their base names, remove backup_, remove .tar (if present), remove .gz
  for file in $(find "${RESTORE_POINT}" -maxdepth 1 \( -type d -o -type f \) \( -name '*.gz' -o -name 'mysql' \) -printf '%f\n' | sed 's/backup_*//' | sed 's/\.[^.]*$//' | sed 's/\.[^.]*$//'); do
    if [[ " ${MAILCOW_BACKUP_COMPONENTS[*]} " =~ " ${file} " ]] || [[ " ${MAILCOW_BACKUP_COMPONENTS[*]} " =~ " all " ]]; then
      RESTORE_COMPONENTS+=("${file}")
    fi
  done

  echo

  # If no components were found, exit with error
  if [[ ${#RESTORE_COMPONENTS[@]} -eq 0 ]]; then
    echo -e "${RED_COLOR}No components found to restore${RESET}"
    exit 1
  fi

  # Print the available files to restore
  echo -e "${GREEN_COLOR}Matching available components to restore:${RESET}"

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
    echo -e "${RED_COLOR}Fatal Error: Docker volume for [${DOCKER_COMPOSE_PROJECT_NAME}_${DOCKER_IMAGE_NAME}] not found!${RESET}"
    exit 1
  fi

  if [[ ! -f "${RESTORE_LOCATION}/backup_${COMPONENT_NAME}.tar.gz" ]]; then
    echo
    echo -e "${YELLOW_COLOR}Warning: ${RESTORE_LOCATION} does not contains a backup for [${COMPONENT_NAME}]!${RESET}" >&2
    echo -e "${YELLOW_COLOR}Skipping restore for [${COMPONENT_NAME}]...${RESET}" >&2
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
    echo -e "${RED_COLOR}Fatal Error: Backup location not specified!${RESET}"
    exit 1
  fi

  if [[ ! -e "${BACKUP_LOCATION}" ]]; then
    echo -e "${YELLOW_COLOR}${BACKUP_LOCATION} is not exist${RESET}"
    read -p "Create it now? [y|N] " CREATE_BACKUP_LOCATION
    if ! [[ ${CREATE_BACKUP_LOCATION,,} =~ ^(yes|y)$ ]]; then
      echo -e "${RED_COLOR}exiting...${RESET}"
      exit 0
    fi

    mkdir -p "${BACKUP_LOCATION}"
    chmod 755 "${BACKUP_LOCATION}"
    if [[ ! "${?}" -eq 0 ]]; then
      echo -e "${RED_COLOR}Failed, check the error above!${RESET}"
      exit 1
    fi
  fi

  if [[ -d "${BACKUP_LOCATION}" ]]; then
    if [[ -z $(echo $(stat -Lc %a "${BACKUP_LOCATION}") | grep -oE '[0-9][0-9][5-7]') ]]; then
      echo -e "${RED_COLOR}${BACKUP_LOCATION} is not writable!${RESET}"
      echo -e "${YELLOW_COLOR}Try: chmod 755 ${BACKUP_LOCATION}${RESET}"
      exit 1
    fi
  else
    echo -e "${RED_COLOR}${BACKUP_LOCATION} is not a valid path! Maybe a file or a symbolic?\e["
    exit 1
  fi
}

function check_valid_restore_directory() {
  RESTORE_LOCATION="${1}"

  if [[ -z "${RESTORE_LOCATION}" ]]; then
    echo
    echo -e "${RED_COLOR}Fatal Error: restore location not specified!${RESET}"
    exit 1
  fi

  if [[ ! -e "${RESTORE_LOCATION}" ]]; then
    echo -e "${RED_COLOR}${RESTORE_LOCATION} is not exist${RESET}"
    exit 1
  fi

  if [[ ! -d "${RESTORE_LOCATION}" ]]; then
    echo -e "${RED_COLOR}${RESTORE_LOCATION} is not a valid path! Maybe a file or a symbolic?${RESET}"
    exit 1
  fi
}

function delete_old_backups() {
  if [[ -z $(find "${MAILCOW_DELETE_LOCATION}"/mailcow-* -maxdepth 0 -mmin +$((${MAILCOW_DELETE_DAYS}*60*24))) ]]; then
    echo -e "${YELLOW_COLOR}No backups to delete found.${RESET}"
    exit 0
  fi

  echo -e "${BLUE_COLOR}Backups scheduled for deletion:${RESET}"
  find "${MAILCOW_DELETE_LOCATION}"/mailcow-* -maxdepth 0 -mmin +$((${MAILCOW_DELETE_DAYS}*60*24)) -exec printf "${YELLOW_COLOR}- %s${RESET}\n" {} \;
  read -p "$(echo -e "${BOLD}${RED_COLOR}Are you sure you want to delete the above backups? type YES in capital letters to delete, else to skip: ${RESET}")" DELETE_CONFIRM
  if [[ "${DELETE_CONFIRM}" == "YES" ]]; then
    find "${MAILCOW_DELETE_LOCATION}"/mailcow-* -maxdepth 0 -mmin +$((${MAILCOW_DELETE_DAYS}*60*24)) -exec rm -rvf {} \;
  else
    echo -e "${YELLOW_COLOR}OK, skipped.${RESET}"
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
  echo -e "${RED_COLOR}Compose file not found${RESET}"
  exit 1
fi

if [ ! -f ${ENV_FILE} ]; then
  echo -e "${RED_COLOR}Environment file not found${RESET}"
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
        echo -e "${RED_COLOR}Invalid Option: -b/--backup requires an argument${RESET}" >&2
        exit 1
      fi

      if ! [[ "${2}" =~ ^/ ]]; then
        echo -e "${RED_COLOR}Invalid Option: -b/--backup requires an absolute path${RESET}" >&2
        exit 1
      fi

      MAILCOW_BACKUP_LOCATION=$(echo "${2}" | sed 's#/$##')
      shift 2
      ;;
    -r|--restore)
      if ! [[ $# -gt 1 ]]; then
        echo -e "${RED_COLOR}Invalid Option: -r/--restore requires an argument${RESET}" >&2
        exit 1
      fi

      if ! [[ "${2}" =~ ^/ ]]; then
        echo -e "${RED_COLOR}Invalid Option: -r/--restore requires an absolute path${RESET}" >&2
        exit 1
      fi

      MAILCOW_RESTORE_LOCATION=$(echo "${2}" | sed 's#/$##')
      shift 2
      ;;
    -d|--delete-days|--delete)
      if ! [[ $# -gt 2 ]]; then
        echo -e "${RED_COLOR}Invalid Option: -d/--delete-days requires <path> <days>${RESET}" >&2
        exit 1
      fi

      if ! [[ "${2}" =~ ^/ ]]; then
        echo -e "${RED_COLOR}Invalid Option: -d/--delete-days requires an absolute path${RESET}" >&2
        exit 1
      fi

      if ! [[ "${3}" =~ ^[0-9]+$ ]]; then
        echo -e "${RED_COLOR}Invalid Option: -d/--delete-days requires a number${RESET}" >&2
        exit 1
      fi

      MAILCOW_DELETE_LOCATION=$(echo "${2}" | sed 's#/$##')
      MAILCOW_DELETE_DAYS="${3}"
      shift 3
      ;;
    -c|--component)
      if ! [[ $# -gt 1 ]]; then
        echo -e "${RED_COLOR}Invalid Option: -c/--component requires an argument${RESET}" >&2
        exit 1
      fi

      if ! [[ "${2}" =~ ^(crypt|vmail|redis|rspamd|postfix|mysql|all)$ ]]; then
        echo -e "${RED_COLOR}Invalid Option: -c/--component requires one of the following: crypt|vmail|redis|rspamd|postfix|mysql|all${RESET}" >&2
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
        echo -e "${RED_COLOR}Invalid Option: -t/--threads requires an argument${RESET}" >&2
        exit 1
      fi

      if ! [[ "${2}" =~ ^[1-9][0-9]*$ ]]; then
        echo -e "${RED_COLOR}Invalid Option: -t/--threads requires a positive number${RESET}" >&2
        exit 1
      fi

      MAILCOW_BACKUP_RESTORE_THREADS="${2}"

      echo -e "${GREEN_COLOR}Using ${MAILCOW_BACKUP_RESTORE_THREADS} thread(s) for this run.${RESET}"
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
  echo -e "${RED_COLOR}You should pass one of the following options: ${YELLOW_COLOR}-b/--backup${RESET}, ${YELLOW_COLOR}-r/--restore${RESET} or ${YELLOW_COLOR}-d/--delete${RESET}"
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
  echo -e "${RED_COLOR}Could not determine compose project name${RESET}"
  exit 1
else
  echo -e "${GREEN_COLOR}Found project name ${COMPOSE_PROJECT_NAME}${RESET}"
  CMPS_PRJ=$(echo ${COMPOSE_PROJECT_NAME} | tr -cd "[0-9A-Za-z-_]")
fi


function backup() {
  DATE=$(date +"%Y-%m-%d-%H-%M-%S")
  mkdir -p "${MAILCOW_BACKUP_LOCATION}/mailcow-${DATE}"
  chmod 755 "${MAILCOW_BACKUP_LOCATION}/mailcow-${DATE}"
  cp "${SCRIPT_DIR}/../mailcow.conf" "${MAILCOW_BACKUP_LOCATION}/mailcow-${DATE}"
  touch "${MAILCOW_BACKUP_LOCATION}/mailcow-${DATE}/.$ARCH"

  echo -e "${GREEN_COLOR}Using ${MAILCOW_BACKUP_RESTORE_THREADS} thread(s) for this backup.${RESET}"

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
        echo -e "${RED_COLOR}Could not determine SQL image version, skipping backup...${RESET}"
        shift
        continue
      else
        echo -e "${GREEN_COLOR}Using SQL image ${SQLIMAGE}, starting...${RESET}"
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
    echo -e "${RED_COLOR}Can not read DOCKER_COMPOSE_VERSION variable from mailcow.conf! Is your mailcow up to date? Exiting...${RESET}"
    exit 1
  fi

  echo
  echo -e "${YELLOW_COLOR}Stopping watchdog-mailcow...${RESET}"
  docker stop $(docker ps -qf name=watchdog-mailcow)
  echo
  RESTORE_LOCATION="${1}"
  shift
  for component in "${RESTORE_COMPONENTS[@]}"; do
    echo
    echo -e "\n${GREEN_COLOR}Restoring ${component}...${RESET}"
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
          echo -e "${YELLOW_COLOR}Could not find a architecture signature of the loaded backup... Maybe the backup was done before the multiarch update?"
          sleep 2
          echo -e "Continuing anyhow. If rspamd is crashing opon boot try remove the rspamd volume with docker volume rm ${CMPS_PRJ}_rspamd-vol-1 after you've stopped the stack.${RESET}"
          sleep 2
          restore_docker_component "${RESTORE_LOCATION}" "${CMPS_PRJ}" "rspamd-mailcow" "rspamd"

        elif [[ $ARCH != $(find "${RESTORE_LOCATION}" \( -name '*x86*' -o -name '*aarch*' \) -exec basename {} \; | sed 's/^\.//' | sed 's/^\.//') ]]; then
          echo -e "${RED_COLOR}The Architecture of the backed up mailcow OS is different then your restoring mailcow OS..."
          sleep 2
          echo -e "Skipping rspamd due to compatibility issues!${RESET}"
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
          echo -e "${RED_COLOR}Could not determine SQL image version, skipping restore...${RESET}"
          shift
          continue
        elif [ ! -f "${RESTORE_LOCATION}/mailcow.conf" ]; then
          echo -e "${RED_COLOR}Could not find the corresponding mailcow.conf in ${RESTORE_LOCATION}, skipping restore.${RESET}"
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

  echo -e "${GREEN_COLOR}Using ${MAILCOW_BACKUP_LOCATION} as backup location...${RESET}"

  # If you didn't specify any backup components, then exit
  if [[ ${#MAILCOW_BACKUP_COMPONENTS[@]} -eq 0 ]]; then
    echo -e "${RED_COLOR}No components specified for the backup, please see --help${RESET}"
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
    echo -e "${RED_COLOR}No components specified for the restore, please see --help${RESET}"
    exit 1
  fi

  echo -e "${GREEN_COLOR}Using ${MAILCOW_RESTORE_LOCATION} as restore location...${RESET}"

  # Calling `declare_restore_point` will
  # declare `RESTORE_POINT` globally.
  declare_restore_point "${MAILCOW_RESTORE_LOCATION}"

  # Calling `declare_restore_components` will
  # declare `RESTORE_COMPONENTS` globally.
  declare_restore_components "${RESTORE_POINT}"

  echo -e "\n${GREEN_COLOR}Restoring will start in ${BOLD}5 seconds${RESET}. Press ${BOLD}Ctrl+C${RESET} to stop.\n${RESET}"
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
