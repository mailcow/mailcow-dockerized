#!/bin/bash

mkdir -p /etc/rspamd/plugins.d \
  /etc/rspamd/custom

touch /etc/rspamd/rspamd.conf.local \
  /etc/rspamd/rspamd.conf.override

chown -R _rspamd:_rspamd /var/lib/rspamd \
  /etc/rspamd/local.d \
  /etc/rspamd/override.d \
  /etc/rspamd/custom \
  /etc/rspamd/rspamd.conf.local \
  /etc/rspamd/rspamd.conf.override \
  /etc/rspamd/plugins.d

chmod 755 /var/lib/rspamd

[[ ! -f /etc/rspamd/override.d/worker-controller-password.inc ]] && echo '# Placeholder' > /etc/rspamd/override.d/worker-controller-password.inc
chown _rspamd:_rspamd /etc/rspamd/override.d/worker-controller-password.inc
[[ ! -f /etc/rspamd/custom/sa-rules-heinlein ]] && echo '# to be auto-filled by dovecot-mailcow' > /etc/rspamd/custom/sa-rules-heinlein

exec "$@"
