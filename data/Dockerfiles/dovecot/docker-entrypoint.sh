#!/bin/bash
set -e

# Hard-code env vars to imapsync due to cron not passing them to the perl script
sed -i "/^\$DBUSER/c\\\$DBUSER='${DBUSER}';" /usr/local/bin/imapsync_cron.pl
sed -i "/^\$DBPASS/c\\\$DBPASS='${DBPASS}';" /usr/local/bin/imapsync_cron.pl
sed -i "/^\$DBNAME/c\\\$DBNAME='${DBNAME}';" /usr/local/bin/imapsync_cron.pl

# Set Dovecot sql config parameters, escape " in db password
DBPASS=$(echo ${DBPASS} | sed 's/"/\\"/g')

cat <<EOF > /etc/dovecot/sql/dovecot-dict-sql.conf
connect = "host=mysql dbname=${DBNAME} user=${DBNAME} password=${DBPASS}"
map {
  pattern = priv/quota/storage
  table = quota2
  username_field = username
  value_field = bytes
}
map {
  pattern = priv/quota/messages
  table = quota2
  username_field = username
  value_field = messages
}
EOF

cat <<EOF > /etc/dovecot/sql/dovecot-mysql.conf
driver = mysql
connect = "host=mysql dbname=${DBNAME} user=${DBNAME} password=${DBPASS}"
default_pass_scheme = SSHA256
password_query = SELECT password FROM mailbox WHERE username = '%u' AND domain IN (SELECT domain FROM domain WHERE domain='%d' AND active='1')
user_query = SELECT CONCAT('maildir:/var/vmail/',maildir) AS mail, 5000 AS uid, 5000 AS gid, concat('*:bytes=', quota) AS quota_rule FROM mailbox WHERE username = '%u' AND active = '1'
iterate_query = SELECT username FROM mailbox WHERE active='1';
EOF

[[ ! -d /var/vmail/sieve ]] && mkdir -p /var/vmail/sieve
[[ ! -d /etc/sogo ]] && mkdir -p /etc/sogo
cat /etc/dovecot/sieve_after > /var/vmail/sieve/global.sieve
sievec /var/vmail/sieve/global.sieve
chown -R vmail:vmail /var/vmail/sieve

# Do not do this every start-up, it may take a very long time. So we use a stat check here.
if [[ $(stat -c %U /var/vmail/) != "vmail" ]] ; then chown -R vmail:vmail /var/vmail ; fi

# Create random master for SOGo sieve features
RAND_USER=$(cat /dev/urandom | tr -dc 'a-z0-9' | fold -w 16 | head -n 1)
RAND_PASS=$(cat /dev/urandom | tr -dc 'a-z0-9' | fold -w 24 | head -n 1)
echo ${RAND_USER}:$(doveadm pw -s SHA1 -p ${RAND_PASS}) > /etc/dovecot/dovecot-master.passwd
echo ${RAND_USER}:${RAND_PASS} > /etc/sogo/sieve.creds

exec "$@"
