#!/bin/bash

BACKGROUND_TASKS=()

echo "Running freshclam..."
freshclam

(
while true; do
  sleep 12600
  freshclam
done
) &
BACKGROUND_TASKS+=($!)

(
while true; do
  sleep 10m
  SANE_MIRRORS="$(dig +ignore +short rsync.sanesecurity.net)"
  for sane_mirror in ${SANE_MIRRORS}; do
    CE=
    rsync -avp --chown=clamav:clamav --chmod=Du=rwx,Dgo=rx,Fu=rw,Fog=r --timeout=5 rsync://${sane_mirror}/sanesecurity/ \
      --include 'blurl.ndb' \
      --include 'junk.ndb' \
      --include 'jurlbl.ndb' \
      --include 'jurbla.ndb' \
      --include 'phishtank.ndb' \
      --include 'phish.ndb' \
      --include 'spamimg.hdb' \
      --include 'scam.ndb' \
      --include 'rogue.hdb' \
      --include 'sanesecurity.ftm' \
      --include 'sigwhitelist.ign2' \
      --exclude='*' /var/lib/clamav/
    CE=$?
    chmod 755 /var/lib/clamav/
    if [ ${CE} -eq 0 ]; then
      while [ ! -z "$(pidof freshclam)" ]; do
        echo "Freshclam is active, waiting..."
        sleep 5
      done
      echo RELOAD | nc clamd-mailcow 3310
      break
    fi
  done
  sleep 12h
done
) &
BACKGROUND_TASKS+=($!)

echo "$(clamd -V) is starting... please wait a moment."
nice -n10 clamd &
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
