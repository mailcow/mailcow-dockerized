#!/bin/bash

function array_by_comma { local IFS=","; echo "$*"; }

# Wait for containers
while ! mysqladmin status --socket=/var/run/mysqld/mysqld.sock -u${DBUSER} -p${DBPASS} --silent; do
  echo "Waiting for SQL..."
  sleep 2
done

until [[ $(redis-cli -h redis-mailcow PING) == "PONG" ]]; do
  echo "Waiting for Redis..."
  sleep 2
done

# Set a default release format

if [[ -z $(redis-cli --raw -h redis-mailcow GET Q_RELEASE_FORMAT) ]]; then
  redis-cli --raw -h redis-mailcow SET Q_RELEASE_FORMAT raw
fi

# Check of mysql_upgrade

CONTAINER_ID=
# Todo: Better check if upgrade failed
# This can happen due to a broken sogo_view
[ -s /mysql_upgrade_loop ] && SQL_LOOP_C=$(cat /mysql_upgrade_loop)
until [[ ! -z "${CONTAINER_ID}" ]] && [[ "${CONTAINER_ID}" =~ ^[[:alnum:]]*$ ]]; do
  CONTAINER_ID=$(curl --silent --insecure https://dockerapi/containers/json | jq -r ".[] | {name: .Config.Labels[\"com.docker.compose.service\"], id: .Id}" 2> /dev/null | jq -rc "select( .name | tostring | contains(\"mysql-mailcow\")) | .id" 2> /dev/null)
done
echo "MySQL @ ${CONTAINER_ID}"
SQL_UPGRADE_RETURN=$(curl --silent --insecure -XPOST https://dockerapi/containers/${CONTAINER_ID}/exec -d '{"cmd":"system", "task":"mysql_upgrade"}' --silent -H 'Content-type: application/json' | jq -r .type)
if [[ ${SQL_UPGRADE_RETURN} == 'warning' ]]; then
  if [ -z ${SQL_LOOP_C} ]; then
    echo 1 > /mysql_upgrade_loop
    echo "MySQL applied an upgrade, restarting PHP-FPM..."
    exit 1
  else
    rm /mysql_upgrade_loop
    echo "MySQL was not applied previously, skipping. Restart php-fpm-mailcow to retry or run mysql_upgrade manually."
    while ! mysqladmin status --socket=/var/run/mysqld/mysqld.sock -u${DBUSER} -p${DBPASS} --silent; do
      echo "Waiting for SQL to return..."
      sleep 2
    done
  fi
else
  echo "MySQL is up-to-date"
fi

# Trigger db init
echo "Running DB init..."
php -c /usr/local/etc/php -f /web/inc/init_db.inc.php

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
DELETE FROM api;
INSERT INTO api (api_key, active, allow_from) VALUES ("${API_KEY}", "1", "${VALIDATED_IPS}");
EOF
  fi
fi

exec "$@"
