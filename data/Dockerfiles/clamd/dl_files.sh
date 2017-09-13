#!/bin/bash

declare -a DB_MIRRORS=(
  "switch.clamav.net"
  "clamavdb.heanet.ie"
  "clamav.iol.cz"
  "clamav.univ-nantes.fr"
  "clamav.easynet.fr"
  "clamav.begi.net"
)
declare -a DB_MIRRORS=( $(shuf -e "${DB_MIRRORS[@]}") )

DB_FILES=(
  "bytecode.cvd"
  "daily.cvd"
  "main.cvd"
)

for i in "${DB_MIRRORS[@]}"; do
  for j in "${DB_FILES[@]}"; do
  [[ -f "/var/lib/clamav/${j}" && -s "/var/lib/clamav/${j}" ]] && continue;
  if [[ $(curl -o /dev/null --connect-timeout 1 \
    --max-time 1 \
    --silent \
    --head \
    --write-out "%{http_code}\n" "${i}/${j}") == 200 ]]; then
    curl "${i}/${j}" -o "/var/lib/clamav/${j}" -#
  fi
  done
done

chown clamav:clamav /var/lib/clamav/*.cvd
