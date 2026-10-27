#!/bin/bash

`openssl req -x509 -newkey rsa:4096 -sha256 -days 3650 -nodes \
  -keyout /app/dockerapi_key.pem \
  -out /app/dockerapi_cert.pem \
  -subj /CN=dockerapi/O=mailcow \
  -addext subjectAltName=DNS:dockerapi`

exec "$@"
