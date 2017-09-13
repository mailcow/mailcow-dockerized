#!/bin/bash
trap "kill 0" SIGINT

touch /var/log/clamav/clamd.log /var/log/clamav/freshclam.log
chown -R clamav:clamav /var/log/clamav/

if [[ "${SKIP_CLAMD}" =~ ^([yY][eE][sS]|[yY])+$ ]]; then
	echo "SKIP_CLAMD=y, skipping ClamAV..."
	exit 0
fi

freshclam -d &
clamd &

tail -f /var/log/clamav/clamd.log /var/log/clamav/freshclam.log
