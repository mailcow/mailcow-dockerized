#!/bin/bash

password_file='/etc/rspamd/override.d/worker-controller-password.inc'
password_hash=`/usr/bin/rspamadm pw -e -p $1`

echo 'enable_password = "'$password_hash'";' > $password_file

if grep -q "$password_hash" "$password_file"; then
    echo "OK"
else
    echo "ERROR"
fi