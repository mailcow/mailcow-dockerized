#!/usr/bin/env bash

set -o pipefail

if [[ "$(uname -r)" =~ ^4\.15\.0-60 ]]; then
  echo "DO NOT RUN mailcow ON THIS UBUNTU KERNEL!";
  echo "Please update to 5.x or use another distribution."
  exit 1
fi

if [[ "$(uname -r)" =~ ^4\.4\. ]]; then
  if grep -q Ubuntu <<< "$(uname -a)"; then
    echo "DO NOT RUN mailcow ON THIS UBUNTU KERNEL!";
    echo "Please update to linux-generic-hwe-16.04 by running \"apt-get install --install-recommends linux-generic-hwe-16.04\""
    exit 1
  fi
fi

if grep --help 2>&1 | head -n 1 | grep -q -i "busybox"; then echo "BusyBox grep detected, please install gnu grep, \"apk add --no-cache --upgrade grep\""; exit 1; fi
# This will also cover sort
if cp --help 2>&1 | head -n 1 | grep -q -i "busybox"; then echo "BusyBox cp detected, please install coreutils, \"apk add --no-cache --upgrade coreutils\""; exit 1; fi
if sed --help 2>&1 | head -n 1 | grep -q -i "busybox"; then echo "BusyBox sed detected, please install gnu sed, \"apk add --no-cache --upgrade sed\""; exit 1; fi

for bin in openssl curl docker git awk sha1sum grep cut; do
  if [[ -z $(which ${bin}) ]]; then echo "Cannot find ${bin}, exiting..."; exit 1; fi
done

# Check Docker Version (need at least 24.X)
docker_version=$(docker version --format '{{.Server.Version}}' | cut -d '.' -f 1)

if [[ $docker_version -lt 24 ]]; then
  echo -e "\e[31mCannot find Docker with a Version higher or equals 24.0.0\e[0m"
  echo -e "\e[33mmailcow needs a newer Docker version to work properly...\e[0m"
  echo -e "\e[31mPlease update your Docker installation... exiting\e[0m"
  exit 1
fi

if docker compose > /dev/null 2>&1; then
    if docker compose version --short | grep -e "^2." -e "^v2." > /dev/null 2>&1; then
      COMPOSE_VERSION=native
      echo -e "\e[33mFound Docker Compose Plugin (native).\e[0m"
      echo -e "\e[33mSetting the DOCKER_COMPOSE_VERSION Variable to native\e[0m"
      sleep 2
      echo -e "\e[33mNotice: You'll have to update this Compose Version via your Package Manager manually!\e[0m"
    else
      echo -e "\e[31mCannot find Docker Compose with a Version Higher than 2.X.X.\e[0m"
      echo -e "\e[31mPlease update/install it manually regarding to this doc site: https://docs.mailcow.email/install/\e[0m"
      exit 1
    fi
elif docker-compose > /dev/null 2>&1; then
  if ! [[ $(alias docker-compose 2> /dev/null) ]] ; then
    if docker-compose version --short | grep "^2." > /dev/null 2>&1; then
      COMPOSE_VERSION=standalone
      echo -e "\e[33mFound Docker Compose Standalone.\e[0m"
      echo -e "\e[33mSetting the DOCKER_COMPOSE_VERSION Variable to standalone\e[0m"
      sleep 2
      echo -e "\e[33mNotice: For an automatic update of docker-compose please use the update_compose.sh scripts located at the helper-scripts folder.\e[0m"
    else
      echo -e "\e[31mCannot find Docker Compose with a Version Higher than 2.X.X.\e[0m"
      echo -e "\e[31mPlease update/install manually regarding to this doc site: https://docs.mailcow.email/install/\e[0m"
      exit 1
    fi
  fi

else
  echo -e "\e[31mCannot find Docker Compose.\e[0m"
  echo -e "\e[31mPlease install it regarding to this doc site: https://docs.mailcow.email/install/\e[0m"
  exit 1
fi

detect_bad_asn() {
  echo -e "\e[33mDetecting if your IP is listed on Spamhaus Bad ASN List...\e[0m"
  response=$(curl --connect-timeout 15 --max-time 30 -s -o /dev/null -w "%{http_code}" "https://asn-check.mailcow.email")
  if [ "$response" -eq 503 ]; then
    if [ -z "$SPAMHAUS_DQS_KEY" ]; then
      echo -e "\e[33mYour server's public IP uses an AS that is blocked by Spamhaus to use their DNS public blocklists for Postfix.\e[0m"
      echo -e "\e[33mmailcow did not detected a value for the variable SPAMHAUS_DQS_KEY inside mailcow.conf!\e[0m"
      sleep 2
      echo ""
      echo -e "\e[33mTo use the Spamhaus DNS Blocklists again, you will need to create a FREE account for their Data Query Service (DQS) at: https://www.spamhaus.com/free-trial/sign-up-for-a-free-data-query-service-account\e[0m"
      echo -e "\e[33mOnce done, enter your DQS API key in mailcow.conf and mailcow will do the rest for you!\e[0m"
      echo ""
      sleep 2

    else
      echo -e "\e[33mYour server's public IP uses an AS that is blocked by Spamhaus to use their DNS public blocklists for Postfix.\e[0m"
      echo -e "\e[32mmailcow detected a Value for the variable SPAMHAUS_DQS_KEY inside mailcow.conf. Postfix will use DQS with the given API key...\e[0m"
    fi
  elif [ "$response" -eq 200 ]; then
    echo -e "\e[33mCheck completed! Your IP is \e[32mclean\e[0m"
  elif [ "$response" -eq 429 ]; then
    echo -e "\e[33mCheck completed! \e[31mYour IP seems to be rate limited on the ASN Check service... please try again later!\e[0m"
  else
    echo -e "\e[31mCheck failed! \e[0mMaybe a DNS or Network problem?\e[0m"
  fi
}

### If generate_config.sh is started with --dev or -d it will not check out nightly or master branch and will keep on the current branch
if [[ ${1} == "--dev" || ${1} == "-d" ]]; then
  SKIP_BRANCH=y
else
  SKIP_BRANCH=n
fi

if [ -f mailcow.conf ]; then
  read -r -p "A config file exists and will be overwritten, are you sure you want to continue? [y/N] " response
  case $response in
    [yY][eE][sS]|[yY])
      mv mailcow.conf mailcow.conf_backup
      chmod 600 mailcow.conf_backup
      ;;
    *)
      exit 1
    ;;
  esac
fi

echo "Press enter to confirm the detected value '[value]' where applicable or enter a custom value."
while [ -z "${MAILCOW_HOSTNAME}" ]; do
  read -p "Mail server hostname (FQDN) - this is not your mail domain, but your mail servers hostname: " -e MAILCOW_HOSTNAME
  DOTS=${MAILCOW_HOSTNAME//[^.]};
  if [ ${#DOTS} -lt 1 ]; then
    echo -e "\e[31mMAILCOW_HOSTNAME (${MAILCOW_HOSTNAME}) is not a FQDN!\e[0m"
    sleep 1
    echo "Please change it to a FQDN and redeploy the stack with docker(-)compose up -d"
    exit 1
  elif [[ "${MAILCOW_HOSTNAME: -1}" == "." ]]; then
    echo "MAILCOW_HOSTNAME (${MAILCOW_HOSTNAME}) is ending with a dot. This is not a valid FQDN!"
    exit 1
  elif [ ${#DOTS} -eq 1 ]; then
    echo -e "\e[33mMAILCOW_HOSTNAME (${MAILCOW_HOSTNAME}) does not contain a Subdomain. This is not fully tested and may cause issues.\e[0m"
    echo "Find more information about why this message exists here: https://github.com/mailcow/mailcow-dockerized/issues/1572"
    read -r -p "Do you want to proceed anyway? [y/N] " response
    if [[ "$response" =~ ^([yY][eE][sS]|[yY])+$ ]]; then
      echo "OK. Procceding."
    else
      echo "OK. Exiting."
      exit 1
    fi
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

MEM_TOTAL=$(awk '/MemTotal/ {print $2}' /proc/meminfo)

if [ -z "${SKIP_CLAMD}" ]; then
  if [ "${MEM_TOTAL}" -le "2621440" ]; then
    echo "Installed memory is <= 2.5 GiB. It is recommended to disable ClamAV to prevent out-of-memory situations."
    echo "ClamAV can be re-enabled by setting SKIP_CLAMD=n in mailcow.conf."
    read -r -p  "Do you want to disable ClamAV now? [Y/n] " response
    case $response in
      [nN][oO]|[nN])
        SKIP_CLAMD=n
        ;;
      *)
        SKIP_CLAMD=y
      ;;
    esac
  else
    SKIP_CLAMD=n
  fi
fi

if [[ ${SKIP_BRANCH} != y ]]; then
  echo "Which branch of mailcow do you want to use?"
  echo ""
  echo "Available Branches:"
  echo "- master branch (stable updates) | default, recommended [1]"
  echo "- nightly branch (unstable updates, testing) | not-production ready [2]"
  echo "- legacy branch (supported until February 2026) | deprecated, security updates only [3]"
  sleep 1

  while [ -z "${MAILCOW_BRANCH}" ]; do
    read -r -p  "Choose the Branch with it's number [1/2] " branch
    case $branch in
      [3])
        MAILCOW_BRANCH="legacy"
        ;;
      [2])
        MAILCOW_BRANCH="nightly"
        ;;
      *)
        MAILCOW_BRANCH="master"
      ;;
    esac
  done

  git fetch --all
  git checkout -f "$MAILCOW_BRANCH"

elif [[ ${SKIP_BRANCH} == y ]]; then
  echo -e "\033[33mEnabled Dev Mode.\033[0m"
  echo -e "\033[33mNot checking out a different branch!\033[0m"
  MAILCOW_BRANCH=$(git rev-parse --short $(git rev-parse @{upstream}))

else
  echo -e "\033[31mCould not determine branch input..."
  echo -e "\033[31mExiting."
  exit 1
fi

if [ ! -z "${MAILCOW_BRANCH}" ]; then
  git_branch=${MAILCOW_BRANCH}
fi

[ ! -f ./data/conf/rspamd/override.d/worker-controller-password.inc ] && echo '# Placeholder' > ./data/conf/rspamd/override.d/worker-controller-password.inc

cat << EOF > mailcow.conf
# ------------------------------
# mailcow web ui configuration
# ------------------------------
# example.org is _not_ a valid hostname, use a fqdn here.
# Default admin user is "admin"
# Default password is "moohoo"

MAILCOW_HOSTNAME=${MAILCOW_HOSTNAME}

# Password hash algorithm
# Only certain password hash algorithm are supported. For a fully list of supported schemes,
# see https://docs.mailcow.email/models/model-passwd/
MAILCOW_PASS_SCHEME=BLF-CRYPT

# ------------------------------
# SQL database configuration
# ------------------------------

DBNAME=mailcow
DBUSER=mailcow

# Please use long, random alphanumeric strings (A-Za-z0-9)

DBPASS=$(LC_ALL=C </dev/urandom tr -dc A-Za-z0-9 2> /dev/null | head -c 28)
DBROOT=$(LC_ALL=C </dev/urandom tr -dc A-Za-z0-9 2> /dev/null | head -c 28)

# ------------------------------
# REDIS configuration
# ------------------------------

REDISPASS=$(LC_ALL=C </dev/urandom tr -dc A-Za-z0-9 2> /dev/null | head -c 28)

# ------------------------------
# HTTP/S Bindings
# ------------------------------

# You should use HTTPS, but in case of SSL offloaded reverse proxies:
# Might be important: This will also change the binding within the container.
# If you use a proxy within Docker, point it to the ports you set below.
# Do _not_ use IP:PORT in HTTP(S)_BIND or HTTP(S)_PORT
# IMPORTANT: Do not use port 8081, 9081, 9082 or 65510!
# Example: HTTP_BIND=1.2.3.4
# For IPv4 leave it as it is: HTTP_BIND= & HTTPS_PORT=
# For IPv6 see https://docs.mailcow.email/post_installation/firststeps-ip_bindings/

HTTP_PORT=80
HTTP_BIND=

HTTPS_PORT=443
HTTPS_BIND=

# Redirect HTTP connections to HTTPS - y/n
HTTP_REDIRECT=n

# ------------------------------
# Other bindings
# ------------------------------
# You should leave that alone
# Format: 11.22.33.44:25 or 12.34.56.78:465 etc.

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
REDIS_PORT=127.0.0.1:7654

# Your timezone
# See https://en.wikipedia.org/wiki/List_of_tz_database_time_zones for a list of timezones
# Use the column named 'TZ identifier' + pay attention for the column named 'Notes'

TZ=${MAILCOW_TZ}

# Fixed project name
# Please use lowercase letters only

COMPOSE_PROJECT_NAME=mailcowdockerized

# Used Docker Compose version
# Switch here between native (compose plugin) and standalone
# For more informations take a look at the mailcow docs regarding the configuration options.
# Normally this should be untouched but if you decided to use either of those you can switch it manually here.
# Please be aware that at least one of those variants should be installed on your machine or mailcow will fail.

DOCKER_COMPOSE_VERSION=${COMPOSE_VERSION}

# Set this to "allow" to enable the anyone pseudo user. Disabled by default.
# When enabled, ACL can be created, that apply to "All authenticated users"
# This should probably only be activated on mail hosts, that are used exclusivly by one organisation.
# Otherwise a user might share data with too many other users.
ACL_ANYONE=disallow

# Garbage collector cleanup
# Deleted domains and mailboxes are moved to /var/vmail/_garbage/timestamp_sanitizedstring
# How long should objects remain in the garbage until they are being deleted? (value in minutes)
# Check interval is hourly

MAILDIR_GC_TIME=7200

# Additional SAN for the certificate
#
# You can use wildcard records to create specific names for every domain you add to mailcow.
# Example: Add domains "example.com" and "example.net" to mailcow, change ADDITIONAL_SAN to a value like:
#ADDITIONAL_SAN=imap.*,smtp.*
# This will expand the certificate to "imap.example.com", "smtp.example.com", "imap.example.net", "smtp.example.net"
# plus every domain you add in the future.
#
# You can also just add static names...
#ADDITIONAL_SAN=srv1.example.net
# ...or combine wildcard and static names:
#ADDITIONAL_SAN=imap.*,srv1.example.com
#

ADDITIONAL_SAN=

# Obtain certificates for autodiscover.* and autoconfig.* domains.
# This can be useful to switch off in case you are in a scenario where a reverse proxy already handles those.
# There are mixed scenarios where ports 80,443 are occupied and you do not want to share certs
# between services. So acme-mailcow obtains for maildomains and all web-things get handled
# in the reverse proxy.
AUTODISCOVER_SAN=y

# Additional server names for mailcow UI
#
# Specify alternative addresses for the mailcow UI to respond to
# This is useful when you set mail.* as ADDITIONAL_SAN and want to make sure mail.maildomain.com will always point to the mailcow UI.
# If the server name does not match a known site, Nginx decides by best-guess and may redirect users to the wrong web root.
# You can understand this as server_name directive in Nginx.
# Comma separated list without spaces! Example: ADDITIONAL_SERVER_NAMES=a.b.c,d.e.f

ADDITIONAL_SERVER_NAMES=

# Skip running ACME (acme-mailcow, Let's Encrypt certs) - y/n

SKIP_LETS_ENCRYPT=n

# Create seperate certificates for all domains - y/n
# this will allow adding more than 100 domains, but some email clients will not be able to connect with alternative hostnames
# see https://doc.dovecot.org/admin_manual/ssl/sni_support
ENABLE_SSL_SNI=n

# Skip IPv4 check in ACME container - y/n

SKIP_IP_CHECK=n

# Skip HTTP verification in ACME container - y/n

SKIP_HTTP_VERIFICATION=n

# Skip Unbound (DNS Resolver) Healthchecks (NOT Recommended!) - y/n

SKIP_UNBOUND_HEALTHCHECK=n

# Skip ClamAV (clamd-mailcow) anti-virus (Rspamd will auto-detect a missing ClamAV container) - y/n

SKIP_CLAMD=${SKIP_CLAMD}

# Skip SOGo: Will disable SOGo integration and therefore webmail, DAV protocols and ActiveSync support (experimental, unsupported, not fully implemented) - y/n

SKIP_SOGO=n

# Skip FTS (Fulltext Search) for Dovecot on low-memory, low-threaded systems or if you simply want to disable it.
# Dovecot inside mailcow use Flatcurve as FTS Backend.

SKIP_FTS=n

# Dovecot Indexing (FTS) Process maximum heap size in MB, there is no recommendation, please see Dovecot docs.
# Flatcurve (Xapian backend) is used as the FTS Indexer. It is supposed to be efficient in CPU and RAM consumption.
# However: Please always monitor your Resource consumption!

FTS_HEAP=128

# Controls how many processes the Dovecot indexing process can spawn at max.
# Too many indexing processes can use a lot of CPU and Disk I/O.
# Please visit: https://doc.dovecot.org/configuration_manual/service_configuration/#indexer-worker for more informations

FTS_PROCS=1

# Allow admins to log into SOGo as email user (without any password)

ALLOW_ADMIN_EMAIL_LOGIN=n

# Enable watchdog (watchdog-mailcow) to restart unhealthy containers

USE_WATCHDOG=y

# Send watchdog notifications by mail (sent from watchdog@MAILCOW_HOSTNAME)
# CAUTION:
# 1. You should use external recipients
# 2. Mails are sent unsigned (no DKIM)
# 3. If you use DMARC, create a separate DMARC policy ("v=DMARC1; p=none;" in _dmarc.MAILCOW_HOSTNAME)
# Multiple rcpts allowed, NO quotation marks, NO spaces

#WATCHDOG_NOTIFY_EMAIL=a@example.com,b@example.com,c@example.com
#WATCHDOG_NOTIFY_EMAIL=

# Send notifications to a webhook URL that receives a POST request with the content type "application/json".
# You can use this to send notifications to services like Discord, Slack and others.
#WATCHDOG_NOTIFY_WEBHOOK=https://discord.com/api/webhooks/XXXXXXXXXXXXXXXXXXX/XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX
# JSON body included in the webhook POST request. Needs to be in single quotes.
# Following variables are available: SUBJECT, BODY
#WATCHDOG_NOTIFY_WEBHOOK_BODY='{"username": "mailcow Watchdog", "content": "**${SUBJECT}**\n${BODY}"}'

# Notify about banned IP (includes whois lookup)
WATCHDOG_NOTIFY_BAN=n

# Send a notification when the watchdog is started.
WATCHDOG_NOTIFY_START=y

# Subject for watchdog mails. Defaults to "Watchdog ALERT" followed by the error message.
#WATCHDOG_SUBJECT=

# Checks if mailcow is an open relay. Requires a SAL. More checks will follow.
# https://www.servercow.de/mailcow?lang=en
# https://www.servercow.de/mailcow?lang=de
# No data is collected. Opt-in and anonymous.
# Will only work with unmodified mailcow setups.
WATCHDOG_EXTERNAL_CHECKS=n

# Enable watchdog verbose logging
WATCHDOG_VERBOSE=n

# Max log lines per service to keep in Redis logs

LOG_LINES=9999

# Internal IPv4 /24 subnet, format n.n.n (expands to n.n.n.0/24)
# Use private IPv4 addresses only, see https://en.wikipedia.org/wiki/Private_network#Private_IPv4_addresses

IPV4_NETWORK=172.22.1

# Internal IPv6 subnet in fc00::/7
# Use private IPv6 addresses only, see https://en.wikipedia.org/wiki/Private_network#Private_IPv6_addresses

IPV6_NETWORK=fd4d:6169:6c63:6f77::/64

# Use this IPv4 for outgoing connections (SNAT)

#SNAT_TO_SOURCE=

# Use this IPv6 for outgoing connections (SNAT)

#SNAT6_TO_SOURCE=

# Create or override an API key for the web UI
# You _must_ define API_ALLOW_FROM, which is a comma separated list of IPs
# An API key defined as API_KEY has read-write access
# An API key defined as API_KEY_READ_ONLY has read-only access
# Allowed chars for API_KEY and API_KEY_READ_ONLY: a-z, A-Z, 0-9, -
# You can define API_KEY and/or API_KEY_READ_ONLY

#API_KEY=
#API_KEY_READ_ONLY=
#API_ALLOW_FROM=172.22.1.1,127.0.0.1

# mail_home is ~/Maildir
MAILDIR_SUB=Maildir

# SOGo session timeout in minutes
SOGO_EXPIRE_SESSION=480

# DOVECOT_MASTER_USER and DOVECOT_MASTER_PASS must both be provided. No special chars.
# Empty by default to auto-generate master user and password on start.
# User expands to DOVECOT_MASTER_USER@mailcow.local
# LEAVE EMPTY IF UNSURE
DOVECOT_MASTER_USER=
# LEAVE EMPTY IF UNSURE
DOVECOT_MASTER_PASS=

# Let's Encrypt registration contact information
# Optional: Leave empty for none
# This value is only used on first order!
# Setting it at a later point will require the following steps:
# https://docs.mailcow.email/troubleshooting/debug-reset_tls/
ACME_CONTACT=

# WebAuthn device manufacturer verification
# After setting WEBAUTHN_ONLY_TRUSTED_VENDORS=y only devices from trusted manufacturers are allowed
# root certificates can be placed for validation under mailcow-dockerized/data/web/inc/lib/WebAuthn/rootCertificates
WEBAUTHN_ONLY_TRUSTED_VENDORS=n

# Spamhaus Data Query Service Key
# Optional: Leave empty for none
# Enter your key here if you are using a blocked ASN (OVH, AWS, Cloudflare e.g) for the unregistered Spamhaus Blocklist.
# If empty, it will completely disable Spamhaus blocklists if it detects that you are running on a server using a blocked AS.
# Otherwise it will work normally.
SPAMHAUS_DQS_KEY=

# Prevent netfilter from setting an iptables/nftables rule to isolate the mailcow docker network - y/n
# CAUTION: Disabling this may expose container ports to other neighbors on the same subnet, even if the ports are bound to localhost
DISABLE_NETFILTER_ISOLATION_RULE=n
EOF

mkdir -p data/assets/ssl

chmod 600 mailcow.conf

# copy but don't overwrite existing certificate
echo "Generating snake-oil certificate..."
# Making Willich more popular
openssl req -x509 -newkey rsa:4096 -keyout data/assets/ssl-example/key.pem -out data/assets/ssl-example/cert.pem -days 365 -subj "/C=DE/ST=NRW/L=Willich/O=mailcow/OU=mailcow/CN=${MAILCOW_HOSTNAME}" -sha256 -nodes
echo "Copying snake-oil certificate..."
cp -n -d data/assets/ssl-example/*.pem data/assets/ssl/

# Set app_info.inc.php
case ${git_branch} in
  master)
    mailcow_git_version=$(git describe --tags `git rev-list --tags --max-count=1`)
    ;;
  nightly)
    mailcow_git_version=$(git rev-parse --short $(git rev-parse @{upstream}))
    mailcow_last_git_version=""
    ;;
  legacy)
    mailcow_git_version=$(git rev-parse --short $(git rev-parse @{upstream}))
    mailcow_last_git_version=""
    ;;
  *)
    mailcow_git_version=$(git rev-parse --short HEAD)
    mailcow_last_git_version=""
    ;;
esac
# if [ ${git_branch} == "master" ]; then
#   mailcow_git_version=$(git describe --tags `git rev-list --tags --max-count=1`)
# elif [ ${git_branch} == "nightly" ]; then
#   mailcow_git_version=$(git rev-parse --short $(git rev-parse @{upstream}))
#   mailcow_last_git_version=""
# else
#   mailcow_git_version=$(git rev-parse --short HEAD)
#   mailcow_last_git_version=""
# fi

if [[ $SKIP_BRANCH != "y" ]]; then
mailcow_git_commit=$(git rev-parse origin/${git_branch})
mailcow_git_commit_date=$(git log -1 --format=%ci @{upstream} )
else
mailcow_git_commit=$(git rev-parse ${git_branch})
mailcow_git_commit_date=$(git log -1 --format=%ci @{upstream} )
git_branch=$(git rev-parse --abbrev-ref HEAD)
fi

if [ $? -eq 0 ]; then
  echo '<?php' > data/web/inc/app_info.inc.php
  echo '  $MAILCOW_GIT_VERSION="'$mailcow_git_version'";' >> data/web/inc/app_info.inc.php
  echo '  $MAILCOW_LAST_GIT_VERSION="";' >> data/web/inc/app_info.inc.php
  echo '  $MAILCOW_GIT_OWNER="mailcow";' >> data/web/inc/app_info.inc.php
  echo '  $MAILCOW_GIT_REPO="mailcow-dockerized";' >> data/web/inc/app_info.inc.php
  echo '  $MAILCOW_GIT_URL="https://github.com/mailcow/mailcow-dockerized";' >> data/web/inc/app_info.inc.php
  echo '  $MAILCOW_GIT_COMMIT="'$mailcow_git_commit'";' >> data/web/inc/app_info.inc.php
  echo '  $MAILCOW_GIT_COMMIT_DATE="'$mailcow_git_commit_date'";' >> data/web/inc/app_info.inc.php
  echo '  $MAILCOW_BRANCH="'$git_branch'";' >> data/web/inc/app_info.inc.php
  echo '  $MAILCOW_UPDATEDAT='$(date +%s)';' >> data/web/inc/app_info.inc.php
  echo '?>' >> data/web/inc/app_info.inc.php
else
  echo '<?php' > data/web/inc/app_info.inc.php
  echo '  $MAILCOW_GIT_VERSION="'$mailcow_git_version'";' >> data/web/inc/app_info.inc.php
  echo '  $MAILCOW_LAST_GIT_VERSION="";' >> data/web/inc/app_info.inc.php
  echo '  $MAILCOW_GIT_OWNER="mailcow";' >> data/web/inc/app_info.inc.php
  echo '  $MAILCOW_GIT_REPO="mailcow-dockerized";' >> data/web/inc/app_info.inc.php
  echo '  $MAILCOW_GIT_URL="https://github.com/mailcow/mailcow-dockerized";' >> data/web/inc/app_info.inc.php
  echo '  $MAILCOW_GIT_COMMIT="";' >> data/web/inc/app_info.inc.php
  echo '  $MAILCOW_GIT_COMMIT_DATE="";' >> data/web/inc/app_info.inc.php
  echo '  $MAILCOW_BRANCH="'$git_branch'";' >> data/web/inc/app_info.inc.php
  echo '  $MAILCOW_UPDATEDAT='$(date +%s)';' >> data/web/inc/app_info.inc.php
  echo '?>' >> data/web/inc/app_info.inc.php
  echo -e "\e[33mCannot determine current git repository version...\e[0m"
fi

detect_bad_asn
