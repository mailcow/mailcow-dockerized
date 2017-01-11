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

# Create random master for SOGo sieve features
RAND_USER=$(cat /dev/urandom | tr -dc 'a-z0-9' | fold -w 16 | head -n 1)
RAND_PASS=$(cat /dev/urandom | tr -dc 'a-z0-9' | fold -w 24 | head -n 1)
echo ${RAND_USER}:$(doveadm pw -s SHA1 -p ${RAND_PASS}) > /etc/dovecot/dovecot-master.passwd
[ -d /etc/sogo ] && echo ${RAND_USER}:${RAND_PASS} > /etc/sogo/sieve.creds

exec "$@"
