#!/bin/bash

. mailcow.conf
./build-network.sh

NAME="rmilter-mailcow"

echo "Stopping and removing containers with name tag ${NAME}..."
if [[ ! -z $(docker ps -af "name=${NAME}" -q) ]]; then
	docker stop $(docker ps -af "name=${NAME}" -q)
	docker rm $(docker ps -af "name=${NAME}" -q)
fi

build() {
	docker build -t rmilter data/Dockerfiles/rmilter/.
}

if [[ ! -z "$(docker images -q rmilter)" ]]; then
	read -r -p "Found image locally. Rebuild anyway? [y/N] " response
	response=${response,,}
	if [[ $response =~ ^(yes|y)$ ]]; then
		docker rmi rmilter
		build
	fi
else
	build
fi

docker run \
	-v ${PWD}/data/conf/rmilter/:/etc/rmilter.conf.d/ \
	--network=${DOCKER_NETWORK} \
	--network-alias rmilter \
	-h rmilter \
	--name ${NAME} \
	-d rmilter
