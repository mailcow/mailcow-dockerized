#!/bin/bash

`openssl req -x509 -newkey rsa:4096 -sha256 -days 3650 -nodes \
  -keyout /app/controller_key.pem \
  -out /app/controller_cert.pem \
  -subj /CN=controller/O=mailcow \
  -addext subjectAltName=DNS:controller`

exec "$@"
