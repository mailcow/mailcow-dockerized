#!/bin/bash
set -e

# Set config parameters, escape " in db password
DBPASS=$(echo ${DBPASS} | sed 's/"/\\"/g')
sed -i "/^connect/c\connect = \"host=mysql dbname=${DBNAME} user=${DBUSER} password=${DBPASS}\"" /etc/dovecot/sql/*

[[ ! -d /var/vmail/sieve ]] && mkdir -p /var/vmail/sieve
cat /etc/dovecot/sieve_after > /var/vmail/sieve/global.sieve
sievec /var/vmail/sieve/global.sieve
chown -R vmail:vmail /var/vmail/sieve

# Do not do this every start-up, it may take a very long time. So we use a stat check here.
if [[ $(stat -c %U /var/vmail/) != "vmail" ]] ; then chown -R vmail:vmail /var/vmail ; fi

exec "$@"
