#!/bin/bash

. mailcow.conf
./build-network.sh

NAME="nginx-mailcow"

echo "Stopping and removing containers with name tag ${NAME}..."
if [[ ! -z $(docker ps -af "name=${NAME}" -q) ]]; then
    docker stop $(docker ps -af "name=${NAME}" -q)
    docker rm $(docker ps -af "name=${NAME}" -q)
fi

if [[ ! -z "$(docker images -q nginx:${NGINXVERS})" ]]; then
    read -r -p "Found image locally. Delete local image and repull? [y/N] " response
    response=${response,,}    # tolower
    if [[ $response =~ ^(yes|y)$ ]]; then
        docker rmi nginx:${NGINXVERS}
    fi
fi

sed -i "s#database_name.*#database_name = \"${DBNAME}\";#" data/web/inc/vars.inc.php
sed -i "s#database_user.*#database_user = \"${DBUSER}\";#" data/web/inc/vars.inc.php
sed -i "s#database_pass.*#database_pass = \"${DBPASS}\";#" data/web/inc/vars.inc.php

docker run \
	-p 443:443 \
	--name ${NAME} \
	-v ${PWD}/data/web:/web:ro \
	-v ${PWD}/data/conf/rspamd/dynmaps:/dynmaps:ro \
	-v ${PWD}/data/assets/ssl/:/etc/ssl/mail/:ro \
	-v ${PWD}/data/conf/nginx/:/etc/nginx/conf.d/:ro \
	--network=${DOCKER_NETWORK} \
	--network-alias nginx \
	-h nginx \
	-d nginx:${NGINXVERS}

echo "Installaing SOGo web resource files..."
docker exec -it ${NAME} /bin/bash -c 'apt-key adv --keyserver keys.gnupg.net --recv-key 0x810273C4 && apt-get update && apt-get -y --force-yes install apt-transport-https'
docker exec -it ${NAME} /bin/bash -c 'echo "deb http://packages.inverse.ca/SOGo/nightly/3/debian/ jessie jessie" > /etc/apt/sources.list.d/sogo.list && apt-get update && apt-get -y --force-yes install sogo'
