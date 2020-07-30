#!/usr/bin/env bash
[[ -f mailcow.conf ]] && source mailcow.conf
[[ -f ../mailcow.conf ]] && source ../mailcow.conf

if [[ -z ${DBUSER} ]] || [[ -z ${DBPASS} ]] || [[ -z ${DBNAME} ]]
then
	echo "Cannot find mailcow.conf, make sure this script is run from within the mailcow folder."
	exit 1
fi

echo -n "Checking MySQL service... "
if [[ -z $(docker ps -qf name=mysql-mailcow) ]]
then
	echo "failed"
	echo "MySQL (mysql-mailcow) is not up and running, exiting..."
	exit 1
fi

echo -n "Getting list of avaible ACLs... "
ACL_NAMES_TMP=$(mktemp)
ACL_REGEX='.{4,}'
docker exec -it $(docker ps -qf name=mysql-mailcow) mysql -u${DBUSER} -p${DBPASS} ${DBNAME} -N -e "SELECT CONCAT(COLUMN_NAME, '@') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'user_acl' AND DATA_TYPE LIKE 'tinyint%';" | while IFS=@ GLOBIGNORE='*' command eval read -a ROW
do
	ACL=$(echo ${ROW}| awk '{print $2}')
	if [[ ${ACL} =~ ${ACL_REGEX} ]]
	then
		echo ${ACL} >> ${ACL_NAMES_TMP}
	fi
done

IFS=$'\r\n' GLOBIGNORE='*' command eval 'ACL_NAMES=($(cat ${ACL_NAMES_TMP}))'
rm -f ${ACL_NAMES_TMP}

if [ ${#ACL_NAMES[@]} -eq 0 ]
then
	echo "Oops, something went wrong... Can't find at least one ACL type"
	exit 1
else
	echo "List of avaible ACLs:"
	echo "${ACL_NAMES[@]}"
fi

read -r -p "Which ACL you want to update?" ACL_INPUT
for ACL in "${ACL_NAMES[@]}"
do
	if [ "${ACL}" == "$ACL_INPUT" ] ; then
		ACL_NAME="${ACL}"
	fi
done

if [[ -z ${ACL_NAME} ]]
then
	echo "Looks like you made a mistake in the ACL name, please run script again and input correct name."
	exit 1
fi

read -r -p "You want to enable or disable ACL? [e/d] " UPDATE_TYPE
UPDATE_TYPE=${UPDATE_TYPE,,} # tolower
if [[ "${UPDATE_TYPE}" =~ ^(enable|e)$ ]]
then
	echo "Working, please wait..."
	docker exec -it $(docker ps -qf name=mysql-mailcow) mysql -u${DBUSER} -p${DBPASS} ${DBNAME} -e "UPDATE user_acl SET ${ACL_NAME} = TRUE WHERE ${ACL_NAME} = FALSE;"
	echo "Done, have a nice day!"
fi
if [[ "${UPDATE_TYPE}" =~ ^(disable|d)$ ]]
then
	echo "Working, please wait..."
	docker exec -it $(docker ps -qf name=mysql-mailcow) mysql -u${DBUSER} -p${DBPASS} ${DBNAME} -e "UPDATE user_acl SET ${ACL_NAME} = FALSE WHERE ${ACL_NAME} = TRUE;"
	echo "Done, have a nice day!"
fi
