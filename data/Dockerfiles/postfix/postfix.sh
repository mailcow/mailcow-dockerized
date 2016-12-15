#!/bin/bash

trap "postfix stop" EXIT

sed -i "/^user/c\user = ${DBUSER}" /opt/postfix/conf/sql/*
sed -i "/^password/c\password = ${DBPASS}" /opt/postfix/conf/sql/*
sed -i "/^dbname/c\dbname = ${DBNAME}" /opt/postfix/conf/sql/*

postfix -c /opt/postfix/conf start

sleep infinity
