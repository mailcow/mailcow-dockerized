#!/bin/bash

# Return values / exit codes
# 0 = cert created successfully
# 1 = cert renewed successfully
# 2 = cert not due for renewal
# * = errors

source /srv/functions.sh

CERT_DOMAINS=(${DOMAINS[@]})
CERT_DOMAIN=${CERT_DOMAINS[0]}
ACME_BASE=/var/lib/acme

TYPE=${1}
PREFIX=""
# only support rsa certificates for now
if [[ "${TYPE}" != "rsa" ]]; then
  log_f "Unknown certificate type '${TYPE}' requested"
  exit 5
fi

if [[ -z "${ACME_DNS_PROVIDER}" ]]; then
  log_f "ACME_DNS_PROVIDER is required when ACME_DNS_CHALLENGE is enabled"
  exit 6
fi

DOMAINS_FILE=${ACME_BASE}/${CERT_DOMAIN}/domains
CERT=${ACME_BASE}/${CERT_DOMAIN}/${PREFIX}cert.pem
SHARED_KEY=${ACME_BASE}/acme/${PREFIX}key.pem  # must already exist
KEY=${ACME_BASE}/${CERT_DOMAIN}/${PREFIX}key.pem
CSR=${ACME_BASE}/${CERT_DOMAIN}/${PREFIX}acme.csr

if [[ -z ${CERT_DOMAINS[*]} ]]; then
  log_f "Missing CERT_DOMAINS to obtain a certificate"
  exit 3
fi

if [[ "${LE_STAGING}" =~ ^([yY][eE][sS]|[yY])+$ ]]; then
  if [[ ! -z "${DIRECTORY_URL}" ]]; then
    log_f "Cannot use DIRECTORY_URL with LE_STAGING=y - ignoring DIRECTORY_URL"
  fi
  log_f "Using Let's Encrypt staging servers"
  ACME_SH_SERVER_ARGS=("--staging")
elif [[ ! -z "${DIRECTORY_URL}" ]]; then
  log_f "Using custom directory URL ${DIRECTORY_URL}"
  ACME_SH_SERVER_ARGS=("--server" "${DIRECTORY_URL}")
else
  log_f "Using Let's Encrypt production servers"
  ACME_SH_SERVER_ARGS=("--server" "letsencrypt")
fi

if [[ -f ${DOMAINS_FILE} && "$(cat ${DOMAINS_FILE})" ==  "${CERT_DOMAINS[*]}" ]]; then
  if [[ ! -f ${CERT} || ! -f "${KEY}" || -f "${ACME_BASE}/force_renew" ]]; then
    log_f "Certificate ${CERT} doesn't exist yet or forced renewal - start obtaining"
  elif ! openssl x509 -checkend 2592000 -noout -in ${CERT} > /dev/null; then
    log_f "Certificate ${CERT} is due for renewal (< 30 days) - start renewing"
  else
    log_f "Certificate ${CERT} validation done, neither changed nor due for renewal."
    exit 2
  fi
else
  log_f "Certificate ${CERT} missing or changed domains '${CERT_DOMAINS[*]}' - start obtaining"
fi

# Make backup
if [[ -f ${CERT} ]]; then
  DATE=$(date +%Y-%m-%d_%H_%M_%S)
  BACKUP_DIR=${ACME_BASE}/backups/${CERT_DOMAIN}/${PREFIX}${DATE}
  log_f "Creating backups in ${BACKUP_DIR} ..."
  mkdir -p ${BACKUP_DIR}/
  [[ -f ${DOMAINS_FILE} ]] && cp ${DOMAINS_FILE} ${BACKUP_DIR}/
  [[ -f ${CERT} ]] && cp ${CERT} ${BACKUP_DIR}/
  [[ -f ${KEY} ]] && cp ${KEY} ${BACKUP_DIR}/
  [[ -f ${CSR} ]] && cp ${CSR} ${BACKUP_DIR}/
fi

mkdir -p ${ACME_BASE}/${CERT_DOMAIN}
if [[ ! -f ${KEY} ]]; then
  log_f "Copying shared private key for this certificate..."
  cp ${SHARED_KEY} ${KEY}
  chmod 600 ${KEY}
fi

# Generating CSR to keep layout parity with HTTP challenge flow
printf "[SAN]\nsubjectAltName=" > /tmp/_SAN
printf "DNS:%s," "${CERT_DOMAINS[@]}" >> /tmp/_SAN
sed -i '$s/,$//' /tmp/_SAN
openssl req -new -sha256 -key ${KEY} -subj "/" -reqexts SAN -config <(cat "$(openssl version -d | sed 's/.*\"\(.*\)\"/\1/g')/openssl.cnf" /tmp/_SAN) > ${CSR}

log_f "Checking resolver..."
until dig letsencrypt.org +time=3 +tries=1 @unbound > /dev/null; do
  sleep 2
done
log_f "Resolver OK"

ACME_SH_BIN_PATH=${ACME_SH_BIN:-/opt/acme.sh/acme.sh}
ACME_SH_WORK_HOME=${ACME_SH_CONFIG_HOME:-/var/lib/acme/acme-sh}
mkdir -p ${ACME_SH_WORK_HOME}

if [[ ! -x ${ACME_SH_BIN_PATH} ]]; then
  log_f "acme.sh binary not found at ${ACME_SH_BIN_PATH}"
  exit 7
fi

if [[ ! -f ${ACME_SH_WORK_HOME}/account.conf ]]; then
  if [[ -z "${ACME_ACCOUNT_EMAIL}" ]]; then
    log_f "ACME_ACCOUNT_EMAIL is required to register a new acme.sh account"
    exit 8
  fi
  log_f "Registering acme.sh account for ${ACME_ACCOUNT_EMAIL}"
  REGISTER_CMD=("${ACME_SH_BIN_PATH}" "--home" "${ACME_SH_WORK_HOME}" "--config-home" "${ACME_SH_WORK_HOME}" "--cert-home" "${ACME_SH_WORK_HOME}" "--register-account" "-m" "${ACME_ACCOUNT_EMAIL}")
  REGISTER_CMD+=("${ACME_SH_SERVER_ARGS[@]}")
  REGISTER_RESPONSE=$("${REGISTER_CMD[@]}" 2>&1)
  if [[ $? -ne 0 ]]; then
    log_f "Failed to register acme.sh account: ${REGISTER_RESPONSE}"
    exit 9
  fi
fi

TMP_CERT=$(mktemp /tmp/acme-cert.XXXXXX)
TMP_FULLCHAIN=$(mktemp /tmp/acme-fullchain.XXXXXX)

ACME_CMD=("${ACME_SH_BIN_PATH}" "--home" "${ACME_SH_WORK_HOME}" "--config-home" "${ACME_SH_WORK_HOME}" "--cert-home" "${ACME_SH_WORK_HOME}")
ACME_CMD+=("${ACME_SH_SERVER_ARGS[@]}")
ACME_CMD+=("--issue" "--dns" "${ACME_DNS_PROVIDER}" "--key-file" "${KEY}" "--cert-file" "${TMP_CERT}" "--fullchain-file" "${TMP_FULLCHAIN}" "--force")
for domain in "${CERT_DOMAINS[@]}"; do
  ACME_CMD+=("-d" "${domain}")
done

log_f "Using command ${ACME_CMD[*]}"
ACME_RESPONSE=$("${ACME_CMD[@]}" 2>&1 | tee /dev/fd/5; exit ${PIPESTATUS[0]})
SUCCESS="$?"
ACME_RESPONSE_B64=$(echo "${ACME_RESPONSE}" | openssl enc -e -A -base64)
log_f "${ACME_RESPONSE_B64}" redis_only b64

case "$SUCCESS" in
  0)
    log_f "Deploying certificate ${CERT}..."
    if verify_hash_match ${TMP_FULLCHAIN} ${KEY}; then
      RETURN=0
      if [[ -f ${CERT} ]]; then
        RETURN=1
      fi
      mv -f ${TMP_FULLCHAIN} ${CERT}
      rm -f ${TMP_CERT}
      echo -n ${CERT_DOMAINS[*]} > ${DOMAINS_FILE}
      log_f "Certificate successfully obtained via DNS challenge"
      exit ${RETURN}
    else
      log_f "Certificate was requested, but key and certificate hashes do not match"
      rm -f ${TMP_CERT} ${TMP_FULLCHAIN}
      exit 4
    fi
    ;;
  *)
    log_f "Failed to obtain certificate ${CERT} for domains '${CERT_DOMAINS[*]}' via DNS challenge"
    redis-cli -h redis -a ${REDISPASS} --no-auth-warning SET ACME_FAIL_TIME "$(date +%s)"
    rm -f ${TMP_CERT} ${TMP_FULLCHAIN}
    exit 100${SUCCESS}
    ;;
esac
