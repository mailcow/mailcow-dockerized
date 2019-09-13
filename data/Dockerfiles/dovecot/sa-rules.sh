#!/bin/bash

# Create temp directories
[[ ! -d /tmp/sa-rules-schaal ]] && mkdir -p /tmp/sa-rules-schaal
[[ ! -d /tmp/sa-rules-heinlein ]] && mkdir -p /tmp/sa-rules-heinlein

# Hash current SA rules
if [[ ! -f /etc/rspamd/custom/sa-rules ]]; then
  HASH_SA_RULES=0
else
  HASH_SA_RULES=$(cat /etc/rspamd/custom/sa-rules | md5sum | cut -d' ' -f1)
fi

# Deploy
## Heinlein
curl --connect-timeout 15 --max-time 30 http://www.spamassassin.heinlein-support.de/$(dig txt 1.4.3.spamassassin.heinlein-support.de +short | tr -d '"').tar.gz --output /tmp/sa-rules-heinlein.tar.gz
if gzip -t /tmp/sa-rules-heinlein.tar.gz; then
  tar xfvz /tmp/sa-rules-heinlein.tar.gz -C /tmp/sa-rules-heinlein
  cat /tmp/sa-rules-heinlein/*cf > /etc/rspamd/custom/sa-rules
fi
## Schaal
curl --connect-timeout 15 --max-time 30 http://sa.schaal-it.net/$(dig txt 1.4.3.sa.schaal-it.net +short | tr -d '"').tar.gz --output /tmp/sa-rules-schaal.tar.gz
if gzip -t /tmp/sa-rules-schaal.tar.gz; then
  tar xfvz /tmp/sa-rules-schaal.tar.gz -C /tmp/sa-rules-schaal
  # Append, do not overwrite
  cat /tmp/sa-rules-schaal/*cf >> /etc/rspamd/custom/sa-rules
fi

if [[ "$(cat /etc/rspamd/custom/sa-rules | md5sum | cut -d' ' -f1)" != "${HASH_SA_RULES}" ]]; then
  CONTAINER_NAME=rspamd-mailcow
  CONTAINER_ID=$(curl --silent --insecure https://dockerapi/containers/json | \
    jq -r ".[] | {name: .Config.Labels[\"com.docker.compose.service\"], id: .Id}" | \
    jq -rc "select( .name | tostring | contains(\"${CONTAINER_NAME}\")) | .id")
  if [[ ! -z ${CONTAINER_ID} ]]; then
    curl --silent --insecure -XPOST --connect-timeout 15 --max-time 120 https://dockerapi/containers/${CONTAINER_ID}/restart
  fi
fi

# Cleanup
rm -rf /tmp/sa-rules-heinlein /tmp/sa-rules-heinlein.tar.gz
rm -rf /tmp/sa-rules-schaal /tmp/sa-rules-schaal.tar.gz
