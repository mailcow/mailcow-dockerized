#!/bin/bash

# http://superuser.com/questions/168412/using-supervisord-to-control-the-postfix-mta

trap "postfix stop" SIGINT
trap "postfix stop" SIGTERM
trap "postfix reload" SIGHUP

# start postfix
postfix -c /opt/postfix/conf start

# lets give postfix some time to start
sleep 3

# wait until postfix is dead (triggered by trap)
while kill -0 $(cat /var/spool/postfix/pid/master.pid); do
  sleep 5
done
