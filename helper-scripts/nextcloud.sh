#!/bin/bash

[[ -z ${1} ]] && { echo "No parameters given"; exit 1; }

for bin in curl dirmngr; do
  if [[ -z $(which ${bin}) ]]; then echo "Cannot find ${bin}, exiting..."; exit 1; fi
done

while [ "$1" != '' ]; do
  case "${1}" in
    -p|--purge) NC_PURGE=y && shift;;
    -i|--install) NC_INSTALL=y && shift;;
    *) echo "Unknown parameter: ${1}" && shift;;
  esac
done

[[ ${NC_PURGE} == "y" ]] && [[ ${NC_INSTALL} == "y" ]] && { echo "Cannot use -p and -i at the same time"; }

source ./mailcow.conf

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

	docker-compose restart nginx-mailcow

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
	NEXTCLOUD_VERSION=$(curl -s https://www.servercow.de/nextcloud/latest.php)

	[[ -z ${NEXTCLOUD_VERSION} ]] && { echo "Error, cannot determine nextcloud version, exiting..."; exit 1; }

	curl -L# -o nextcloud.tar.bz2 "https://download.nextcloud.com/server/releases/nextcloud-${NEXTCLOUD_VERSION}.tar.bz2" \
	  && curl -L# -o nextcloud.tar.bz2.asc "https://download.nextcloud.com/server/releases/nextcloud-${NEXTCLOUD_VERSION}.tar.bz2.asc" \
	  && export GNUPGHOME="$(mktemp -d)" \
	  && gpg --keyserver ha.pool.sks-keyservers.net --recv-keys 28806A878AE423A28372792ED75899B9A724937A \
	  && gpg --batch --verify nextcloud.tar.bz2.asc nextcloud.tar.bz2 \
	  && rm -r "$GNUPGHOME" nextcloud.tar.bz2.asc \
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
	  /web/nextcloud/occ config:system:set memcache.locking --value='\OC\Memcache\Redis' --type=string; \
	  /web/nextcloud/occ config:system:set memcache.local --value='\OC\Memcache\Redis' --type=string; \
	  /web/nextcloud/occ config:system:set trusted_proxies 0 --value=fd4d:6169:6c63:6f77::1; \
	  /web/nextcloud/occ config:system:set trusted_proxies 1 --value=172.22.1.0/24; \
	  /web/nextcloud/occ config:system:set overwritewebroot --value=/nextcloud; \
	  /web/nextcloud/occ config:system:set overwritehost --value=${MAILCOW_HOSTNAME}; \
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
		docker exec -it -u www-data $(docker ps -f name=php-fpm-mailcow -q) /web/nextcloud/occ config:system:set overwritewebroot --value=/
		docker exec -it -u www-data $(docker ps -f name=php-fpm-mailcow -q) /web/nextcloud/occ config:system:set overwritehost --value=nextcloud.develcow.de
		cp ./data/assets/nextcloud/nextcloud.conf ./data/conf/nginx/
		sed -i 's/NC_SUBD/${NC_SUBD}/g' ./data/conf/nginx/nextcloud.conf
	elif [[ ${NC_TYPE} == "subfolder" ]]; then
		cp ./data/assets/nextcloud/site.nextcloud.custom ./data/conf/nginx/
	fi

	docker-compose restart nginx-mailcow

	echo "Login as admin with password: ${ADMIN_NC_PASS}"

fi
