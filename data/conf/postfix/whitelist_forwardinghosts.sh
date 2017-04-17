#!/bin/bash

while true; do
	read QUERY
	QUERY=($QUERY)
	if [ "${QUERY[0]}" != "get" ]; then
		echo "500 dunno"
		continue
	fi
	echo $(curl -s http://172.22.1.251:8081/forwardinghosts.php?host=${QUERY[1]})
done
