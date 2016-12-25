#!/bin/bash

# Go in a 5 minute loop
while true; do

	# Wait for MySQL to warm-up
	while ! mysqladmin ping --host mysql --silent; do
		sleep 1
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
	DOMAIN_SANE=$(echo ${line} | tr '-' 'b' | tr '.' 'p' | tr -cd '[[:alnum:]]')
	echo "        <key>${line}</key>
        <dict>
            <key>SOGoMailDomain</key>
            <string>$(echo ${line} | tr '-' 'b' | tr '.' 'p')</string>
            <key>SOGoUserSources</key>
            <array>
                <dict>
                    <key>MailFieldNames</key>
                    <array>
                        <string>aliases</string>
                        <string>ad_aliases</string>
                        <string>senderacl</string>
                    </array>
                    <key>KindFieldName</key>
                    <string>kind</string>
                    <key>MultipleBookingsFieldName</key>
                    <string>multiple_bookings</string>
                    <key>IMAPLoginFieldName</key>
                    <string>c_uid</string>
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
                    <string>mysql://${DBUSER}:${DBPASS}@mysql:3306/${DBNAME}/sogo_view_${DOMAIN_SANE}</string>
                </dict>
            </array>
        </dict>" >> /var/lib/sogo/GNUstep/Defaults/sogod.plist
	mysql --host mysql -u ${DBUSER} -p${DBPASS} ${DBNAME} -e "DROP VIEW IF EXISTS sogo_view_${DOMAIN_SANE}"
	mysql --host mysql -u ${DBUSER} -p${DBPASS} ${DBNAME} << EOF
CREATE VIEW sogo_view_${DOMAIN_SANE} (c_uid, c_name, c_password, c_cn, mail, aliases, ad_aliases, senderacl, home) AS
SELECT mailbox.username, mailbox.username, mailbox.password, mailbox.name, mailbox.username, IFNULL(ga.aliases, ''), IFNULL(gda.ad_alias, ''), IFNULL(gs.send_as, ''), CONCAT('/var/vmail/', maildir) FROM mailbox
LEFT OUTER JOIN grouped_mail_aliases ga ON ga.username = mailbox.username
LEFT OUTER JOIN grouped_sender_acl gs ON gs.username = mailbox.username
LEFT OUTER JOIN grouped_domain_alias_address gda ON gda.username = mailbox.username
WHERE mailbox.active = '1' AND domain = '${line}';
EOF
done < <(mysql --host mysql -u ${DBUSER} -p${DBPASS} ${DBNAME} -e "SELECT domain FROM domain;" -B -N)

	# Generate footer
	echo '    </dict>
</dict>
</plist>' >> /var/lib/sogo/GNUstep/Defaults/sogod.plist

	# Fix permissions
	chown sogo:sogo -R /var/lib/sogo/
	chmod 600 /var/lib/sogo/GNUstep/Defaults/sogod.plist

	sleep 300

done
