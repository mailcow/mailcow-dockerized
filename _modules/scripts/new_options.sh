#!/usr/bin/env bash
# _modules/scripts/new_options.sh
# THIS SCRIPT IS DESIGNED TO BE RUNNING BY MAILCOW SCRIPTS ONLY!
# DO NOT, AGAIN, NOT TRY TO RUN THIS SCRIPT STANDALONE!!!!!!

adapt_new_options() {

  CONFIG_ARRAY=(
  "AUTODISCOVER_SAN"
  "SKIP_LETS_ENCRYPT"
  "SKIP_SOGO"
  "USE_WATCHDOG"
  "WATCHDOG_NOTIFY_EMAIL"
  "WATCHDOG_NOTIFY_WEBHOOK"
  "WATCHDOG_NOTIFY_WEBHOOK_BODY"
  "WATCHDOG_NOTIFY_BAN"
  "WATCHDOG_NOTIFY_START"
  "WATCHDOG_EXTERNAL_CHECKS"
  "WATCHDOG_SUBJECT"
  "SKIP_CLAMD"
  "SKIP_OLEFY"
  "SKIP_IP_CHECK"
  "ADDITIONAL_SAN"
  "DOVEADM_PORT"
  "IPV4_NETWORK"
  "IPV6_NETWORK"
  "LOG_LINES"
  "SNAT_TO_SOURCE"
  "SNAT6_TO_SOURCE"
  "COMPOSE_PROJECT_NAME"
  "DOCKER_COMPOSE_VERSION"
  "SQL_PORT"
  "API_KEY"
  "API_KEY_READ_ONLY"
  "API_ALLOW_FROM"
  "MAILDIR_GC_TIME"
  "MAILDIR_SUB"
  "ACL_ANYONE"
  "FTS_HEAP"
  "FTS_PROCS"
  "SKIP_FTS"
  "ENABLE_SSL_SNI"
  "ALLOW_ADMIN_EMAIL_LOGIN"
  "SKIP_HTTP_VERIFICATION"
  "SOGO_EXPIRE_SESSION"
  "REDIS_PORT"
  "REDISPASS"
  "DOVECOT_MASTER_USER"
  "DOVECOT_MASTER_PASS"
  "MAILCOW_PASS_SCHEME"
  "ADDITIONAL_SERVER_NAMES"
  "ACME_CONTACT"
  "WATCHDOG_VERBOSE"
  "WEBAUTHN_ONLY_TRUSTED_VENDORS"
  "SPAMHAUS_DQS_KEY"
  "SKIP_UNBOUND_HEALTHCHECK"
  "DISABLE_NETFILTER_ISOLATION_RULE"
  "HTTP_REDIRECT"
  "ENABLE_IPV6"
  )

  sed -i --follow-symlinks '$a\' mailcow.conf
  for option in ${CONFIG_ARRAY[@]}; do
    if grep -q "${option}" mailcow.conf; then
      continue
    fi

    echo "Adding new option \"${option}\" to mailcow.conf"

    case "${option}" in
        AUTODISCOVER_SAN)
            echo '# Obtain certificates for autodiscover.* and autoconfig.* domains.' >> mailcow.conf
            echo '# This can be useful to switch off in case you are in a scenario where a reverse proxy already handles those.' >> mailcow.conf
            echo '# There are mixed scenarios where ports 80,443 are occupied and you do not want to share certs' >> mailcow.conf
            echo '# between services. So acme-mailcow obtains for maildomains and all web-things get handled' >> mailcow.conf
            echo '# in the reverse proxy.' >> mailcow.conf
            echo 'AUTODISCOVER_SAN=y' >> mailcow.conf
            ;;

        DOCKER_COMPOSE_VERSION)
            echo "# Used Docker Compose version" >> mailcow.conf
            echo "# Switch here between native (compose plugin) and standalone" >> mailcow.conf
            echo "# For more informations take a look at the mailcow docs regarding the configuration options." >> mailcow.conf
            echo "# Normally this should be untouched but if you decided to use either of those you can switch it manually here." >> mailcow.conf
            echo "# Please be aware that at least one of those variants should be installed on your machine or mailcow will fail." >> mailcow.conf
            echo "" >> mailcow.conf
            echo "DOCKER_COMPOSE_VERSION=${DOCKER_COMPOSE_VERSION}" >> mailcow.conf
            ;;

        DOVEADM_PORT)
            echo "DOVEADM_PORT=127.0.0.1:19991" >> mailcow.conf
            ;;

        LOG_LINES)
            echo '# Max log lines per service to keep in Redis logs' >> mailcow.conf
            echo "LOG_LINES=9999" >> mailcow.conf
            ;;
        
        IPV4_NETWORK)
            echo '# Internal IPv4 /24 subnet, format n.n.n. (expands to n.n.n.0/24)' >> mailcow.conf
            echo "IPV4_NETWORK=172.22.1" >> mailcow.conf
            ;;
        IPV6_NETWORK)
            echo '# Internal IPv6 subnet in fc00::/7' >> mailcow.conf
            echo "IPV6_NETWORK=fd4d:6169:6c63:6f77::/64" >> mailcow.conf
            ;;
        SQL_PORT)
            echo '# Bind SQL to 127.0.0.1 on port 13306' >> mailcow.conf
            echo "SQL_PORT=127.0.0.1:13306" >> mailcow.conf
            ;;
        API_KEY)
            echo '# Create or override API key for web UI' >> mailcow.conf
            echo "#API_KEY=" >> mailcow.conf
            ;;
        API_KEY_READ_ONLY)
            echo '# Create or override read-only API key for web UI' >> mailcow.conf
            echo "#API_KEY_READ_ONLY=" >> mailcow.conf
            ;;
        API_ALLOW_FROM)
            echo '# Must be set for API_KEY to be active' >> mailcow.conf
            echo '# IPs only, no networks (networks can be set via UI)' >> mailcow.conf
            echo "#API_ALLOW_FROM=" >> mailcow.conf
            ;;
        SNAT_TO_SOURCE)
            echo '# Use this IPv4 for outgoing connections (SNAT)' >> mailcow.conf
            echo "#SNAT_TO_SOURCE=" >> mailcow.conf
            ;;
        SNAT6_TO_SOURCE)
            echo '# Use this IPv6 for outgoing connections (SNAT)' >> mailcow.conf
            echo "#SNAT6_TO_SOURCE=" >> mailcow.conf
            ;;
        MAILDIR_GC_TIME)
            echo '# Garbage collector cleanup' >> mailcow.conf
            echo '# Deleted domains and mailboxes are moved to /var/vmail/_garbage/timestamp_sanitizedstring' >> mailcow.conf
            echo '# How long should objects remain in the garbage until they are being deleted? (value in minutes)' >> mailcow.conf
            echo '# Check interval is hourly' >> mailcow.conf
            echo 'MAILDIR_GC_TIME=1440' >> mailcow.conf
            ;;
        ACL_ANYONE)
            echo '# Set this to "allow" to enable the anyone pseudo user. Disabled by default.' >> mailcow.conf
            echo '# When enabled, ACL can be created, that apply to "All authenticated users"' >> mailcow.conf
            echo '# This should probably only be activated on mail hosts, that are used exclusivly by one organisation.' >> mailcow.conf
            echo '# Otherwise a user might share data with too many other users.' >> mailcow.conf
            echo 'ACL_ANYONE=disallow' >> mailcow.conf
            ;;
        FTS_HEAP)
            echo '# Dovecot Indexing (FTS) Process maximum heap size in MB, there is no recommendation, please see Dovecot docs.' >> mailcow.conf
            echo '# Flatcurve is used as FTS Engine. It is supposed to be pretty efficient in CPU and RAM consumption.' >> mailcow.conf
            echo '# Please always monitor your Resource consumption!' >> mailcow.conf
            echo "FTS_HEAP=128" >> mailcow.conf
            ;;
        SKIP_FTS)
            echo '# Skip FTS (Fulltext Search) for Dovecot on low-memory, low-threaded systems or if you simply want to disable it.' >> mailcow.conf
            echo "# Dovecot inside mailcow use Flatcurve as FTS Backend." >> mailcow.conf
            echo "SKIP_FTS=y" >> mailcow.conf
            ;;
        FTS_PROCS)
            echo '# Controls how many processes the Dovecot indexing process can spawn at max.' >> mailcow.conf
            echo '# Too many indexing processes can use a lot of CPU and Disk I/O' >> mailcow.conf
            echo '# Please visit: https://doc.dovecot.org/configuration_manual/service_configuration/#indexer-worker for more informations' >> mailcow.conf
            echo "FTS_PROCS=1" >> mailcow.conf
            ;;
        ENABLE_SSL_SNI)
            echo '# Create seperate certificates for all domains - y/n' >> mailcow.conf
            echo '# this will allow adding more than 100 domains, but some email clients will not be able to connect with alternative hostnames' >> mailcow.conf
            echo '# see https://wiki.dovecot.org/SSL/SNIClientSupport' >> mailcow.conf
            echo "ENABLE_SSL_SNI=n" >> mailcow.conf
            ;;
        SKIP_SOGO)
            echo '# Skip SOGo: Will disable SOGo integration and therefore webmail, DAV protocols and ActiveSync support (experimental, unsupported, not fully implemented) - y/n' >> mailcow.conf
            echo "SKIP_SOGO=n" >> mailcow.conf
            ;;
        MAILDIR_SUB)
            echo '# MAILDIR_SUB defines a path in a users virtual home to keep the maildir in. Leave empty for updated setups.' >> mailcow.conf
            echo "#MAILDIR_SUB=Maildir" >> mailcow.conf
            echo "MAILDIR_SUB=" >> mailcow.conf
            ;;
        WATCHDOG_NOTIFY_WEBHOOK)
            echo '# Send notifications to a webhook URL that receives a POST request with the content type "application/json".' >> mailcow.conf
            echo '# You can use this to send notifications to services like Discord, Slack and others.' >> mailcow.conf
            echo '#WATCHDOG_NOTIFY_WEBHOOK=https://discord.com/api/webhooks/XXXXXXXXXXXXXXXXXXX/XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX' >> mailcow.conf
            ;;
        WATCHDOG_NOTIFY_WEBHOOK_BODY)
            echo '# JSON body included in the webhook POST request. Needs to be in single quotes.' >> mailcow.conf
            echo '# Following variables are available: SUBJECT, BODY' >> mailcow.conf
            WEBHOOK_BODY='{"username": "mailcow Watchdog", "content": "**${SUBJECT}**\n${BODY}"}'
            echo "#WATCHDOG_NOTIFY_WEBHOOK_BODY='${WEBHOOK_BODY}'" >> mailcow.conf
            ;;
        WATCHDOG_NOTIFY_BAN)
            echo '# Notify about banned IP. Includes whois lookup.' >> mailcow.conf
            echo "WATCHDOG_NOTIFY_BAN=y" >> mailcow.conf
            ;;
        WATCHDOG_NOTIFY_START)
            echo '# Send a notification when the watchdog is started.' >> mailcow.conf
            echo "WATCHDOG_NOTIFY_START=y" >> mailcow.conf
            ;;
        WATCHDOG_SUBJECT)
            echo '# Subject for watchdog mails. Defaults to "Watchdog ALERT" followed by the error message.' >> mailcow.conf
            echo "#WATCHDOG_SUBJECT=" >> mailcow.conf
            ;;
        WATCHDOG_EXTERNAL_CHECKS)
            echo '# Checks if mailcow is an open relay. Requires a SAL. More checks will follow.' >> mailcow.conf
            echo '# No data is collected. Opt-in and anonymous.' >> mailcow.conf
            echo '# Will only work with unmodified mailcow setups.' >> mailcow.conf
            echo "WATCHDOG_EXTERNAL_CHECKS=n" >> mailcow.conf
            ;;
        SOGO_EXPIRE_SESSION)
            echo '# SOGo session timeout in minutes' >> mailcow.conf
            echo "SOGO_EXPIRE_SESSION=480" >> mailcow.conf
            ;;
        REDIS_PORT)
            echo "REDIS_PORT=127.0.0.1:7654" >> mailcow.conf
            ;;
        DOVECOT_MASTER_USER)
            echo '# DOVECOT_MASTER_USER and _PASS must _both_ be provided. No special chars.' >> mailcow.conf
            echo '# Empty by default to auto-generate master user and password on start.' >> mailcow.conf
            echo '# User expands to DOVECOT_MASTER_USER@mailcow.local' >> mailcow.conf
            echo '# LEAVE EMPTY IF UNSURE' >> mailcow.conf
            echo "DOVECOT_MASTER_USER=" >> mailcow.conf
            ;;
        DOVECOT_MASTER_PASS)
            echo '# LEAVE EMPTY IF UNSURE' >> mailcow.conf
            echo "DOVECOT_MASTER_PASS=" >> mailcow.conf
            ;;
        MAILCOW_PASS_SCHEME)
            echo '# Password hash algorithm' >> mailcow.conf
            echo '# Only certain password hash algorithm are supported. For a fully list of supported schemes,' >> mailcow.conf
            echo '# see https://docs.mailcow.email/models/model-passwd/' >> mailcow.conf
            echo "MAILCOW_PASS_SCHEME=BLF-CRYPT" >> mailcow.conf
            ;;
        ADDITIONAL_SERVER_NAMES)
            echo '# Additional server names for mailcow UI' >> mailcow.conf
            echo '#' >> mailcow.conf
            echo '# Specify alternative addresses for the mailcow UI to respond to' >> mailcow.conf
            echo '# This is useful when you set mail.* as ADDITIONAL_SAN and want to make sure mail.maildomain.com will always point to the mailcow UI.' >> mailcow.conf
            echo '# If the server name does not match a known site, Nginx decides by best-guess and may redirect users to the wrong web root.' >> mailcow.conf
            echo '# You can understand this as server_name directive in Nginx.' >> mailcow.conf
            echo '# Comma separated list without spaces! Example: ADDITIONAL_SERVER_NAMES=a.b.c,d.e.f' >> mailcow.conf
            echo 'ADDITIONAL_SERVER_NAMES=' >> mailcow.conf
            ;;
        ACME_CONTACT)
            echo '# Lets Encrypt registration contact information' >> mailcow.conf
            echo '# Optional: Leave empty for none' >> mailcow.conf
            echo '# This value is only used on first order!' >> mailcow.conf
            echo '# Setting it at a later point will require the following steps:' >> mailcow.conf
            echo '# https://docs.mailcow.email/troubleshooting/debug-reset_tls/' >> mailcow.conf
            echo 'ACME_CONTACT=' >> mailcow.conf
            ;;
        WEBAUTHN_ONLY_TRUSTED_VENDORS)
            echo "# WebAuthn device manufacturer verification" >> mailcow.conf
            echo '# After setting WEBAUTHN_ONLY_TRUSTED_VENDORS=y only devices from trusted manufacturers are allowed' >> mailcow.conf
            echo '# root certificates can be placed for validation under mailcow-dockerized/data/web/inc/lib/WebAuthn/rootCertificates' >> mailcow.conf
            echo 'WEBAUTHN_ONLY_TRUSTED_VENDORS=n' >> mailcow.conf
            ;;
        SPAMHAUS_DQS_KEY)
            echo "# Spamhaus Data Query Service Key" >> mailcow.conf
            echo '# Optional: Leave empty for none' >> mailcow.conf
            echo '# Enter your key here if you are using a blocked ASN (OVH, AWS, Cloudflare e.g) for the unregistered Spamhaus Blocklist.' >> mailcow.conf
            echo '# If empty, it will completely disable Spamhaus blocklists if it detects that you are running on a server using a blocked AS.' >> mailcow.conf
            echo '# Otherwise it will work as usual.' >> mailcow.conf
            echo 'SPAMHAUS_DQS_KEY=' >> mailcow.conf
            ;;
        WATCHDOG_VERBOSE)
            echo '# Enable watchdog verbose logging' >> mailcow.conf
            echo 'WATCHDOG_VERBOSE=n' >> mailcow.conf
            ;;
        SKIP_UNBOUND_HEALTHCHECK)
            echo '# Skip Unbound (DNS Resolver) Healthchecks (NOT Recommended!) - y/n' >> mailcow.conf
            echo 'SKIP_UNBOUND_HEALTHCHECK=n' >> mailcow.conf
            ;;
        DISABLE_NETFILTER_ISOLATION_RULE)
            echo '# Prevent netfilter from setting an iptables/nftables rule to isolate the mailcow docker network - y/n' >> mailcow.conf
            echo '# CAUTION: Disabling this may expose container ports to other neighbors on the same subnet, even if the ports are bound to localhost' >> mailcow.conf
            echo 'DISABLE_NETFILTER_ISOLATION_RULE=n' >> mailcow.conf
            ;;
        HTTP_REDIRECT)
            echo '# Redirect HTTP connections to HTTPS - y/n' >> mailcow.conf
            echo 'HTTP_REDIRECT=n' >> mailcow.conf
            ;;
        ENABLE_IPV6)
            echo '# IPv6 Controller Section' >> mailcow.conf
            echo '# This variable controls the usage of IPv6 within mailcow.' >> mailcow.conf
            echo '# Can either be true or false | Defaults to true' >> mailcow.conf
            echo '# WARNING: MAKE SURE TO PROPERLY CONFIGURE IPv6 ON YOUR HOST FIRST BEFORE ENABLING THIS AS FAULTY CONFIGURATIONS CAN LEAD TO OPEN RELAYS!' >> mailcow.conf
            echo '# A COMPLETE DOCKER STACK REBUILD (compose down && compose up -d) IS NEEDED TO APPLY THIS.' >> mailcow.conf
            echo ENABLE_IPV6=${IPV6_BOOL} >> mailcow.conf
            ;;
    
        SKIP_CLAMD)
            echo '# Skip ClamAV (clamd-mailcow) anti-virus (Rspamd will auto-detect a missing ClamAV container) - y/n' >> mailcow.conf
            echo 'SKIP_CLAMD=n' >> mailcow.conf
            ;;

        SKIP_OLEFY)
            echo '# Skip Olefy (olefy-mailcow) anti-virus for Office documents (Rspamd will auto-detect a missing Olefy container) - y/n' >> mailcow.conf
            echo 'SKIP_OLEFY=n' >> mailcow.conf
            ;;
        
        REDISPASS)
            echo "REDISPASS=$(LC_ALL=C </dev/urandom tr -dc A-Za-z0-9 2>/dev/null | head -c 28)" >> mailcow.conf
            ;;
                  
        *)
            echo "${option}=" >> mailcow.conf
            ;;
    esac
  done
}