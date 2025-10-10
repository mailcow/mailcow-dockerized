#!/bin/bash
catch_non_zero() {
  CMD=${1}
  ${CMD} > /dev/null
  EC=$?
  if [ ${EC} -ne 0 ]; then
    echo "Command ${CMD} failed to execute, exit code was ${EC}"
  fi
}
source /source_env.sh
# Do not attempt to write to slave
if [[ ! -z ${VALKEY_SLAVEOF_IP} ]]; then
  VALKEY_CMDLINE="redis-cli -h ${VALKEY_SLAVEOF_IP} -p ${VALKEY_SLAVEOF_PORT} -a ${VALKEYPASS} --no-auth-warning"
else
  VALKEY_CMDLINE="redis-cli -h valkey-mailcow -p 6379 -a ${VALKEYPASS} --no-auth-warning"
fi
catch_non_zero "${VALKEY_CMDLINE} LTRIM ACME_LOG 0 ${LOG_LINES}"
catch_non_zero "${VALKEY_CMDLINE} LTRIM POSTFIX_MAILLOG 0 ${LOG_LINES}"
catch_non_zero "${VALKEY_CMDLINE} LTRIM DOVECOT_MAILLOG 0 ${LOG_LINES}"
catch_non_zero "${VALKEY_CMDLINE} LTRIM SOGO_LOG 0 ${LOG_LINES}"
catch_non_zero "${VALKEY_CMDLINE} LTRIM NETFILTER_LOG 0 ${LOG_LINES}"
catch_non_zero "${VALKEY_CMDLINE} LTRIM AUTODISCOVER_LOG 0 ${LOG_LINES}"
catch_non_zero "${VALKEY_CMDLINE} LTRIM API_LOG 0 ${LOG_LINES}"
catch_non_zero "${VALKEY_CMDLINE} LTRIM RL_LOG 0 ${LOG_LINES}"
catch_non_zero "${VALKEY_CMDLINE} LTRIM WATCHDOG_LOG 0 ${LOG_LINES}"
