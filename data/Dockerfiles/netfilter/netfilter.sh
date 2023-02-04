#!/bin/sh

server_to_use="server.py"

if [ -n "$USE_NFTABLES" ]; then
   if echo "$USE_NFTABLES" | grep -Eq "^[yY]$"; then
        server_to_use="server-nft.py"
   fi
fi

exec python -u ${server_to_use}