#!/bin/sh

set -e

if [ "${REWRITE_OUTGOING_IP4}" = "yes" ] || [ "${REWRITE_OUTGOING_IP4}" = "y" ]; then
    echo "Start setting rules for source NATting IP4"
    sudo iptables -t nat -D POSTROUTING -o "${REWRITE_OUTGOING_IP4_INTERFACE_TO:-eth0}" -s 172.22.1.0/24 -j SNAT --to-source "${REWRITE_OUTGOING_IP4_TO}" || true
    sudo iptables -t nat -I POSTROUTING -o "${REWRITE_OUTGOING_IP4_INTERFACE_TO:-eth0}" -s 172.22.1.0/24 -j SNAT --to-source "${REWRITE_OUTGOING_IP4_TO}"
    echo "Added rules for source NATting IP4 outgoing requests as ${REWRITE_OUTGOING_IP4_TO}"
fi

if [ "${REWRITE_OUTGOING_IP6}" = "yes" ] || [ "${REWRITE_OUTGOING_IP6}" = "y" ]; then
    echo "Start setting rules for source NATting IP6"
    sudo ip6tables -t nat -D POSTROUTING -o "${REWRITE_OUTGOING_IP6_INTERFACE_TO:-eth0}" -s fd4d:6169:6c63:6f77::/64 -j SNAT --to-source "${REWRITE_OUTGOING_IP6_TO}" || true
    sudo ip6tables -t nat -I POSTROUTING -o "${REWRITE_OUTGOING_IP6_INTERFACE_TO:-eth0}" -s fd4d:6169:6c63:6f77::/64 -j SNAT --to-source "${REWRITE_OUTGOING_IP6_TO}"
    echo "Added rules for source NATting IP6 outgoing requests as ${REWRITE_OUTGOING_IP6_TO}"
fi