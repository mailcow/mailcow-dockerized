#!/bin/bash
trap "kill 0" SIGINT

touch /var/log/clamav/clamd.log /var/log/clamav/freshclam.log
chown -R clamav:clamav /var/log/clamav/

freshclam -d &
clamd &

tail -f /var/log/clamav/clamd.log /var/log/clamav/freshclam.log
