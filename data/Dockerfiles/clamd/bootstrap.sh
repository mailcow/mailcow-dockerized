#!/bin/bash

if [[ "${SKIP_CLAMD}" =~ ^([yY][eE][sS]|[yY])+$ ]]; then
  echo "SKIP_CLAMD=y, skipping ClamAV..."
  sleep 365d
  exit 0
fi

# Create log pipes
mkdir /var/log/clamav
touch /var/log/clamav/clamd.log /var/log/clamav/freshclam.log
mkfifo -m 600 /tmp/logpipe_clamd
mkfifo -m 600 /tmp/logpipe_freshclam
chown -R clamav:clamav /var/log/clamav/ /tmp/logpipe_*
cat <> /tmp/logpipe_clamd 1>&2 &
cat <> /tmp/logpipe_freshclam 1>&2 &

# Prepare
BACKGROUND_TASKS=()

freshclam -d &
BACKGROUND_TASKS+=($!)

clamd &
BACKGROUND_TASKS+=($!)

while true; do
  for bg_task in ${BACKGROUND_TASKS[*]}; do
    if ! kill -0 ${bg_task} 1>&2; then
      echo "Worker ${bg_task} died, stopping container waiting for respawn..."
      kill -TERM 1
    fi
    sleep 10
  done
done
