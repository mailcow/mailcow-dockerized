#!/bin/sh

set -eu

if [ "${CLAMAV_NO_CLAMD:-}" != "false" ]; then
	if [ "$(echo "PING" | nc localhost 3310)" != "PONG" ]; then
		echo "ERROR: Unable to contact server"
		exit 1
	fi

	echo "Clamd is up"
fi

exit 0