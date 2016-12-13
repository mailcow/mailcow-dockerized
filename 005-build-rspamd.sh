#!/bin/bash

. mailcow.conf

NAME="rspamd-mailcow"

build() {
    docker build --no-cache -t rspamd data/Dockerfiles/rspamd/.
}

PDNS_IP=$(docker inspect -f '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' pdns-mailcow 2> /dev/null)
if [[ ! ${PDNS_IP} =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
	echo "Cannot determine Powerdns Recursor ip address. Is the container running?"
	exit 1
fi

echo "Stopping and removing containers with name tag ${NAME}..."
if [[ ! -z $(docker ps -af "name=${NAME}" -q) ]]; then
    docker stop $(docker ps -af "name=${NAME}" -q)
    docker rm $(docker ps -af "name=${NAME}" -q)
fi

if [[ ! -z "$(docker images -q rspamd)" ]]; then
    read -r -p "Found image locally. Delete local and rebuild without cache anyway? [y/N] " response
    response=${response,,}
    if [[ $response =~ ^(yes|y)$ ]]; then
        docker rmi rspamd
		build
    fi
else
	build
fi

# Needs network-alias because of different dns

docker run \
	-v ${PWD}/data/conf/rspamd/override.d/:/etc/rspamd/override.d:ro \
	-v ${PWD}/data/conf/rspamd/local.d/:/etc/rspamd/local.d:ro \
	-v ${PWD}/data/conf/rspamd/lua/:/etc/rspamd/lua/:ro \
	-v ${PWD}/data/dkim/txt/:/etc/rspamd/dkim/txt/:ro \
	-v ${PWD}/data/dkim/keys/:/etc/rspamd/dkim/keys/:ro \
	--dns=${PDNS_IP} \
	--dns-search=${DOCKER_NETWORK} \
	--network=${DOCKER_NETWORK} \
	--network-alias=rspamd \
	-h rspamd \
	--name ${NAME} \
	-d rspamd

/bin/bash ./fix-permissions.sh

