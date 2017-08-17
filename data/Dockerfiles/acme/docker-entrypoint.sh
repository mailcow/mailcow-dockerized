#!/bin/bash

ACME_BASE=/var/lib/acme
SSL_EXAMPLE=/var/lib/ssl-example

mkdir -p ${ACME_BASE}/acme/private

restart_containers(){
	for container in $*; do
		echo "Restarting ${container}..."
		curl -X POST \
			--unix-socket /var/run/docker.sock \
			"http/containers/${container}/restart"
	done
}

verify_hash_match(){
	CERT_HASH=$(openssl x509 -noout -modulus -in "${1}" | openssl md5)
	KEY_HASH=$(openssl rsa -noout -modulus -in "${2}" | openssl md5)
	if [[ ${CERT_HASH} != ${KEY_HASH} ]]; then
		echo "Certificate and key hashes do not match!"
		return 1
	else
		echo "Verified hashes."
		return 0
	fi
}

[[ ! -f ${ACME_BASE}/dhparams.pem ]] && cp ${SSL_EXAMPLE}/dhparams.pem ${ACME_BASE}/dhparams.pem

if [[ -f ${ACME_BASE}/cert.pem ]] && [[ -f ${ACME_BASE}/key.pem ]]; then
	ISSUER=$(openssl x509 -in ${ACME_BASE}/cert.pem -noout -issuer)
	if [[ ${ISSUER} != *"Let's Encrypt"* && ${ISSUER} != *"mailcow"* ]]; then
		echo "Found certificate with issuer other than mailcow snake-oil CA and Let's Encrypt, skipping ACME client..."
		exit 0
	else
		declare -a SAN_ARRAY_NOW
		SAN_NAMES=$(openssl x509 -noout -text -in ${ACME_BASE}/cert.pem | awk '/X509v3 Subject Alternative Name/ {getline;gsub(/ /, "", $0); print}' | tr -d "DNS:")
		if [[ ! -z ${SAN_NAMES} ]]; then
			IFS=',' read -a SAN_ARRAY_NOW <<< ${SAN_NAMES}
			echo "Found Let's Encrypt or mailcow snake-oil CA issued certificate with SANs: ${SAN_ARRAY_NOW[*]}"
		fi
	fi
else
	if [[ -f ${ACME_BASE}/acme/fullchain.pem ]] && [[ -f ${ACME_BASE}/acme/private/privkey.pem ]]; then
		if verify_hash_match ${ACME_BASE}/acme/fullchain.pem ${ACME_BASE}/acme/private/privkey.pem; then
			echo "Restoring previous acme certificate and restarting script..."
			cp ${ACME_BASE}/acme/fullchain.pem ${ACME_BASE}/cert.pem
			cp ${ACME_BASE}/acme/private/privkey.pem ${ACME_BASE}/key.pem
			exec env TRIGGER_RESTART=1 $(readlink -f "$0")
		fi
	ISSUER="mailcow"
	else
		echo "Restoring mailcow snake-oil certificates and restarting script..."
		cp ${SSL_EXAMPLE}/cert.pem ${ACME_BASE}/cert.pem
		cp ${SSL_EXAMPLE}/key.pem ${ACME_BASE}/key.pem
		exec env TRIGGER_RESTART=1 $(readlink -f "$0")
	fi
fi

while true; do
	if [[ "${SKIP_LETS_ENCRYPT}" =~ ^([yY][eE][sS]|[yY])+$ ]]; then
		echo "SKIP_LETS_ENCRYPT=y, skipping Let's Encrypt..."
		exit 0
	fi
	if [[ "${SKIP_IP_CHECK}" =~ ^([yY][eE][sS]|[yY])+$ ]]; then
		SKIP_IP_CHECK=y
	fi
	unset SQL_DOMAIN_ARR
	unset VALIDATED_CONFIG_DOMAINS
	unset ADDITIONAL_VALIDATED_SAN
	declare -a SQL_DOMAIN_ARR
	declare -a VALIDATED_CONFIG_DOMAINS
	declare -a ADDITIONAL_VALIDATED_SAN
	IFS=',' read -r -a ADDITIONAL_SAN_ARR <<< "${ADDITIONAL_SAN}"
	IPV4=$(curl -4s https://mailcow.email/ip.php)
	# Container ids may have changed
	CONTAINERS_RESTART=($(curl --silent --unix-socket /var/run/docker.sock http/containers/json | jq -rc 'map(select(.Names[] | contains ("nginx-mailcow") or contains ("postfix-mailcow") or contains ("dovecot-mailcow"))) | .[] .Id' | tr "\n" " "))

	while read line; do
		SQL_DOMAIN_ARR+=("${line}")
	done < <(mysql -h mysql-mailcow -u ${DBUSER} -p${DBPASS} ${DBNAME} -e "SELECT domain FROM domain WHERE backupmx=0" -Bs)

	for SQL_DOMAIN in "${SQL_DOMAIN_ARR[@]}"; do
		A_CONFIG=$(dig A autoconfig.${SQL_DOMAIN} +short | tail -n 1)
		if [[ ! -z ${A_CONFIG} ]]; then
			echo "Found A record for autoconfig.${SQL_DOMAIN}: ${A_CONFIG}"
			if [[ ${IPV4:-ERR} == ${A_CONFIG} ]] || [[ ${SKIP_IP_CHECK} == "y" ]]; then
				echo "Confirmed A record autoconfig.${SQL_DOMAIN}"
				VALIDATED_CONFIG_DOMAINS+=("autoconfig.${SQL_DOMAIN}")
			else
				echo "Cannot match your IP ${IPV4} against hostname autoconfig.${SQL_DOMAIN} (${A_CONFIG})"
			fi
		else
			echo "No A record for autoconfig.${SQL_DOMAIN} found"
		fi

        A_DISCOVER=$(dig A autodiscover.${SQL_DOMAIN} +short | tail -n 1)
		if [[ ! -z ${A_DISCOVER} ]]; then
			echo "Found A record for autodiscover.${SQL_DOMAIN}: ${A_DISCOVER}"
			if [[ ${IPV4:-ERR} == ${A_DISCOVER} ]] || [[ ${SKIP_IP_CHECK} == "y" ]]; then
				echo "Confirmed A record autodiscover.${SQL_DOMAIN}"
				VALIDATED_CONFIG_DOMAINS+=("autodiscover.${SQL_DOMAIN}")
			else
				echo "Cannot match your IP ${IPV4} against hostname autodiscover.${SQL_DOMAIN} (${A_DISCOVER})"
			fi
		else
			echo "No A record for autodiscover.${SQL_DOMAIN} found"
		fi
	done

	A_MAILCOW_HOSTNAME=$(dig A ${MAILCOW_HOSTNAME} +short | tail -n 1)
	if [[ ! -z ${A_MAILCOW_HOSTNAME} ]]; then
		echo "Found A record for ${MAILCOW_HOSTNAME}: ${A_MAILCOW_HOSTNAME}"
		if [[ ${IPV4:-ERR} == ${A_MAILCOW_HOSTNAME} ]] || [[ ${SKIP_IP_CHECK} == "y" ]]; then
			echo "Confirmed A record ${MAILCOW_HOSTNAME}"
			VALIDATED_MAILCOW_HOSTNAME=${MAILCOW_HOSTNAME}
		else
			echo "Cannot match your IP ${IPV4} against hostname ${MAILCOW_HOSTNAME} (${A_MAILCOW_HOSTNAME}) "
		fi
	else
		echo "No A record for ${MAILCOW_HOSTNAME} found"
	fi

	for SAN in "${ADDITIONAL_SAN_ARR[@]}"; do
		A_SAN=$(dig A ${SAN} +short | tail -n 1)
		if [[ ! -z ${A_SAN} ]]; then
			echo "Found A record for ${SAN}: ${A_SAN}"
			if [[ ${IPV4:-ERR} == ${A_SAN} ]] || [[ ${SKIP_IP_CHECK} == "y" ]]; then
				echo "Confirmed A record ${SAN}"
				ADDITIONAL_VALIDATED_SAN+=("${SAN}")
			else
				echo "Cannot match your IP against hostname ${SAN}"
			fi
		else
			echo "No A record for ${SAN} found"
		fi
	done

  # Unique elements
	ALL_VALIDATED=($(echo ${VALIDATED_CONFIG_DOMAINS[*]} ${ADDITIONAL_VALIDATED_SAN[*]} ${VALIDATED_MAILCOW_HOSTNAME} | xargs -n1 | sort -u | xargs))
	if [[ -z ${ALL_VALIDATED[*]} ]]; then
		echo "Cannot validate hostnames, skipping Let's Encrypt..."
		exit 0
	fi

	ORPHANED_SAN=($(echo ${SAN_ARRAY_NOW[*]} ${VALIDATED_CONFIG_DOMAINS[*]} ${ADDITIONAL_VALIDATED_SAN[*]} ${MAILCOW_HOSTNAME} | tr ' ' '\n' | sort | uniq -u ))
	if [[ ! -z ${ORPHANED_SAN[*]} ]] && [[ ${ISSUER} != *"mailcow"* ]]; then
		DATE=$(date +%Y-%m-%d_%H_%M_%S)
		echo "Found orphaned SAN ${ORPHANED_SAN[*]} in certificate, moving old files to ${ACME_BASE}/acme/private/${DATE}.bak/, keeping key file..."
		mkdir -p ${ACME_BASE}/acme/private/${DATE}.bak/
		[[ -f ${ACME_BASE}/acme/private/account.key ]] && mv ${ACME_BASE}/acme/private/account.key ${ACME_BASE}/acme/private/${DATE}.bak/
		mv ${ACME_BASE}/acme/fullchain.pem ${ACME_BASE}/acme/private/${DATE}.bak/
        mv ${ACME_BASE}/acme/cert.pem ${ACME_BASE}/acme/private/${DATE}.bak/
		cp ${ACME_BASE}/acme/private/privkey.pem ${ACME_BASE}/acme/private/${DATE}.bak/ # Keep key for TLSA 3 1 1 records
	fi

	acme-client \
		-v -e -b -N -n \
		-f ${ACME_BASE}/acme/private/account.key \
		-k ${ACME_BASE}/acme/private/privkey.pem \
		-c ${ACME_BASE}/acme \
		${ALL_VALIDATED[*]}

	case "$?" in
		0) # new certs
			# cp the new certificates and keys
			cp ${ACME_BASE}/acme/fullchain.pem ${ACME_BASE}/cert.pem
			cp ${ACME_BASE}/acme/private/privkey.pem ${ACME_BASE}/key.pem

			# restart docker containers
			if ! verify_hash_match ${ACME_BASE}/cert.pem ${ACME_BASE}/key.pem; then
				echo "Certificate was successfully requested, but key and certificate have non-matching hashes, restoring mailcow snake-oil and restarting containers..."
				cp ${SSL_EXAMPLE}/cert.pem ${ACME_BASE}/cert.pem
				cp ${SSL_EXAMPLE}/key.pem ${ACME_BASE}/key.pem
			fi
			restart_containers ${CONTAINERS_RESTART[*]}
			;;
		1) # failure
			if [[ -f ${ACME_BASE}/acme/private/${DATE}.bak/fullchain.pem ]] && [[ -f ${ACME_BASE}/acme/private/${DATE}.bak/privkey.pem ]]; then
				echo "Error requesting certificate, restoring previous certificate from backup and restarting containers...."
				cp ${ACME_BASE}/acme/private/${DATE}.bak/fullchain.pem ${ACME_BASE}/cert.pem
				cp ${ACME_BASE}/acme/private/${DATE}.bak/privkey.pem ${ACME_BASE}/key.pem
				TRIGGER_RESTART=1
            elif [[ -f ${ACME_BASE}/acme/fullchain.pem ]] && [[ -f ${ACME_BASE}/acme/private/privkey.pem ]]; then
				echo "Error requesting certificate, restoring from previous acme request and restarting containers..."
				cp ${ACME_BASE}/acme/fullchain.pem ${ACME_BASE}/cert.pem
				cp ${ACME_BASE}/acme/private/privkey.pem ${ACME_BASE}/key.pem
				TRIGGER_RESTART=1
			fi
			if ! verify_hash_match ${ACME_BASE}/cert.pem ${ACME_BASE}/key.pem; then
				echo "Error verifying certificates, restoring mailcow snake-oil and restarting containers..."
				cp ${SSL_EXAMPLE}/cert.pem ${ACME_BASE}/cert.pem
				cp ${SSL_EXAMPLE}/key.pem ${ACME_BASE}/key.pem
				TRIGGER_RESTART=1
			fi
			[[ ${TRIGGER_RESTART} == 1 ]] && restart_containers ${CONTAINERS_RESTART[*]}
			exit 1;;
		2) # no change
			if ! diff ${ACME_BASE}/acme/fullchain.pem ${ACME_BASE}/cert.pem; then
				echo "Certificate was not changed, but active certificate does not match the verified certificate, fixing and restarting containers..."
				cp ${ACME_BASE}/acme/fullchain.pem ${ACME_BASE}/cert.pem
				cp ${ACME_BASE}/acme/private/privkey.pem ${ACME_BASE}/key.pem
				restart_containers ${CONTAINERS_RESTART[*]}
			fi
			if ! verify_hash_match ${ACME_BASE}/cert.pem ${ACME_BASE}/key.pem; then
				echo "Certificate was not changed, but hashes do not match, restoring from previous acme request and restarting containers..."
				cp ${ACME_BASE}/acme/fullchain.pem ${ACME_BASE}/cert.pem
				cp ${ACME_BASE}/acme/private/privkey.pem ${ACME_BASE}/key.pem
				restart_containers ${CONTAINERS_RESTART[*]}
			fi
			;;
		*) # unspecified
			if [[ -f ${ACME_BASE}/acme/private/${DATE}.bak/fullchain.pem ]] && [[ -f ${ACME_BASE}/acme/private/${DATE}.bak/privkey.pem ]]; then
				echo "Error requesting certificate, restoring previous certificate from backup and restarting containers...."
				cp ${ACME_BASE}/acme/private/${DATE}.bak/fullchain.pem ${ACME_BASE}/cert.pem
				cp ${ACME_BASE}/acme/private/${DATE}.bak/privkey.pem ${ACME_BASE}/key.pem
				TRIGGER_RESTART=1
            elif [[ -f ${ACME_BASE}/acme/fullchain.pem ]] && [[ -f ${ACME_BASE}/acme/private/privkey.pem ]]; then
				echo "Error requesting certificate, restoring from previous acme request and restarting containers..."
				cp ${ACME_BASE}/acme/fullchain.pem ${ACME_BASE}/cert.pem
				cp ${ACME_BASE}/acme/private/privkey.pem ${ACME_BASE}/key.pem
				TRIGGER_RESTART=1
			fi
			if ! verify_hash_match ${ACME_BASE}/cert.pem ${ACME_BASE}/key.pem; then
				echo "Error verifying certificates, restoring mailcow snake-oil..."
				cp ${SSL_EXAMPLE}/cert.pem ${ACME_BASE}/cert.pem
				cp ${SSL_EXAMPLE}/key.pem ${ACME_BASE}/key.pem
				TRIGGER_RESTART=1
			fi
			[[ ${TRIGGER_RESTART} == 1 ]] && restart_containers ${CONTAINERS_RESTART[*]}
			exit 1;;
	esac

	echo "ACME certificate validation done. Sleeping for another day."
	sleep 86400

done
