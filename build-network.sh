#!/bin/bash
. mailcow.conf

if [[ -z $(docker network ls --filter "name=${DOCKER_NETWORK}" -q) ]]; then
	docker network create ${DOCKER_NETWORK} --subnet ${DOCKER_SUBNET}
else
	exit 0
fi
