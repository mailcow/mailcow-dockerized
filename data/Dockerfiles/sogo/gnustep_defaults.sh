#!/bin/bash
defaults write sogod SOGoUserSources '({type = sql;id = directory;viewURL = mysql://${DBUSER}:${DBPASS}@${DBHOST}:3306/${DBNAME}/sogo_view;canAuthenticate = YES;isAddressBook = YES;displayName = \"GAL\";MailFieldNames = (aliases, ad_aliases, senderacl);userPasswordAlgorithm = ssha256;})'
defaults write sogod SOGoProfileURL 'mysql://${DBUSER}:${DBPASS}@${DBHOST}:3306/${DBNAME}/sogo_user_profile'
defaults write sogod OCSFolderInfoURL 'mysql://${DBUSER}:${DBPASS}@${DBHOST}:3306/${DBNAME}/sogo_folder_info'
defaults write sogod OCSEMailAlarmsFolderURL 'mysql://${DBUSER}:${DBPASS}@${DBHOST}:3306/${DBNAME}/sogo_alarms_folder'
defaults write sogod OCSSessionsFolderURL 'mysql://${DBUSER}:${DBPASS}@${DBHOST}:3306/${DBNAME}/sogo_sessions_folder'

