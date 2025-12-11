#!/bin/bash

if [[ "${SKIP_CLAMD}" =~ ^([yY][eE][sS]|[yY])+$ ]]; then
  echo "SKIP_CLAMD=y, skipping ClamAV..."
  sleep 365d
  exit 0
fi

# Cleaning up garbage
echo "Cleaning up tmp files..."
rm -rf /var/lib/clamav/tmp.*

# Prepare whitelist

mkdir -p /run/clamav /var/lib/clamav

if [[ -s /etc/clamav/whitelist.ign2 ]]; then
  echo "Copying non-empty whitelist.ign2 to /var/lib/clamav/whitelist.ign2"
  cp /etc/clamav/whitelist.ign2 /var/lib/clamav/whitelist.ign2
fi

if [[ ! -f /var/lib/clamav/whitelist.ign2 ]]; then
  echo "Creating /var/lib/clamav/whitelist.ign2"
  cat <<EOF > /var/lib/clamav/whitelist.ign2
# Please restart ClamAV after changing signatures
Example-Signature.Ignore-1
PUA.Win.Trojan.EmbeddedPDF-1
PUA.Pdf.Trojan.EmbeddedJavaScript-1
PUA.Pdf.Trojan.OpenActionObjectwithJavascript-1
EOF
fi

chown clamav:clamav -R /var/lib/clamav /run/clamav

chmod 755 /var/lib/clamav
chmod 644 -R /var/lib/clamav/*
chmod 750 /run/clamav

stat /var/lib/clamav/whitelist.ign2
dos2unix /var/lib/clamav/whitelist.ign2
sed -i '/^\s*$/d' /var/lib/clamav/whitelist.ign2
# Copying to /etc/clamav to expose file as-is to administrator
cp -p /var/lib/clamav/whitelist.ign2 /etc/clamav/whitelist.ign2


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

# Give clamd time to start up, especially with limited resources
# This grace period allows clamd to initialize fully before health checks begin
# Can be configured via CLAMD_STARTUP_TIMEOUT environment variable
STARTUP_GRACE_PERIOD=${CLAMD_STARTUP_TIMEOUT:-600}  # Default: 10 minutes in seconds
echo "Waiting up to ${STARTUP_GRACE_PERIOD} seconds for clamd to start up..."

# Helper function to check if clamd is ready
clamd_is_ready() {
  echo "PING" | nc -w 1 127.0.0.1 3310 2>/dev/null | grep -q "PONG"
}

# Wait for clamd to be ready or until timeout
ELAPSED=0
POLL_INTERVAL=10
CLAMD_READY=0

while [ ${ELAPSED} -lt ${STARTUP_GRACE_PERIOD} ]; do
  # Check if clamd is responsive by attempting to connect on localhost
  # clamd listens on 0.0.0.0:3310 (configured in Dockerfile)
  if clamd_is_ready; then
    echo "clamd is ready after ${ELAPSED} seconds"
    CLAMD_READY=1
    break
  fi
  
  sleep ${POLL_INTERVAL}
  ELAPSED=$((ELAPSED + POLL_INTERVAL))
done

# Report final status
if [ ${CLAMD_READY} -eq 0 ]; then
  if clamd_is_ready; then
    echo "clamd is now ready (started during final check)"
  else
    echo "Warning: clamd did not respond to PING within ${STARTUP_GRACE_PERIOD} seconds - it may still be starting up"
  fi
fi

while true; do
  for bg_task in ${BACKGROUND_TASKS[*]}; do
    if ! kill -0 ${bg_task} 1>&2; then
      echo "Worker ${bg_task} died, stopping container waiting for respawn..."
      kill -TERM 1
    fi
    sleep 10
  done
done
