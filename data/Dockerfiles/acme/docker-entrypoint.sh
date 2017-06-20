#!/bin/bash

ACME_BASE=/var/lib/acme
SSL_EXAMPLE=/var/lib/ssl-example
mkdir -p ${ACME_BASE}/acme/private

restart_containers(){
	for container in $*; do
		curl -X POST \
			--unix-socket /var/run/docker.sock \
			"http/containers/${container}/restart"
	done
}

if [[ -f ${ACME_BASE}/cert.pem ]]; then
	if [[ $(openssl x509 -in ${ACME_BASE}/cert.pem -noout -issuer) != *"Let's Encrypt"* &&
		  $(openssl x509 -in ${ACME_BASE}/cert.pem -noout -issuer) != *"mailcow"* ]]; then
		echo "Skipping ACME client"
		exit 0
	else
		declare -a SAN_ARRAY_NOW
		SAN_NAMES=$(openssl x509 -noout -text -in ${ACME_BASE}/cert.pem | awk '/X509v3 Subject Alternative Name/ {getline;gsub(/ /, "", $0); print}' | tr -d "DNS:")
		if [[ ! -z ${SAN_NAMES} ]]; then
			IFS=',' read -a SAN_ARRAY_NOW <<< ${SAN_NAMES}
		fi
	fi
else
	if [[ -f ${ACME_BASE}/acme/fullchain.pem ]] && [[ -f ${ACME_BASE}/acme/private/privkey.pem ]]; then
		cp ${ACME_BASE}/acme/fullchain.pem ${ACME_BASE}/cert.pem
		cp ${ACME_BASE}/acme/private/privkey.pem ${ACME_BASE}/key.pem
	else
		cp ${SSL_EXAMPLE}/cert.pem ${ACME_BASE}/cert.pem
		cp ${SSL_EXAMPLE}/key.pem ${ACME_BASE}/key.pem
	fi
fi

while true; do

	declare -a SQL_DOMAIN_ARR
    declare -a VALIDATED_CONFIG_DOMAINS
	declare -a ADDITIONAL_VALIDATED_SAN
	IFS=' ' read -r -a ADDITIONAL_SAN_ARR <<< "${ADDITIONAL_SAN}"
	IPV4=$(curl -4s https://mailcow.email/ip.php)

	while read line; do
		SQL_DOMAIN_ARR+=("${line}")
	done < <(mysql -h mysql-mailcow -u ${DBUSER} -p${DBPASS} ${DBNAME} -e "SELECT domain FROM domain" -Bs)

	for SQL_DOMAIN in "${SQL_DOMAIN_ARR[@]}"; do
		A_CONFIG=$(dig A autoconfig.${SQL_DOMAIN} +short)
		if [[ ! -z ${A_CONFIG} ]]; then
			echo "Found A record for autoconfig.${SQL_DOMAIN}: ${A_CONFIG}"
			if [[ ${IPV4} == ${A_CONFIG} ]]; then
				echo "Confirmed A record autoconfig.${SQL_DOMAIN}"
				VALIDATED_CONFIG_DOMAINS+=("autoconfig.${SQL_DOMAIN}")
			else
				echo "Cannot match Your IP against hostname autoconfig.${SQL_DOMAIN}"
			fi
		else
			echo "No A record for autoconfig.${SQL_DOMAIN} found"
		fi

        A_DISCOVER=$(dig A autodiscover.${SQL_DOMAIN} +short)
		if [[ ! -z ${A_DISCOVER} ]]; then
			echo "Found A record for autodiscover.${SQL_DOMAIN}: ${A_CONFIG}"
			if [[ ${IPV4} == ${A_DISCOVER} ]]; then
				echo "Confirmed A record autodiscover.${SQL_DOMAIN}"
				VALIDATED_CONFIG_DOMAINS+=("autodiscover.${SQL_DOMAIN}")
			else
				echo "Cannot match Your IP against hostname autodiscover.${SQL_DOMAIN}"
			fi
		else
			echo "No A record for autodiscover.${SQL_DOMAIN} found"
		fi
	done

	for SAN in "${ADDITIONAL_SAN_ARR[@]}"; do
		A_SAN=$(dig A ${SAN} +short)
		if [[ ! -z ${A_SAN} ]]; then
			echo "Found A record for ${SAN}: ${A_SAN}"
			if [[ ${IPV4} == ${A_SAN} ]]; then
				echo "Confirmed A record ${SAN}"
				ADDITIONAL_VALIDATED_SAN+=("${SAN}")
			else
				echo "Cannot match Your IP against hostname ${SAN}"
			fi
		else
			echo "No A record for ${SAN} found"
		fi
	done

	ORPHANED_SAN=($(echo ${SAN_ARRAY_NOW[*]} ${VALIDATED_CONFIG_DOMAINS[*]} ${ADDITIONAL_VALIDATED_SAN[*]} ${MAILCOW_HOSTNAME} | tr ' ' '\n' | sort | uniq -u ))
	if [[ ! -z ${ORPHANED_SAN[*]} ]]; then
		DATE=$(date +%Y-%m-%d_%H_%M_%S)
		echo "Found orphaned SAN in certificate, moving old files to ${ACME_BASE}/acme/private/${DATE}/"
		mkdir -p ${ACME_BASE}/acme/private/${DATE}/
		mv ${ACME_BASE}/acme/private/privkey.pem ${ACME_BASE}/acme/private/${DATE}/
		mv ${ACME_BASE}/acme/fullchain.pem ${ACME_BASE}/acme/private/${DATE}/
        mv ${ACME_BASE}/acme/cert.pem ${ACME_BASE}/acme/private/${DATE}/
	fi

	acme-client \
		-v -e -b -N -n \
		-f ${ACME_BASE}/acme/private/account.key \
		-k ${ACME_BASE}/acme/private/privkey.pem \
		-c ${ACME_BASE}/acme \
		${MAILCOW_HOSTNAME} ${VALIDATED_CONFIG_DOMAINS[*]} ${ADDITIONAL_VALIDATED_SAN[*]}

	case "$?" in
		0) # new certs
			# cp the new certificates and keys
			cp ${ACME_BASE}/acme/fullchain.pem ${ACME_BASE}/cert.pem
			cp ${ACME_BASE}/acme/private/privkey.pem ${ACME_BASE}/key.pem

			# restart docker containers
			restart_containers ${CONTAINERS_RESTART}
			;;
		1) # failure
			exit 1;;
		2) # no change
			;;
		*) # unspecified
			exit 1;;
	esac

	echo "ACME certificate validation done. Sleeping for another day."
	sleep 86400

done
