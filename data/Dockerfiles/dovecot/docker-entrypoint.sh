#!/bin/bash
set -e

sed -i "/^connect/c\connect = \"host=mysql dbname=${DBNAME} user=${DBUSER} password=${DBPASS}\"" /etc/dovecot/sql/*

exec "$@"
