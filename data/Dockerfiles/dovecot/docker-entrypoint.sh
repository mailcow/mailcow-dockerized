#!/bin/bash

# Run hooks
for file in /hooks/*; do
  if [ -x "${file}" ]; then
    echo "Running hook ${file}"
    "${file}"
  fi
done

python3 -u /bootstrap/main.py
BOOTSTRAP_EXIT_CODE=$?

# Fix OpenSSL 3.X TLS1.0, 1.1 support (https://community.mailcow.email/d/4062-hi-all/20)
if grep -qE 'ssl_min_protocol\s*=\s*(TLSv1|TLSv1\.1)\s*$' /etc/dovecot/dovecot.conf /etc/dovecot/extra.conf; then
    sed -i '/\[openssl_init\]/a ssl_conf = ssl_configuration' /etc/ssl/openssl.cnf

    echo "[ssl_configuration]" >> /etc/ssl/openssl.cnf
    echo "system_default = tls_system_default" >> /etc/ssl/openssl.cnf
    echo "[tls_system_default]" >> /etc/ssl/openssl.cnf
    echo "MinProtocol = TLSv1" >> /etc/ssl/openssl.cnf
    echo "CipherString = DEFAULT@SECLEVEL=0" >> /etc/ssl/openssl.cnf
fi

if [ $BOOTSTRAP_EXIT_CODE -ne 0 ]; then
  echo "Bootstrap failed with exit code $BOOTSTRAP_EXIT_CODE. Not starting Dovecot."
  exit $BOOTSTRAP_EXIT_CODE
fi

echo "Bootstrap succeeded. Starting Dovecot..."
/usr/sbin/dovecot -F
