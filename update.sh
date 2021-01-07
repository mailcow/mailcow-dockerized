#!/usr/bin/env bash

# Check permissions
if [ "$(id -u)" -ne "0" ]; then
  echo "You need to be root"
  exit 1
fi

if [[ "$(uname -r)" =~ ^4\.15\.0-60 ]]; then
  echo "DO NOT RUN mailcow ON THIS UBUNTU KERNEL!";
  echo "Please update to 5.x or use another distribution."
  exit 1
fi

if [[ "$(uname -r)" =~ ^4\.4\. ]]; then
  if grep -q Ubuntu <<< $(uname -a); then
    echo "DO NOT RUN mailcow ON THIS UBUNTU KERNEL!"
    echo "Please update to linux-generic-hwe-16.04 by running \"apt-get install --install-recommends linux-generic-hwe-16.04\""
    exit 1
  fi
  echo "mailcow on a 4.4.x kernel is not supported. It may or may not work, please upgrade your kernel or continue at your own risk."
  read -p "Press any key to continue..." < /dev/tty
fi

# Exit on error and pipefail
set -o pipefail

# Setting high dc timeout
export COMPOSE_HTTP_TIMEOUT=600

# Add /opt/bin to PATH
PATH=$PATH:/opt/bin

umask 0022

for bin in curl docker-compose docker git awk sha1sum; do
  if [[ -z $(which ${bin}) ]]; then echo "Cannot find ${bin}, exiting..."; exit 1; fi
done

export LC_ALL=C
DATE=$(date +%Y-%m-%d_%H_%M_%S)
BRANCH=$(git rev-parse --abbrev-ref HEAD)

check_online_status() {
  CHECK_ONLINE_IPS=(1.1.1.1 9.9.9.9 8.8.8.8)
  for ip in "${CHECK_ONLINE_IPS[@]}"; do
    if timeout 3 ping -c 1 ${ip} > /dev/null; then
      return 0
    fi
  done
  return 1
}

prefetch_images() {
  [[ -z ${BRANCH} ]] && { echo -e "\e[33m\nUnknown branch...\e[0m"; exit 1; }
  git fetch origin #${BRANCH}
  while read image; do
    RET_C=0
    until docker pull ${image}; do
      RET_C=$((RET_C + 1))
      echo -e "\e[33m\nError pulling $image, retrying...\e[0m"
      [ ${RET_C} -gt 3 ] && { echo -e "\e[31m\nToo many failed retries, exiting\e[0m"; exit 1; }
      sleep 1
    done
  done < <(git show origin/${BRANCH}:docker-compose.yml | grep "image:" | awk '{ gsub("image:","", $3); print $2 }')
}

docker_garbage() {
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
    if [ ! $FORCE ]; then
      read -r -p "Do you want to delete old image tags right now? [y/N] " response
      if [[ "$response" =~ ^([yY][eE][sS]|[yY])+$ ]]; then
        docker rmi ${IMGS_TO_DELETE[*]}
      else
        echo "OK, skipped."
      fi
    else
      echo "Skipped image removal because of force mode."
    fi
  fi
  echo -e "\e[32mFurther cleanup...\e[0m"
  echo "If you want to cleanup further garbage collected by Docker, please make sure all containers are up and running before cleaning your system by executing \"docker system prune\""
}

while (($#)); do
  case "${1}" in
    --check|-c)
      echo "Checking remote code for updates..."
      LATEST_REV=$(git ls-remote --exit-code --refs --quiet https://github.com/mailcow/mailcow-dockerized ${BRANCH} | cut -f1)
      if [ $? -ne 0 ]; then
        echo "A problem occurred while trying to fetch the latest revision from github."
        exit 99
      fi
      if [[ -z $(git log HEAD --pretty=format:"%H" | grep "${LATEST_REV}") ]]; then
        echo "Updated code is available."
        git log --date=short --pretty=format:"%ad - %s" $(git rev-parse --short HEAD)..origin/master
        exit 0
      else
        echo "No updates available."
        exit 3
      fi
    ;;
    --ours)
      MERGE_STRATEGY=ours
    ;;
    --skip-start)
      SKIP_START=y
    ;;
    --gc)
      echo -e "\e[32mCollecting garbage...\e[0m"
      docker_garbage
      exit 0
    ;;
    --prefetch)
      echo -e "\e[32mPrefetching images...\e[0m"
      prefetch_images
      exit 0
    ;;
    -f|--force)
      echo -e "\e[32mForcing Update...\e[0m"
      FORCE=y
    ;;
    --no-update-compose)
      NO_UPDATE_COMPOSE=y
    ;;
    --help|-h)
    echo './update.sh [-c|--check, --ours, --gc, --no-update-compose, --prefetch, --skip-start, -f|--force, -h|--help]

  -c|--check           -   Check for updates and exit (exit codes => 0: update available, 3: no updates)
  --ours               -   Use merge strategy option "ours" to solve conflicts in favor of non-mailcow code (local changes over remote changes), not recommended!
  --gc                 -   Run garbage collector to delete old image tags
  --no-update-compose  -   Do not update docker-compose
  --prefetch           -   Only prefetch new images and exit (useful to prepare updates)
  --skip-start         -   Do not start mailcow after update
  -f|--force           -   Force update, do not ask questions
'
    exit 1
  esac
  shift
done

[[ ! -f mailcow.conf ]] && { echo "mailcow.conf is missing"; exit 1;}
chmod 600 mailcow.conf
source mailcow.conf
DOTS=${MAILCOW_HOSTNAME//[^.]};
if [ ${#DOTS} -lt 2 ]; then
  echo "MAILCOW_HOSTNAME (${MAILCOW_HOSTNAME}) is not a FQDN!"
  echo "Please change it to a FQDN and run docker-compose down followed by docker-compose up -d"
  exit 1
fi

if grep --help 2>&1 | head -n 1 | grep -q -i "busybox"; then echo "BusyBox grep detected, please install gnu grep, \"apk add --no-cache --upgrade grep\""; exit 1; fi
if cp --help 2>&1 | head -n 1 | grep -q -i "busybox"; then echo "BusyBox cp detected, please install coreutils, \"apk add --no-cache --upgrade coreutils\""; exit 1; fi
if sed --help 2>&1 | head -n 1 | grep -q -i "busybox"; then echo "BusyBox sed detected, please install gnu sed, \"apk add --no-cache --upgrade sed\""; exit 1; fi

CONFIG_ARRAY=(
  "SKIP_LETS_ENCRYPT"
  "SKIP_SOGO"
  "USE_WATCHDOG"
  "WATCHDOG_NOTIFY_EMAIL"
  "WATCHDOG_NOTIFY_BAN"
  "WATCHDOG_EXTERNAL_CHECKS"
  "SKIP_CLAMD"
  "SKIP_IP_CHECK"
  "ADDITIONAL_SAN"
  "DOVEADM_PORT"
  "IPV4_NETWORK"
  "IPV6_NETWORK"
  "LOG_LINES"
  "SNAT_TO_SOURCE"
  "SNAT6_TO_SOURCE"
  "COMPOSE_PROJECT_NAME"
  "SQL_PORT"
  "API_KEY"
  "API_KEY_READ_ONLY"
  "API_ALLOW_FROM"
  "MAILDIR_GC_TIME"
  "MAILDIR_SUB"
  "ACL_ANYONE"
  "SOLR_HEAP"
  "SKIP_SOLR"
  "ENABLE_SSL_SNI"
  "ALLOW_ADMIN_EMAIL_LOGIN"
  "SKIP_HTTP_VERIFICATION"
  "SOGO_EXPIRE_SESSION"
  "REDIS_PORT"
  "DOVECOT_MASTER_USER"
  "DOVECOT_MASTER_PASS"
  "MAILCOW_PASS_SCHEME"
)

sed -i --follow-symlinks '$a\' mailcow.conf
for option in ${CONFIG_ARRAY[@]}; do
  if [[ ${option} == "ADDITIONAL_SAN" ]]; then
    if ! grep -q ${option} mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo "${option}=" >> mailcow.conf
    fi
  elif [[ ${option} == "COMPOSE_PROJECT_NAME" ]]; then
    if ! grep -q ${option} mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo "COMPOSE_PROJECT_NAME=mailcowdockerized" >> mailcow.conf
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
  elif [[ ${option} == "API_KEY" ]]; then
    if ! grep -q ${option} mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo '# Create or override API key for web UI' >> mailcow.conf
      echo "#API_KEY=" >> mailcow.conf
    fi
  elif [[ ${option} == "API_KEY_READ_ONLY" ]]; then
    if ! grep -q ${option} mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo '# Create or override read-only API key for web UI' >> mailcow.conf
      echo "#API_KEY_READ_ONLY=" >> mailcow.conf
    fi
  elif [[ ${option} == "API_ALLOW_FROM" ]]; then
    if ! grep -q ${option} mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo '# Must be set for API_KEY to be active' >> mailcow.conf
      echo '# IPs only, no networks (networks can be set via UI)' >> mailcow.conf
      echo "#API_ALLOW_FROM=" >> mailcow.conf
    fi
  elif [[ ${option} == "SNAT_TO_SOURCE" ]]; then
    if ! grep -q ${option} mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo '# Use this IPv4 for outgoing connections (SNAT)' >> mailcow.conf
      echo "#SNAT_TO_SOURCE=" >> mailcow.conf
    fi
  elif [[ ${option} == "SNAT6_TO_SOURCE" ]]; then
    if ! grep -q ${option} mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo '# Use this IPv6 for outgoing connections (SNAT)' >> mailcow.conf
      echo "#SNAT6_TO_SOURCE=" >> mailcow.conf
    fi
  elif [[ ${option} == "MAILDIR_GC_TIME" ]]; then
    if ! grep -q ${option} mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo '# Garbage collector cleanup' >> mailcow.conf
      echo '# Deleted domains and mailboxes are moved to /var/vmail/_garbage/timestamp_sanitizedstring' >> mailcow.conf
      echo '# How long should objects remain in the garbage until they are being deleted? (value in minutes)' >> mailcow.conf
      echo '# Check interval is hourly' >> mailcow.conf
      echo 'MAILDIR_GC_TIME=1440' >> mailcow.conf
    fi
  elif [[ ${option} == "ACL_ANYONE" ]]; then
    if ! grep -q ${option} mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo '# Set this to "allow" to enable the anyone pseudo user. Disabled by default.' >> mailcow.conf
      echo '# When enabled, ACL can be created, that apply to "All authenticated users"' >> mailcow.conf
      echo '# This should probably only be activated on mail hosts, that are used exclusivly by one organisation.' >> mailcow.conf
      echo '# Otherwise a user might share data with too many other users.' >> mailcow.conf
      echo 'ACL_ANYONE=disallow' >> mailcow.conf
    fi
  elif [[ ${option} == "SOLR_HEAP" ]]; then
    if ! grep -q ${option} mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo '# Solr heap size, there is no recommendation, please see Solr docs.' >> mailcow.conf
      echo '# Solr is a prone to run OOM on large systems and should be monitored. Unmonitored Solr setups are not recommended.' >> mailcow.conf
      echo '# Solr will refuse to start with total system memory below or equal to 2 GB.' >> mailcow.conf
      echo "SOLR_HEAP=1024" >> mailcow.conf
  fi
  elif [[ ${option} == "SKIP_SOLR" ]]; then
    if ! grep -q ${option} mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo '# Solr is disabled by default after upgrading from non-Solr to Solr-enabled mailcows.' >> mailcow.conf
      echo '# Disable Solr or if you do not want to store a readable index of your mails in solr-vol-1.' >> mailcow.conf
      echo "SKIP_SOLR=y" >> mailcow.conf
    fi
  elif [[ ${option} == "ENABLE_SSL_SNI" ]]; then
    if ! grep -q ${option} mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo '# Create seperate certificates for all domains - y/n' >> mailcow.conf
      echo '# this will allow adding more than 100 domains, but some email clients will not be able to connect with alternative hostnames' >> mailcow.conf
      echo '# see https://wiki.dovecot.org/SSL/SNIClientSupport' >> mailcow.conf
      echo "ENABLE_SSL_SNI=n" >> mailcow.conf
    fi
  elif [[ ${option} == "SKIP_SOGO" ]]; then
    if ! grep -q ${option} mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo '# Skip SOGo: Will disable SOGo integration and therefore webmail, DAV protocols and ActiveSync support (experimental, unsupported, not fully implemented) - y/n' >> mailcow.conf
      echo "SKIP_SOGO=n" >> mailcow.conf
    fi
  elif [[ ${option} == "MAILDIR_SUB" ]]; then
    if ! grep -q ${option} mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo '# MAILDIR_SUB defines a path in a users virtual home to keep the maildir in. Leave empty for updated setups.' >> mailcow.conf
      echo "#MAILDIR_SUB=Maildir" >> mailcow.conf
      echo "MAILDIR_SUB=" >> mailcow.conf
  fi
  elif [[ ${option} == "WATCHDOG_NOTIFY_BAN" ]]; then
    if ! grep -q ${option} mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo '# Notify about banned IP. Includes whois lookup.' >> mailcow.conf
      echo "WATCHDOG_NOTIFY_BAN=y" >> mailcow.conf
  fi
  elif [[ ${option} == "WATCHDOG_EXTERNAL_CHECKS" ]]; then
    if ! grep -q ${option} mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo '# Checks if mailcow is an open relay. Requires a SAL. More checks will follow.' >> mailcow.conf
      echo '# No data is collected. Opt-in and anonymous.' >> mailcow.conf
      echo '# Will only work with unmodified mailcow setups.' >> mailcow.conf
      echo "WATCHDOG_EXTERNAL_CHECKS=n" >> mailcow.conf
  fi
  elif [[ ${option} == "SOGO_EXPIRE_SESSION" ]]; then
    if ! grep -q ${option} mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo '# SOGo session timeout in minutes' >> mailcow.conf
      echo "SOGO_EXPIRE_SESSION=480" >> mailcow.conf
  fi
  elif [[ ${option} == "REDIS_PORT" ]]; then
    if ! grep -q ${option} mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo "REDIS_PORT=127.0.0.1:7654" >> mailcow.conf
  fi
  elif [[ ${option} == "DOVECOT_MASTER_USER" ]]; then
    if ! grep -q ${option} mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo '# DOVECOT_MASTER_USER and _PASS must _both_ be provided. No special chars.' >> mailcow.conf
      echo '# Empty by default to auto-generate master user and password on start.' >> mailcow.conf
      echo '# User expands to DOVECOT_MASTER_USER@mailcow.local' >> mailcow.conf
      echo '# LEAVE EMPTY IF UNSURE' >> mailcow.conf
      echo "DOVECOT_MASTER_USER=" >> mailcow.conf
  fi
  elif [[ ${option} == "DOVECOT_MASTER_PASS" ]]; then
    if ! grep -q ${option} mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo '# LEAVE EMPTY IF UNSURE' >> mailcow.conf
      echo "DOVECOT_MASTER_PASS=" >> mailcow.conf
  fi
  elif [[ ${option} == "MAILCOW_PASS_SCHEME" ]]; then
    if ! grep -q ${option} mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo '# Password hash algorithm' >> mailcow.conf
      echo '# Only certain password hash algorithm are supported. For a fully list of supported schemes,' >> mailcow.conf
      echo '# see https://mailcow.github.io/mailcow-dockerized-docs/model-passwd/' >> mailcow.conf
      echo "MAILCOW_PASS_SCHEME=BLF-CRYPT" >> mailcow.conf
  fi
  elif ! grep -q ${option} mailcow.conf; then
    echo "Adding new option \"${option}\" to mailcow.conf"
    echo "${option}=n" >> mailcow.conf
  fi
done

echo -en "Checking internet connection... "
if ! check_online_status; then
  echo -e "\e[31mfailed\e[0m"
  exit 1
else
  echo -e "\e[32mOK\e[0m"
fi

echo -e "\e[32mChecking for newer update script...\e[0m"
SHA1_1=$(sha1sum update.sh)
git fetch origin #${BRANCH}
git checkout origin/${BRANCH} update.sh
SHA1_2=$(sha1sum update.sh)
if [[ ${SHA1_1} != ${SHA1_2} ]]; then
  echo "update.sh changed, please run this script again, exiting."
  chmod +x update.sh
  exit 2
fi

if [[ -f mailcow.conf ]]; then
  source mailcow.conf
else
  echo -e "\e[31mNo mailcow.conf - is mailcow installed?\e[0m"
  exit 1
fi

if [ ! $FORCE ]; then
  read -r -p "Are you sure you want to update mailcow: dockerized? All containers will be stopped. [y/N] " response
  if [[ ! "$response" =~ ^([yY][eE][sS]|[yY])+$ ]]; then
    echo "OK, exiting."
    exit 0
  fi
fi

echo -e "\e[32mValidating docker-compose stack configuration...\e[0m"
if ! docker-compose config -q; then
  echo -e "\e[31m\nOh no, something went wrong. Please check the error message above.\e[0m"
  exit 1
fi

echo -e "\e[32mChecking for conflicting bridges...\e[0m"
MAILCOW_BRIDGE=$(docker-compose config | grep -i com.docker.network.bridge.name | cut -d':' -f2)
while read NAT_ID; do
  iptables -t nat -D POSTROUTING $NAT_ID
done < <(iptables -L -vn -t nat --line-numbers | grep $IPV4_NETWORK | grep -E 'MASQUERADE.*all' | grep -v ${MAILCOW_BRIDGE} | cut -d' ' -f1)

DIFF_DIRECTORY=update_diffs
DIFF_FILE=${DIFF_DIRECTORY}/diff_before_update_$(date +"%Y-%m-%d-%H-%M-%S")
mv diff_before_update* ${DIFF_DIRECTORY}/ 2> /dev/null
if ! git diff-index --quiet HEAD; then
  echo -e "\e[32mSaving diff to ${DIFF_FILE}...\e[0m"
  mkdir -p ${DIFF_DIRECTORY}
  git diff --stat > ${DIFF_FILE}
  git diff >> ${DIFF_FILE}
fi

echo -e "\e[32mPrefetching images...\e[0m"
prefetch_images

echo -e "\e[32mStopping mailcow...\e[0m"
sleep 2
MAILCOW_CONTAINERS=($(docker-compose ps -q))
docker-compose down
echo -e "\e[32mChecking for remaining containers...\e[0m"
sleep 2
for container in "${MAILCOW_CONTAINERS[@]}"; do
  docker rm -f "$container" 2> /dev/null
done

# Silently fixing remote url from andryyy to mailcow
git remote set-url origin https://github.com/mailcow/mailcow-dockerized
echo -e "\e[32mCommitting current status...\e[0m"
[[ -z "$(git config user.name)" ]] && git config user.name moo
[[ -z "$(git config user.email)" ]] && git config user.email moo@cow.moo
[[ ! -z $(git ls-files data/conf/rspamd/override.d/worker-controller-password.inc) ]] && git rm data/conf/rspamd/override.d/worker-controller-password.inc
git add -u
git commit -am "Before update on ${DATE}" > /dev/null
echo -e "\e[32mFetching updated code from remote...\e[0m"
git fetch origin #${BRANCH}
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

if [[ ${NO_UPDATE_COMPOSE} == "y" ]]; then
  echo -e "\e[33mNot fetching latest docker-compose, please check for updates manually!\e[0m"
elif [[ -e /etc/alpine-release ]]; then
  echo -e "\e[33mNot fetching latest docker-compose, because you are using Alpine Linux without glibc support. Please update docker-compose via apk!\e[0m"
else
  echo -e "\e[32mFetching new docker-compose version...\e[0m"
  sleep 1
  if [[ ! -z $(which pip) && $(pip list --local 2>&1 | grep -v DEPRECATION | grep -c docker-compose) == 1 ]]; then
    true
    #prevent breaking a working docker-compose installed with pip
  elif [[ $(curl -sL -w "%{http_code}" https://www.servercow.de/docker-compose/latest.php -o /dev/null) == "200" ]]; then
    LATEST_COMPOSE=$(curl -#L https://www.servercow.de/docker-compose/latest.php)
    COMPOSE_VERSION=$(docker-compose version --short)
    if [[ "$LATEST_COMPOSE" != "$COMPOSE_VERSION" ]]; then
      COMPOSE_PATH=$(which docker-compose)
      if [[ -w ${COMPOSE_PATH} ]]; then
        curl -#L https://github.com/docker/compose/releases/download/${LATEST_COMPOSE}/docker-compose-$(uname -s)-$(uname -m) > $COMPOSE_PATH
        chmod +x $COMPOSE_PATH
      else
        echo -e "\e[33mWARNING: $COMPOSE_PATH is not writable, but new version $LATEST_COMPOSE is available (installed: $COMPOSE_VERSION)\e[0m"
      fi
    fi
  else
    echo -e "\e[33mCannot determine latest docker-compose version, skipping...\e[0m"
  fi
fi

echo -e "\e[32mFetching new images, if any...\e[0m"
sleep 2
docker-compose pull

# Fix missing SSL, does not overwrite existing files
[[ ! -d data/assets/ssl ]] && mkdir -p data/assets/ssl
cp -n -d data/assets/ssl-example/*.pem data/assets/ssl/

echo -e "Checking IPv6 settings... "
if grep -q 'SYSCTL_IPV6_DISABLED=1' mailcow.conf; then
  echo
  echo '!! IMPORTANT !!'
  echo
  echo 'SYSCTL_IPV6_DISABLED was removed due to complications. IPv6 can be disabled by editing "docker-compose.yml" and setting "enable_ipv6: true" to "enable_ipv6: false".'
  echo 'This setting will only be active after a complete shutdown of mailcow by running "docker-compose down" followed by "docker-compose up -d".'
  echo
  echo '!! IMPORTANT !!'
  echo
  read -p "Press any key to continue..." < /dev/tty
fi

# Checking for old project name bug
sed -i --follow-symlinks 's#COMPOSEPROJECT_NAME#COMPOSE_PROJECT_NAME#g' mailcow.conf
# Checking old, wrong bindings
sed -i --follow-symlinks 's/HTTP_BIND=0.0.0.0/HTTP_BIND=/g' mailcow.conf
sed -i --follow-symlinks 's/HTTPS_BIND=0.0.0.0/HTTPS_BIND=/g' mailcow.conf

# Fix Rspamd maps
if [ -f data/conf/rspamd/custom/global_from_blacklist.map ]; then
  mv data/conf/rspamd/custom/global_from_blacklist.map data/conf/rspamd/custom/global_smtp_from_blacklist.map
fi
if [ -f data/conf/rspamd/custom/global_from_whitelist.map ]; then
  mv data/conf/rspamd/custom/global_from_whitelist.map data/conf/rspamd/custom/global_smtp_from_whitelist.map
fi

# Fix deprecated metrics.conf
if [ -f "data/conf/rspamd/local.d/metrics.conf" ]; then
  if [ ! -z "$(git diff --name-only origin/master data/conf/rspamd/local.d/metrics.conf)" ]; then
    echo -e "\e[33mWARNING\e[0m - Please migrate your customizations of data/conf/rspamd/local.d/metrics.conf to actions.conf and groups.conf after this update."
    echo "The deprecated configuration file metrics.conf will be moved to metrics.conf_deprecated after updating mailcow."
  fi
  mv data/conf/rspamd/local.d/metrics.conf data/conf/rspamd/local.d/metrics.conf_deprecated
fi

if [[ ${SKIP_START} == "y" ]]; then
  echo -e "\e[33mNot starting mailcow, please run \"docker-compose up -d --remove-orphans\" to start mailcow.\e[0m"
else
  echo -e "\e[32mStarting mailcow...\e[0m"
  sleep 2
  docker-compose up -d --remove-orphans
fi

echo -e "\e[32mCollecting garbage...\e[0m"
docker_garbage

#echo "In case you encounter any problem, hard-reset to a state before updating mailcow:"
#echo
#git reflog --color=always | grep "Before update on "
#echo
#echo "Use \"git reset --hard hash-on-the-left\" and run docker-compose up -d afterwards."
