#!/bin/sh

set -e

if [ "${REWRITE_OUTGOING_IP}" = "yes" ] || [ "${REWRITE_OUTGOING_IP}" = "y" ]; then
    echo "Start setting rules for source NATting"
    sudo iptables -t nat -D POSTROUTING -o eth0 -s 172.22.1.0/24 -j SNAT --to-source "${REWRITE_OUTGOING_IP_TO}" || true
    sudo iptables -t nat -I POSTROUTING -o eth0 -s 172.22.1.0/24 -j SNAT --to-source "${REWRITE_OUTGOING_IP_TO}"
    echo "Added rules for source NATting outgoing requests as ${REWRITE_OUTGOING_IP_TO}"
fi