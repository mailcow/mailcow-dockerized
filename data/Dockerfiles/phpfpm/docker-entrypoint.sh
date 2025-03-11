#!/bin/bash

function array_by_comma { local IFS=","; echo "$*"; }

# Wait for containers
while ! mariadb-admin status --ssl=false --socket=/var/run/mysqld/mysqld.sock -u${DBUSER} -p${DBPASS} --silent; do
  echo "Waiting for SQL..."
  sleep 2
done

# Do not attempt to write to slave
if [[ ! -z ${VALKEY_SLAVEOF_IP} ]]; then
  VALKEY_HOST=$VALKEY_SLAVEOF_IP
  VALKEY_PORT=$VALKEY_SLAVEOF_PORT
else
  VALKEY_HOST="valkey-mailcow"
  VALKEY_PORT="6379"
fi
VALKEY_CMDLINE="redis-cli -h ${VALKEY_HOST} -p ${VALKEY_PORT} -a ${VALKEYPASS} --no-auth-warning"

until [[ $(${VALKEY_CMDLINE} PING) == "PONG" ]]; do
  echo "Waiting for Valkey..."
  sleep 2
done

# Set valkey session store
echo -n '
session.save_handler = redis
session.save_path = "tcp://'${VALKEY_HOST}':'${VALKEY_PORT}'?auth='${VALKEYPASS}'"
' > /usr/local/etc/php/conf.d/session_store.ini

# Check mysql_upgrade (master and slave)
CONTAINER_ID=
until [[ ! -z "${CONTAINER_ID}" ]] && [[ "${CONTAINER_ID}" =~ ^[[:alnum:]]*$ ]]; do
  CONTAINER_ID=$(curl --silent --insecure https://dockerapi.${COMPOSE_PROJECT_NAME}_mailcow-network/containers/json | jq -r ".[] | {name: .Config.Labels[\"com.docker.compose.service\"], project: .Config.Labels[\"com.docker.compose.project\"], id: .Id}" 2> /dev/null | jq -rc "select( .name | tostring | contains(\"mysql-mailcow\")) | select( .project | tostring | contains(\"${COMPOSE_PROJECT_NAME,,}\")) | .id" 2> /dev/null)
  echo "Could not get mysql-mailcow container id... trying again"
  sleep 2
done
echo "MySQL @ ${CONTAINER_ID}"
SQL_LOOP_C=0
SQL_CHANGED=0
until [[ ${SQL_UPGRADE_STATUS} == 'success' ]]; do
  if [ ${SQL_LOOP_C} -gt 4 ]; then
    echo "Tried to upgrade MySQL and failed, giving up after ${SQL_LOOP_C} retries and starting container (oops, not good)"
    break
  fi
  SQL_FULL_UPGRADE_RETURN=$(curl --silent --insecure -XPOST https://dockerapi.${COMPOSE_PROJECT_NAME}_mailcow-network/containers/${CONTAINER_ID}/exec -d '{"cmd":"system", "task":"mysql_upgrade"}' --silent -H 'Content-type: application/json')
  SQL_UPGRADE_STATUS=$(echo ${SQL_FULL_UPGRADE_RETURN} | jq -r .type)
  SQL_LOOP_C=$((SQL_LOOP_C+1))
  echo "SQL upgrade iteration #${SQL_LOOP_C}"
  if [[ ${SQL_UPGRADE_STATUS} == 'warning' ]]; then
    SQL_CHANGED=1
    echo "MySQL applied an upgrade, debug output:"
    echo ${SQL_FULL_UPGRADE_RETURN}
    sleep 3
    while ! mariadb-admin status --ssl=false --socket=/var/run/mysqld/mysqld.sock -u${DBUSER} -p${DBPASS} --silent; do
      echo "Waiting for SQL to return, please wait"
      sleep 2
    done
    continue
  elif [[ ${SQL_UPGRADE_STATUS} == 'success' ]]; then
    echo "MySQL is up-to-date - debug output:"
    echo ${SQL_FULL_UPGRADE_RETURN}
  else
    echo "No valid reponse for mysql_upgrade was received, debug output:"
    echo ${SQL_FULL_UPGRADE_RETURN}
  fi
done

# doing post-installation stuff, if SQL was upgraded (master and slave)
if [ ${SQL_CHANGED} -eq 1 ]; then
  POSTFIX=$(curl --silent --insecure https://dockerapi.${COMPOSE_PROJECT_NAME}_mailcow-network/containers/json | jq -r ".[] | {name: .Config.Labels[\"com.docker.compose.service\"], project: .Config.Labels[\"com.docker.compose.project\"], id: .Id}" 2> /dev/null | jq -rc "select( .name | tostring | contains(\"postfix-mailcow\")) | select( .project | tostring | contains(\"${COMPOSE_PROJECT_NAME,,}\")) | .id" 2> /dev/null)
  if [[ -z "${POSTFIX}" ]] || ! [[ "${POSTFIX}" =~ ^[[:alnum:]]*$ ]]; then
    echo "Could not determine Postfix container ID, skipping Postfix restart."
  else
    echo "Restarting Postfix"
    curl -X POST --silent --insecure https://dockerapi.${COMPOSE_PROJECT_NAME}_mailcow-network/containers/${POSTFIX}/restart | jq -r '.msg'
    echo "Sleeping 5 seconds..."
    sleep 5
  fi
fi

# Check mysql tz import (master and slave)
TZ_CHECK=$(mysql --socket=/var/run/mysqld/mysqld.sock -u ${DBUSER} -p${DBPASS} ${DBNAME} -e "SELECT CONVERT_TZ('2019-11-02 23:33:00','Europe/Berlin','UTC') AS time;" -BN 2> /dev/null)
if [[ -z ${TZ_CHECK} ]] || [[ "${TZ_CHECK}" == "NULL" ]]; then
  SQL_FULL_TZINFO_IMPORT_RETURN=$(curl --silent --insecure -XPOST https://dockerapi.${COMPOSE_PROJECT_NAME}_mailcow-network/containers/${CONTAINER_ID}/exec -d '{"cmd":"system", "task":"mysql_tzinfo_to_sql"}' --silent -H 'Content-type: application/json')
  echo "MySQL mysql_tzinfo_to_sql - debug output:"
  echo ${SQL_FULL_TZINFO_IMPORT_RETURN}
fi

if [[ "${MASTER}" =~ ^([yY][eE][sS]|[yY])+$ ]]; then
  echo "We are master, preparing..."
  # Set a default release format
  if [[ -z $(${VALKEY_CMDLINE} --raw GET Q_RELEASE_FORMAT) ]]; then
    ${VALKEY_CMDLINE} --raw SET Q_RELEASE_FORMAT raw
  fi

  # Set max age of q items - if unset
  if [[ -z $(${VALKEY_CMDLINE} --raw GET Q_MAX_AGE) ]]; then
    ${VALKEY_CMDLINE} --raw SET Q_MAX_AGE 365
  fi

  # Set default password policy - if unset
  if [[ -z $(${VALKEY_CMDLINE} --raw HGET PASSWD_POLICY length) ]]; then
    ${VALKEY_CMDLINE} --raw HSET PASSWD_POLICY length 6
    ${VALKEY_CMDLINE} --raw HSET PASSWD_POLICY chars 0
    ${VALKEY_CMDLINE} --raw HSET PASSWD_POLICY special_chars 0
    ${VALKEY_CMDLINE} --raw HSET PASSWD_POLICY lowerupper 0
    ${VALKEY_CMDLINE} --raw HSET PASSWD_POLICY numbers 0
  fi

  # Trigger db init
  echo "Running DB init..."
  php -c /usr/local/etc/php -f /web/inc/init_db.inc.php

  # Recreating domain map
  echo "Rebuilding domain map in Valkey..."
  declare -a DOMAIN_ARR
    ${VALKEY_CMDLINE} DEL DOMAIN_MAP > /dev/null
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
    ${VALKEY_CMDLINE} HSET DOMAIN_MAP ${domain} 1 > /dev/null
  done
  fi

  # Set API options if env vars are not empty
  if [[ ${API_ALLOW_FROM} != "invalid" ]] && [[ ! -z ${API_ALLOW_FROM} ]]; then
    IFS=',' read -r -a API_ALLOW_FROM_ARR <<< "${API_ALLOW_FROM}"
    declare -a VALIDATED_API_ALLOW_FROM_ARR
    REGEX_IP6='^([0-9a-fA-F]{0,4}:){1,7}[0-9a-fA-F]{0,4}(/([0-9]|[1-9][0-9]|1[0-1][0-9]|12[0-8]))?$'
    REGEX_IP4='^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+(/([0-9]|[1-2][0-9]|3[0-2]))?$'
    for IP in "${API_ALLOW_FROM_ARR[@]}"; do
      if [[ ${IP} =~ ${REGEX_IP6} ]] || [[ ${IP} =~ ${REGEX_IP4} ]]; then
        VALIDATED_API_ALLOW_FROM_ARR+=("${IP}")
      fi
    done
    VALIDATED_IPS=$(array_by_comma ${VALIDATED_API_ALLOW_FROM_ARR[*]})
    if [[ ! -z ${VALIDATED_IPS} ]]; then
      if [[ ${API_KEY} != "invalid" ]] && [[ ! -z ${API_KEY} ]]; then
        mysql --socket=/var/run/mysqld/mysqld.sock -u ${DBUSER} -p${DBPASS} ${DBNAME} << EOF
DELETE FROM api WHERE access = 'rw';
INSERT INTO api (api_key, active, allow_from, access) VALUES ("${API_KEY}", "1", "${VALIDATED_IPS}", "rw");
EOF
      fi
      if [[ ${API_KEY_READ_ONLY} != "invalid" ]] && [[ ! -z ${API_KEY_READ_ONLY} ]]; then
        mysql --socket=/var/run/mysqld/mysqld.sock -u ${DBUSER} -p${DBPASS} ${DBNAME} << EOF
DELETE FROM api WHERE access = 'ro';
INSERT INTO api (api_key, active, allow_from, access) VALUES ("${API_KEY_READ_ONLY}", "1", "${VALIDATED_IPS}", "ro");
EOF
      fi
    fi
  fi

  # Create events (master only, STATUS for event on slave will be SLAVESIDE_DISABLED)
  mysql --socket=/var/run/mysqld/mysqld.sock -u ${DBUSER} -p${DBPASS} ${DBNAME} << EOF
DROP EVENT IF EXISTS clean_spamalias;
DELIMITER //
CREATE EVENT clean_spamalias
ON SCHEDULE EVERY 1 DAY DO
BEGIN
  DELETE FROM spamalias WHERE validity < UNIX_TIMESTAMP();
END;
//
DELIMITER ;
DROP EVENT IF EXISTS clean_oauth2;
DELIMITER //
CREATE EVENT clean_oauth2
ON SCHEDULE EVERY 1 DAY DO
BEGIN
  DELETE FROM oauth_refresh_tokens WHERE expires < NOW();
  DELETE FROM oauth_access_tokens WHERE expires < NOW();
  DELETE FROM oauth_authorization_codes WHERE expires < NOW();
END;
//
DELIMITER ;
DROP EVENT IF EXISTS clean_sasl_log;
DELIMITER //
CREATE EVENT clean_sasl_log
ON SCHEDULE EVERY 1 DAY DO
BEGIN
  DELETE sasl_log.* FROM sasl_log
    LEFT JOIN (
      SELECT username, service, MAX(datetime) AS lastdate
      FROM sasl_log
      GROUP BY username, service
    ) AS last ON sasl_log.username = last.username AND sasl_log.service = last.service
    WHERE datetime < DATE_SUB(NOW(), INTERVAL 31 DAY) AND datetime < lastdate;
  DELETE FROM sasl_log
    WHERE username NOT IN (SELECT username FROM mailbox) AND
    datetime < DATE_SUB(NOW(), INTERVAL 31 DAY);
END;
//
DELIMITER ;
EOF
fi

# Create dummy for custom overrides of mailcow style
[[ ! -f /web/css/build/0081-custom-mailcow.css ]] && echo '/* Autogenerated by mailcow */' > /web/css/build/0081-custom-mailcow.css

# Fix permissions for global filters
chown -R 82:82 /global_sieve/*

# Fix permissions on twig cache folder
chown -R 82:82 /web/templates/cache
# Clear cache
find /web/templates/cache/* -not -name '.gitkeep' -delete

# Run hooks
for file in /hooks/*; do
  if [ -x "${file}" ]; then
    echo "Running hook ${file}"
    "${file}"
  fi
done

exec "$@"
