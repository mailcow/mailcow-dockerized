#!/bin/bash
set -e

# Wait for MySQL to warm-up
while ! mysqladmin status --socket=/var/run/mysqld/mysqld.sock -u${DBUSER} -p${DBPASS} --silent; do
  echo "Waiting for database to come up..."
  sleep 2
done

# Hard-code env vars to scripts due to cron not passing them to the scripts
sed -i "s/__DBUSER__/${DBUSER}/g" /usr/local/bin/imapsync_cron.pl
sed -i "s/__DBPASS__/${DBPASS}/g" /usr/local/bin/imapsync_cron.pl
sed -i "s/__DBNAME__/${DBNAME}/g" /usr/local/bin/imapsync_cron.pl

sed -i "s/__DBUSER__/${DBUSER}/g" /usr/local/bin/quarantine_notify.py
sed -i "s/__DBPASS__/${DBPASS}/g" /usr/local/bin/quarantine_notify.py
sed -i "s/__DBNAME__/${DBNAME}/g" /usr/local/bin/quarantine_notify.py

sed -i "s/__LOG_LINES__/${LOG_LINES}/g" /usr/local/bin/trim_logs.sh

# Create missing directories
[[ ! -d /usr/local/etc/dovecot/sql/ ]] && mkdir -p /usr/local/etc/dovecot/sql/
[[ ! -d /var/vmail/_garbage ]] && mkdir -p /var/vmail/_garbage
[[ ! -d /var/vmail/sieve ]] && mkdir -p /var/vmail/sieve
[[ ! -d /etc/sogo ]] && mkdir -p /etc/sogo
[[ ! -d /var/volatile ]] && mkdir -p /var/volatile

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

echo -n ${ACL_ANYONE} > /usr/local/etc/dovecot/acl_anyone

if [[ "${SKIP_SOLR}" =~ ^([yY][eE][sS]|[yY])+$ ]]; then
echo -n 'quota acl zlib listescape mail_crypt mail_crypt_acl mail_log notify' > /usr/local/etc/dovecot/mail_plugins
echo -n 'quota imap_quota imap_acl acl zlib imap_zlib imap_sieve listescape mail_crypt mail_crypt_acl notify mail_log' > /usr/local/etc/dovecot/mail_plugins_imap
echo -n 'quota sieve acl zlib listescape mail_crypt mail_crypt_acl' > /usr/local/etc/dovecot/mail_plugins_lmtp
else
echo -n 'quota acl zlib listescape mail_crypt mail_crypt_acl mail_log notify fts fts_solr' > /usr/local/etc/dovecot/mail_plugins
echo -n 'quota imap_quota imap_acl acl zlib imap_zlib imap_sieve listescape mail_crypt mail_crypt_acl notify mail_log fts fts_solr' > /usr/local/etc/dovecot/mail_plugins_imap
echo -n 'quota sieve acl zlib listescape mail_crypt mail_crypt_acl fts fts_solr' > /usr/local/etc/dovecot/mail_plugins_lmtp
fi
chmod 644 /usr/local/etc/dovecot/mail_plugins /usr/local/etc/dovecot/mail_plugins_imap /usr/local/etc/dovecot/mail_plugins_lmtp /templates/quarantine.tpl

cat <<EOF > /usr/local/etc/dovecot/sql/dovecot-dict-sql-userdb.conf
driver = mysql
connect = "host=/var/run/mysqld/mysqld.sock dbname=${DBNAME} user=${DBUSER} password=${DBPASS}"
user_query = SELECT CONCAT(JSON_UNQUOTE(JSON_EXTRACT(attributes, '$.mailbox_format')), mailbox_path_prefix, '%d/%n/:VOLATILEDIR=/var/volatile/%u') AS mail, 5000 AS uid, 5000 AS gid, concat('*:bytes=', quota) AS quota_rule FROM mailbox WHERE username = '%u' AND active = '1'
iterate_query = SELECT username FROM mailbox WHERE active='1';
EOF

# Create pass dict for Dovecot
cat <<EOF > /usr/local/etc/dovecot/sql/dovecot-dict-sql-passdb.conf
driver = mysql
connect = "host=/var/run/mysqld/mysqld.sock dbname=${DBNAME} user=${DBUSER} password=${DBPASS}"
default_pass_scheme = SSHA256
password_query = SELECT password FROM mailbox WHERE active = '1' AND username = '%u' AND domain IN (SELECT domain FROM domain WHERE domain='%d' AND active='1') AND JSON_EXTRACT(attributes, '$.force_pw_update') NOT LIKE '%%1%%'
EOF

# Create global sieve_after script
cat /usr/local/etc/dovecot/sieve_after > /var/vmail/sieve/global.sieve

# Check permissions of vmail/attachments directory.
# Do not do this every start-up, it may take a very long time. So we use a stat check here.
if [[ $(stat -c %U /var/vmail/) != "vmail" ]] ; then chown -R vmail:vmail /var/vmail ; fi
if [[ $(stat -c %U /var/vmail/_garbage) != "vmail" ]] ; then chown -R vmail:vmail /var/vmail/_garbage ; fi
if [[ $(stat -c %U /var/attachments) != "vmail" ]] ; then chown -R vmail:vmail /var/attachments ; fi

# Create random master for SOGo sieve features
RAND_USER=$(cat /dev/urandom | tr -dc 'a-z0-9' | fold -w 16 | head -n 1)
RAND_PASS=$(cat /dev/urandom | tr -dc 'a-z0-9' | fold -w 24 | head -n 1)

echo ${RAND_USER}@mailcow.local:{SHA1}$(echo -n ${RAND_PASS} | sha1sum | awk '{print $1}') > /usr/local/etc/dovecot/dovecot-master.passwd
echo ${RAND_USER}@mailcow.local::5000:5000:::: > /usr/local/etc/dovecot/dovecot-master.userdb
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
chown root:root /usr/local/etc/dovecot/sql/*.conf
chown root:dovecot /usr/local/etc/dovecot/sql/dovecot-dict-sql-sieve* /usr/local/etc/dovecot/sql/dovecot-dict-sql-quota*
chmod 640 /usr/local/etc/dovecot/sql/*.conf
chown -R vmail:vmail /var/vmail/sieve
chown -R vmail:vmail /var/volatile
adduser vmail tty
chmod g+rw /dev/console
chmod +x /usr/local/lib/dovecot/sieve/rspamd-pipe-ham \
  /usr/local/lib/dovecot/sieve/rspamd-pipe-spam \
  /usr/local/bin/imapsync_cron.pl \
  /usr/local/bin/postlogin.sh \
  /usr/local/bin/imapsync \
  /usr/local/bin/trim_logs.sh \
  /usr/local/bin/sa-rules.sh \
  /usr/local/bin/maildir_gc.sh \
  /usr/local/sbin/stop-supervisor.sh \
  /usr/local/bin/quota_notify.py

# Setup cronjobs
echo '* * * * *    root  /usr/local/bin/imapsync_cron.pl 2>&1 | /usr/bin/logger' > /etc/cron.d/imapsync
echo '30 3 * * *   vmail /usr/local/bin/doveadm quota recalc -A' > /etc/cron.d/dovecot-sync
echo '* * * * *    vmail /usr/local/bin/trim_logs.sh >> /dev/console 2>&1' > /etc/cron.d/trim_logs
echo '25 * * * *   vmail /usr/local/bin/maildir_gc.sh >> /dev/console 2>&1' > /etc/cron.d/maildir_gc
echo '30 1 * * *   root  /usr/local/bin/sa-rules.sh  >> /dev/console 2>&1' > /etc/cron.d/sa-rules
echo '0 2 * * *    root  /usr/bin/curl http://solr:8983/solr/dovecot/update?optimize=true >> /dev/console 2>&1' > /etc/cron.d/solr-optimize
echo '*/20 * * * * vmail /usr/local/bin/quarantine_notify.py >> /dev/console 2>&1' > /etc/cron.d/quarantine_notify

# Fix more than 1 hardlink issue
touch /etc/crontab /etc/cron.*/*

# Clean old PID if any
[[ -f /usr/local/var/run/dovecot/master.pid ]] && rm /usr/local/var/run/dovecot/master.pid

# Clean stopped imapsync jobs
rm -f /tmp/imapsync_busy.lock
IMAPSYNC_TABLE=$(mysql --socket=/var/run/mysqld/mysqld.sock -u ${DBUSER} -p${DBPASS} ${DBNAME} -e "SHOW TABLES LIKE 'imapsync'" -Bs)
[[ ! -z ${IMAPSYNC_TABLE} ]] && mysql --socket=/var/run/mysqld/mysqld.sock -u ${DBUSER} -p${DBPASS} ${DBNAME} -e "UPDATE imapsync SET is_running='0'"

# Envsubst maildir_gc
echo "$(envsubst < /usr/local/bin/maildir_gc.sh)" > /usr/local/bin/maildir_gc.sh

# Collect SA rules once now
/usr/local/bin/sa-rules.sh

exec "$@"
