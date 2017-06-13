#!/bin/bash

ACME_BASE=/var/lib/acme
mkdir -p ${ACME_BASE}/acme/private

restart_containers(){
	for container in $*; do
		curl -X POST \
			--unix-socket /var/run/docker.sock \
			"http/containers/${container}/restart"
	done
}

while true; do

	# Autodiscover and Autoconfig (Thunderbird)
	declare -a SQL_DOMAIN_ARR
	declare -a DOMAIN_ARR
	declare -a DOMAIN_ARR

	while read line; do
		SQL_DOMAIN_ARR+=("${line}")
	done < <(mysql -h mysql-mailcow -u ${DBUSER} -p${DBPASS} ${DBNAME} -e "SELECT domain FROM domain" -Bs)

	for SQL_DOMAIN in "${SQL_DOMAIN_ARR[@]}"; do
		IPV4=$(curl -4s https://mailcow.email/ip.php)
		A_CONFIG=$(dig A autoconfig.${SQL_DOMAIN} +short @208.67.220.222)
		if [[ ! -z ${A_CONFIG} ]]; then
			echo "Found A record for autoconfig.${SQL_DOMAIN}: ${A_CONFIG}"
			if [[ ${IPV4} == ${A_CONFIG} ]]; then
				echo "Confirmed A record autoconfig.${SQL_DOMAIN}"
				CONFIG_DOMAINS+=("autoconfig.${SQL_DOMAIN}")
			else
				echo "Cannot match Your IP against hostname autoconfig.${SQL_DOMAIN}"
			fi
		else
			echo "No A record for autoconfig.${SQL_DOMAIN} found"
		fi

        A_DISCOVER=$(dig A autodiscover.${SQL_DOMAIN} +short @208.67.220.222)
		if [[ ! -z ${A_DISCOVER} ]]; then
			echo "Found A record for autodiscover.${SQL_DOMAIN}: ${A_CONFIG}"
			if [[ ${IPV4} == ${A_DISCOVER} ]]; then
				echo "Confirmed A record autodiscover.${SQL_DOMAIN}"
				CONFIG_DOMAINS+=("autodiscover.${SQL_DOMAIN}")
			else
				echo "Cannot match Your IP against hostname autodiscover.${SQL_DOMAIN}"
			fi
		else
			echo "No A record for autodiscover.${SQL_DOMAIN} found"
		fi
	done

	acme-client \
		-v -e -b -N -n \
		-f ${ACME_BASE}/acme/private/account.key \
		-k ${ACME_BASE}/acme/private/privkey.pem \
		-c ${ACME_BASE}/acme \
		${MAILCOW_HOSTNAME} ${CONFIG_DOMAINS[*]} ${ADDITIONAL_SAN}

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
