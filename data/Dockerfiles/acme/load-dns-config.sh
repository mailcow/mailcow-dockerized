#!/bin/bash

SCRIPT_SOURCE="${BASH_SOURCE[0]:-${0}}"
if [[ "${SCRIPT_SOURCE}" == "${0}" ]]; then
  __dns_loader_standalone=1
else
  __dns_loader_standalone=0
fi

CONFIG_PATH="${ACME_DNS_CONFIG_FILE:-/etc/acme/dns-101.conf}"

if [[ ! -f "${CONFIG_PATH}" ]]; then
  if [[ $__dns_loader_standalone -eq 1 ]]; then
    exit 0
  else
    return 0
  fi
fi

source /srv/functions.sh

log_f "Loading DNS-01 configuration from ${CONFIG_PATH}"

LINE_NO=0
while IFS= read -r line || [[ -n "${line}" ]]; do
  LINE_NO=$((LINE_NO+1))
  line="${line%$'\r'}"
  line_trimmed="$(printf '%s' "${line}" | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//')"
  [[ -z "${line_trimmed}" ]] && continue
  [[ "${line_trimmed:0:1}" == "#" ]] && continue
  if [[ "${line_trimmed}" != *=* ]]; then
    log_f "Skipping invalid DNS config line ${LINE_NO} (missing key=value)"
    continue
  fi
  KEY="${line_trimmed%%=*}"
  VALUE="${line_trimmed#*=}"
  KEY="$(printf '%s' "${KEY}" | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//')"
  VALUE="$(printf '%s' "${VALUE}" | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//')"
  if [[ -z "${KEY}" ]]; then
    log_f "Skipping invalid DNS config line ${LINE_NO} (empty key)"
    continue
  fi
  if [[ "${VALUE}" =~ ^\".*\"$ ]]; then
    VALUE="${VALUE:1:-1}"
  elif [[ "${VALUE}" =~ ^\'.*\'$ ]]; then
    VALUE="${VALUE:1:-1}"
  fi
  export "${KEY}"="${VALUE}"
  log_f "Exported DNS config key ${KEY}"

done < "${CONFIG_PATH}"

if [[ $__dns_loader_standalone -eq 1 ]]; then
  exit 0
else
  return 0
fi
