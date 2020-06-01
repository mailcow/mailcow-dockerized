#!/usr/bin/env bash

POSTFIX=$(echo | openssl s_client -connect mail.79v.de:143 -starttls imap 2>/dev/null | openssl x509 -inform pem -noout -enddate | cut -d "=" -f 2)
DOVECOT=$(echo | openssl s_client -connect mail.79v.de:143 -starttls imap 2>/dev/null | openssl x509 -inform pem -noout -enddate | cut -d "=" -f 2)
NGINX=$(echo | openssl s_client -connect mail.79v.de:443 2>/dev/null | openssl x509 -inform pem -noout -enddate | cut -d "=" -f 2)
echo TLS expiry dates:
echo Postfix: ${POSTFIX}
echo Dovecot: ${DOVECOT}
echo Nginx: ${NGINX}
