#!/bin/bash

source mailcow.conf

NAME="dovecot-mailcow"

echo "Stopping and removing containers with name tag ${NAME}..."
if [[ ! -z $(docker ps -af "name=${NAME}" -q) ]]; then
    docker stop $(docker ps -af "name=${NAME}" -q)
    docker rm $(docker ps -af "name=${NAME}" -q)
fi

sed -i "/^connect/c\connect = \"host=mysql dbname=${DBNAME} user=${DBUSER} password=${DBPASS}\"" data/conf/dovecot/sql/*

docker run \
	-p ${IMAP_PORT}:143 \
	-p ${IMAPS_PORT}:993 \
	-p ${POP_PORT}:110 \
	-p ${POPS_PORT}:995 \
	-p ${SIEVE_PORT}:4190\
	-v ${PWD}/data/conf/dovecot:/etc/dovecot:ro \
	-v ${PWD}/data/vmail:/var/vmail \
	-v ${PWD}/data/assets/ssl:/etc/ssl/mail/:ro \
	--name ${NAME} \
	--network=${DOCKER_NETWORK} \
	--network-alias dovecot \
	-h ${MAILCOW_HOSTNAME} \
	-d andryyy/mailcow-dockerized:dovecot

/bin/bash ./fix-permissions.sh
