#!/bin/bash

LOGLVL=info

if [ ${DEV_MODE} != "n" ]; then
  echo -e "\e[31mEnabling debug mode\e[0m"
  set -x
  LOGLVL=debug
fi

[[ ! -d /etc/postfix-tlspol ]] && mkdir -p /etc/postfix-tlspol
[[ ! -d /var/lib/postfix-tlspol ]] && mkdir -p /var/lib/postfix-tlspol

until dig +short mailcow.email > /dev/null; do
  echo "Waiting for DNS..."
  sleep 1
done

# Do not attempt to write to slave
if [[ ! -z ${REDIS_SLAVEOF_IP} ]]; then
  export REDIS_CMDLINE="redis-cli -h ${REDIS_SLAVEOF_IP} -p ${REDIS_SLAVEOF_PORT} -a ${REDISPASS} --no-auth-warning"
else
  export REDIS_CMDLINE="redis-cli -h redis -p 6379 -a ${REDISPASS} --no-auth-warning"
fi

until [[ $(${REDIS_CMDLINE} PING) == "PONG" ]]; do
  echo "Waiting for Redis..."
  sleep 2
done

echo "Waiting for Postfix..."
until ping postfix -c1 > /dev/null; do
  sleep 1
done
echo "Postfix OK"

cat <<EOF > /etc/postfix-tlspol/config.yaml
server:
  address: 0.0.0.0:8642

  log-level: ${LOGLVL}

  prefetch: true

  cache-file: /var/lib/postfix-tlspol/cache.db

dns:
  # must support DNSSEC
  address: 127.0.0.11:53
EOF

/usr/local/bin/postfix-tlspol -config /etc/postfix-tlspol/config.yaml