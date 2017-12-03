#!/bin/bash

chown -R _rspamd:_rspamd /var/lib/rspamd

exec /sbin/tini -- "$@"
