#!/bin/bash

trap "postfix stop" EXIT

postfix -c /opt/postfix/conf start

sleep infinity
