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
      tr '%&;$"[]{}-\r\n' ' ')\"}" > /dev/null
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

mkdir -p ${ACME_BASE}/acme

# Migrate
[[ -f ${ACME_BASE}/acme/private/privkey.pem ]] && mv ${ACME_BASE}/acme/private/privkey.pem ${ACME_BASE}/acme/key.pem
[[ -f ${ACME_BASE}/acme/private/account.key ]] && mv ${ACME_BASE}/acme/private/account.key ${ACME_BASE}/acme/account.pem


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

verify_challenge_path(){
  # verify_challenge_path URL 4|6
  RAND_FILE=${RANDOM}${RANDOM}${RANDOM}
  touch /var/www/acme/${RAND_FILE}
  if [[ "$(curl -${2} http://${1}/.well-known/acme-challenge/${RAND_FILE} --write-out %{http_code} --silent --output /dev/null)" =~ ^(2|3)  ]]; then
    rm /var/www/acme/${RAND_FILE}
    return 0
  else
    rm /var/www/acme/${RAND_FILE}
    return 1
  fi
}

[[ ! -f ${ACME_BASE}/dhparams.pem ]] && cp ${SSL_EXAMPLE}/dhparams.pem ${ACME_BASE}/dhparams.pem

if [[ -f ${ACME_BASE}/cert.pem ]] && [[ -f ${ACME_BASE}/key.pem ]]; then
  ISSUER=$(openssl x509 -in ${ACME_BASE}/cert.pem -noout -issuer)
  if [[ ${ISSUER} != *"Let's Encrypt"* && ${ISSUER} != *"mailcow"* && ${ISSUER} != *"Fake LE Intermediate"* ]]; then
    log_f "Found certificate with issuer other than mailcow snake-oil CA and Let's Encrypt, skipping ACME client..."
    sleep 3650d
    exec $(readlink -f "$0")
  fi
else
  if [[ -f ${ACME_BASE}/acme/cert.pem ]] && [[ -f ${ACME_BASE}/acme/key.pem ]]; then
    if verify_hash_match ${ACME_BASE}/acme/cert.pem ${ACME_BASE}/acme/key.pem; then
      log_f "Restoring previous acme certificate and restarting script..."
      cp ${ACME_BASE}/acme/cert.pem ${ACME_BASE}/cert.pem
      cp ${ACME_BASE}/acme/key.pem ${ACME_BASE}/key.pem
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

log_f "Waiting for database... " no_nl
while ! mysqladmin status --socket=/var/run/mysqld/mysqld.sock -u${DBUSER} -p${DBPASS} --silent; do
  sleep 2
done
log_f "OK" no_date

log_f "Waiting for Nginx... " no_nl
until $(curl --output /dev/null --silent --head --fail http://nginx:8081); do
  sleep 2
done
log_f "OK" no_date

# Waiting for domain table
log_f "Waiting for domain table... " no_nl
while [[ -z ${DOMAIN_TABLE} ]]; do
  curl --silent http://nginx/ >/dev/null 2>&1
  DOMAIN_TABLE=$(mysql --socket=/var/run/mysqld/mysqld.sock -u ${DBUSER} -p${DBPASS} ${DBNAME} -e "SHOW TABLES LIKE 'domain'" -Bs)
  [[ -z ${DOMAIN_TABLE} ]] && sleep 10
done
log_f "OK" no_date

log_f "Initializing, please wait... "

while true; do

  # Re-using previous acme-mailcow account and domain keys
  if [[ ! -f ${ACME_BASE}/acme/key.pem ]]; then
    log_f "Generating missing domain private key..."
    openssl genrsa 4096 > ${ACME_BASE}/acme/key.pem
  else
    log_f "Using existing domain key ${ACME_BASE}/acme/key.pem"
  fi
  if [[ ! -f ${ACME_BASE}/acme/account.pem ]]; then
    log_f "Generating missing Lets Encrypt account key..."
    openssl genrsa 4096 > ${ACME_BASE}/acme/account.pem
  else
    log_f "Using existing Lets Encrypt account key ${ACME_BASE}/acme/account.pem"
  fi

  # Skipping IP check when we like to live dangerously
  if [[ "${SKIP_IP_CHECK}" =~ ^([yY][eE][sS]|[yY])+$ ]]; then
    SKIP_IP_CHECK=y
  fi

  # Cleaning up and init validation arrays
  unset SQL_DOMAIN_ARR
  unset VALIDATED_CONFIG_DOMAINS
  unset ADDITIONAL_VALIDATED_SAN
  unset ADDITIONAL_WC_ARR
  unset ADDITIONAL_SAN_ARR
  unset SAN_CHANGE
  unset SAN_ARRAY_NOW
  unset ORPHANED_SAN
  unset ADDED_SAN
  SAN_CHANGE=0
  declare -a SAN_ARRAY_NOW
  declare -a ORPHANED_SAN
  declare -a ADDED_SAN
  declare -a SQL_DOMAIN_ARR
  declare -a VALIDATED_CONFIG_DOMAINS
  declare -a ADDITIONAL_VALIDATED_SAN
  declare -a ADDITIONAL_WC_ARR
  declare -a ADDITIONAL_SAN_ARR
  IFS=',' read -r -a TMP_ARR <<< "${ADDITIONAL_SAN}"
  for i in "${TMP_ARR[@]}" ; do
    if [[ "$i" =~ \.\*$ ]]; then
      ADDITIONAL_WC_ARR+=(${i::-2})
    else
      ADDITIONAL_SAN_ARR+=($i)
    fi
  done
  ADDITIONAL_WC_ARR+=('autodiscover')

  # Start IP detection
  log_f "Detecting IP addresses... " no_nl
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

  #########################################
  # IP and webroot challenge verification #
  while read domains; do
    SQL_DOMAIN_ARR+=("${domains}")
  done < <(mysql --socket=/var/run/mysqld/mysqld.sock -u ${DBUSER} -p${DBPASS} ${DBNAME} -e "SELECT domain FROM domain WHERE backupmx=0" -Bs)

  for SQL_DOMAIN in "${SQL_DOMAIN_ARR[@]}"; do
    for SUBDOMAIN in "${ADDITIONAL_WC_ARR[@]}"; do
      if [[  "${SUBDOMAIN}.${SQL_DOMAIN}" != "${MAILCOW_HOSTNAME}" ]]; then
        A_SUBDOMAIN=$(dig A ${SUBDOMAIN}.${SQL_DOMAIN} +short | tail -n 1)
        AAAA_SUBDOMAIN=$(dig AAAA ${SUBDOMAIN}.${SQL_DOMAIN} +short | tail -n 1)
        # Check if CNAME without v6 enabled target
        if [[ ! -z ${AAAA_SUBDOMAIN} ]] && [[ -z $(echo ${AAAA_SUBDOMAIN} | grep "^\([0-9a-fA-F]\{0,4\}:\)\{1,7\}[0-9a-fA-F]\{0,4\}$") ]]; then
          AAAA_SUBDOMAIN=
        fi
        if [[ ! -z ${AAAA_SUBDOMAIN} ]]; then
          log_f "Found AAAA record for ${SUBDOMAIN}.${SQL_DOMAIN}: ${AAAA_SUBDOMAIN} - skipping A record check"
          if [[ $(expand ${IPV6:-"0000:0000:0000:0000:0000:0000:0000:0000"}) == $(expand ${AAAA_SUBDOMAIN}) ]] || [[ ${SKIP_IP_CHECK} == "y" ]]; then
            if verify_challenge_path "${SUBDOMAIN}.${SQL_DOMAIN}" 6; then
              log_f "Confirmed AAAA record ${AAAA_SUBDOMAIN}"
              VALIDATED_CONFIG_DOMAINS+=("${SUBDOMAIN}.${SQL_DOMAIN}")
            else
              log_f "Confirmed AAAA record ${AAAA_SUBDOMAIN}, but HTTP validation failed"
            fi
          else
            log_f "Cannot match your IP ${IPV6:-NO_IPV6_LINK} against hostname ${SUBDOMAIN}.${SQL_DOMAIN} ($(expand ${AAAA_SUBDOMAIN}))"
          fi
        elif [[ ! -z ${A_SUBDOMAIN} ]]; then
          log_f "Found A record for ${SUBDOMAIN}.${SQL_DOMAIN}: ${A_SUBDOMAIN}"
          if [[ ${IPV4:-ERR} == ${A_SUBDOMAIN} ]] || [[ ${SKIP_IP_CHECK} == "y" ]]; then
            if verify_challenge_path "${SUBDOMAIN}.${SQL_DOMAIN}" 4; then
              log_f "Confirmed A record ${A_SUBDOMAIN}"
              VALIDATED_CONFIG_DOMAINS+=("${SUBDOMAIN}.${SQL_DOMAIN}")
            else
              log_f "Confirmed AAAA record ${A_SUBDOMAIN}, but HTTP validation failed"
            fi
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
      if verify_challenge_path "${MAILCOW_HOSTNAME}" 6; then
        log_f "Confirmed AAAA record ${AAAA_MAILCOW_HOSTNAME}"
        VALIDATED_MAILCOW_HOSTNAME=${MAILCOW_HOSTNAME}
      else
        log_f "Confirmed AAAA record ${A_MAILCOW_HOSTNAME}, but HTTP validation failed"
      fi
    else
      log_f "Cannot match your IP ${IPV6:-NO_IPV6_LINK} against hostname ${MAILCOW_HOSTNAME} ($(expand ${AAAA_MAILCOW_HOSTNAME}))"
    fi
  elif [[ ! -z ${A_MAILCOW_HOSTNAME} ]]; then
    log_f "Found A record for ${MAILCOW_HOSTNAME}: ${A_MAILCOW_HOSTNAME}"
    if [[ ${IPV4:-ERR} == ${A_MAILCOW_HOSTNAME} ]] || [[ ${SKIP_IP_CHECK} == "y" ]]; then
      if verify_challenge_path "${MAILCOW_HOSTNAME}" 4; then
        log_f "Confirmed A record ${A_MAILCOW_HOSTNAME}"
        VALIDATED_MAILCOW_HOSTNAME=${MAILCOW_HOSTNAME}
      else
        log_f "Confirmed A record ${A_MAILCOW_HOSTNAME}, but HTTP validation failed"
      fi
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
        if verify_challenge_path "${SAN}" 6; then
          log_f "Confirmed AAAA record ${AAAA_SAN}"
          ADDITIONAL_VALIDATED_SAN+=("${SAN}")
        else
          log_f "Confirmed AAAA record ${AAAA_SAN}, but HTTP validation failed"
        fi
      else
        log_f "Cannot match your IP ${IPV6:-NO_IPV6_LINK} against hostname ${SAN} ($(expand ${AAAA_SAN}))"
      fi
    elif [[ ! -z ${A_SAN} ]]; then
      log_f "Found A record for ${SAN}: ${A_SAN}"
      if [[ ${IPV4:-ERR} == ${A_SAN} ]] || [[ ${SKIP_IP_CHECK} == "y" ]]; then
        if verify_challenge_path "${SAN}" 4; then
          log_f "Confirmed A record ${A_SAN}"
          ADDITIONAL_VALIDATED_SAN+=("${SAN}")
        else
          log_f "Confirmed A record ${A_SAN}, but HTTP validation failed"
        fi
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

  # Collecting SANs from active certificate
  SAN_NAMES=$(openssl x509 -noout -text -in ${ACME_BASE}/cert.pem | awk '/X509v3 Subject Alternative Name/ {getline;gsub(/ /, "", $0); print}' | tr -d "DNS:")
  if [[ ! -z ${SAN_NAMES} ]]; then
    IFS=',' read -a SAN_ARRAY_NOW <<< ${SAN_NAMES}
  fi

  # Finding difference in SAN array now vs. SAN array by current configuration
  array_diff ORPHANED_SAN SAN_ARRAY_NOW ALL_VALIDATED
  if [[ ! -z ${ORPHANED_SAN[*]} ]]; then
    log_f "Found orphaned SANs ${ORPHANED_SAN[*]}"
    SAN_CHANGE=1
  fi
  array_diff ADDED_SAN ALL_VALIDATED SAN_ARRAY_NOW
  if [[ ! -z ${ADDED_SAN[*]} ]]; then
    log_f "Found new SANs ${ADDED_SAN[*]}"
    SAN_CHANGE=1
  fi

  if [[ ${SAN_CHANGE} == 0 ]]; then
    # Certificate did not change but could be due for renewal (4 weeks)
    if ! openssl x509 -checkend 1209600 -noout -in ${ACME_BASE}/cert.pem; then
      log_f "Certificate is due for renewal (< 2 weeks)"
    else
      log_f "Certificate validation done, neither changed nor due for renewal, sleeping for another day."
      sleep 1d
      continue
    fi
  fi

  DATE=$(date +%Y-%m-%d_%H_%M_%S)
  log_f "Creating backups in ${ACME_BASE}/backups/${DATE}/ ..."
  mkdir -p ${ACME_BASE}/backups/${DATE}/
  [[ -f ${ACME_BASE}/acme/acme.csr ]] && cp ${ACME_BASE}/acme/acme.csr ${ACME_BASE}/backups/${DATE}/
  [[ -f ${ACME_BASE}/acme/cert.pem ]] && cp ${ACME_BASE}/acme/cert.pem ${ACME_BASE}/backups/${DATE}/
  [[ -f ${ACME_BASE}/acme/key.pem ]] && cp ${ACME_BASE}/acme/key.pem ${ACME_BASE}/backups/${DATE}/
  [[ -f ${ACME_BASE}/acme/account.pem ]] && cp ${ACME_BASE}/acme/account.pem ${ACME_BASE}/backups/${DATE}/

  # Generating CSR
  printf "[SAN]\nsubjectAltName=" > /tmp/_SAN
  printf "DNS:%s," "${ALL_VALIDATED[@]}" >> /tmp/_SAN
  sed -i '$s/,$//' /tmp/_SAN
  openssl req -new -sha256 -key ${ACME_BASE}/acme/key.pem -subj "/" -reqexts SAN -config <(cat /etc/ssl/openssl.cnf /tmp/_SAN) > ${ACME_BASE}/acme/acme.csr

  if [[ "${LE_STAGING}" =~ ^([yY][eE][sS]|[yY])+$ ]]; then
    log_f "Using Let's Encrypt staging servers"
    STAGING_PARAMETER='--directory-url https://acme-staging-v02.api.letsencrypt.org/directory'
  else
    STAGING_PARAMETER=
  fi

  # acme-tiny writes info to stderr and ceritifcate to stdout
  # The redirects will do the following:
  # - redirect stdout to temp certificate file
  # - redirect acme-tiny stderr to stdout (logs to variable ACME_RESPONSE)
  # - tee stderr to get live output and log to dockerd

  ACME_RESPONSE=$(acme-tiny ${STAGING_PARAMETER} \
    --account-key ${ACME_BASE}/acme/account.pem \
    --disable-check \
    --csr ${ACME_BASE}/acme/acme.csr \
    --acme-dir /var/www/acme/ 2>&1 > /tmp/_cert.pem | tee /dev/fd/5)

  case "$?" in
    0) # cert requested
      ACME_RESPONSE_B64=$(echo "${ACME_RESPONSE}" | openssl enc -e -A -base64)
      log_f "${ACME_RESPONSE_B64}" redis_only b64
      log_f "Deploying..."
      # Deploy the new certificate and key
      # Moving temp cert to acme/cert.pem
      if verify_hash_match /tmp/_cert.pem ${ACME_BASE}/acme/key.pem; then
        mv /tmp/_cert.pem ${ACME_BASE}/acme/cert.pem
        cp ${ACME_BASE}/acme/cert.pem ${ACME_BASE}/cert.pem
        cp ${ACME_BASE}/acme/key.pem ${ACME_BASE}/key.pem
        reload_configurations
        rm /var/www/acme/*
        log_f "Certificate successfully deployed, removing backup, sleeping 1d"
        sleep 1d
      else
        log_f "Certificate was successfully requested, but key and certificate have non-matching hashes, ignoring certificate"
        log_f "Retrying in 30 minutes..."
        sleep 30m
        exec $(readlink -f "$0")
      fi
      ;;
    *) # non-zero is non-fun
      ACME_RESPONSE_B64=$(echo "${ACME_RESPONSE}" | openssl enc -e -A -base64)
      log_f "${ACME_RESPONSE_B64}" redis_only b64
      log_f "Retrying in 30 minutes..."
      sleep 30m
      exec $(readlink -f "$0")
      ;;
  esac

done
