#!/bin/bash
. mailcow.conf

if [[ -z $(docker network ls --filter "name=${DOCKER_NETWORK}" -q) ]]; then
	docker network create ${DOCKER_NETWORK} --subnet ${DOCKER_SUBNET}
else
	if [[ $(docker network inspect mailcow-network --format='{{range .IPAM.Config}}{{.Subnet}}{{end}}' 2> /dev/null) != ${DOCKER_SUBNET} ]]; then
		echo "ERROR: mailcow network exists, but has wrong subnet!"
		exit 1
	fi
	echo "Correct mailcow network exists, skipped..."
	exit 0
fi
