#!/bin/bash
set -e

AS_SOGO="gosu sogo"

${AS_SOGO} defaults write sogod SOGoUserSources "({type = sql;id = directory;viewURL = mysql://${DBUSER}:${DBPASS}@mysql:3306/${DBNAME}/sogo_view;canAuthenticate = YES;isAddressBook = YES;displayName = \"GAL\";MailFieldNames = (aliases, ad_aliases, senderacl);userPasswordAlgorithm = ssha256;})"
${AS_SOGO} defaults write sogod SOGoProfileURL "mysql://${DBUSER}:${DBPASS}@mysql:3306/${DBNAME}/sogo_user_profile"
${AS_SOGO} defaults write sogod OCSFolderInfoURL "mysql://${DBUSER}:${DBPASS}@mysql:3306/${DBNAME}/sogo_folder_info"
${AS_SOGO} defaults write sogod OCSEMailAlarmsFolderURL "mysql://${DBUSER}:${DBPASS}@mysql:3306/${DBNAME}/sogo_alarms_folder"
${AS_SOGO} defaults write sogod OCSSessionsFolderURL "mysql://${DBUSER}:${DBPASS}@mysql:3306/${DBNAME}/sogo_sessions_folder"
${AS_SOGO} defaults write sogod SOGoTimeZone "${TZ}"

exec "$@"
