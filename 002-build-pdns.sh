#!/bin/bash

. mailcow.conf

NAME="pdns-mailcow"

echo "Stopping and removing containers with name tag ${NAME}..."
if [[ ! -z $(docker ps -af "name=${NAME}" -q) ]]; then
	docker stop $(docker ps -af "name=${NAME}" -q)
	docker rm $(docker ps -af "name=${NAME}" -q)
fi

docker run \
	-v ${PWD}/data/conf/pdns/:/etc/powerdns/ \
	--network=${DOCKER_NETWORK} \
	-h pdns \
	--network-alias=pdns \
	--name ${NAME} \
	-d andryyy/mailcow-dockerized:pdns
