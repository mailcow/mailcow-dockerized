#!/bin/bash

for bin in curl dirmngr; do
  if [[ -z $(which ${bin}) ]]; then echo "Cannot find ${bin}, exiting..."; exit 1; fi
done

[[ -z ${1} ]] && NC_HELP=y

while [ "$1" != '' ]; do
  case "${1}" in
    -p|--purge) NC_PURGE=y && shift;;
    -i|--install) NC_INSTALL=y && shift;;
    -h|--help) NC_HELP=y && shift;;
    *) echo "Unknown parameter: ${1}" && shift;;
  esac
done

if [[ ${NC_HELP} == "y" ]]; then
  printf 'Usage:\n\n'
  printf '  -p|--purge\n    Purge Nextcloud\n'
  printf '  -i|--install\n    Install Nextcloud\n\n'
  exit 0
fi

[[ ${NC_PURGE} == "y" ]] && [[ ${NC_INSTALL} == "y" ]] && { echo "Cannot use -p and -i at the same time"; }

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd ${SCRIPT_DIR}/../
source mailcow.conf

if [[ ${NC_PURGE} == "y" ]]; then

	docker exec -it $(docker ps -f name=mysql-mailcow -q) mysql -uroot -p${DBROOT} -e \
	  "$(docker exec -it $(docker ps -f name=mysql-mailcow -q) mysql -uroot -p${DBROOT} -e "SELECT GROUP_CONCAT('DROP TABLE ', TABLE_SCHEMA, '.', TABLE_NAME SEPARATOR ';') FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME LIKE 'nc_%' AND TABLE_SCHEMA = '${DBNAME}';" -BN)"
	docker exec -it $(docker ps -f name=redis-mailcow -q) /bin/sh -c 'redis-cli KEYS "*nextcloud*" | xargs redis-cli DEL'
	if [ -d ./data/web/nextcloud/config ]; then
	  mv ./data/web/nextcloud/config/ ./data/conf/nextcloud-config-folder-$(date +%s).bak
	fi
	[[ -d ./data/web/nextcloud ]] && rm -rf ./data/web/nextcloud

	[[ -f ./data/conf/nginx/site.nextcloud.custom ]] && mv ./data/conf/nginx/site.nextcloud.custom ./data/conf/nginx/site.nextcloud.custom-$(date +%s).bak
	[[ -f ./data/conf/nginx/nextcloud.conf ]] && mv ./data/conf/nginx/nextcloud.conf ./data/conf/nginx/nextcloud.conf-$(date +%s).bak

  docker restart $(docker ps -aqf name=nginx-mailcow)

elif [[ ${NC_INSTALL} == "y" ]]; then

	NC_TYPE=
	while [[ ! ${NC_TYPE} =~ ^subfolder$|^subdomain$ ]]; do
		read -p "Configure as subdomain or subfolder? [subdomain/subfolder] " NC_TYPE
	done


	if [[ ${NC_TYPE} == "subdomain" ]]; then
		NC_SUBD=
	    while [[ -z ${NC_SUBD} ]]; do
    	    read -p "Which subdomain? [format: nextcloud.domain.tld] " NC_SUBD
    	done
		if ! ping -q -c2 ${NC_SUBD} > /dev/null 2>&1 ; then
			read -p "Cannot ping subdomain, continue anyway? [y|N] " NC_CONT_FAIL
			[[ ! ${NC_CONT_FAIL,,} =~ ^(yes|y)$ ]] && { echo "Ok, exiting..."; exit 1; }
		fi
	fi

	ADMIN_NC_PASS=$(</dev/urandom tr -dc A-Za-z0-9 | head -c 28)

	curl -L# -o nextcloud.tar.bz2 "https://download.nextcloud.com/server/releases/latest-13.tar.bz2" || { echo "Failed to download Nextcloud archive."; exit 1; } \
	  && tar -xjf nextcloud.tar.bz2 -C ./data/web/ \
	  && rm nextcloud.tar.bz2 \
	  && rm -rf ./data/web/nextcloud/updater \
	  && mkdir -p ./data/web/nextcloud/data \
	  && mkdir -p ./data/web/nextcloud/custom_apps \
	  && chmod +x ./data/web/nextcloud/occ

	docker exec -it $(docker ps -f name=php-fpm-mailcow -q) /bin/bash -c "chown -R www-data:www-data /web/nextcloud/data /web/nextcloud/config /web/nextcloud/apps /web/nextcloud/custom_apps"
	docker exec -it -u www-data $(docker ps -f name=php-fpm-mailcow -q) /web/nextcloud/occ maintenance:install \
	  --database mysql \
	  --database-host mysql \
	  --database-name ${DBNAME} \
	  --database-user ${DBUSER} \
	  --database-pass ${DBPASS} \
	  --database-table-prefix nc_ \
	  --admin-user admin \
	  --admin-pass ${ADMIN_NC_PASS} \
      --data-dir /web/nextcloud/data

	docker exec -it -u www-data $(docker ps -f name=php-fpm-mailcow -q) bash -c "/web/nextcloud/occ config:system:set redis host --value=redis --type=string; \
	  /web/nextcloud/occ config:system:set redis port --value=6379 --type=integer; \
          /web/nextcloud/occ config:system:set redis timeout --value=0.0 --type=integer; \
	  /web/nextcloud/occ config:system:set memcache.locking --value='\OC\Memcache\Redis' --type=string; \
	  /web/nextcloud/occ config:system:set memcache.local --value='\OC\Memcache\Redis' --type=string; \
	  /web/nextcloud/occ config:system:set trusted_domains 1 --value=${MAILCOW_HOSTNAME}; \
	  /web/nextcloud/occ config:system:set trusted_proxies 0 --value=${IPV6_NETWORK}; \
	  /web/nextcloud/occ config:system:set trusted_proxies 1 --value=${IPV4_NETWORK}.0/24; \
	  /web/nextcloud/occ config:system:set overwritewebroot --value=/nextcloud; \
	  /web/nextcloud/occ config:system:set overwritehost --value=${MAILCOW_HOSTNAME}; \
	  /web/nextcloud/occ config:system:set overwriteprotocol --value=https; \
	  /web/nextcloud/occ config:system:set mail_smtpmode --value=smtp; \
	  /web/nextcloud/occ config:system:set mail_smtpauthtype --value=LOGIN; \
	  /web/nextcloud/occ config:system:set mail_from_address --value=nextcloud; \
	  /web/nextcloud/occ config:system:set mail_domain --value=${MAILCOW_HOSTNAME}; \
	  /web/nextcloud/occ config:system:set mail_smtphost --value=postfix; \
	  /web/nextcloud/occ config:system:set mail_smtpport --value=588
	  /web/nextcloud/occ app:enable user_external
	  /web/nextcloud/occ config:system:set user_backends 0 arguments 0 --value={dovecot:143/imap/tls/novalidate-cert}
	  /web/nextcloud/occ config:system:set user_backends 0 class --value=OC_User_IMAP"

	if [[ ${NC_TYPE} == "subdomain" ]]; then
		docker exec -it -u www-data $(docker ps -f name=php-fpm-mailcow -q) /web/nextcloud/occ config:system:set trusted_domains 1 --value=${NC_SUBD}
		docker exec -it -u www-data $(docker ps -f name=php-fpm-mailcow -q) /web/nextcloud/occ config:system:set overwritewebroot --value=/
		docker exec -it -u www-data $(docker ps -f name=php-fpm-mailcow -q) /web/nextcloud/occ config:system:set overwritehost --value=${NC_SUBD}
		cp ./data/assets/nextcloud/nextcloud.conf ./data/conf/nginx/
		sed -i "s/NC_SUBD/${NC_SUBD}/g" ./data/conf/nginx/nextcloud.conf
	elif [[ ${NC_TYPE} == "subfolder" ]]; then
		cp ./data/assets/nextcloud/site.nextcloud.custom ./data/conf/nginx/
	fi

  docker restart $(docker ps -aqf name=nginx-mailcow)

	echo "Login as admin with password: ${ADMIN_NC_PASS}"

fi
