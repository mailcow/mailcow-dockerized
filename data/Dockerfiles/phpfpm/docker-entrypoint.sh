#!/bin/bash
set -e

if [[ ! -d "/data/dkim/txt" || ! -d "/data/dkim/keys" ]] ; then mkdir -p /data/dkim/{txt,keys} ; chown -R www-data:www-data /data/dkim; fi
if [[ $(stat -c %U /data/dkim/) != "www-data" ]] ; then chown -R www-data:www-data /data/dkim ; fi

# Wait for containers

while ! mysqladmin ping --host mysql -u${DBUSER} -p${DBPASS} --silent; do
  sleep 2
done

until [ $(redis-cli -h redis-mailcow PING) == "PONG" ]; do
  sleep 2
done

# Migrate domain map
declare -a DOMAIN_ARR
redis-cli -h redis-mailcow DEL DOMAIN_MAP
while read line
do
  DOMAIN_ARR+=("$line")
done < <(mysql -h mysql-mailcow -u ${DBUSER} -p${DBPASS} ${DBNAME} -e "SELECT domain FROM domain" -Bs)
while read line
do
  DOMAIN_ARR+=("$line")
done < <(mysql -h mysql-mailcow -u ${DBUSER} -p${DBPASS} ${DBNAME} -e "SELECT alias_domain FROM alias_domain" -Bs)


if [[ ! -z ${DOMAIN_ARR} ]]; then
for domain in "${DOMAIN_ARR[@]}"; do
  redis-cli -h redis-mailcow HSET DOMAIN_MAP ${domain} 1
done
fi

exec "$@"
