#!/bin/bash
set -e
while true; do
  SC=$(curl -s -o /dev/null -w "%{http_code}" http://nginx:8081/settings.php)
  if [[ ${SC} == "200" ]]; then
    sleep 3
    exec "$@"
  fi
  sleep 3
done
