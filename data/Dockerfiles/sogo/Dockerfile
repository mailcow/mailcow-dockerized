FROM debian:jessie
MAINTAINER Andre Peters <andre.peters@debinux.de>

ENV DEBIAN_FRONTEND noninteractive

RUN apt-get update \
	&& apt-get -y --force-yes install apt-transport-https \
	&& apt-key adv --keyserver keys.gnupg.net --recv-key 0x810273C4 \
	&& echo "deb http://packages.inverse.ca/SOGo/nightly/3/debian/ jessie jessie" > /etc/apt/sources.list.d/sogo.list \
	&& apt-get update \
	&& apt-get -y --force-yes install sogo sogo-activesync

RUN defaults write sogod SOGoUserSources '({type = sql;id = directory;viewURL = mysql://${DBUSER}:${DBPASS}@${DBHOST}:3306/${DBNAME}/sogo_view;canAuthenticate = YES;isAddressBook = YES;displayName = \"GAL\";MailFieldNames = (aliases, ad_aliases, senderacl);userPasswordAlgorithm = ssha256;})'
RUN defaults write sogod SOGoProfileURL 'mysql://${DBUSER}:${DBPASS}@${DBHOST}:3306/${DBNAME}/sogo_user_profile'
RUN defaults write sogod OCSFolderInfoURL 'mysql://${DBUSER}:${DBPASS}@${DBHOST}:3306/${DBNAME}/sogo_folder_info'
RUN defaults write sogod OCSEMailAlarmsFolderURL 'mysql://${DBUSER}:${DBPASS}@${DBHOST}:3306/${DBNAME}/sogo_alarms_folder'
RUN defaults write sogod OCSSessionsFolderURL 'mysql://${DBUSER}:${DBPASS}@${DBHOST}:3306/${DBNAME}/sogo_sessions_folder'


USER sogo

CMD ["/usr/sbin/sogod"]

EXPOSE 20000
