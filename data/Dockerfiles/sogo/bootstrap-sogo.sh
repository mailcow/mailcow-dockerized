#!/bin/bash

# Wait for MySQL to warm-up
while ! mysqladmin ping --host mysql -u${DBUSER} -p${DBPASS} --silent; do
  echo "Waiting for database to come up..."
  sleep 2
done

# Wait until port becomes free and send sig
until ! nc -z sogo-mailcow 20000;
do
        killall -TERM sogod
        sleep 3
done

# Recreate view

mysql --host mysql -u ${DBUSER} -p${DBPASS} ${DBNAME} -e "DROP VIEW IF EXISTS sogo_view"

while [[ ${VIEW_OK} != 'OK' ]]; do
  mysql --host mysql -u ${DBUSER} -p${DBPASS} ${DBNAME} << EOF
CREATE VIEW sogo_view (c_uid, domain, c_name, c_password, c_cn, mail, aliases, ad_aliases, home, kind, multiple_bookings) AS
SELECT mailbox.username, mailbox.domain, mailbox.username, if(json_extract(attributes, '$.force_pw_update') LIKE '%0%', password, 'invalid'), mailbox.name, mailbox.username, IFNULL(GROUP_CONCAT(ga.aliases SEPARATOR ' '), ''), IFNULL(gda.ad_alias, ''), CONCAT('/var/vmail/', maildir), mailbox.kind, mailbox.multiple_bookings FROM mailbox
LEFT OUTER JOIN grouped_mail_aliases ga ON ga.username REGEXP CONCAT('(^|,)', mailbox.username, '($|,)')
LEFT OUTER JOIN grouped_domain_alias_address gda ON gda.username = mailbox.username
WHERE mailbox.active = '1'
GROUP BY mailbox.username;
EOF
  if [[ ! -z $(mysql --host mysql -u ${DBUSER} -p${DBPASS} ${DBNAME} -B -e "SELECT 'OK' FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'sogo_view'") ]]; then
    VIEW_OK=OK
  else
    echo "Will retry to setup SOGo view in 3s"
    sleep 3
  fi
done

mkdir -p /var/lib/sogo/GNUstep/Defaults/

# Generate plist header with timezone data
cat <<EOF > /var/lib/sogo/GNUstep/Defaults/sogod.plist
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//GNUstep//DTD plist 0.9//EN" "http://www.gnustep.org/plist-0_9.xml">
<plist version="0.9">
<dict>
    <key>OCSAclURL</key>
    <string>mysql://${DBUSER}:${DBPASS}@mysql:3306/${DBNAME}/sogo_acl</string>
    <key>OCSCacheFolderURL</key>
    <string>mysql://${DBUSER}:${DBPASS}@mysql:3306/${DBNAME}/sogo_cache_folder</string>
    <key>OCSEMailAlarmsFolderURL</key>
    <string>mysql://${DBUSER}:${DBPASS}@mysql:3306/${DBNAME}/sogo_alarms_folder</string>
    <key>OCSFolderInfoURL</key>
    <string>mysql://${DBUSER}:${DBPASS}@mysql:3306/${DBNAME}/sogo_folder_info</string>
    <key>OCSSessionsFolderURL</key>
    <string>mysql://${DBUSER}:${DBPASS}@mysql:3306/${DBNAME}/sogo_sessions_folder</string>
    <key>OCSStoreURL</key>
    <string>mysql://${DBUSER}:${DBPASS}@mysql:3306/${DBNAME}/sogo_store</string>
    <key>SOGoProfileURL</key>
    <string>mysql://${DBUSER}:${DBPASS}@mysql:3306/${DBNAME}/sogo_user_profile</string>
    <key>SOGoTimeZone</key>
    <string>${TZ}</string>
    <key>domains</key>
    <dict>
EOF

# Generate multi-domain setup
while read line
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
                    <string>GAL</string>
                    <key>id</key>
                    <string>${line}</string>
                    <key>isAddressBook</key>
                    <string>YES</string>
                    <key>type</key>
                    <string>sql</string>
                    <key>userPasswordAlgorithm</key>
                    <string>ssha256</string>
                    <key>viewURL</key>
                    <string>mysql://${DBUSER}:${DBPASS}@mysql:3306/${DBNAME}/sogo_view</string>
                </dict>
            </array>
        </dict>" >> /var/lib/sogo/GNUstep/Defaults/sogod.plist
done < <(mysql --host mysql -u ${DBUSER} -p${DBPASS} ${DBNAME} -e "SELECT domain FROM domain;" -B -N)

# Generate footer
echo '    </dict>
</dict>
</plist>' >> /var/lib/sogo/GNUstep/Defaults/sogod.plist

# Fix permissions
chown sogo:sogo -R /var/lib/sogo/
chmod 600 /var/lib/sogo/GNUstep/Defaults/sogod.plist

exec gosu sogo /usr/sbin/sogod
