#!/bin/sh

while getopts "H:s:" opt; do
  case "$opt" in
    H) HOST="$OPTARG" ;;
    s) SERVER="$OPTARG" ;;
    *) echo "Usage: $0 -H host -s server"; exit 3 ;;
  esac
done

if [ -z "$SERVER" ]; then
  echo "No DNS Server provided"
  exit 3
fi

if [ -z "$HOST" ]; then
  echo "No host to test provided"
  exit 3
fi

# run dig and measure the time it takes to run
START_TIME=$(perl -MTime::HiRes -e 'print Time::HiRes::time')
dig_output=$(dig +short +timeout=2 +tries=1 "$HOST" @"$SERVER" 2>/dev/null)
dig_rc=$?
END_TIME=$(perl -MTime::HiRes -e 'print Time::HiRes::time')
dig_output_ips=$(echo "$dig_output" | grep -E '^[0-9.]+$' | sort | paste -sd ',' -)
ELAPSED_TIME=$(perl -e "printf('%.3f', $END_TIME - $START_TIME)")

# validate and perform nagios like output and exit codes
if [ $dig_rc -ne 0 ] || [ -z "$dig_output" ]; then
  echo "Domain $HOST was not found by the server"
  exit 2
elif [ $dig_rc -eq 0 ]; then
  echo "DNS OK: $ELAPSED_TIME seconds response time. $HOST returns $dig_output_ips"
  exit 0
else
  echo "Unknown error"
  exit 3
fi
