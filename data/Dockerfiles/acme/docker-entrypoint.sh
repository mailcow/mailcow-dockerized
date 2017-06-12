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

	AUTODISCOVER=
	AUTODISCOVER_A=$(dig a autodiscover.${MAILCOW_HOSTNAME#*} +short @208.67.220.222)
	if [[ ! -z ${AUTODISCOVER_A} ]]; then
		if [[ $(curl -4s https://mailcow.email/ip.php) == ${AUTODISCOVER_A} ]]; then
			AUTODISCOVER="autodiscover.${MAILCOW_HOSTNAME#*}"
		fi
	fi

	AUTOCONFIG=
	AUTOCONFIG_A=$(dig a autoconfig.${MAILCOW_HOSTNAME#*} +short @208.67.220.222)
	if [[ ! -z ${AUTOCONFIG_A} ]]; then
		if [[ $(curl -4s https://mailcow.email/ip.php) == ${AUTOCONFIG_A} ]]; then
			AUTOCONFIG="autoconfig.${MAILCOW_HOSTNAME#*}"
		fi
	fi

	acme-client \
		-v -b -N -n \
		-f ${ACME_BASE}/acme/private/account.key \
		-k ${ACME_BASE}/acme/private/privkey.pem \
		-c ${ACME_BASE}/acme \
		${MAILCOW_HOSTNAME} ${AUTOCONFIG} ${AUTODISCOVER} ${ADDITIONAL_SAN}

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
