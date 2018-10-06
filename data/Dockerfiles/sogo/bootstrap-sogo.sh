#!/bin/bash

# Wait for MySQL to warm-up
while ! mysqladmin ping --socket=/var/run/mysqld/mysqld.sock -u${DBUSER} -p${DBPASS} --silent; do
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

mysql --socket=/var/run/mysqld/mysqld.sock -u ${DBUSER} -p${DBPASS} ${DBNAME} -e "DROP VIEW IF EXISTS sogo_view"

while [[ ${VIEW_OK} != 'OK' ]]; do
  mysql --socket=/var/run/mysqld/mysqld.sock -u ${DBUSER} -p${DBPASS} ${DBNAME} << EOF
CREATE VIEW sogo_view (c_uid, domain, c_name, c_password, c_cn, mail, aliases, ad_aliases, home, kind, multiple_bookings) AS
SELECT mailbox.username, mailbox.domain, mailbox.username, if(json_extract(attributes, '$.force_pw_update') LIKE '%0%', password, 'invalid'), mailbox.name, mailbox.username, IFNULL(GROUP_CONCAT(ga.aliases SEPARATOR ' '), ''), IFNULL(gda.ad_alias, ''), CONCAT('/var/vmail/', maildir), mailbox.kind, mailbox.multiple_bookings FROM mailbox
LEFT OUTER JOIN grouped_mail_aliases ga ON ga.username REGEXP CONCAT('(^|,)', mailbox.username, '($|,)')
LEFT OUTER JOIN grouped_domain_alias_address gda ON gda.username = mailbox.username
WHERE mailbox.active = '1'
GROUP BY mailbox.username;
EOF
  if [[ ! -z $(mysql --socket=/var/run/mysqld/mysqld.sock -u ${DBUSER} -p${DBPASS} ${DBNAME} -B -e "SELECT 'OK' FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'sogo_view'") ]]; then
    VIEW_OK=OK
  else
    echo "Will retry to setup SOGo view in 3s"
    sleep 3
  fi
done

# Wait for static view table if missing after update and update content

while [[ ${STATIC_VIEW_OK} != 'OK' ]]; do
  if [[ ! -z $(mysql --socket=/var/run/mysqld/mysqld.sock -u ${DBUSER} -p${DBPASS} ${DBNAME} -B -e "SELECT 'OK' FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = '_sogo_static_view'") ]]; then
    STATIC_VIEW_OK=OK
    echo "Updating _sogo_static_view content..."
    mysql --socket=/var/run/mysqld/mysqld.sock -u ${DBUSER} -p${DBPASS} ${DBNAME} -B -e "REPLACE INTO _sogo_static_view SELECT * from sogo_view"
    mysql --socket=/var/run/mysqld/mysqld.sock -u ${DBUSER} -p${DBPASS} ${DBNAME} -B -e "DELETE FROM _sogo_static_view WHERE c_uid NOT IN (SELECT username FROM mailbox WHERE active = '1')"
  else
    echo "Waiting for database initialization..."
    sleep 3
  fi
done

# Recreate password update trigger

mysql --socket=/var/run/mysqld/mysqld.sock -u ${DBUSER} -p${DBPASS} ${DBNAME} -e "DROP TRIGGER IF EXISTS sogo_update_password"

while [[ ${TRIGGER_OK} != 'OK' ]]; do
  mysql --socket=/var/run/mysqld/mysqld.sock -u ${DBUSER} -p${DBPASS} ${DBNAME} << EOF
DELIMITER -
CREATE TRIGGER sogo_update_password AFTER UPDATE ON _sogo_static_view
FOR EACH ROW
BEGIN
UPDATE mailbox SET password = NEW.c_password WHERE NEW.c_uid = username;
END;
-
DELIMITER ;
EOF
  if [[ ! -z $(mysql --socket=/var/run/mysqld/mysqld.sock -u ${DBUSER} -p${DBPASS} ${DBNAME} -B -e "SELECT 'OK' FROM INFORMATION_SCHEMA.TRIGGERS WHERE TRIGGER_NAME = 'sogo_update_password'") ]]; then
    TRIGGER_OK=OK
  else
    echo "Will retry to setup SOGo password update trigger in 3s"
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
    <string>mysql://${DBUSER}:${DBPASS}@%2Fvar%2Frun%2Fmysqld%2Fmysqld.sock/${DBNAME}/sogo_acl</string>
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
                    <key>prependPasswordScheme</key>
                    <string>YES</string>
                    <key>viewURL</key>
                    <string>mysql://${DBUSER}:${DBPASS}@%2Fvar%2Frun%2Fmysqld%2Fmysqld.sock/${DBNAME}/_sogo_static_view</string>
                </dict>
            </array>
        </dict>" >> /var/lib/sogo/GNUstep/Defaults/sogod.plist
done < <(mysql --socket=/var/run/mysqld/mysqld.sock -u ${DBUSER} -p${DBPASS} ${DBNAME} -e "SELECT domain FROM domain;" -B -N)

# Generate footer
echo '    </dict>
</dict>
</plist>' >> /var/lib/sogo/GNUstep/Defaults/sogod.plist

# Fix permissions
chown sogo:sogo -R /var/lib/sogo/
chmod 600 /var/lib/sogo/GNUstep/Defaults/sogod.plist

# Prevent theme switching
sed -i \
  -e 's/eaf5e9/E3F2FD/g' \
  -e 's/cbe5c8/BBDEFB/g' \
  -e 's/aad6a5/90CAF9/g' \
  -e 's/88c781/64B5F6/g' \
  -e 's/66b86a/42A5F5/g' \
  -e 's/56b04c/2196F3/g' \
  -e 's/4da143/1E88E5/g' \
  -e 's/388e3c/1976D2/g' \
  -e 's/367d2e/1565C0/g' \
  -e 's/225e1b/0D47A1/g' \
  -e 's/fafafa/82B1FF/g' \
  -e 's/69f0ae/448AFF/g' \
  -e 's/00e676/2979ff/g' \
  -e 's/00c853/2962ff/g'  \
  /usr/lib/GNUstep/SOGo/WebServerResources/js/Common/Common.app.js \
  /usr/lib/GNUstep/SOGo/WebServerResources/js/Common.js

sed -i \
  -e 's/default: "900"/default: "700"/g' \
  -e 's/default: "500"/default: "700"/g' \
  -e 's/"hue-1": "400"/"hue-1": "500"/g' \
  -e 's/"hue-1": "A100"/"hue-1": "500"/g' \
  -e 's/"hue-2": "800"/"hue-2": "700"/g' \
  -e 's/"hue-2": "300"/"hue-2": "700"/g' \
  -e 's/"hue-3": "A700"/"hue-3": "A200"/' \
  -e 's/default:"900"/default:"700"/g' \
  -e 's/default:"500"/default:"700"/g' \
  -e 's/"hue-1":"400"/"hue-1":"500"/g' \
  -e 's/"hue-1":"A100"/"hue-1":"500"/g' \
  -e 's/"hue-2":"800"/"hue-2":"700"/g' \
  -e 's/"hue-2":"300"/"hue-2":"700"/g' \
  -e 's/"hue-3":"A700"/"hue-3":"A200"/' \
  /usr/lib/GNUstep/SOGo/WebServerResources/js/Common/Common.app.js \
  /usr/lib/GNUstep/SOGo/WebServerResources/js/Common.js

# Patch ACLs (comment this out to enable any or authenticated targets for ACL)
if patch -sfN --dry-run /usr/lib/GNUstep/SOGo/Templates/UIxAclEditor.wox < /acl.diff > /dev/null; then
  patch /usr/lib/GNUstep/SOGo/Templates/UIxAclEditor.wox < /acl.diff;
fi

exec gosu sogo /usr/sbin/sogod
