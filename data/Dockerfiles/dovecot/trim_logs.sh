#!/bin/bash
/usr/bin/redis-cli -h redis LTRIM ACME_LOG 0 LOG_LINES
/usr/bin/redis-cli -h redis LTRIM POSTFIX_MAILLOG 0 LOG_LINES
/usr/bin/redis-cli -h redis LTRIM DOVECOT_MAILLOG 0 LOG_LINES
/usr/bin/redis-cli -h redis LTRIM SOGO_LOG 0 LOG_LINES
/usr/bin/redis-cli -h redis LTRIM NETFILTER_LOG 0 LOG_LINES
/usr/bin/redis-cli -h redis LTRIM AUTODISCOVER_LOG 0 LOG_LINES
