#!/bin/bash
set -e

# Wait for MySQL to warm-up
while ! mysqladmin ping --socket=/var/run/mysqld/mysqld.sock -u${DBUSER} -p${DBPASS} --silent; do
  echo "Waiting for database to come up..."
  sleep 2
done

# Hard-code env vars to scripts due to cron not passing them to the perl script
sed -i "/^\$DBUSER/c\\\$DBUSER='${DBUSER}';" /usr/local/bin/imapsync_cron.pl
sed -i "/^\$DBPASS/c\\\$DBPASS='${DBPASS}';" /usr/local/bin/imapsync_cron.pl
sed -i "/^\$DBNAME/c\\\$DBNAME='${DBNAME}';" /usr/local/bin/imapsync_cron.pl
sed -i "s/LOG_LINES/${LOG_LINES}/g" /usr/local/bin/trim_logs.sh

# Create missing directories
[[ ! -d /usr/local/etc/dovecot/sql/ ]] && mkdir -p /usr/local/etc/dovecot/sql/
[[ ! -d /var/vmail/_garbage ]] && mkdir -p /var/vmail/_garbage
[[ ! -d /var/vmail/sieve ]] && mkdir -p /var/vmail/sieve
[[ ! -d /etc/sogo ]] && mkdir -p /etc/sogo

# Set Dovecot sql config parameters, escape " in db password
DBPASS=$(echo ${DBPASS} | sed 's/"/\\"/g')

# Create quota dict for Dovecot
cat <<EOF > /usr/local/etc/dovecot/sql/dovecot-dict-sql-quota.conf
connect = "host=/var/run/mysqld/mysqld.sock dbname=${DBNAME} user=${DBUSER} password=${DBPASS}"
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

# Create dict used for sieve pre and postfilters
cat <<EOF > /usr/local/etc/dovecot/sql/dovecot-dict-sql-sieve_before.conf
connect = "host=/var/run/mysqld/mysqld.sock dbname=${DBNAME} user=${DBUSER} password=${DBPASS}"
map {
  pattern = priv/sieve/name/\$script_name
  table = sieve_before
  username_field = username
  value_field = id
  fields {
    script_name = \$script_name
  }
}
map {
  pattern = priv/sieve/data/\$id
  table = sieve_before
  username_field = username
  value_field = script_data
  fields {
    id = \$id
  }
}
EOF

cat <<EOF > /usr/local/etc/dovecot/sql/dovecot-dict-sql-sieve_after.conf
connect = "host=/var/run/mysqld/mysqld.sock dbname=${DBNAME} user=${DBUSER} password=${DBPASS}"
map {
  pattern = priv/sieve/name/\$script_name
  table = sieve_after
  username_field = username
  value_field = id
  fields {
    script_name = \$script_name
  }
}
map {
  pattern = priv/sieve/data/\$id
  table = sieve_after
  username_field = username
  value_field = script_data
  fields {
    id = \$id
  }
}
EOF


# Create userdb dict for Dovecot
cat <<EOF > /usr/local/etc/dovecot/sql/dovecot-dict-sql-userdb.conf
driver = mysql
connect = "host=/var/run/mysqld/mysqld.sock dbname=${DBNAME} user=${DBUSER} password=${DBPASS}"
user_query = SELECT CONCAT('maildir:/var/vmail/',maildir) AS mail, 5000 AS uid, 5000 AS gid, concat('*:bytes=', quota) AS quota_rule FROM mailbox WHERE username = '%u' AND active = '1'
iterate_query = SELECT username FROM mailbox WHERE active='1';
EOF

# Create pass dict for Dovecot
cat <<EOF > /usr/local/etc/dovecot/sql/dovecot-dict-sql-passdb.conf
driver = mysql
connect = "host=/var/run/mysqld/mysqld.sock dbname=${DBNAME} user=${DBUSER} password=${DBPASS} ssl_verify_server_cert=no ssl_ca=/etc/ssl/certs/ca-certificates.crt"
default_pass_scheme = SSHA256
password_query = SELECT password FROM mailbox WHERE username = '%u' AND domain IN (SELECT domain FROM domain WHERE domain='%d' AND active='1') AND JSON_EXTRACT(attributes, '$.force_pw_update') NOT LIKE '%%1%%'
EOF

# Create global sieve_after script
cat /usr/local/etc/dovecot/sieve_after > /var/vmail/sieve/global.sieve

# Check permissions of vmail directory.
# Do not do this every start-up, it may take a very long time. So we use a stat check here.
if [[ $(stat -c %U /var/vmail/) != "vmail" ]] ; then chown -R vmail:vmail /var/vmail ; fi
if [[ $(stat -c %U /var/vmail/_garbage) != "vmail" ]] ; then chown -R vmail:vmail /var/vmail/_garbage ; fi

# Create random master for SOGo sieve features
RAND_USER=$(cat /dev/urandom | tr -dc 'a-z0-9' | fold -w 16 | head -n 1)
RAND_PASS=$(cat /dev/urandom | tr -dc 'a-z0-9' | fold -w 24 | head -n 1)

echo ${RAND_USER}@mailcow.local:$(doveadm pw -s SHA1 -p ${RAND_PASS}) > /usr/local/etc/dovecot/dovecot-master.passwd
echo ${RAND_USER}@mailcow.local:${RAND_PASS} > /etc/sogo/sieve.creds

# 401 is user dovecot
if [[ ! -s /mail_crypt/ecprivkey.pem || ! -s /mail_crypt/ecpubkey.pem ]]; then
	openssl ecparam -name prime256v1 -genkey | openssl pkey -out /mail_crypt/ecprivkey.pem
	openssl pkey -in /mail_crypt/ecprivkey.pem -pubout -out /mail_crypt/ecpubkey.pem
	chown 401 /mail_crypt/ecprivkey.pem /mail_crypt/ecpubkey.pem
else
	chown 401 /mail_crypt/ecprivkey.pem /mail_crypt/ecpubkey.pem
fi

# Compile sieve scripts
sievec /var/vmail/sieve/global.sieve
sievec /usr/local/lib/dovecot/sieve/report-spam.sieve
sievec /usr/local/lib/dovecot/sieve/report-ham.sieve

# Fix permissions
chown -R vmail:vmail /var/vmail/sieve

# Fix more than 1 hardlink issue
touch /etc/crontab /etc/cron.*/*

# Clean old PID if any
[[ -f /usr/local/var/run/dovecot/master.pid ]] && rm /usr/local/var/run/dovecot/master.pid

# Clean stopped imapsync jobs
rm -f /tmp/imapsync_busy.lock
IMAPSYNC_TABLE=$(mysql --socket=/var/run/mysqld/mysqld.sock -u ${DBUSER} -p${DBPASS} ${DBNAME} -e "SHOW TABLES LIKE 'imapsync'" -Bs)
[[ ! -z ${IMAPSYNC_TABLE} ]] && mysql --socket=/var/run/mysqld/mysqld.sock -u ${DBUSER} -p${DBPASS} ${DBNAME} -e "UPDATE imapsync SET is_running='0'"

# Collect SA rules once now
/usr/local/bin/sa-rules.sh

exec "$@"
