#!/bin/bash
catch_non_zero() {
  CMD=${1}
  ${CMD} > /dev/null
  EC=$?
  if [ ${EC} -ne 0 ]; then
    echo "Command ${CMD} failed to execute, exit code was ${EC}"
  fi
}
catch_non_zero "/usr/bin/redis-cli -h redis LTRIM ACME_LOG 0 LOG_LINES"
catch_non_zero "/usr/bin/redis-cli -h redis LTRIM POSTFIX_MAILLOG 0 LOG_LINES"
catch_non_zero "/usr/bin/redis-cli -h redis LTRIM DOVECOT_MAILLOG 0 LOG_LINES"
catch_non_zero "/usr/bin/redis-cli -h redis LTRIM SOGO_LOG 0 LOG_LINES"
catch_non_zero "/usr/bin/redis-cli -h redis LTRIM NETFILTER_LOG 0 LOG_LINES"
catch_non_zero "/usr/bin/redis-cli -h redis LTRIM AUTODISCOVER_LOG 0 LOG_LINES"
