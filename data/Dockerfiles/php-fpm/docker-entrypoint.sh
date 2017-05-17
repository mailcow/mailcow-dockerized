#!/bin/bash
set -e

if [[ ! -d "/data/dkim/txt" || ! -d "/data/dkim/keys" ]] ; then mkdir -p /data/dkim/{txt,keys} ; chown -R www-data:www-data /data/dkim; fi
if [[ $(stat -c %U /data/dkim/) != "www-data" ]] ; then chown -R www-data:www-data /data/dkim ; fi

# Wait for containers

while ! mysqladmin ping --host mysql --silent; do
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

if [[ ! -z ${DOMAIN_ARR} ]]; then
for domain in "${DOMAIN_ARR[@]}"; do
  redis-cli -h redis-mailcow HSET DOMAIN_MAP ${domain} 1
done
fi

# Migrate tag settings map

declare -a SUBJ_TAG_ARR
redis-cli -h redis-mailcow DEL SUBJ_TAG_ARR
while read line
do
  SUBJ_TAG_ARR+=("$line")
done < <(mysql -h mysql-mailcow -u ${DBUSER} -p${DBPASS} ${DBNAME} -e "SELECT username FROM mailbox WHERE wants_tagged_subject='1'" -Bs)

if [[ ! -z ${SUBJ_TAG_ARR} ]]; then
for user in "${SUBJ_TAG_ARR[@]}"; do
  redis-cli -h redis-mailcow HSET RCPT_WANTS_SUBJECT_TAG ${user} 1
  mysql -h mysql-mailcow -u ${DBUSER} -p${DBPASS} ${DBNAME} -e "UPDATE mailbox SET wants_tagged_subject='2' WHERE username = '${user}'"
done
fi

# Migrate DKIM keys

for file in $(ls /data/dkim/keys/); do
  domain=${file%.dkim}
  if [[ -f /data/dkim/txt/${file} ]]; then
    redis-cli -h redis-mailcow HSET DKIM_PUB_KEYS "${domain}" "$(cat /data/dkim/txt/${file})"
    redis-cli -h redis-mailcow HSET DKIM_PRIV_KEYS "dkim.${domain}" "$(cat /data/dkim/keys/${file})"
    redis-cli -h redis-mailcow HSET DKIM_SELECTORS "${domain}" "dkim"
  fi
  rm /data/dkim/{keys,txt}/${file}
done

# Fix DKIM keys

# Fetch domains
declare -a DOMAIN_ARRAY
while read line
do
 DOMAIN_ARRAY+=("$line")
done < <(mysql -h mysql-mailcow -u ${DBUSER} -p${DBPASS} ${DBNAME} -e "SELECT domain FROM domain" -Bs)
while read line
do
 DOMAIN_ARRAY+=("$line")
done < <(mysql -h mysql-mailcow -u ${DBUSER} -p${DBPASS} ${DBNAME} -e "SELECT alias_domain FROM alias_domain" -Bs)

# Loop through array and fix keys
if [[ ! -z ${DOMAIN_ARRAY} ]]; then
 for domain in "${DOMAIN_ARRAY[@]}"; do
   WRONG_KEY=$(redis-cli -h redis-mailcow HGET DKIM_PRIV_KEYS ${domain} | tr -d \")
   if [[ ! -z ${WRONG_KEY} ]]; then
     echo "Migrating defect key for domain ${domain}"
     redis-cli -h redis-mailcow HSET DKIM_PRIV_KEYS "dkim.${domain}" ${WRONG_KEY}
     redis-cli -h redis-mailcow HDEL DKIM_PRIV_KEYS "${domain}"
   fi
 done
fi

exec "$@"
