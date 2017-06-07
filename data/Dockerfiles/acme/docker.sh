#!/bin/sh

ACME_BASE=/var/lib/acme
mkdir -p ${ACME_BASE}/acme/private

restart_containers(){
	for container in $*; do
		curl -X POST \
			--unix-socket /var/run/docker.sock \
			"http:/v1.24/containers/${container}/restart"
	done
}

while true; do

	acme-client \
		-v -b -N -n \
		-f ${ACME_BASE}/acme/private/account.key \
		-k ${ACME_BASE}/acme/private/privkey.pem \
		-c ${ACME_BASE}/acme \
		$*

	case "$?" in
		0) # new certs
			# cp the new certificates and keys 
			cp ${ACME_BASE}/acme/fullchain.pem ${ACME_BASE}/cert.pem
			cp ${ACME_BASE}/acme/private/privkey.pem ${ACME_BASE}/key.pem

			# also generate new dhparams with new certs
			#(on the first run, the old dhparams may be the fakeoil from git)
			openssl dhparam -out ${ACME_BASE}/dhparams.pem 2048

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
