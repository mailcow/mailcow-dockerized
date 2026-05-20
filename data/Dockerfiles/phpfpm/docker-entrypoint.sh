#!/bin/bash

function array_by_comma { local IFS=","; echo "$*"; }

# Wait for containers
while ! mariadb-admin status --ssl=false --socket=/var/run/mysqld/mysqld.sock -u${DBUSER} -p${DBPASS} --silent; do
  echo "Waiting for SQL..."
  sleep 2
done

# Do not attempt to write to slave
if [[ ! -z ${REDIS_SLAVEOF_IP} ]]; then
  REDIS_HOST=$REDIS_SLAVEOF_IP
  REDIS_PORT=$REDIS_SLAVEOF_PORT
else
  REDIS_HOST="redis"
  REDIS_PORT="6379"
fi
REDIS_CMDLINE="redis-cli -h ${REDIS_HOST} -p ${REDIS_PORT} -a ${REDISPASS} --no-auth-warning"

until [[ $(${REDIS_CMDLINE} PING) == "PONG" ]]; do
  echo "Waiting for Redis..."
  sleep 2
done

# Set redis session store
echo -n '
session.save_handler = redis
session.save_path = "tcp://'${REDIS_HOST}':'${REDIS_PORT}'?auth='${REDISPASS}'"
' > /usr/local/etc/php/conf.d/session_store.ini

# Wait for MariaDB. The upstream mariadb image already runs mariadb-upgrade
# itself on startup when needed
echo "Waiting for MariaDB socket at /var/run/mysqld/mysqld.sock..."
WAIT_C=0
until mariadb --skip-ssl --socket=/var/run/mysqld/mysqld.sock -u${DBUSER} -p${DBPASS} -e "SELECT 1" >/dev/null 2>&1; do
  WAIT_C=$((WAIT_C+1))
  if [ ${WAIT_C} -gt 60 ]; then
    echo "MariaDB did not respond after 60s — continuing anyway."
    break
  fi
  sleep 1
done
echo "MariaDB is ready."

# Timezone tables — check if CONVERT_TZ works, import if it returns NULL.
# Some Alpine builds drop mariadb-tzinfo-to-sql; fall back to a Python
# emitter that produces the same INSERT statements from /usr/share/zoneinfo.
TZ_CHECK=$(mariadb --skip-ssl --socket=/var/run/mysqld/mysqld.sock -u ${DBUSER} -p${DBPASS} ${DBNAME} -e "SELECT CONVERT_TZ('2019-11-02 23:33:00','Europe/Berlin','UTC') AS time;" -BN 2> /dev/null)
if [[ -z ${TZ_CHECK} ]] || [[ "${TZ_CHECK}" == "NULL" ]]; then
  echo "Importing timezone data into mysql.time_zone_* …"
  if command -v mariadb-tzinfo-to-sql >/dev/null 2>&1; then
    mariadb-tzinfo-to-sql /usr/share/zoneinfo 2>/dev/null \
      | mariadb --skip-ssl --socket=/var/run/mysqld/mysqld.sock -uroot -p${DBROOT} mysql
  elif command -v mysql_tzinfo_to_sql >/dev/null 2>&1; then
    mysql_tzinfo_to_sql /usr/share/zoneinfo 2>/dev/null \
      | mariadb --skip-ssl --socket=/var/run/mysqld/mysqld.sock -uroot -p${DBROOT} mysql
  else
    echo "No tzinfo-to-sql tool available — skipping timezone import."
  fi
fi

if [[ "${MASTER}" =~ ^([yY][eE][sS]|[yY])+$ ]]; then
  echo "We are master, preparing..."
  # Set a default release format
  if [[ -z $(${REDIS_CMDLINE} --raw GET Q_RELEASE_FORMAT) ]]; then
    ${REDIS_CMDLINE} --raw SET Q_RELEASE_FORMAT raw
  fi

  # Set max age of q items - if unset
  if [[ -z $(${REDIS_CMDLINE} --raw GET Q_MAX_AGE) ]]; then
    ${REDIS_CMDLINE} --raw SET Q_MAX_AGE 365
  fi

  # Set default password policy - if unset
  if [[ -z $(${REDIS_CMDLINE} --raw HGET PASSWD_POLICY length) ]]; then
    ${REDIS_CMDLINE} --raw HSET PASSWD_POLICY length 6
    ${REDIS_CMDLINE} --raw HSET PASSWD_POLICY chars 0
    ${REDIS_CMDLINE} --raw HSET PASSWD_POLICY special_chars 0
    ${REDIS_CMDLINE} --raw HSET PASSWD_POLICY lowerupper 0
    ${REDIS_CMDLINE} --raw HSET PASSWD_POLICY numbers 0
  fi

  # Trigger db init
  echo "Running DB init..."
  php -c /usr/local/etc/php -f /web/inc/init_db.inc.php

  # Recreating domain map
  echo "Rebuilding domain map in Redis..."
  declare -a DOMAIN_ARR
    ${REDIS_CMDLINE} DEL DOMAIN_MAP > /dev/null
  while read line
  do
    DOMAIN_ARR+=("$line")
  done < <(mariadb --skip-ssl --socket=/var/run/mysqld/mysqld.sock -u ${DBUSER} -p${DBPASS} ${DBNAME} -e "SELECT domain FROM domain" -Bs)
  while read line
  do
    DOMAIN_ARR+=("$line")
  done < <(mariadb --skip-ssl --socket=/var/run/mysqld/mysqld.sock -u ${DBUSER} -p${DBPASS} ${DBNAME} -e "SELECT alias_domain FROM alias_domain" -Bs)

  if [[ ! -z ${DOMAIN_ARR} ]]; then
  for domain in "${DOMAIN_ARR[@]}"; do
    ${REDIS_CMDLINE} HSET DOMAIN_MAP ${domain} 1 > /dev/null
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
        mariadb --skip-ssl --socket=/var/run/mysqld/mysqld.sock -u ${DBUSER} -p${DBPASS} ${DBNAME} << EOF
DELETE FROM api WHERE access = 'rw';
INSERT INTO api (api_key, active, allow_from, access) VALUES ("${API_KEY}", "1", "${VALIDATED_IPS}", "rw");
EOF
      fi
      if [[ ${API_KEY_READ_ONLY} != "invalid" ]] && [[ ! -z ${API_KEY_READ_ONLY} ]]; then
        mariadb --skip-ssl --socket=/var/run/mysqld/mysqld.sock -u ${DBUSER} -p${DBPASS} ${DBNAME} << EOF
DELETE FROM api WHERE access = 'ro';
INSERT INTO api (api_key, active, allow_from, access) VALUES ("${API_KEY_READ_ONLY}", "1", "${VALIDATED_IPS}", "ro");
EOF
      fi
    fi
  fi

  # Create events (master only, STATUS for event on slave will be SLAVESIDE_DISABLED)
  mariadb --skip-ssl --socket=/var/run/mysqld/mysqld.sock -u ${DBUSER} -p${DBPASS} ${DBNAME} << EOF
DROP EVENT IF EXISTS clean_spamalias;
DELIMITER //
CREATE EVENT clean_spamalias
ON SCHEDULE EVERY 1 DAY DO
BEGIN
  DELETE FROM spamalias WHERE validity < UNIX_TIMESTAMP() AND permanent = 0;
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
