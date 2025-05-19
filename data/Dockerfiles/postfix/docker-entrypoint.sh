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

if [ $BOOTSTRAP_EXIT_CODE -ne 0 ]; then
  echo "Bootstrap failed with exit code $BOOTSTRAP_EXIT_CODE. Not starting Postfix."
  exit $BOOTSTRAP_EXIT_CODE
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


# Start Postfix
postconf -c /opt/postfix/conf > /dev/null
if [[ $? != 0 ]]; then
  echo "Postfix configuration error, refusing to start."
  exit 1
else
  echo "Bootstrap succeeded. Starting Postfix..."
  postfix -c /opt/postfix/conf start
  sleep 126144000
fi
