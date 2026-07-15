#!/bin/bash

echo "Setting console permissions..."
chown root:tty /dev/console
chmod g+rw /dev/console
echo "Receiving anchor key..."
/usr/sbin/unbound-anchor -a /etc/unbound/trusted-key.key
echo "Receiving root hints..."
curl -#o /etc/unbound/root.hints https://www.internic.net/domain/named.cache
/usr/sbin/unbound-control-setup

# Follow ENABLE_IPV6: when IPv6 is disabled the container has no IPv6 route, and
# unbound with do-ip6: yes keeps selecting IPv6 targets, fails, and gives up instead
# of falling back to IPv4 (SERVFAIL -> unhealthy). Override do-ip6 accordingly.
mkdir -p /etc/unbound/conf.d
if [ "${ENABLE_IPV6}" = "false" ]; then
  echo "ENABLE_IPV6 is false, setting do-ip6: no for unbound"
  printf 'server:\n  do-ip6: no\n' > /etc/unbound/conf.d/ipv6.conf
else
  rm -f /etc/unbound/conf.d/ipv6.conf
fi

# Run hooks
for file in /hooks/*; do
  if [ -x "${file}" ]; then
    echo "Running hook ${file}"
    "${file}"
  fi
done

exec "$@"
