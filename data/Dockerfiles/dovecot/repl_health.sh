#!/bin/bash

source /source_env.sh

# Do not attempt to write to slave
if [[ ! -z ${VALKEY_SLAVEOF_IP} ]]; then
  VALKEY_CMDLINE="redis-cli -h ${VALKEY_SLAVEOF_IP} -p ${VALKEY_SLAVEOF_PORT} -a ${VALKEYPASS} --no-auth-warning"
else
  VALKEY_CMDLINE="redis-cli -h valkey-mailcow -p 6379 -a ${VALKEYPASS} --no-auth-warning"
fi

# Is replication active?
# grep on file is less expensive than doveconf
if [ -n ${MAILCOW_REPLICA_IP} ]; then
  ${VALKEY_CMDLINE} SET DOVECOT_REPL_HEALTH 1 > /dev/null
  exit
fi

FAILED_SYNCS=$(doveadm replicator status | grep "Waiting 'failed' requests" | grep -oE '[0-9]+')

# Set amount of failed jobs as DOVECOT_REPL_HEALTH
# 1 failed job for mailcow.local is expected and healthy
if [[ "${FAILED_SYNCS}" != 0 ]] && [[ "${FAILED_SYNCS}" != 1 ]]; then
  printf "Dovecot replicator has %d failed jobs\n" "${FAILED_SYNCS}"
  ${VALKEY_CMDLINE} SET DOVECOT_REPL_HEALTH "${FAILED_SYNCS}" > /dev/null
else
  ${VALKEY_CMDLINE} SET DOVECOT_REPL_HEALTH 1 > /dev/null
fi
