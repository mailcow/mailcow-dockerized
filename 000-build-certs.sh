#!/bin/bash
. mailcow.conf

openssl dhparam -out data/assets/ssl/dhparams.pem 2048

docker run \
	--rm \
	-v ${PWD}/data/assets/ssl:/certs \
	ehazlett/certm \
	-d /certs ca generate -o=mailcow

docker run \
	--rm \
	-v ${PWD}/data/assets/ssl:/certs \
	ehazlett/certm \
	-d /certs client generate --common-name=${MAILCOW_HOSTNAME} -o=mailcow
