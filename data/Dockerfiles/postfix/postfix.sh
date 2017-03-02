#!/bin/bash

trap "postfix stop" EXIT

sed -i "/^user/c\user = ${DBUSER}" /opt/postfix/conf/sql/*
sed -i "/^password/c\password = ${DBPASS}" /opt/postfix/conf/sql/*
sed -i "/^dbname/c\dbname = ${DBNAME}" /opt/postfix/conf/sql/*

postconf -c /opt/postfix/conf
if [[ $? != 0 ]]; then
	echo "Postfix configuration error, refusing to start."
	exit 1
else
	postfix -c /opt/postfix/conf start
	sleep infinity
fi
