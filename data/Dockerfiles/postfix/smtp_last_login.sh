#!/bin/bash
echo action=OK
exit
while read QUERY; do
	logger -t last_login -p mail.info "$QUERY"
done
echo action=OK
