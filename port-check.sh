#!/bin/bash

. mailcow.conf

if [[ -z $(which ss) ]]; then echo "Please install the ss util first."; exit 1; fi

for port in ${SMTP_PORT} ${SMTPS_PORT} ${SUBMISSION_PORT} ${IMAP_PORT} ${IMAPS_PORT} ${POP_PORT} ${POPS_PORT} ${SIEVE_PORT} 443; do
	if [[ ! -z $(ss -tlnp "( sport = :$port )" 2> /dev/null | grep LISTEN | grep -vi docker) ]]; then
		echo "Port $port is in use by another process."
		err=1
	fi
done

if [[ ${err} == "1" ]]; then
	echo
	echo "Exiting."
	exit 1
fi

exit 0
