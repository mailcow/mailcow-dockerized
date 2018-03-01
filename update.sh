#!/bin/bash

#exit on error and pipefail
set -o pipefail

for bin in curl docker-compose docker git awk sha1sum; do
  if [[ -z $(which ${bin}) ]]; then echo "Cannot find ${bin}, exiting..."; exit 1; fi
done

export LC_ALL=C
DATE=$(date +%Y-%m-%d_%H_%M_%S)
BRANCH=$(git rev-parse --abbrev-ref HEAD)

while (($#)); do
  case "${1}" in
    --check|-c)
      echo "Checking remote code for updates..."
      git fetch origin ${BRANCH}
      if [[ -z $(git log HEAD --pretty=format:"%H" | grep $(git rev-parse origin/${BRANCH})) ]]; then
        echo "Updated code is available."
        exit 0
      else
        echo "No updates available."
        exit 3
      fi
    ;;
    --ours)
      MERGE_STRATEGY=ours
    ;;
  esac
done

[[ ! -f mailcow.conf ]] && { echo "mailcow.conf is missing"; exit 1;}

if grep --help 2>&1 | head -n 1 | grep -q -i "busybox"; then echo "BusybBox grep detected, please install gnu grep, \"apk add --no-cache --upgrade grep\""; exit 1; fi
if cp --help 2>&1 | head -n 1 | grep -q -i "busybox"; then echo "BusybBox cp detected, please install coreutils, \"apk add --no-cache --upgrade coreutils\""; exit 1; fi

CONFIG_ARRAY=(
  "SKIP_LETS_ENCRYPT"
  "USE_WATCHDOG"
  "WATCHDOG_NOTIFY_EMAIL"
  "SKIP_CLAMD"
  "SKIP_IP_CHECK"
  "ADDITIONAL_SAN"
  "DOVEADM_PORT"
  "IPV4_NETWORK"
  "IPV6_NETWORK"
  "LOG_LINES"
  "SNAT_TO_SOURCE"
  "SYSCTL_IPV6_DISABLED"
  "SQL_PORT"
)

sed -i '$a\' mailcow.conf
for option in ${CONFIG_ARRAY[@]}; do
  if [[ ${option} == "ADDITIONAL_SAN" ]]; then
    if ! grep -q ${option} mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo "${option}=" >> mailcow.conf
    fi
  elif [[ ${option} == "SYSCTL_IPV6_DISABLED" ]]; then
    if ! grep -q ${option} mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo "# Disable IPv6" >> mailcow.conf
      echo "# mailcow-network will still be created as IPv6 enabled, all containers will be created" >> mailcow.conf
      echo "# without IPv6 support." >> mailcow.conf
      echo "# Use 1 for disabled, 0 for enabled" >> mailcow.conf
      echo "SYSCTL_IPV6_DISABLED=0" >> mailcow.conf
    fi
  elif [[ ${option} == "COMPOSE_PROJECT_NAME" ]]; then
    if ! grep -q ${option} mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo "COMPOSE_PROJECT_NAME=mailcow-dockerized" >> mailcow.conf
    fi
  elif [[ ${option} == "DOVEADM_PORT" ]]; then
    if ! grep -q ${option} mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo "DOVEADM_PORT=127.0.0.1:19991" >> mailcow.conf
    fi
  elif [[ ${option} == "WATCHDOG_NOTIFY_EMAIL" ]]; then
    if ! grep -q ${option} mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo "WATCHDOG_NOTIFY_EMAIL=" >> mailcow.conf
    fi
  elif [[ ${option} == "LOG_LINES" ]]; then
    if ! grep -q ${option} mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo '# Max log lines per service to keep in Redis logs' >> mailcow.conf
      echo "LOG_LINES=9999" >> mailcow.conf
    fi
  elif [[ ${option} == "IPV4_NETWORK" ]]; then
    if ! grep -q ${option} mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo '# Internal IPv4 /24 subnet, format n.n.n. (expands to n.n.n.0/24)' >> mailcow.conf
      echo "IPV4_NETWORK=172.22.1" >> mailcow.conf
    fi
  elif [[ ${option} == "IPV6_NETWORK" ]]; then
    if ! grep -q ${option} mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo '# Internal IPv6 subnet in fc00::/7' >> mailcow.conf
      echo "IPV6_NETWORK=fd4d:6169:6c63:6f77::/64" >> mailcow.conf
    fi
  elif [[ ${option} == "SQL_PORT" ]]; then
    if ! grep -q ${option} mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo '# Bind SQL to 127.0.0.1 on port 13306' >> mailcow.conf
      echo "SQL_PORT=127.0.0.1:13306" >> mailcow.conf
    fi
  elif [[ ${option} == "SNAT_TO_SOURCE" ]]; then
    if ! grep -q ${option} mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo '# Use this IP for outgoing connections (SNAT)' >> mailcow.conf
      echo "#SNAT_TO_SOURCE=" >> mailcow.conf
    fi
  elif ! grep -q ${option} mailcow.conf; then
    echo "Adding new option \"${option}\" to mailcow.conf"
    echo "${option}=n" >> mailcow.conf
  fi
done

echo -en "Checking internet connection... "
curl -o /dev/null google.com -sm3
if [[ $? != 0 ]]; then
  echo -e "\e[31mfailed\e[0m"
  exit 1
else
  echo -e "\e[32mOK\e[0m"
fi

echo -e "\e[32mChecking for newer update script...\e[0m"
SHA1_1=$(sha1sum update.sh)
git fetch origin ${BRANCH}
git checkout origin/${BRANCH} update.sh
SHA1_2=$(sha1sum update.sh)
if [[ ${SHA1_1} != ${SHA1_2} ]]; then
  echo "update.sh changed, please run this script again, exiting."
  chmod +x update.sh
  exit 0
fi

if [[ -f mailcow.conf ]]; then
  source mailcow.conf
else
  echo -e "\e[31mNo mailcow.conf - is mailcow installed?\e[0m"
  exit 1
fi

read -r -p "Are you sure you want to update mailcow: dockerized? All containers will be stopped. [y/N] " response
if [[ ! "$response" =~ ^([yY][eE][sS]|[yY])+$ ]]; then
  echo "OK, exiting."
  exit 0
fi

echo -e "Stopping mailcow... "
sleep 2
docker-compose down

# Silently fixing remote url from andryyy to mailcow
git remote set-url origin https://github.com/mailcow/mailcow-dockerized
echo -e "\e[32mCommitting current status...\e[0m"
git update-index --assume-unchanged data/conf/rspamd/override.d/worker-controller-password.inc
git add -u
git commit -am "Before update on ${DATE}" > /dev/null
echo -e "\e[32mFetching updated code from remote...\e[0m"
git fetch origin ${BRANCH}
echo -e "\e[32mMerging local with remote code (recursive, strategy: \"${MERGE_STRATEGY:-theirs}\", options: \"patience\"...\e[0m"
git config merge.defaultToUpstream true
git merge -X${MERGE_STRATEGY:-theirs} -Xpatience -m "After update on ${DATE}"
# Need to use a variable to not pass return codes of if checks
MERGE_RETURN=$?
if [[ ${MERGE_RETURN} == 128 ]]; then
  echo -e "\e[31m\nOh no, what happened?\n=> You most likely added files to your local mailcow instance that were now added to the official mailcow repository. Please move them to another location before updating mailcow.\e[0m"
  exit 1
elif [[ ${MERGE_RETURN} == 1 ]]; then
  echo -e "\e[93mPotenial conflict, trying to fix...\e[0m"
  git status --porcelain | grep -E "UD|DU" | awk '{print $2}' | xargs rm -v
  git add -A
  git commit -m "After update on ${DATE}" > /dev/null
  git checkout .
  echo -e "\e[32mRemoved and recreated files if necessary.\e[0m"
elif [[ ${MERGE_RETURN} != 0 ]]; then
  echo -e "\e[31m\nOh no, something went wrong. Please check the error message above.\e[0m"
  echo
  echo "Run docker-compose up -d to restart your stack without updates or try again after fixing the mentioned errors."
  exit 1
fi


echo -e "\e[32mFetching new docker-compose version...\e[0m"
sleep 2
if [[ ! -z $(which pip) && $(pip list --local | grep -c docker-compose) == 1 ]]; then
  true
  #prevent breaking a working docker-compose installed with pip
elif [[ $(curl -sL -w "%{http_code}" https://www.servercow.de/docker-compose/latest.php -o /dev/null) == "200" ]]; then
  LATEST_COMPOSE=$(curl -#L https://www.servercow.de/docker-compose/latest.php)
  curl -#L https://github.com/docker/compose/releases/download/${LATEST_COMPOSE}/docker-compose-$(uname -s)-$(uname -m) > $(which docker-compose)
  chmod +x $(which docker-compose)
else
  echo -e "\e[33mCannot determine latest docker-compose version, skipping...\e[0m"
fi

echo -e "\e[32mFetching new images, if any...\e[0m"
sleep 2
docker-compose pull --parallel

# Fix missing SSL, does not overwrite existing files
[[ ! -d data/assets/ssl ]] && mkdir -p data/assets/ssl
cp -n data/assets/ssl-example/*.pem data/assets/ssl/

echo -e "\e[32mStarting mailcow...\e[0m"
sleep 2
docker-compose up -d --remove-orphans

echo -e "\e[32mCollecting garbage...\e[0m"
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
    [[ ! $V_MAIN_EXISTING =~ ^[0-9]+$ ]] && continue
    [[ ! $V_SUB_EXISTING =~ ^[0-9]+$ ]] && continue

    if [[ $V_MAIN_EXISTING == "latest" ]]; then
      echo "Found deprecated label \"latest\" for repository $REPOSITORY, it should be deleted."
      IMGS_TO_DELETE+=($REPOSITORY:$existing_tag)
    elif [[ $V_MAIN_EXISTING -lt $V_MAIN ]]; then
      echo "Found tag $existing_tag for $REPOSITORY, which is older than the current tag $TAG and should be deleted."
      IMGS_TO_DELETE+=($REPOSITORY:$existing_tag)
    elif [[ $V_SUB_EXISTING -lt $V_SUB ]]; then
      echo "Found tag $existing_tag for $REPOSITORY, which is older than the current tag $TAG and should be deleted."
      IMGS_TO_DELETE+=($REPOSITORY:$existing_tag)
    fi
  done
done

if [[ ! -z ${IMGS_TO_DELETE[*]} ]]; then
  echo "Run the following command to delete unused image tags:"
  echo
  echo "    docker rmi ${IMGS_TO_DELETE[*]}"
  echo
  read -r -p "Do you want to delete old image tags right now? [y/N] " response
  if [[ "$response" =~ ^([yY][eE][sS]|[yY])+$ ]]; then
    docker rmi ${IMGS_TO_DELETE[*]}
  else
    echo "OK, skipped."
  fi
fi
echo -e "\e[32mFurther cleanup...\e[0m"
echo "If you want to cleanup further garbage collected by Docker, please make sure all containers are up and running before cleaning your system by executing \"docker system prune\""

#echo "In case you encounter any problem, hard-reset to a state before updating mailcow:"
#echo
#git reflog --color=always | grep "Before update on "
#echo
#echo "Use \"git reset --hard hash-on-the-left\" and run docker-compose up -d afterwards."
