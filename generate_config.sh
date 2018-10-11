#!/bin/bash

set -o pipefail

if grep --help 2>&1 | grep -q -i "busybox"; then
  echo "BusybBox grep detected, please install gnu grep, \"apk add --no-cache --upgrade grep\""
  exit 1
fi
if cp --help 2>&1 | grep -q -i "busybox"; then
  echo "BusybBox cp detected, please install coreutils, \"apk add --no-cache --upgrade coreutils\""
  exit 1
fi

if [ -f mailcow.conf ]; then
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

echo "Press enter to confirm the detected value '[value]' where applicable or enter a custom value."
while [ -z "${MAILCOW_HOSTNAME}" ]; do
  read -p "Hostname (FQDN): " -e MAILCOW_HOSTNAME
  DOTS=${MAILCOW_HOSTNAME//[^.]};
  if [ ${#DOTS} -lt 2 ] && [ ! -z ${MAILCOW_HOSTNAME} ]; then
    echo "${MAILCOW_HOSTNAME} is not a FQDN"
    MAILCOW_HOSTNAME=
  fi
done

if [ -a /etc/timezone ]; then
  DETECTED_TZ=$(cat /etc/timezone)
elif [ -a /etc/localtime ]; then
  DETECTED_TZ=$(readlink /etc/localtime|sed -n 's|^.*zoneinfo/||p')
fi

while [ -z "${MAILCOW_TZ}" ]; do
  if [ -z "${DETECTED_TZ}" ]; then
    read -p "Timezone: " -e MAILCOW_TZ
  else
    read -p "Timezone [${DETECTED_TZ}]: " -e MAILCOW_TZ
    [ -z "${MAILCOW_TZ}" ] && MAILCOW_TZ=${DETECTED_TZ}
  fi
done

[ ! -f ./data/conf/rspamd/override.d/worker-controller-password.inc ] && echo '# Placeholder' > ./data/conf/rspamd/override.d/worker-controller-password.inc

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
DBPASS=$(LC_ALL=C </dev/urandom tr -dc A-Za-z0-9 | head -c 28)
DBROOT=$(LC_ALL=C </dev/urandom tr -dc A-Za-z0-9 | head -c 28)

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
SQL_PORT=127.0.0.1:13306

# Your timezone
TZ=${MAILCOW_TZ}

# Fixed project name
COMPOSE_PROJECT_NAME=mailcowdockerized

# Garbage collector cleanup
# Deleted domains and mailboxes are moved to /var/vmail/_garbage/timestamp_sanitizedstring
# How long should objects remain in the garbage until they are being deleted? (value in minutes)
# Check interval is hourly
MAILDIR_GC_TIME=1440

# Additional SAN for the certificate
ADDITIONAL_SAN=

# Skip running ACME (acme-mailcow, Let's Encrypt certs) - y/n
SKIP_LETS_ENCRYPT=n

# Skip IPv4 check in ACME container - y/n
SKIP_IP_CHECK=n

# Skip ClamAV (clamd-mailcow) anti-virus (Rspamd will auto-detect a missing ClamAV container) - y/n
SKIP_CLAMD=n

# Enable watchdog (watchdog-mailcow) to restart unhealthy containers (experimental)
USE_WATCHDOG=n
# Send notifications by mail (no DKIM signature, sent from watchdog@MAILCOW_HOSTNAME)
#WATCHDOG_NOTIFY_EMAIL=

# Max log lines per service to keep in Redis logs
LOG_LINES=9999

# Internal IPv4 /24 subnet, format n.n.n. (expands to n.n.n.0/24)
IPV4_NETWORK=172.22.1

# Internal IPv6 subnet in fc00::/7
IPV6_NETWORK=fd4d:6169:6c63:6f77::/64

# Use this IPv4 for outgoing connections (SNAT)
#SNAT_TO_SOURCE=

# Use this IPv6 for outgoing connections (SNAT)
#SNAT6_TO_SOURCE=

# Disable IPv6
# mailcow-network will still be created as IPv6 enabled, all containers will be created
# without IPv6 support.
# Use 1 for disabled, 0 for enabled
SYSCTL_IPV6_DISABLED=0

# Create or override API key for web uI
# You _must_ define API_ALLOW_FROM, which is a comma separated list of IPs
# API_KEY allowed chars: a-z, A-Z, 0-9, -
#API_KEY=
#API_ALLOW_FROM=127.0.0.1,1.2.3.4

EOF

mkdir -p data/assets/ssl

# copy but don't overwrite existing certificate
cp -n data/assets/ssl-example/*.pem data/assets/ssl/
