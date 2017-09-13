#!/bin/bash

if [[ -f mailcow.conf ]]; then
  read -r -p "A config file exists and will be overwritten, are you sure you want to contine? [y/N] " response
  case $response in
    [yY][eE][sS]|[yY])
      mv mailcow.conf mailcow.conf_backup
      ;;
    *)
      exit 1
    ;;
  esac
fi

if [ -z "$MAILCOW_HOSTNAME" ]; then
  read -p "Hostname (FQDN): " -ei "mx.example.org" MAILCOW_HOSTNAME
fi

[[ -a /etc/timezone ]] && TZ=$(cat /etc/timezone) || [[ -a /etc/localtime ]] && TZ=$(readlink /etc/localtime|sed -n 's|^.*zoneinfo/||p')
if [ -z "$TZ" ]; then
  read -p "Timezone: " -ei "Europe/Berlin" TZ
else
  read -p "Timezone: " -ei ${TZ} TZ
fi

cat << EOF > mailcow.conf
# ------------------------------
# mailcow web ui configuration
# ------------------------------
# example.org is _not_ a valid hostname, use a fqdn here.
# Default admin user is "admin"
# Default password is "moohoo"
MAILCOW_HOSTNAME=${MAILCOW_HOSTNAME}

# ------------------------------
# SQL database configuration
# ------------------------------
DBNAME=mailcow
DBUSER=mailcow

# Please use long, random alphanumeric strings (A-Za-z0-9)
DBPASS=$(</dev/urandom tr -dc A-Za-z0-9 | head -c 28)
DBROOT=$(</dev/urandom tr -dc A-Za-z0-9 | head -c 28)

# ------------------------------
# HTTP/S Bindings
# ------------------------------

# You should use HTTPS, but in case of SSL offloaded reverse proxies:
HTTP_PORT=80
HTTP_BIND=0.0.0.0

HTTPS_PORT=443
HTTPS_BIND=0.0.0.0

# ------------------------------
# Other bindings
# ------------------------------
# You should leave that alone
# Format: 11.22.33.44:25 or 0.0.0.0:465 etc.
# Do _not_ use IP:PORT in HTTP(S)_BIND or HTTP(S)_PORT

SMTP_PORT=25
SMTPS_PORT=465
SUBMISSION_PORT=587
IMAP_PORT=143
IMAPS_PORT=993
POP_PORT=110
POPS_PORT=995
SIEVE_PORT=4190
DOVEADM_PORT=127.0.0.1:19991

# Your timezone
TZ=${TZ}

# Fixed project name
COMPOSE_PROJECT_NAME=mailcow-dockerized

# Additional SAN for the certificate
ADDITIONAL_SAN=

# To never run acme-mailcow for Let's Encrypt, set this to y
SKIP_LETS_ENCRYPT=n

# Skip IPv4 check in ACME container
SKIP_IP_CHECK=n

# To never run fail2ban-mailcow
SKIP_FAIL2BAN=n

# To never run clamd-mailcow
SKIP_CLAMD=n

EOF

mkdir -p data/assets/ssl

# copy but don't overwrite existing certificate
cp -n data/assets/ssl-example/*.pem data/assets/ssl/
