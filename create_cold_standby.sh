#!/bin/bash

export REMOTE_SSH_KEY=/root/.ssh/id_rsa
export REMOTE_SSH_PORT=22
export REMOTE_SSH_HOST=my.remote.host

/opt/mailcow-dockerized/helper-scripts/_cold-standby.sh
