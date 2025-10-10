#!/bin/bash

if [[ ! -z ${VALKEY_SLAVEOF_IP} ]]; then
  cp /etc/syslog-ng/syslog-ng-valkey_slave.conf /etc/syslog-ng/syslog-ng.conf
fi

exec "$@"