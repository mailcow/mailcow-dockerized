FROM debian:jessie
MAINTAINER Andre Peters <andre.peters@debinux.de>

ENV DEBIAN_FRONTEND noninteractive

RUN apt-get update \
	&& apt-get -y --force-yes install apt-transport-https \
	&& apt-key adv --keyserver keys.gnupg.net --recv-key 0x810273C4 \
	&& echo "deb http://packages.inverse.ca/SOGo/nightly/3/debian/ jessie jessie" > /etc/apt/sources.list.d/sogo.list \
	&& apt-get update \
	&& apt-get -y --force-yes install sogo sogo-activesync

echo "<?xml version="1.0" encoding="UTF-8"?> \n\
<!DOCTYPE plist PUBLIC "-//GNUstep//DTD plist 0.9//EN" "http://www.gnustep.org/plist-0_9.xml">\n\
<plist version="0.9">\n\
<dict>\n\
    <key>OCSEMailAlarmsFolderURL</key>\n\
    <string>mysql://${DBUSER}:${DBPASS}@${DBHOST}:3306/${DBNAME}/sogo_alarms_folder</string>\n\
    <key>OCSFolderInfoURL</key>\n\
    <string>mysql://${DBUSER}:${DBPASS}@${DBHOST}:3306/${DBNAME}/sogo_folder_info</string>\n\
    <key>OCSSessionsFolderURL</key>\n\
    <string>mysql://${DBUSER}:${DBPASS}@${DBHOST}:3306/${DBNAME}/sogo_sessions_folder</string>\n\
    <key>SOGoProfileURL</key>\n\
    <string>mysql://${DBUSER}:${DBPASS}@${DBHOST}:3306/${DBNAME}/sogo_user_profile</string>\n\
    <key>SOGoUserSources</key>\n\
    <string>\(\{type = sql;id = directory;viewURL = mysql://${DBUSER}:${DBPASS}@${DBHOST}:3306/${DBNAME}/sogo_view;canAuthenticate = YES;isAddressBook = YES;displayName = \&quot;GAL\&quot;;MailFieldNames = (aliases, ad_aliases, senderacl);userPasswordAlgorithm = ssha256;\}\)</string>\n\
</dict>\n\
</plist>" > /var/lib/sogo/GNUstep/Defaults/sogod.plist

USER sogo

CMD ["/usr/sbin/sogod"]

EXPOSE 20000
