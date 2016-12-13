#!/bin/bash

. mailcow.conf

NAME="postfix-mailcow"

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

sed -i "/^user/c\user = ${DBUSER}" data/conf/postfix/sql/*
sed -i "/^password/c\password = ${DBPASS}" data/conf/postfix/sql/*
sed -i "/^dbname/c\dbname = ${DBNAME}" data/conf/postfix/sql/*

docker run \
	-p ${SMTP_PORT}:25 \
	-p ${SMTPS_PORT}:465 \
	-p ${SUBMISSION_PORT}:587 \
	-v ${PWD}/data/conf/postfix:/opt/postfix/conf:ro \
	-v ${PWD}/data/assets/ssl:/etc/ssl/mail/:ro \
	--dns=${PDNS_IP} \
	--dns-search=${DOCKER_NETWORK} \
	--name ${NAME} \
	--network=${DOCKER_NETWORK} \
	--network-alias postfix \
	-h ${MAILCOW_HOSTNAME} \
	-d andryyy/mailcow-dockerized:postfix
