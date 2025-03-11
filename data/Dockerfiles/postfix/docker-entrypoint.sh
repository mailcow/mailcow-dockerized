#!/bin/bash

# Run hooks
for file in /hooks/*; do
  if [ -x "${file}" ]; then
    echo "Running hook ${file}"
    "${file}"
  fi
done

if [[ ! -z ${VALKEY_SLAVEOF_IP} ]]; then
  cp /etc/syslog-ng/syslog-ng-valkey_slave.conf /etc/syslog-ng/syslog-ng.conf
fi

# Fix OpenSSL 3.X TLS1.0, 1.1 support (https://community.mailcow.email/d/4062-hi-all/20)
if grep -qE '\!SSLv2|\!SSLv3|>=TLSv1(\.[0-1])?$' /opt/postfix/conf/main.cf /opt/postfix/conf/extra.cf; then
    sed -i '/\[openssl_init\]/a ssl_conf = ssl_configuration' /etc/ssl/openssl.cnf

    echo "[ssl_configuration]" >> /etc/ssl/openssl.cnf
    echo "system_default = tls_system_default" >> /etc/ssl/openssl.cnf
    echo "[tls_system_default]" >> /etc/ssl/openssl.cnf
    echo "MinProtocol = TLSv1" >> /etc/ssl/openssl.cnf
    echo "CipherString = DEFAULT@SECLEVEL=0" >> /etc/ssl/openssl.cnf
fi

exec "$@"
