#!/bin/bash

nslookup mailcow.email 127.0.0.1 1> /dev/null

if [ $? == 0 ]; then
    echo "DNS resolution is working!"
    exit 0
else
    echo "DNS resolution is not working correctly..."
    echo "Maybe check your outbound firewall, as it needs to resolve DNS over TCP AND UDP!"
    exit 1
fi
