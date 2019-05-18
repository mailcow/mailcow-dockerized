#!/bin/bash

mkdir -p /etc/rspamd/plugins.d \
  /etc/rspamd/custom

touch /etc/rspamd/rspamd.conf.local \
  /etc/rspamd/rspamd.conf.override

chmod 755 /var/lib/rspamd

[[ ! -f /etc/rspamd/override.d/worker-controller-password.inc ]] && echo '# Placeholder' > /etc/rspamd/override.d/worker-controller-password.inc
[[ ! -f /etc/rspamd/custom/sa-rules-heinlein ]] && echo '# to be auto-filled by dovecot-mailcow' > /etc/rspamd/custom/sa-rules-heinlein
[[ ! -f /etc/rspamd/custom/dovecot_trusted.map ]] && echo '# to be auto-filled by dovecot-mailcow' > /etc/rspamd/custom/dovecot_trusted.map

DOVECOT_V4=
DOVECOT_V6=
until [[ ! -z ${DOVECOT_V4} ]]; do
  DOVECOT_V4=$(dig a dovecot +short)
  DOVECOT_V6=$(dig aaaa dovecot +short)
  [[ ! -z ${DOVECOT_V4} ]] && break;
  echo "Waiting for Dovecot"
  sleep 3
done
echo ${DOVECOT_V4}/32 > /etc/rspamd/custom/dovecot_trusted.map
if [[ ! -z ${DOVECOT_V6} ]]; then
  echo ${DOVECOT_V6}/128 >> /etc/rspamd/custom/dovecot_trusted.map
fi

chown -R _rspamd:_rspamd /var/lib/rspamd \
  /etc/rspamd/local.d \
  /etc/rspamd/override.d \
  /etc/rspamd/custom \
  /etc/rspamd/rspamd.conf.local \
  /etc/rspamd/rspamd.conf.override \
  /etc/rspamd/plugins.d

exec "$@"
