#!/bin/bash
[[ ! -d /tmp/sa-rules-heinlein ]] && mkdir -p /tmp/sa-rules-heinlein
if [[ ! -f /etc/rspamd/custom/sa-rules-heinlein ]]; then
  HASH_SA_RULES=0
else
  HASH_SA_RULES=$(cat /etc/rspamd/custom/sa-rules-heinlein | md5sum | cut -d' ' -f1)
fi

curl --connect-timeout 15 --max-time 30 http://www.spamassassin.heinlein-support.de/$(dig txt 1.4.3.spamassassin.heinlein-support.de +short | tr -d '"').tar.gz --output /tmp/sa-rules.tar.gz
if [[ -f /tmp/sa-rules.tar.gz ]]; then
  tar xfvz /tmp/sa-rules.tar.gz -C /tmp/sa-rules-heinlein
  # create complete list of rules in a single file
  cat /tmp/sa-rules-heinlein/*cf > /etc/rspamd/custom/sa-rules-heinlein
  # Only restart rspamd-mailcow when rules changed
  if [[ $(cat /etc/rspamd/custom/sa-rules-heinlein | md5sum | cut -d' ' -f1) != ${HASH_SA_RULES} ]]; then
    CONTAINER_NAME=rspamd-mailcow
    CONTAINER_ID=$(curl --silent --insecure https://dockerapi/containers/json | \
      jq -r ".[] | {name: .Config.Labels[\"com.docker.compose.service\"], id: .Id}" | \
      jq -rc "select( .name | tostring | contains(\"${CONTAINER_NAME}\")) | .id")
    if [[ ! -z ${CONTAINER_ID} ]]; then
      curl --silent --insecure -XPOST --connect-timeout 15 --max-time 120 https://dockerapi/containers/${CONTAINER_ID}/restart
    fi
  fi
fi
rm -rf /tmp/sa-rules-heinlein /tmp/sa-rules.tar.gz
