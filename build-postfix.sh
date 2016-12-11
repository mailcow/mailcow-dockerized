#!/bin/bash

. mailcow.conf

NAME="postfix-mailcow"

build() {
	docker build --no-cache -t postfix data/Dockerfiles/postfix/.
}

if [[  ${1} == "--reconf" ]]; then
    reconf
    exit 0
fi

echo "Stopping and removing containers with name tag ${NAME}..."
if [[ ! -z $(docker ps -af "name=${NAME}" -q) ]]; then
    docker stop $(docker ps -af "name=${NAME}" -q)
    docker rm $(docker ps -af "name=${NAME}" -q)
fi

if [[ ! -z "$(docker images -q postfix)" ]]; then
    read -r -p "Found image locally. Delete local and rebuild without cache anyway? [y/N] " response
    response=${response,,}
    if [[ $response =~ ^(yes|y)$ ]]; then
        docker rmi postfix
        build
    fi
else
    build
fi

sed -i "/myhostname/c\myhostname=${MAILCOW_HOSTNAME}" data/conf/postfix/main.cf
sed -i "/^user/c\user = ${DBUSER}" data/conf/postfix/sql/*
sed -i "/^password/c\password = ${DBPASS}" data/conf/postfix/sql/*
sed -i "/^dbname/c\dbname = ${DBNAME}" data/conf/postfix/sql/*

if [[ -z $(cat data/conf/postfix/main.cf | grep ${DOCKER_SUBNET}) ]]; then
	sed -i -e "s_^mynetworks.*_& ${DOCKER_SUBNET}_" data/conf/postfix/main.cf
fi

docker run \
	-p ${SMTP_PORT}:25 \
	-p ${SMTPS_PORT}:465 \
	-p ${SUBMISSION_PORT}:587 \
	-v ${PWD}/data/conf/postfix:/opt/postfix/conf:ro \
	-v ${PWD}/data/assets/ssl:/etc/ssl/mail/:ro \
	--name ${NAME} \
	--network=${DOCKER_NETWORK} \
	--network-alias postfix \
	-h ${MAILCOW_HOSTNAME} \
	-d postfix
