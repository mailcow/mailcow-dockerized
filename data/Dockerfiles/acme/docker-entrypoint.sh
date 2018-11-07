#!/bin/bash
set -o pipefail
exec 5>&1

# Thanks to https://github.com/cvmiller -> https://github.com/cvmiller/expand6
source /srv/expand6.sh

log_f() {
  if [[ ${2} == "no_nl" ]]; then
    echo -n "$(date) - ${1}"
  elif [[ ${2} == "no_date" ]]; then
    echo "${1}"
  elif [[ ${2} != "redis_only" ]]; then
    echo "$(date) - ${1}"
  fi
  if [[ ${3} == "b64" ]]; then
    redis-cli -h redis LPUSH ACME_LOG "{\"time\":\"$(date +%s)\",\"message\":\"base64,$(printf '%s' "${1}")\"}" > /dev/null
  else
    redis-cli -h redis LPUSH ACME_LOG "{\"time\":\"$(date +%s)\",\"message\":\"$(printf '%s' "${1}" | \
      tr '%&;$"_[]{}-\r\n' ' ')\"}" > /dev/null
  fi
}

if [[ "${SKIP_LETS_ENCRYPT}" =~ ^([yY][eE][sS]|[yY])+$ ]]; then
  log_f "SKIP_LETS_ENCRYPT=y, skipping Let's Encrypt..."
  sleep 365d
  exec $(readlink -f "$0")
fi

log_f "Waiting for Docker API..." no_nl
until ping dockerapi -c1 > /dev/null; do
  sleep 1
done
log_f "OK" no_date

ACME_BASE=/var/lib/acme
SSL_EXAMPLE=/var/lib/ssl-example

mkdir -p ${ACME_BASE}/acme/private

reload_configurations(){
  # Reading container IDs
  # Wrapping as array to ensure trimmed content when calling $NGINX etc.
  local NGINX=($(curl --silent --insecure https://dockerapi/containers/json | jq -r '.[] | {name: .Config.Labels["com.docker.compose.service"], id: .Id}' | jq -rc 'select( .name | tostring | contains("nginx-mailcow")) | .id' | tr "\n" " "))
  local DOVECOT=($(curl --silent --insecure https://dockerapi/containers/json | jq -r '.[] | {name: .Config.Labels["com.docker.compose.service"], id: .Id}' | jq -rc 'select( .name | tostring | contains("dovecot-mailcow")) | .id' | tr "\n" " "))
  local POSTFIX=($(curl --silent --insecure https://dockerapi/containers/json | jq -r '.[] | {name: .Config.Labels["com.docker.compose.service"], id: .Id}' | jq -rc 'select( .name | tostring | contains("postfix-mailcow")) | .id' | tr "\n" " "))
  # Reloading
  echo "Reloading Nginx..."
  NGINX_RELOAD_RET=$(curl -X POST --insecure https://dockerapi/containers/${NGINX}/exec -d '{"cmd":"reload", "task":"nginx"}' --silent -H 'Content-type: application/json' | jq -r .type)
  [[ ${NGINX_RELOAD_RET} != 'success' ]] && { echo "Could not reload Nginx, restarting container..."; restart_container ${NGINX} ; }
  echo "Reloading Dovecot..."
  DOVECOT_RELOAD_RET=$(curl -X POST --insecure https://dockerapi/containers/${DOVECOT}/exec -d '{"cmd":"reload", "task":"dovecot"}' --silent -H 'Content-type: application/json' | jq -r .type)
  [[ ${DOVECOT_RELOAD_RET} != 'success' ]] && { echo "Could not reload Dovecot, restarting container..."; restart_container ${DOVECOT} ; }
  echo "Reloading Postfix..."
  POSTFIX_RELOAD_RET=$(curl -X POST --insecure https://dockerapi/containers/${POSTFIX}/exec -d '{"cmd":"reload", "task":"postfix"}' --silent -H 'Content-type: application/json' | jq -r .type)
  [[ ${POSTFIX_RELOAD_RET} != 'success' ]] && { echo "Could not reload Postfix, restarting container..."; restart_container ${POSTFIX} ; }
}

restart_container(){
  for container in $*; do
    log_f "Restarting ${container}..." no_nl
    C_REST_OUT=$(curl -X POST --insecure https://dockerapi/containers/${container}/restart | jq -r '.msg')
    log_f "${C_REST_OUT}" no_date
  done
}

array_diff() {
  # https://stackoverflow.com/questions/2312762, Alex Offshore
  eval local ARR1=\(\"\${$2[@]}\"\)
  eval local ARR2=\(\"\${$3[@]}\"\)
  local IFS=$'\n'
  mapfile -t $1 < <(comm -23 <(echo "${ARR1[*]}" | sort) <(echo "${ARR2[*]}" | sort))
}

verify_hash_match(){
  CERT_HASH=$(openssl x509 -noout -modulus -in "${1}" | openssl md5)
  KEY_HASH=$(openssl rsa -noout -modulus -in "${2}" | openssl md5)
  if [[ ${CERT_HASH} != ${KEY_HASH} ]]; then
    log_f "Certificate and key hashes do not match!"
    return 1
  else
    log_f "Verified hashes."
    return 0
  fi
}

get_ipv4(){
  local IPV4=
  local IPV4_SRCS=
  local TRY=
  IPV4_SRCS[0]="ip4.mailcow.email"
  IPV4_SRCS[1]="ip4.korves.net"
  until [[ ! -z ${IPV4} ]] || [[ ${TRY} -ge 10 ]]; do
    IPV4=$(curl --connect-timeout 3 -m 10 -L4s ${IPV4_SRCS[$RANDOM % ${#IPV4_SRCS[@]} ]} | grep -E "^((25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$")
    [[ ! -z ${TRY} ]] && sleep 1
    TRY=$((TRY+1))
  done
  echo ${IPV4}
}

get_ipv6(){
  local IPV6=
  local IPV6_SRCS=
  local TRY=
  IPV6_SRCS[0]="ip6.korves.net"
  IPV6_SRCS[1]="ip6.mailcow.email"
  until [[ ! -z ${IPV6} ]] || [[ ${TRY} -ge 10 ]]; do
    IPV6=$(curl --connect-timeout 3 -m 10 -L6s ${IPV6_SRCS[$RANDOM % ${#IPV6_SRCS[@]} ]} | grep "^\([0-9a-fA-F]\{0,4\}:\)\{1,7\}[0-9a-fA-F]\{0,4\}$")
    [[ ! -z ${TRY} ]] && sleep 1
    TRY=$((TRY+1))
  done
  echo ${IPV6}
}

[[ ! -f ${ACME_BASE}/dhparams.pem ]] && cp ${SSL_EXAMPLE}/dhparams.pem ${ACME_BASE}/dhparams.pem

if [[ -f ${ACME_BASE}/cert.pem ]] && [[ -f ${ACME_BASE}/key.pem ]]; then
  ISSUER=$(openssl x509 -in ${ACME_BASE}/cert.pem -noout -issuer)
  if [[ ${ISSUER} != *"Let's Encrypt"* && ${ISSUER} != *"mailcow"* && ${ISSUER} != *"Fake LE Intermediate"* ]]; then
    log_f "Found certificate with issuer other than mailcow snake-oil CA and Let's Encrypt, skipping ACME client..."
    sleep 3650d
    exec $(readlink -f "$0")
  else
    declare -a SAN_ARRAY_NOW
    SAN_NAMES=$(openssl x509 -noout -text -in ${ACME_BASE}/cert.pem | awk '/X509v3 Subject Alternative Name/ {getline;gsub(/ /, "", $0); print}' | tr -d "DNS:")
    if [[ ! -z ${SAN_NAMES} ]]; then
      IFS=',' read -a SAN_ARRAY_NOW <<< ${SAN_NAMES}
      log_f "Found Let's Encrypt or mailcow snake-oil CA issued certificate with SANs: ${SAN_ARRAY_NOW[*]}"
    fi
  fi
else
  if [[ -f ${ACME_BASE}/acme/fullchain.pem ]] && [[ -f ${ACME_BASE}/acme/private/privkey.pem ]]; then
    if verify_hash_match ${ACME_BASE}/acme/fullchain.pem ${ACME_BASE}/acme/private/privkey.pem; then
      log_f "Restoring previous acme certificate and restarting script..."
      cp ${ACME_BASE}/acme/fullchain.pem ${ACME_BASE}/cert.pem
      cp ${ACME_BASE}/acme/private/privkey.pem ${ACME_BASE}/key.pem
      # Restarting with env var set to trigger a restart,
      exec env TRIGGER_RESTART=1 $(readlink -f "$0")
    fi
  ISSUER="mailcow"
  else
    log_f "Restoring mailcow snake-oil certificates and restarting script..."
    cp ${SSL_EXAMPLE}/cert.pem ${ACME_BASE}/cert.pem
    cp ${SSL_EXAMPLE}/key.pem ${ACME_BASE}/key.pem
    exec env TRIGGER_RESTART=1 $(readlink -f "$0")
  fi
fi

log_f "Waiting for database... "
while ! mysqladmin status --socket=/var/run/mysqld/mysqld.sock -u${DBUSER} -p${DBPASS} --silent; do
  sleep 2
done
log_f "Initializing, please wait... "


while true; do
  if [[ "${SKIP_IP_CHECK}" =~ ^([yY][eE][sS]|[yY])+$ ]]; then
    SKIP_IP_CHECK=y
  fi
  unset SQL_DOMAIN_ARR
  unset VALIDATED_CONFIG_DOMAINS
  unset ADDITIONAL_VALIDATED_SAN
  declare -a SQL_DOMAIN_ARR
  declare -a VALIDATED_CONFIG_DOMAINS
  declare -a ADDITIONAL_VALIDATED_SAN
  IFS=',' read -r -a TMP_ARR <<< "${ADDITIONAL_SAN}"
  log_f "Detecting IP addresses... " no_nl

  unset ADDITIONAL_WC_ARR
  unset ADDITIONAL_SAN_ARR
  for i in "${TMP_ARR[@]}" ; do
    if [[ "$i" =~ \.\*$ ]]; then
      ADDITIONAL_WC_ARR+=(${i::-2})
    else
      ADDITIONAL_SAN_ARR+=($i)
    fi
  done
  ADDITIONAL_WC_ARR+=('autodiscover')

  IPV4=$(get_ipv4)
  IPV6=$(get_ipv6)
  log_f "OK" no_date

  # Hard-fail on CAA errors for MAILCOW_HOSTNAME
  MH_PARENT_DOMAIN=$(echo ${MAILCOW_HOSTNAME} | cut -d. -f2-)
  MH_CAAS=( $(dig CAA ${MH_PARENT_DOMAIN} +short | sed -n 's/\d issue "\(.*\)"/\1/p') )
  if [[ ! -z ${MH_CAAS} ]]; then
    if [[ ${MH_CAAS[@]} =~ "letsencrypt.org" ]]; then
      echo "Validated CAA for parent domain ${MH_PARENT_DOMAIN}"
    else
      echo "Skipping ACME validation: Lets Encrypt disallowed for ${MAILCOW_HOSTNAME} by CAA record, retrying in 1h..."
      sleep 1h
      exec $(readlink -f "$0")
    fi
  fi

  log_f "Waiting for domain table... " no_nl
  while [[ -z ${DOMAIN_TABLE} ]]; do
    curl --silent http://nginx/ >/dev/null 2>&1
    DOMAIN_TABLE=$(mysql --socket=/var/run/mysqld/mysqld.sock -u ${DBUSER} -p${DBPASS} ${DBNAME} -e "SHOW TABLES LIKE 'domain'" -Bs)
    [[ -z ${DOMAIN_TABLE} ]] && sleep 10
  done
  log_f "OK" no_date

  while read domains; do
    SQL_DOMAIN_ARR+=("${domains}")
  done < <(mysql --socket=/var/run/mysqld/mysqld.sock -u ${DBUSER} -p${DBPASS} ${DBNAME} -e "SELECT domain FROM domain WHERE backupmx=0" -Bs)

  for SQL_DOMAIN in "${SQL_DOMAIN_ARR[@]}"; do
    for SUBDOMAIN in "${ADDITIONAL_WC_ARR[@]}"; do
      if [[  "${SUBDOMAIN}.${SQL_DOMAIN}" == ${MAILCOW_HOSTNAME} ]]; then
        log_f "Skipping mailcow hostname (${MAILCOW_HOSTNAME}), will be added anyway"
      else
        A_SUBDOMAIN=$(dig A ${SUBDOMAIN}.${SQL_DOMAIN} +short | tail -n 1)
        AAAA_SUBDOMAIN=$(dig AAAA ${SUBDOMAIN}.${SQL_DOMAIN} +short | tail -n 1)
        # Check if CNAME without v6 enabled target
        if [[ ! -z ${AAAA_SUBDOMAIN} ]] && [[ -z $(echo ${AAAA_SUBDOMAIN} | grep "^\([0-9a-fA-F]\{0,4\}:\)\{1,7\}[0-9a-fA-F]\{0,4\}$") ]]; then
          AAAA_SUBDOMAIN=
        fi
        if [[ ! -z ${AAAA_SUBDOMAIN} ]]; then
          log_f "Found AAAA record for ${SUBDOMAIN}.${SQL_DOMAIN}: ${AAAA_SUBDOMAIN} - skipping A record check"
          if [[ $(expand ${IPV6:-"0000:0000:0000:0000:0000:0000:0000:0000"}) == $(expand ${AAAA_SUBDOMAIN}) ]] || [[ ${SKIP_IP_CHECK} == "y" ]]; then
            log_f "Confirmed AAAA record ${SUBDOMAIN}.${SQL_DOMAIN}"
            VALIDATED_CONFIG_DOMAINS+=("${SUBDOMAIN}.${SQL_DOMAIN}")
          else
            log_f "Cannot match your IP ${IPV6:-NO_IPV6_LINK} against hostname ${SUBDOMAIN}.${SQL_DOMAIN} ($(expand ${AAAA_SUBDOMAIN}))"
          fi
        elif [[ ! -z ${A_SUBDOMAIN} ]]; then
          log_f "Found A record for ${SUBDOMAIN}.${SQL_DOMAIN}: ${A_SUBDOMAIN}"
          if [[ ${IPV4:-ERR} == ${A_SUBDOMAIN} ]] || [[ ${SKIP_IP_CHECK} == "y" ]]; then
            log_f "Confirmed A record ${SUBDOMAIN}.${SQL_DOMAIN}"
            VALIDATED_CONFIG_DOMAINS+=("${SUBDOMAIN}.${SQL_DOMAIN}")
          else
            log_f "Cannot match your IP ${IPV4} against hostname ${SUBDOMAIN}.${SQL_DOMAIN} (${A_SUBDOMAIN})"
          fi
        else
          log_f "No A or AAAA record found for hostname ${SUBDOMAIN}.${SQL_DOMAIN}"
        fi
      fi
    done
  done

  A_MAILCOW_HOSTNAME=$(dig A ${MAILCOW_HOSTNAME} +short | tail -n 1)
  AAAA_MAILCOW_HOSTNAME=$(dig AAAA ${MAILCOW_HOSTNAME} +short | tail -n 1)
  # Check if CNAME without v6 enabled target
  if [[ ! -z ${AAAA_MAILCOW_HOSTNAME} ]] && [[ -z $(echo ${AAAA_MAILCOW_HOSTNAME} | grep "^\([0-9a-fA-F]\{0,4\}:\)\{1,7\}[0-9a-fA-F]\{0,4\}$") ]]; then
    AAAA_MAILCOW_HOSTNAME=
  fi
  if [[ ! -z ${AAAA_MAILCOW_HOSTNAME} ]]; then
    log_f "Found AAAA record for ${MAILCOW_HOSTNAME}: ${AAAA_MAILCOW_HOSTNAME} - skipping A record check"
    if [[ $(expand ${IPV6:-"0000:0000:0000:0000:0000:0000:0000:0000"}) == $(expand ${AAAA_MAILCOW_HOSTNAME}) ]] || [[ ${SKIP_IP_CHECK} == "y" ]]; then
      log_f "Confirmed AAAA record ${MAILCOW_HOSTNAME}"
      VALIDATED_MAILCOW_HOSTNAME=${MAILCOW_HOSTNAME}
    else
      log_f "Cannot match your IP ${IPV6:-NO_IPV6_LINK} against hostname ${MAILCOW_HOSTNAME} ($(expand ${AAAA_MAILCOW_HOSTNAME}))"
    fi
  elif [[ ! -z ${A_MAILCOW_HOSTNAME} ]]; then
    log_f "Found A record for ${MAILCOW_HOSTNAME}: ${A_MAILCOW_HOSTNAME}"
    if [[ ${IPV4:-ERR} == ${A_MAILCOW_HOSTNAME} ]] || [[ ${SKIP_IP_CHECK} == "y" ]]; then
      log_f "Confirmed A record ${A_MAILCOW_HOSTNAME}"
      VALIDATED_MAILCOW_HOSTNAME=${MAILCOW_HOSTNAME}
    else
      log_f "Cannot match your IP ${IPV4} against hostname ${MAILCOW_HOSTNAME} (${A_MAILCOW_HOSTNAME})"
    fi
  else
    log_f "No A or AAAA record found for hostname ${MAILCOW_HOSTNAME}"
  fi

  for SAN in "${ADDITIONAL_SAN_ARR[@]}"; do
    # Skip on CAA errors for SAN
    SAN_PARENT_DOMAIN=$(echo ${SAN} | cut -d. -f2-)
    SAN_CAAS=( $(dig CAA ${SAN_PARENT_DOMAIN} +short | sed -n 's/\d issue "\(.*\)"/\1/p') )
    if [[ ! -z ${SAN_CAAS} ]]; then
      if [[ ${SAN_CAAS[@]} =~ "letsencrypt.org" ]]; then
        echo "Validated CAA for parent domain ${SAN_PARENT_DOMAIN} of ${SAN}"
      else
        echo "Skipping ACME validation for ${SAN}: Lets Encrypt disallowed for ${SAN} by CAA record"
        continue
      fi
    fi
    if [[ ${SAN} == ${MAILCOW_HOSTNAME} ]]; then
      continue
    fi
    A_SAN=$(dig A ${SAN} +short | tail -n 1)
    AAAA_SAN=$(dig AAAA ${SAN} +short | tail -n 1)
    # Check if CNAME without v6 enabled target
    if [[ ! -z ${AAAA_SAN} ]] && [[ -z $(echo ${AAAA_SAN} | grep "^\([0-9a-fA-F]\{0,4\}:\)\{1,7\}[0-9a-fA-F]\{0,4\}$") ]]; then
      AAAA_SAN=
    fi
    if [[ ! -z ${AAAA_SAN} ]]; then
      log_f "Found AAAA record for ${SAN}: ${AAAA_SAN} - skipping A record check"
      if [[ $(expand ${IPV6:-"0000:0000:0000:0000:0000:0000:0000:0000"}) == $(expand ${AAAA_SAN}) ]] || [[ ${SKIP_IP_CHECK} == "y" ]]; then
        log_f "Confirmed AAAA record ${SAN}"
        ADDITIONAL_VALIDATED_SAN+=("${SAN}")
      else
        log_f "Cannot match your IP ${IPV6:-NO_IPV6_LINK} against hostname ${SAN} ($(expand ${AAAA_SAN}))"
      fi
    elif [[ ! -z ${A_SAN} ]]; then
      log_f "Found A record for ${SAN}: ${A_SAN}"
      if [[ ${IPV4:-ERR} == ${A_SAN} ]] || [[ ${SKIP_IP_CHECK} == "y" ]]; then
        log_f "Confirmed A record ${A_SAN}"
        ADDITIONAL_VALIDATED_SAN+=("${SAN}")
      else
        log_f "Cannot match your IP ${IPV4} against hostname ${SAN} (${A_SAN})"
      fi
    else
      log_f "No A or AAAA record found for hostname ${SAN}"
    fi
  done

  # Unique elements
  ALL_VALIDATED=(${VALIDATED_MAILCOW_HOSTNAME} $(echo ${VALIDATED_CONFIG_DOMAINS[*]} ${ADDITIONAL_VALIDATED_SAN[*]} | xargs -n1 | sort -u | xargs))
  if [[ -z ${ALL_VALIDATED[*]} ]]; then
    log_f "Cannot validate hostnames, skipping Let's Encrypt for 1 hour."
    log_f "Use SKIP_LETS_ENCRYPT=y in mailcow.conf to skip it permanently."
    sleep 1h
    exec $(readlink -f "$0")
  fi

  array_diff ORPHANED_SAN SAN_ARRAY_NOW ALL_VALIDATED
  if [[ ! -z ${ORPHANED_SAN[*]} ]] && [[ ${ISSUER} != *"mailcow"* ]]; then
    DATE=$(date +%Y-%m-%d_%H_%M_%S)
    log_f "Found orphaned SAN ${ORPHANED_SAN[*]} in certificate, moving old files to ${ACME_BASE}/acme/private/${DATE}.bak/, keeping key file..."
    mkdir -p ${ACME_BASE}/acme/private/${DATE}.bak/
    [[ -f ${ACME_BASE}/acme/private/account.key ]] && mv ${ACME_BASE}/acme/private/account.key ${ACME_BASE}/acme/private/${DATE}.bak/
    [[ -f ${ACME_BASE}/acme/fullchain.pem ]] && mv ${ACME_BASE}/acme/fullchain.pem ${ACME_BASE}/acme/private/${DATE}.bak/
    [[ -f ${ACME_BASE}/acme/cert.pem ]] && mv ${ACME_BASE}/acme/cert.pem ${ACME_BASE}/acme/private/${DATE}.bak/
    cp ${ACME_BASE}/acme/private/privkey.pem ${ACME_BASE}/acme/private/${DATE}.bak/ # Keep key for TLSA 3 1 1 records
  fi

  if [[ "${LE_STAGING}" =~ ^([yY][eE][sS]|[yY])+$ ]]; then
    log_f "Using Let's Encrypt staging servers"
    STAGING_PARAMETER="-s"
  else
    STAGING_PARAMETER=
  fi

  ACME_RESPONSE=$(acme-client \
    -v -e -b -N -n ${STAGING_PARAMETER} \
    -a 'https://letsencrypt.org/documents/LE-SA-v1.2-November-15-2017.pdf' \
    -f ${ACME_BASE}/acme/private/account.key \
    -k ${ACME_BASE}/acme/private/privkey.pem \
    -c ${ACME_BASE}/acme \
    ${ALL_VALIDATED[*]} 2>&1 | tee /dev/fd/5)
  case "$?" in
    0) # new certs
      ACME_RESPONSE_B64=$(echo ${ACME_RESPONSE} | openssl enc -e -A -base64)
      log_f "${ACME_RESPONSE_B64}" redis_only b64
      # cp the new certificates and keys
      cp ${ACME_BASE}/acme/fullchain.pem ${ACME_BASE}/cert.pem
      cp ${ACME_BASE}/acme/private/privkey.pem ${ACME_BASE}/key.pem

      # restart docker containers
      if ! verify_hash_match ${ACME_BASE}/cert.pem ${ACME_BASE}/key.pem; then
        log_f "Certificate was successfully requested, but key and certificate have non-matching hashes, restoring mailcow snake-oil and restarting containers..."
        cp ${SSL_EXAMPLE}/cert.pem ${ACME_BASE}/cert.pem
        cp ${SSL_EXAMPLE}/key.pem ${ACME_BASE}/key.pem
      fi
      reload_configurations
      ;;
    1) # failure
      ACME_RESPONSE_B64=$(echo ${ACME_RESPONSE} | openssl enc -e -A -base64)
      log_f "${ACME_RESPONSE_B64}" redis_only b64
      if [[ $ACME_RESPONSE =~ "No registration exists" ]]; then
        log_f "Registration keys are invalid, deleting old keys and restarting..."
        rm ${ACME_BASE}/acme/private/account.key
        exec $(readlink -f "$0")
      fi
      if [[ -f ${ACME_BASE}/acme/private/${DATE}.bak/fullchain.pem ]] && [[ -f ${ACME_BASE}/acme/private/${DATE}.bak/privkey.pem ]]; then
        log_f "Error requesting certificate, restoring previous certificate from backup and restarting containers...."
        cp ${ACME_BASE}/acme/private/${DATE}.bak/fullchain.pem ${ACME_BASE}/cert.pem
        cp ${ACME_BASE}/acme/private/${DATE}.bak/privkey.pem ${ACME_BASE}/key.pem
        TRIGGER_RESTART=1
      elif [[ -f ${ACME_BASE}/acme/fullchain.pem ]] && [[ -f ${ACME_BASE}/acme/private/privkey.pem ]]; then
        log_f "Error requesting certificate, restoring from previous acme request and restarting containers..."
        cp ${ACME_BASE}/acme/fullchain.pem ${ACME_BASE}/cert.pem
        cp ${ACME_BASE}/acme/private/privkey.pem ${ACME_BASE}/key.pem
        TRIGGER_RESTART=1
      fi
      if ! verify_hash_match ${ACME_BASE}/cert.pem ${ACME_BASE}/key.pem; then
        log_f "Error verifying certificates, restoring mailcow snake-oil and restarting containers..."
        cp ${SSL_EXAMPLE}/cert.pem ${ACME_BASE}/cert.pem
        cp ${SSL_EXAMPLE}/key.pem ${ACME_BASE}/key.pem
        TRIGGER_RESTART=1
      fi
      [[ ${TRIGGER_RESTART} == 1 ]] && reload_configurations
      log_f "Retrying in 30 minutes..."
      sleep 30m
      exec $(readlink -f "$0")
      ;;
    2) # no change
      ACME_RESPONSE_B64=$(echo ${ACME_RESPONSE} | openssl enc -e -A -base64)
      log_f "${ACME_RESPONSE_B64}" redis_only b64
      if ! diff ${ACME_BASE}/acme/fullchain.pem ${ACME_BASE}/cert.pem; then
        log_f "Certificate was not changed, but active certificate does not match the verified certificate, fixing and restarting containers..."
        cp ${ACME_BASE}/acme/fullchain.pem ${ACME_BASE}/cert.pem
        cp ${ACME_BASE}/acme/private/privkey.pem ${ACME_BASE}/key.pem
        TRIGGER_RESTART=1
      fi
      if ! verify_hash_match ${ACME_BASE}/cert.pem ${ACME_BASE}/key.pem; then
        log_f "Certificate was not changed, but hashes do not match, restoring from previous acme request and restarting containers..."
        cp ${ACME_BASE}/acme/fullchain.pem ${ACME_BASE}/cert.pem
        cp ${ACME_BASE}/acme/private/privkey.pem ${ACME_BASE}/key.pem
        TRIGGER_RESTART=1
      fi
      log_f "Certificate was not changed"
      [[ ${TRIGGER_RESTART} == 1 ]] && reload_configurations
      ;;
    *) # unspecified
      ACME_RESPONSE_B64=$(echo ${ACME_RESPONSE} | openssl enc -e -A -base64)
      log_f "${ACME_RESPONSE_B64}" redis_only b64
      if [[ -f ${ACME_BASE}/acme/private/${DATE}.bak/fullchain.pem ]] && [[ -f ${ACME_BASE}/acme/private/${DATE}.bak/privkey.pem ]]; then
        log_f "Error requesting certificate, restoring previous certificate from backup and restarting containers...."
        cp ${ACME_BASE}/acme/private/${DATE}.bak/fullchain.pem ${ACME_BASE}/cert.pem
        cp ${ACME_BASE}/acme/private/${DATE}.bak/privkey.pem ${ACME_BASE}/key.pem
        TRIGGER_RESTART=1
            elif [[ -f ${ACME_BASE}/acme/fullchain.pem ]] && [[ -f ${ACME_BASE}/acme/private/privkey.pem ]]; then
        log_f "Error requesting certificate, restoring from previous acme request and restarting containers..."
        cp ${ACME_BASE}/acme/fullchain.pem ${ACME_BASE}/cert.pem
        cp ${ACME_BASE}/acme/private/privkey.pem ${ACME_BASE}/key.pem
        TRIGGER_RESTART=1
      fi
      if ! verify_hash_match ${ACME_BASE}/cert.pem ${ACME_BASE}/key.pem; then
        log_f "Error verifying certificates, restoring mailcow snake-oil..."
        cp ${SSL_EXAMPLE}/cert.pem ${ACME_BASE}/cert.pem
        cp ${SSL_EXAMPLE}/key.pem ${ACME_BASE}/key.pem
        TRIGGER_RESTART=1
      fi
      [[ ${TRIGGER_RESTART} == 1 ]] && reload_configurations
      log_f "Retrying in 30 minutes..."
      sleep 30m
      exec $(readlink -f "$0")
      ;;
  esac

  log_f "ACME certificate validation done. Sleeping for another day."
  sleep 1d

done
