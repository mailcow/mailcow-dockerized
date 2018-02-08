#!/bin/bash

chown -R _rspamd:_rspamd /var/lib/rspamd
[[ ! -f /etc/rspamd/override.d/worker-controller-password.inc ]] && echo '# Placeholder' > /etc/rspamd/override.d/worker-controller-password.inc

exec /sbin/tini -- "$@"
