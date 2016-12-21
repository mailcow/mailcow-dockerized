#!/bin/bash

PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

while true; do
/usr/sbin/sogo-tool expire-sessions 60
/usr/sbin/sogo-ealarms-notify
/usr/sbin/sogo-tool update-autoreply -p /etc/sogo/sieve.creds
sleep 60
done
