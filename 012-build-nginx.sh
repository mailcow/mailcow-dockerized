#!/bin/bash

. mailcow.conf

NAME="nginx-mailcow"

echo "Stopping and removing containers with name tag ${NAME}..."
if [[ ! -z $(docker ps -af "name=${NAME}" -q) ]]; then
    docker stop $(docker ps -af "name=${NAME}" -q)
    docker rm $(docker ps -af "name=${NAME}" -q)
fi

sed -i "s#database_name.*#database_name = \"${DBNAME}\";#" data/web/inc/vars.inc.php
sed -i "s#database_user.*#database_user = \"${DBUSER}\";#" data/web/inc/vars.inc.php
sed -i "s#database_pass.*#database_pass = \"${DBPASS}\";#" data/web/inc/vars.inc.php

docker run \
	-p 443:443 \
    --expose 8081 \
	--name ${NAME} \
	-v ${PWD}/data/web:/web:ro \
	-v ${PWD}/data/conf/rspamd/dynmaps:/dynmaps:ro \
	-v ${PWD}/data/assets/ssl/:/etc/ssl/mail/:ro \
	-v ${PWD}/data/conf/nginx/:/etc/nginx/conf.d/:ro \
	--network=${DOCKER_NETWORK} \
	-h nginx \
	--network-alias=nginx \
	-d andryyy/mailcow-dockerized:nginx

/bin/bash ./fix-permissions.sh
