#!/bin/sh

backend=nftables

nft list table ip filter &>/dev/null
nftables_found=$?

iptables -L -n &>/dev/null
iptables_found=$?

if [ $nftables_found -lt $iptables_found ]; then
  backend=nftables
fi

if [ $nftables_found -gt $iptables_found ]; then
  backend=iptables
fi

if [ $nftables_found -eq 0 ] && [ $nftables_found -eq $iptables_found ]; then
  nftables_lines=$(nft list ruleset | wc -l)
  iptables_lines=$(iptables-save | wc -l)
  if [ $nftables_lines -gt $iptables_lines ]; then
    backend=nftables
  else
    backend=iptables
  fi
fi

exec python -u /app/main.py $backend
