#!/bin/bash

# Wait for MySQL to warm-up
while ! mysqladmin status --socket=/var/run/mysqld/mysqld.sock -u${DBUSER} -p${DBPASS} --silent; do
  echo "Waiting for database to come up..."
  sleep 2
done

# Wait until port becomes free and send sig
until ! nc -z sogo-mailcow 20000;
do
  killall -TERM sogod
  sleep 3
done

# Wait for updated schema
DBV_NOW=$(mysql --socket=/var/run/mysqld/mysqld.sock -u ${DBUSER} -p${DBPASS} ${DBNAME} -e "SELECT version FROM versions WHERE application = 'db_schema';" -BN)
DBV_NEW=$(grep -oE '\$db_version = .*;' init_db.inc.php | sed 's/$db_version = //g;s/;//g' | cut -d \" -f2)
while [[ "${DBV_NOW}" != "${DBV_NEW}" ]]; do
  echo "Waiting for schema update..."
  DBV_NOW=$(mysql --socket=/var/run/mysqld/mysqld.sock -u ${DBUSER} -p${DBPASS} ${DBNAME} -e "SELECT version FROM versions WHERE application = 'db_schema';" -BN)
  DBV_NEW=$(grep -oE '\$db_version = .*;' init_db.inc.php | sed 's/$db_version = //g;s/;//g' | cut -d \" -f2)
  sleep 5
done
echo "DB schema is ${DBV_NOW}"

# cat /dev/urandom seems to hang here occasionally and is not recommended anyway, better use openssl
RAND_PASS=$(openssl rand -base64 16 | tr -dc _A-Z-a-z-0-9)

# Generate plist header with timezone data
mkdir -p /var/lib/sogo/GNUstep/Defaults/
cat <<EOF > /var/lib/sogo/GNUstep/Defaults/sogod.plist
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//GNUstep//DTD plist 0.9//EN" "http://www.gnustep.org/plist-0_9.xml">
<plist version="0.9">
<dict>
    <key>OCSAclURL</key>
    <string>mysql://${DBUSER}:${DBPASS}@%2Fvar%2Frun%2Fmysqld%2Fmysqld.sock/${DBNAME}/sogo_acl</string>
    <key>SOGoIMAPServer</key>
    <string>imap://${IPV4_NETWORK}.250:143/?TLS=YES&amp;tlsVerifyMode=none</string>
    <key>SOGoSieveServer</key>
    <string>sieve://${IPV4_NETWORK}.250:4190/?TLS=YES&amp;tlsVerifyMode=none</string>
    <key>SOGoSMTPServer</key>
    <string>smtp://${IPV4_NETWORK}.253:588/?TLS=YES&amp;tlsVerifyMode=none</string>
    <key>SOGoTrustProxyAuthentication</key>
    <string>YES</string>
    <key>SOGoEncryptionKey</key>
    <string>${RAND_PASS}</string>
    <key>OCSAdminURL</key>
    <string>mysql://${DBUSER}:${DBPASS}@%2Fvar%2Frun%2Fmysqld%2Fmysqld.sock/${DBNAME}/sogo_admin</string>
    <key>OCSCacheFolderURL</key>
    <string>mysql://${DBUSER}:${DBPASS}@%2Fvar%2Frun%2Fmysqld%2Fmysqld.sock/${DBNAME}/sogo_cache_folder</string>
    <key>OCSEMailAlarmsFolderURL</key>
    <string>mysql://${DBUSER}:${DBPASS}@%2Fvar%2Frun%2Fmysqld%2Fmysqld.sock/${DBNAME}/sogo_alarms_folder</string>
    <key>OCSFolderInfoURL</key>
    <string>mysql://${DBUSER}:${DBPASS}@%2Fvar%2Frun%2Fmysqld%2Fmysqld.sock/${DBNAME}/sogo_folder_info</string>
    <key>OCSSessionsFolderURL</key>
    <string>mysql://${DBUSER}:${DBPASS}@%2Fvar%2Frun%2Fmysqld%2Fmysqld.sock/${DBNAME}/sogo_sessions_folder</string>
    <key>OCSStoreURL</key>
    <string>mysql://${DBUSER}:${DBPASS}@%2Fvar%2Frun%2Fmysqld%2Fmysqld.sock/${DBNAME}/sogo_store</string>
    <key>SOGoProfileURL</key>
    <string>mysql://${DBUSER}:${DBPASS}@%2Fvar%2Frun%2Fmysqld%2Fmysqld.sock/${DBNAME}/sogo_user_profile</string>
    <key>SOGoTimeZone</key>
    <string>${TZ}</string>
    <key>domains</key>
    <dict>
EOF

# Generate multi-domain setup
while read -r line gal
  do
  echo "        <key>${line}</key>
        <dict>
            <key>SOGoMailDomain</key>
            <string>${line}</string>
            <key>SOGoUserSources</key>
            <array>
                <dict>
                    <key>MailFieldNames</key>
                    <array>
                        <string>aliases</string>
                        <string>ad_aliases</string>
                        <string>ext_acl</string>
                    </array>
                    <key>KindFieldName</key>
                    <string>kind</string>
                    <key>DomainFieldName</key>
                    <string>domain</string>
                    <key>MultipleBookingsFieldName</key>
                    <string>multiple_bookings</string>
                    <key>listRequiresDot</key>
                    <string>NO</string>
                    <key>canAuthenticate</key>
                    <string>YES</string>
                    <key>displayName</key>
                    <string>GAL ${line}</string>
                    <key>id</key>
                    <string>${line}</string>
                    <key>isAddressBook</key>
                    <string>${gal}</string>
                    <key>type</key>
                    <string>sql</string>
                    <key>userPasswordAlgorithm</key>
                    <string>${MAILCOW_PASS_SCHEME}</string>
                    <key>prependPasswordScheme</key>
                    <string>YES</string>
                    <key>viewURL</key>
                    <string>mysql://${DBUSER}:${DBPASS}@%2Fvar%2Frun%2Fmysqld%2Fmysqld.sock/${DBNAME}/_sogo_static_view</string>
                </dict>" >> /var/lib/sogo/GNUstep/Defaults/sogod.plist
  # Generate alternative LDAP authentication dict, when SQL authentication fails
  # This will nevertheless read attributes from LDAP
  /etc/sogo/plist_ldap.sh ${line} ${gal} >> /var/lib/sogo/GNUstep/Defaults/sogod.plist
  echo "            </array>
        </dict>" >> /var/lib/sogo/GNUstep/Defaults/sogod.plist
done < <(mysql --socket=/var/run/mysqld/mysqld.sock -u ${DBUSER} -p${DBPASS} ${DBNAME} -e "SELECT domain, CASE gal WHEN '1' THEN 'YES' ELSE 'NO' END AS gal FROM domain;" -B -N)

# Generate footer
echo '    </dict>
</dict>
</plist>' >> /var/lib/sogo/GNUstep/Defaults/sogod.plist

# Fix permissions
chown sogo:sogo -R /var/lib/sogo/
chmod 600 /var/lib/sogo/GNUstep/Defaults/sogod.plist

# Patch ACLs
#if [[ ${ACL_ANYONE} == 'allow' ]]; then
#  #enable any or authenticated targets for ACL
#  if patch -R -sfN --dry-run /usr/lib/GNUstep/SOGo/Templates/UIxAclEditor.wox < /acl.diff > /dev/null; then
#    patch -R /usr/lib/GNUstep/SOGo/Templates/UIxAclEditor.wox < /acl.diff;
#  fi
#else
#  #disable any or authenticated targets for ACL
#  if patch -sfN --dry-run /usr/lib/GNUstep/SOGo/Templates/UIxAclEditor.wox < /acl.diff > /dev/null; then
#    patch /usr/lib/GNUstep/SOGo/Templates/UIxAclEditor.wox < /acl.diff;
#  fi
#fi

# Copy logo, if any
[[ -f /etc/sogo/sogo-full.svg ]] && cp /etc/sogo/sogo-full.svg /usr/lib/GNUstep/SOGo/WebServerResources/img/sogo-full.svg

# Rsync web content
echo "Syncing web content with named volume"
rsync -a /usr/lib/GNUstep/SOGo/. /sogo_web/

# Chown backup path
chown -R sogo:sogo /sogo_backup

exec gosu sogo /usr/sbin/sogod
