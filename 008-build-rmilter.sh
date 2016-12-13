#!/bin/bash

. mailcow.conf

NAME="rmilter-mailcow"

echo "Stopping and removing containers with name tag ${NAME}..."
if [[ ! -z $(docker ps -af "name=${NAME}" -q) ]]; then
	docker stop $(docker ps -af "name=${NAME}" -q)
	docker rm $(docker ps -af "name=${NAME}" -q)
fi

docker run \
	-v ${PWD}/data/conf/rmilter/:/etc/rmilter.conf.d/:ro \
	--network=${DOCKER_NETWORK} \
	-h rmilter \
	--network-alias=rmilter
	--name ${NAME} \
	-d andryyy/mailcow-dockerized:rmilter
