#!/bin/bash

. mailcow.conf

NAME="sogo-mailcow"

echo "Stopping and removing containers with name tag ${NAME}..."
if [[ ! -z $(docker ps -af "name=${NAME}" -q) ]]; then
    docker stop $(docker ps -af "name=${NAME}" -q)
    docker rm $(docker ps -af "name=${NAME}" -q)
fi

docker run \
	-v ${PWD}/data/conf/sogo/:/etc/sogo/ \
	--name ${NAME} \
	--network=${DOCKER_NETWORK} \
	--network-alias sogo \
	-h sogo \
	-e DBNAME=${DBNAME} \
	-e DBUSER=${DBUSER} \
	-e DBPASS=${DBPASS} \
	-d -t andryyy/mailcow-dockerized:sogo
