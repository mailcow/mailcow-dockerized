#!/bin/bash

# http://superuser.com/questions/168412/using-supervisord-to-control-the-postfix-mta

trap "postfix stop" SIGINT
trap "postfix stop" SIGTERM
trap "postfix reload" SIGHUP

# start postfix
postfix -c /opt/postfix/conf start

sleep infinity
