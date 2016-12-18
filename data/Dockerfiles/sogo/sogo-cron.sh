#!/bin/bash

PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

while true; do
/usr/sbin/sogo-tool expire-sessions 60
/usr/sbin/sogo-ealarms-notify
sleep 60
done
