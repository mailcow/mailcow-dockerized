#!/bin/bash

. mailcow.conf

NAME="sogo-mailcow"

echo "Stopping and removing containers with name tag ${NAME}..."
if [[ ! -z $(docker ps -af "name=${NAME}" -q) ]]; then
    docker stop $(docker ps -af "name=${NAME}" -q)
    docker rm $(docker ps -af "name=${NAME}" -q)
fi

build() {
	docker build --no-cache -t sogo data/Dockerfiles/sogo/.
}

if [[ ! -z "$(docker images -q sogo)" ]]; then
    read -r -p "Found image locally. Delete local and rebuild without cache anyway? [y/N] " response
    response=${response,,}    # tolower
    if [[ $response =~ ^(yes|y)$ ]]; then
        docker rmi sogo
        build
	fi
else
	build
fi

sed -i "s#OCSEMailAlarmsFolderURL.*#OCSEMailAlarmsFolderURL = \"mysql://${DBUSER}:${DBPASS}@mysql:3306/${DBNAME}/sogo_alarms_folder\";#" data/conf/sogo/sogo.conf
sed -i "s#OCSFolderInfoURL.*#OCSFolderInfoURL = \"mysql://${DBUSER}:${DBPASS}@mysql:3306/${DBNAME}/sogo_folder_info\";#" data/conf/sogo/sogo.conf
sed -i "s#OCSSessionsFolderURL.*#OCSSessionsFolderURL = \"mysql://${DBUSER}:${DBPASS}@mysql:3306/${DBNAME}/sogo_sessions_folder\";#" data/conf/sogo/sogo.conf
sed -i "s#SOGoProfileURL.*#SOGoProfileURL = \"mysql://${DBUSER}:${DBPASS}@mysql:3306/${DBNAME}/sogo_user_profile\";#" data/conf/sogo/sogo.conf
sed -i "s#viewURL.*#viewURL = \"mysql://${DBUSER}:${DBPASS}@mysql:3306/${DBNAME}/sogo_view\";#" data/conf/sogo/sogo.conf
sed -i "s#WOWorkersCount.*#WOWorkersCount = \"${SOGOCHILDS}\";#" data/conf/sogo/sogo.conf

docker run \
	-v ${PWD}/data/conf/sogo/:/etc/sogo/ \
	--name ${NAME} \
	--network=${DOCKER_NETWORK} \
	--network-alias sogo \
	-h sogo \
	-d -t sogo
