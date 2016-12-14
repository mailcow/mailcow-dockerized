#!/bin/bash

. mailcow.conf

NAME="php-fpm-mailcow"

if [[ ! -z $(docker ps -af "name=${NAME}" -q) ]]; then
	docker stop $(docker ps -af "name=${NAME}" -q)
	docker rm $(docker ps -af "name=${NAME}" -q)
fi

if [[ ! -z "$(docker images -q php:${PHPVERS})" ]]; then
    read -r -p "Found image locally. Delete local image and repull? [y/N] " response
    response=${response,,}
    if [[ $response =~ ^(yes|y)$ ]]; then
        docker rmi php:${PHPVERS}
    fi
fi

docker run \
	-v ${PWD}/data/web:/web:ro \
	-v ${PWD}/data/conf/rspamd/dynmaps:/dynmaps:ro \
    -v ${PWD}/data/dkim/:/shared/dkim/ \
	-d --network=${DOCKER_NETWORK} \
	--name ${NAME} \
	--network-alias=phpfpm \
	-h phpfpm \
	andryyy/mailcow-dockerized:phpfpm
