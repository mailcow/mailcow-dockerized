#!/usr/bin/env bash
# renovate: datasource=github-releases depName=nextcloud/server versioning=semver extractVersion=^v(?<version>.*)$
NEXTCLOUD_VERSION=30.0.2

display_warning() {
    local message=("$@")
    local max_length=0

    for line in "${message[@]}"; do
        if (( ${#line} > max_length )); then
            max_length=${#line}
        fi
    done

    local border=$(printf '%*s' "$((max_length + 4))" '' | tr ' ' '#')

    echo -e "\e[31m${border}"
    for line in "${message[@]}"; do
        printf "\e[31m# %-*s #\n" "$max_length" "$line"
    done
    echo -e "\e[31m${border}"
    echo -e "\e[0m"
}

display_warning "WARNING: This Script is deprecated and will be removed in December 2024!" \
                "mailcow will drop this installation/maintenance script within December 2024..." \
                "To ensure you can still use your Nextcloud Datas, please migrate to a standalone" \
                "Nextcloud instance either on a new Host or this host." \
                "You can either use Nextcloud in Docker or install it manually." \
                " "\
                "mailcow will NOT DELETE any Nextcloud Data, even when this script was removed!!"

echo -e "Waiting 5 seconds before continuing..."


sleep 5

echo -ne "Checking prerequisites..."
sleep 1
for bin in curl dirmngr tar bzip2; do
  if [[ -z $(which ${bin}) ]]; then echo -ne "\r\033[31mCannot find ${bin}, exiting...\033[0m\n"; exit 1; fi
done
echo -ne "\r\033[32mFound all prerequisites! Continuing...\033[0m\n"

[[ -z ${1} ]] && NC_HELP=y

while [ "$1" != '' ]; do
  if [[ $# -ne 1 ]]; then
      echo -e "\033[31mPlease use only one parameter at the same time!\033[0m" >&2
      exit 2
  fi
  case "${1}" in
    -p|--purge) NC_PURGE=y && shift;;
    -i|--install) NC_INSTALL=y && shift;;
    -u|--update)  NC_UPDATE=y && shift;;
    -r|--resetpw) NC_RESETPW=y && shift;;
    -h|--help) NC_HELP=y && shift;;
    *) echo "Unknown parameter: ${1}" && shift;;
  esac
done

if [[ ${NC_HELP} == "y" ]]; then
  printf 'Usage:\n\n'
  printf '  -p|--purge\n    Purge Nextcloud\n'
  printf '  -i|--install\n    Install Nextcloud\n'
  printf '  -u|--update\n    Update Nextcloud\n'
  printf '  -r|--resetpw\n    Reset password\n\n'
  exit 0
fi

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd ${SCRIPT_DIR}/../
source mailcow.conf

if [[ ${NC_PURGE} == "y" ]]; then
  read -r -p "Are you sure you want to purge Nextcloud? [y/N] " response
  response=${response,,}
  if [[ ! "$response" =~ ^(yes|y)$ ]]; then
    echo "OK, aborting."
    exit 1
  fi

  echo -e "\033[33mDetecting Database information...\033[0m"
  if [[ $(docker exec -it $(docker ps -f name=mysql-mailcow -q) mysql -uroot -p${DBROOT} -e "Show databases" | grep "nextcloud") ]]; then
    echo -e "\033[32mFound seperate Nextcloud database (newer scheme)!\033[0m"
    echo -e "\033[31mPurging...\033[0m"
    docker exec -it $(docker ps -f name=mysql-mailcow -q) mysql -uroot -p${DBROOT} -e "DROP DATABASE nextcloud;" > /dev/null
    docker exec -it $(docker ps -f name=mysql-mailcow -q) mysql -uroot -p${DBROOT} -e "DROP USER 'nextcloud'@'%';" > /dev/null
  elif [[ $(docker exec -it $(docker ps -f name=mysql-mailcow -q) mysql -uroot -p${DBROOT} mailcow -e "SHOW TABLES LIKE 'oc_%'") && $? -eq 0 ]]; then
    echo -e "\033[32mFound Nextcloud (oc) tables inside of mailcow database (old scheme)!\033[0m"
    echo -e "\033[31mPurging...\033[0m"
    docker exec -it $(docker ps -f name=mysql-mailcow -q) mysql -uroot -p${DBROOT} -e \
     "$(docker exec -it $(docker ps -f name=mysql-mailcow -q) mysql -uroot -p${DBROOT} -e "SELECT IFNULL(GROUP_CONCAT('DROP TABLE ', TABLE_SCHEMA, '.', TABLE_NAME SEPARATOR ';'),'SELECT NULL;') FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME LIKE 'oc_%' AND TABLE_SCHEMA = '${DBNAME}';" -BN)" > /dev/null
  elif [[ $(docker exec -it $(docker ps -f name=mysql-mailcow -q) mysql -uroot -p${DBROOT} mailcow -e "SHOW TABLES LIKE 'nc_%'") && $? -eq 0 ]]; then
    echo -e "\033[32mFound Nextcloud (nc) tables inside of mailcow database (old scheme)!\033[0m"
    echo -e "\033[31mPurging...\033[0m"
    docker exec -it $(docker ps -f name=mysql-mailcow -q) mysql -uroot -p${DBROOT} -e \
     "$(docker exec -it $(docker ps -f name=mysql-mailcow -q) mysql -uroot -p${DBROOT} -e "SELECT IFNULL(GROUP_CONCAT('DROP TABLE ', TABLE_SCHEMA, '.', TABLE_NAME SEPARATOR ';'),'SELECT NULL;') FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME LIKE 'nc_%' AND TABLE_SCHEMA = '${DBNAME}';" -BN)" > /dev/null
  else
    echo -e "\033[31mError: No Nextcloud databases/tables found!"
    echo -e "\033[33mNot purging anything...\033[0m"
    exit 1
  fi
  docker exec -it $(docker ps -f name=redis-mailcow -q) /bin/sh -c "cat <<EOF | redis-cli -a ${REDISPASS} --no-auth-warning
SELECT 10
FLUSHDB
EOF
"
  if [ -d ./data/web/nextcloud/config ]; then
    mv ./data/web/nextcloud/config/ ./data/conf/nextcloud-config-folder-$(date +%s).bak
  fi
  [[ -d ./data/web/nextcloud ]] && rm -rf ./data/web/nextcloud

  [[ -f ./data/conf/nginx/site.nextcloud.custom ]] && mv ./data/conf/nginx/site.nextcloud.custom ./data/conf/nginx/site.nextcloud.custom-$(date +%s).bak
  [[ -f ./data/conf/nginx/nextcloud.conf ]] && mv ./data/conf/nginx/nextcloud.conf ./data/conf/nginx/nextcloud.conf-$(date +%s).bak

  docker restart $(docker ps -aqf name=nginx-mailcow)

  echo -e "\033[32mNextcloud has been uninstalled sucessfully!\033[0m"

elif [[ ${NC_UPDATE} == "y" ]]; then
  read -r -p "Are you sure you want to update Nextcloud (with Nextclouds own updater)? [y/N] " response
  response=${response,,}
  if [[ ! "$response" =~ ^(yes|y)$ ]]; then
    echo "OK, aborting."
    exit 1
  fi

  if [ ! -f data/web/nextcloud/occ ]; then
    echo -e "\033[31mError: Nextcloud occ not found. Is Nextcloud installed?\033[0m"
    exit 1
  fi
  if grep -Pq 'This version of Nextcloud is not compatible with (?:PHP)?(?>=?)(?:PHP)?(?>.+)' <<<$(docker exec -it -u www-data $(docker ps -f name=php-fpm-mailcow -q) bash -c "/web/nextcloud/occ --no-warnings status"); then
    echo -e "\033[31mError: This version of Nextcloud is not compatible with the current PHP version of php-fpm-mailcow, we'll fix it\033[0m"
    wget -q https://raw.githubusercontent.com/nextcloud/server/v26.0.0/lib/versioncheck.php -O ./data/web/nextcloud/lib/versioncheck.php
	echo -e "\e[33mPlease restart the update again.\e[0m"
  elif ! grep -q 'installed: true' <<<$(docker exec -it -u www-data $(docker ps -f name=php-fpm-mailcow -q) bash -c "/web/nextcloud/occ --no-warnings status"); then
    echo -e "\033[31mError: Nextcloud seems not to be installed.\033[0m"
    exit 1
  else
    docker exec -it -u www-data $(docker ps -f name=php-fpm-mailcow -q) bash -c "php /web/nextcloud/updater/updater.phar"
    NC_SUBD=$(docker exec -i -u www-data $(docker ps -f name=php-fpm-mailcow -q) /web/nextcloud/occ config:system:get overwritehost)
    mv ./data/conf/nginx/nextcloud.conf ./data/conf/nginx/nextcloud.conf-$(date +%s).bak
    cp ./data/assets/nextcloud/nextcloud.conf ./data/conf/nginx/
    sed -i "s/NC_SUBD/${NC_SUBD}/g" ./data/conf/nginx/nextcloud.conf
  fi

elif [[ ${NC_INSTALL} == "y" ]]; then
  NC_SUBD=
  while [[ -z ${NC_SUBD} ]]; do
    read -p "Subdomain to run Nextcloud from [format: nextcloud.domain.tld]: " NC_SUBD
  done
  if ! ping -q -c2 ${NC_SUBD} > /dev/null 2>&1 ; then
    read -p "Cannot ping subdomain, continue anyway? [y|N] " NC_CONT_FAIL
    [[ ! ${NC_CONT_FAIL,,} =~ ^(yes|y)$ ]] && { echo "Ok, exiting..."; exit 1; }
  fi

  echo -e "\033[33mDownloading \033[34mNextcloud ${NEXTCLOUD_VERSION}\033[33m...\033[0m"
  curl -L# -o nextcloud.tar.bz2 "https://download.nextcloud.com/server/releases/nextcloud-$NEXTCLOUD_VERSION.tar.bz2" || { echo "Failed to download Nextcloud archive."; exit 1; } \
    && tar -xjf nextcloud.tar.bz2 -C ./data/web/ \
    && rm nextcloud.tar.bz2 \
    && mkdir -p ./data/web/nextcloud/data \
    && chmod +x ./data/web/nextcloud/occ

  echo -e "\033[33mCreating 'nextcloud' database...\033[0m"
  NC_DBPASS=$(</dev/urandom tr -dc A-Za-z0-9 2> /dev/null | head -c 28)
  NC_DBUSER=nextcloud
  NC_DBNAME=nextcloud

  echo -ne "[1/3] Creating 'nextcloud' database"
  docker exec -it $(docker ps -f name=mysql-mailcow -q) mysql -uroot -p${DBROOT} -e "CREATE DATABASE ${NC_DBNAME};"
  sleep 2
  echo -ne "\r[2/3] Creating 'nextcloud' database user"
  docker exec -it $(docker ps -f name=mysql-mailcow -q) mysql -uroot -p${DBROOT} -e "CREATE USER '${NC_DBUSER}'@'%' IDENTIFIED BY '${NC_DBPASS}';"
  sleep 2
  echo -ne "\r[3/3] Granting 'nextcloud' user all permissions on database 'nextcloud'"
  docker exec -it $(docker ps -f name=mysql-mailcow -q) mysql -uroot -p${DBROOT} -e "GRANT ALL PRIVILEGES ON ${NC_DBNAME}.* TO '${NC_DBUSER}'@'%';"
  sleep 2

  echo ""
  echo -e "\033[33mInstalling Nextcloud...\033[0m"
  ADMIN_NC_PASS=$(</dev/urandom tr -dc A-Za-z0-9 2> /dev/null | head -c 28)

  echo -ne "[1/4] Setting correct permissions for www-data"
  docker exec -it $(docker ps -f name=php-fpm-mailcow -q) /bin/bash -c "chown -R www-data:www-data /web/nextcloud"
  sleep 2
  echo -ne "\r[2/4] Running occ maintenance:install to install Nextcloud"
  docker exec -it -u www-data $(docker ps -f name=php-fpm-mailcow -q) /web/nextcloud/occ --no-warnings maintenance:install \
    --database mysql \
    --database-host mysql \
    --database-name ${NC_DBNAME} \
    --database-user ${NC_DBUSER} \
    --database-pass ${NC_DBPASS} \
    --admin-user admin \
    --admin-pass ${ADMIN_NC_PASS} \
    --data-dir /web/nextcloud/data > /dev/null 2>&1

  echo -ne "\r[3/4] Setting custom parameters inside the Nextcloud config file"
  echo ""
  docker exec -it -u www-data $(docker ps -f name=php-fpm-mailcow -q) bash -c "/web/nextcloud/occ --no-warnings config:system:set redis host --value=redis --type=string; \
    /web/nextcloud/occ --no-warnings config:system:set redis port --value=6379 --type=integer; \
    /web/nextcloud/occ --no-warnings config:system:set redis timeout --value=0.0 --type=integer; \
    /web/nextcloud/occ --no-warnings config:system:set redis dbindex --value=10 --type=integer; \
    /web/nextcloud/occ --no-warnings config:system:set memcache.locking --value='\OC\Memcache\Redis' --type=string; \
    /web/nextcloud/occ --no-warnings config:system:set memcache.local --value='\OC\Memcache\Redis' --type=string; \
    /web/nextcloud/occ --no-warnings config:system:set trusted_domains 1 --value=${NC_SUBD}; \
    /web/nextcloud/occ --no-warnings config:system:set trusted_proxies 0 --value=${IPV6_NETWORK}; \
    /web/nextcloud/occ --no-warnings config:system:set trusted_proxies 1 --value=${IPV4_NETWORK}.0/24; \
    /web/nextcloud/occ --no-warnings config:system:set overwritehost --value=${NC_SUBD}; \
    /web/nextcloud/occ --no-warnings config:system:set overwriteprotocol --value=https; \
    /web/nextcloud/occ --no-warnings config:system:set overwritewebroot --value=/; \
    /web/nextcloud/occ --no-warnings config:system:set mail_smtpmode --value=smtp; \
    /web/nextcloud/occ --no-warnings config:system:set mail_smtpauthtype --value=LOGIN; \
    /web/nextcloud/occ --no-warnings config:system:set mail_from_address --value=nextcloud; \
    /web/nextcloud/occ --no-warnings config:system:set mail_domain --value=${MAILCOW_HOSTNAME}; \
    /web/nextcloud/occ --no-warnings config:system:set mail_smtphost --value=postfix; \
    /web/nextcloud/occ --no-warnings config:system:set mail_smtpport --value=588; \
    /web/nextcloud/occ --no-warnings config:system:set mail_smtpstreamoptions ssl verify_peer --value=false --type=boolean
    /web/nextcloud/occ --no-warnings config:system:set mail_smtpstreamoptions ssl verify_peer_name --value=false --type=boolean
    /web/nextcloud/occ --no-warnings db:convert-filecache-bigint -n"

    # Not installing by default, broke too often
    #/web/nextcloud/occ --no-warnings app:install user_external; \
    #/web/nextcloud/occ --no-warnings config:system:set user_backends 0 arguments 0 --value={dovecot:143/imap/tls/novalidate-cert}; \
    #/web/nextcloud/occ --no-warnings config:system:set user_backends 0 class --value=OC_User_IMAP; \

    echo -e "\r[4/4] Enabling Nginx Configuration"
    cp ./data/assets/nextcloud/nextcloud.conf ./data/conf/nginx/
    sed -i "s/NC_SUBD/${NC_SUBD}/g" ./data/conf/nginx/nextcloud.conf
    sleep 2

  echo ""
  echo -e "\033[33mFinalizing installation...\033[0m"
  docker restart $(docker ps -aqf name=nginx-mailcow)

  echo ""
  echo "******************************************"
  echo "*        SAVE THESE CREDENTIALS          *"
  echo "*    INSTALL DATE: $(date +%Y-%m-%d_%H-%M-%S)   *"
  echo "******************************************"
  echo ""
  echo -e "\033[36mDatabase name:      ${NC_DBNAME}\033[0m"
  echo -e "\033[36mDatabase user:      ${NC_DBUSER}\033[0m"
  echo -e "\033[36mDatabase password:  ${NC_DBPASS}\033[0m"
  echo ""
  echo -e "\033[31mUI admin password:  ${ADMIN_NC_PASS}\033[0m"
  echo ""


elif [[ ${NC_RESETPW} == "y" ]]; then
    printf 'You are about to set a new password for a Nextcloud user.\n\nDo not use this option if your Nextcloud is configured to use mailcow for authentication.\nSet a new password for the corresponding mailbox in mailcow, instead.\n\n'
    read -r -p "Continue? [y/N] " response
    response=${response,,}
    if [[ ! "$response" =~ ^(yes|y)$ ]]; then
      echo "OK, aborting."
      exit 1
    fi

    NC_USER=
    while [[ -z ${NC_USER} ]]; do
      read -p "Enter the username: " NC_USER
    done
    docker exec -it -u www-data $(docker ps -f name=php-fpm-mailcow -q) /web/nextcloud/occ user:resetpassword ${NC_USER}
fi
