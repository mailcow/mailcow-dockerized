#!/bin/bash
set -e

sed -i "/^connect/c\connect = \"host=mysql dbname=${DBNAME} user=${DBUSER} password=${DBPASS}\"" /etc/dovecot/sql/*

if [[ $(stat -c %U /var/vmail/) != "vmail" ]] ; then chown -R vmail:vmail /var/vmail ; fi

exec "$@"
