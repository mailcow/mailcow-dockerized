#!/bin/bash
set -e

if [[ ! -d "/data/dkim/txt" || ! -d "/data/dkim/keys" ]] ; then	mkdir -p /data/dkim/{txt,keys} ; chown -R www-data:www-data /data/dkim; fi
if [[ $(stat -c %U /data/dkim/) != "www-data" ]] ; then chown -R www-data:www-data /data/dkim ; fi

# Migrate domain table to redis


exec "$@"
