#!/bin/bash
set -e

function array_by_comma { local IFS=","; echo "$*"; }

# Wait for containers
while ! mysqladmin ping --socket=/var/run/mysqld/mysqld.sock -u${DBUSER} -p${DBPASS} --silent; do
  echo "Waiting for SQL..."
  sleep 2
done

until [[ $(redis-cli -h redis-mailcow PING) == "PONG" ]]; do
  echo "Waiting for Redis..."
  sleep 2
done

# Migrate domain map
declare -a DOMAIN_ARR
redis-cli -h redis-mailcow DEL DOMAIN_MAP
while read line
do
  DOMAIN_ARR+=("$line")
done < <(mysql --socket=/var/run/mysqld/mysqld.sock -u ${DBUSER} -p${DBPASS} ${DBNAME} -e "SELECT domain FROM domain" -Bs)
while read line
do
  DOMAIN_ARR+=("$line")
done < <(mysql --socket=/var/run/mysqld/mysqld.sock -u ${DBUSER} -p${DBPASS} ${DBNAME} -e "SELECT alias_domain FROM alias_domain" -Bs)

if [[ ! -z ${DOMAIN_ARR} ]]; then
for domain in "${DOMAIN_ARR[@]}"; do
  redis-cli -h redis-mailcow HSET DOMAIN_MAP ${domain} 1
done
fi

# Set API options if env vars are not empty

if [[ ${API_ALLOW_FROM} != "invalid" ]] && \
  [[ ${API_KEY} != "invalid" ]] && \
  [[ ! -z ${API_KEY} ]] && \
  [[ ! -z ${API_ALLOW_FROM} ]]; then
  IFS=',' read -r -a API_ALLOW_FROM_ARR <<< "${API_ALLOW_FROM}"
  declare -a VALIDATED_API_ALLOW_FROM_ARR
  REGEX_IP6='^([0-9a-fA-F]{0,4}:){1,7}[0-9a-fA-F]{0,4}$'
  REGEX_IP4='^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$'

  for IP in "${API_ALLOW_FROM_ARR[@]}"; do
    if [[ ${IP} =~ ${REGEX_IP6} ]] || [[ ${IP} =~ ${REGEX_IP4} ]]; then
      VALIDATED_API_ALLOW_FROM_ARR+=("${IP}")
    fi
  done
  VALIDATED_IPS=$(array_by_comma ${VALIDATED_API_ALLOW_FROM_ARR[*]})
  if [[ ! -z ${VALIDATED_IPS} ]]; then
    mysql --socket=/var/run/mysqld/mysqld.sock -u ${DBUSER} -p${DBPASS} ${DBNAME} << EOF
INSERT INTO api (username, api_key, active, allow_from)
SELECT username, "${API_KEY}", '1', "${VALIDATED_IPS}" FROM admin WHERE superadmin='1' AND active='1'
ON DUPLICATE KEY UPDATE active = '1', allow_from = "${VALIDATED_IPS}", api_key = "${API_KEY}";
EOF
  fi
fi

exec "$@"
