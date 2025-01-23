#!/usr/bin/env bash

############## Begin Function Section ##############

check_online_status() {
  CHECK_ONLINE_DOMAINS=('https://github.com' 'https://hub.docker.com')
  for domain in "${CHECK_ONLINE_DOMAINS[@]}"; do
    if timeout 6 curl --head --silent --output /dev/null ${domain}; then
      return 0
    fi
  done
  return 1
}

prefetch_images() {
  [[ -z ${BRANCH} ]] && { echo -e "\e[33m\nUnknown branch...\e[0m"; exit 1; }
  git fetch origin #${BRANCH}
  while read image; do
    if [[ "${image}" == "robbertkl/ipv6nat" ]]; then
      if ! grep -qi "ipv6nat-mailcow" docker-compose.yml || grep -qi "enable_ipv6: false" docker-compose.yml; then
        continue
      fi
    fi
    RET_C=0
    until docker pull "${image}"; do
      RET_C=$((RET_C + 1))
      echo -e "\e[33m\nError pulling $image, retrying...\e[0m"
      [ ${RET_C} -gt 3 ] && { echo -e "\e[31m\nToo many failed retries, exiting\e[0m"; exit 1; }
      sleep 1
    done
  done < <(git show "origin/${BRANCH}:docker-compose.yml" | grep "image:" | awk '{ gsub("image:","", $3); print $2 }')
}

docker_garbage() {
  SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
  IMGS_TO_DELETE=()

  declare -A IMAGES_INFO
  COMPOSE_IMAGES=($(grep -oP "image: \Kmailcow.+" "${SCRIPT_DIR}/docker-compose.yml"))

  for existing_image in $(docker images --format "{{.ID}}:{{.Repository}}:{{.Tag}}" | grep 'mailcow/'); do
      ID=$(echo "$existing_image" | cut -d ':' -f 1)
      REPOSITORY=$(echo "$existing_image" | cut -d ':' -f 2)
      TAG=$(echo "$existing_image" | cut -d ':' -f 3)

      if [[ " ${COMPOSE_IMAGES[@]} " =~ " ${REPOSITORY}:${TAG} " ]]; then
          continue
      else
          IMGS_TO_DELETE+=("$ID")
          IMAGES_INFO["$ID"]="$REPOSITORY:$TAG"
      fi
  done

  if [[ ! -z ${IMGS_TO_DELETE[*]} ]]; then
      echo "The following unused mailcow images were found:"
      for id in "${IMGS_TO_DELETE[@]}"; do
          echo "    ${IMAGES_INFO[$id]} ($id)"
      done

      if [ ! $FORCE ]; then
          read -r -p "Do you want to delete them to free up some space? [y/N] " response
          if [[ "$response" =~ ^([yY][eE][sS]|[yY])+$ ]]; then
              docker rmi ${IMGS_TO_DELETE[*]}
          else
              echo "OK, skipped."
          fi
      else
          echo "Running in forced mode! Force removing old mailcow images..."
          docker rmi ${IMGS_TO_DELETE[*]}
      fi
      echo -e "\e[32mFurther cleanup...\e[0m"
      echo "If you want to cleanup further garbage collected by Docker, please make sure all containers are up and running before cleaning your system by executing \"docker system prune\""
  fi
}

in_array() {
  local e match="$1"
  shift
  for e; do [[ "$e" == "$match" ]] && return 0; done
  return 1
}

migrate_docker_nat() {
  NAT_CONFIG='{"ipv6":true,"fixed-cidr-v6":"fd00:dead:beef:c0::/80","experimental":true,"ip6tables":true}'
  # Min Docker version
  DOCKERV_REQ=20.10.2
  # Current Docker version
  DOCKERV_CUR=$(docker version -f '{{.Server.Version}}')
  if grep -qi "ipv6nat-mailcow" docker-compose.yml && grep -qi "enable_ipv6: true" docker-compose.yml; then
    echo -e "\e[32mNative IPv6 implementation available.\e[0m"
    echo "This will enable experimental features in the Docker daemon and configure Docker to do the IPv6 NATing instead of ipv6nat-mailcow."
    echo '!!! This step is recommended !!!'
    echo "mailcow will try to roll back the changes if starting Docker fails after modifying the daemon.json configuration file."
    read -r -p "Should we try to enable the native IPv6 implementation in Docker now (recommended)? [y/N] " dockernatresponse
    if [[ ! "${dockernatresponse}" =~ ^([yY][eE][sS]|[yY])+$ ]]; then
      echo "OK, skipping this step."
      return 0
    fi
  fi
  # Sort versions and check if we are running a newer or equal version to req
  if [ $(printf "${DOCKERV_REQ}\n${DOCKERV_CUR}" | sort -V | tail -n1) == "${DOCKERV_CUR}" ]; then
    # If Dockerd daemon json exists
    if [ -s /etc/docker/daemon.json ]; then
      IFS=',' read -r -a dockerconfig <<< $(cat /etc/docker/daemon.json | tr -cd '[:alnum:],')
      if ! in_array ipv6true "${dockerconfig[@]}" || \
        ! in_array experimentaltrue "${dockerconfig[@]}" || \
        ! in_array ip6tablestrue "${dockerconfig[@]}" || \
        ! grep -qi "fixed-cidr-v6" /etc/docker/daemon.json; then
          echo -e "\e[33mWarning:\e[0m You seem to have modified the /etc/docker/daemon.json configuration by yourself and not fully/correctly activated the native IPv6 NAT implementation."
          echo "You will need to merge your existing configuration manually or fix/delete the existing daemon.json configuration before trying the update process again."
          echo -e "Please merge the following content and restart the Docker daemon:\n"
          echo "${NAT_CONFIG}"
          return 1
      fi
    else
      echo "Working on IPv6 NAT, please wait..."
      echo "${NAT_CONFIG}" > /etc/docker/daemon.json
      ip6tables -F -t nat
      [[ -e /etc/rc.conf ]] && rc-service docker restart || systemctl restart docker.service
      if [[ $? -ne 0 ]]; then
        echo -e "\e[31mError:\e[0m Failed to activate IPv6 NAT! Reverting and exiting."
        rm /etc/docker/daemon.json
        if [[ -e /etc/rc.conf ]]; then
          rc-service docker restart
        else
          systemctl reset-failed docker.service
          systemctl restart docker.service
        fi
        return 1
      fi
    fi
    # Removing legacy container
    sed -i '/ipv6nat-mailcow:$/,/^$/d' docker-compose.yml
    if [ -s docker-compose.override.yml ]; then
        sed -i '/ipv6nat-mailcow:$/,/^$/d' docker-compose.override.yml
        if [[ "$(cat docker-compose.override.yml | sed '/^\s*$/d' | wc -l)" == "2" ]]; then
            mv docker-compose.override.yml docker-compose.override.yml_backup
        fi
    fi
    echo -e "\e[32mGreat! \e[0mNative IPv6 NAT is active.\e[0m"
  else
    echo -e "\e[31mPlease upgrade Docker to version ${DOCKERV_REQ} or above.\e[0m"
    return 0
  fi
}

remove_obsolete_nginx_ports() {
    # Removing obsolete docker-compose.override.yml
    for override in docker-compose.override.yml docker-compose.override.yaml; do
    if [ -s $override ] ; then
        if cat $override | grep nginx-mailcow > /dev/null 2>&1; then
          if cat $override | grep -E '(\[::])' > /dev/null 2>&1; then
            if cat $override | grep -w 80:80 > /dev/null 2>&1 && cat $override | grep -w 443:443 > /dev/null 2>&1 ; then
              echo -e "\e[33mBacking up ${override} to preserve custom changes...\e[0m"
              echo -e "\e[33m!!! Manual Merge needed (if other overrides are set) !!!\e[0m"
              sleep 3
              cp $override ${override}_backup
              sed -i '/nginx-mailcow:$/,/^$/d' $override
              echo -e "\e[33mRemoved obsolete NGINX IPv6 Bind from original override File.\e[0m"
                if [[ "$(cat $override | sed '/^\s*$/d' | wc -l)" == "2" ]]; then
                  mv $override ${override}_empty
                  echo -e "\e[31m${override} is empty. Renamed it to ensure mailcow is startable.\e[0m"
                fi
            fi
          fi
        fi
    fi
    done
}

detect_docker_compose_command(){
if ! [[ "${DOCKER_COMPOSE_VERSION}" =~ ^(native|standalone)$ ]]; then
  if docker compose > /dev/null 2>&1; then
      if docker compose version --short | grep -e "^2." -e "^v2." > /dev/null 2>&1; then
        DOCKER_COMPOSE_VERSION=native
        COMPOSE_COMMAND="docker compose"
        echo -e "\e[33mFound Docker Compose Plugin (native).\e[0m"
        echo -e "\e[33mSetting the DOCKER_COMPOSE_VERSION Variable to native\e[0m"
        sed -i 's/^DOCKER_COMPOSE_VERSION=.*/DOCKER_COMPOSE_VERSION=native/' "$SCRIPT_DIR/mailcow.conf"
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
        DOCKER_COMPOSE_VERSION=standalone
        COMPOSE_COMMAND="docker-compose"
        echo -e "\e[33mFound Docker Compose Standalone.\e[0m"
        echo -e "\e[33mSetting the DOCKER_COMPOSE_VERSION Variable to standalone\e[0m"
        sed -i 's/^DOCKER_COMPOSE_VERSION=.*/DOCKER_COMPOSE_VERSION=standalone/' "$SCRIPT_DIR/mailcow.conf"
        sleep 2
        echo -e "\e[33mNotice: For an automatic update of docker-compose please use the update_compose.sh scripts located at the helper-scripts folder.\e[0m"
      else
        echo -e "\e[31mCannot find Docker Compose with a Version Higher than 2.X.X.\e[0m"
        echo -e "\e[31mPlease update/install regarding to this doc site: https://docs.mailcow.email/install/\e[0m"
        exit 1
      fi
    fi

  else
    echo -e "\e[31mCannot find Docker Compose.\e[0m"
    echo -e "\e[31mPlease install it regarding to this doc site: https://docs.mailcow.email/install/\e[0m"
    exit 1
  fi

elif [ "${DOCKER_COMPOSE_VERSION}" == "native" ]; then
  COMPOSE_COMMAND="docker compose"
  # Check if Native Compose works and has not been deleted
  if ! $COMPOSE_COMMAND > /dev/null 2>&1; then
    # IF it not exists/work anymore try the other command
    COMPOSE_COMMAND="docker-compose"
    if ! $COMPOSE_COMMAND > /dev/null 2>&1 || ! $COMPOSE_COMMAND --version | grep "^2." > /dev/null 2>&1; then
      # IF it cannot find Standalone in > 2.X, then script stops
      echo -e "\e[31mCannot find Docker Compose or the Version is lower then 2.X.X.\e[0m"
      echo -e "\e[31mPlease install it regarding to this doc site: https://docs.mailcow.email/install/\e[0m"
      exit 1
    fi
      # If it finds the standalone Plugin it will use this instead and change the mailcow.conf Variable accordingly
      echo -e "\e[31mFound different Docker Compose Version then declared in mailcow.conf!\e[0m"
      echo -e "\e[31mSetting the DOCKER_COMPOSE_VERSION Variable from native to standalone\e[0m"
      sed -i 's/^DOCKER_COMPOSE_VERSION=.*/DOCKER_COMPOSE_VERSION=standalone/' "$SCRIPT_DIR/mailcow.conf"
      sleep 2
  fi


elif [ "${DOCKER_COMPOSE_VERSION}" == "standalone" ]; then
  COMPOSE_COMMAND="docker-compose"
  # Check if Standalone Compose works and has not been deleted
  if ! $COMPOSE_COMMAND > /dev/null 2>&1 && ! $COMPOSE_COMMAND --version > /dev/null 2>&1 | grep "^2." > /dev/null 2>&1; then
    # IF it not exists/work anymore try the other command
    COMPOSE_COMMAND="docker compose"
    if ! $COMPOSE_COMMAND > /dev/null 2>&1; then
      # IF it cannot find Native in > 2.X, then script stops
      echo -e "\e[31mCannot find Docker Compose.\e[0m"
      echo -e "\e[31mPlease install it regarding to this doc site: https://docs.mailcow.email/install/\e[0m"
      exit 1
    fi
      # If it finds the native Plugin it will use this instead and change the mailcow.conf Variable accordingly
      echo -e "\e[31mFound different Docker Compose Version then declared in mailcow.conf!\e[0m"
      echo -e "\e[31mSetting the DOCKER_COMPOSE_VERSION Variable from standalone to native\e[0m"
      sed -i 's/^DOCKER_COMPOSE_VERSION=.*/DOCKER_COMPOSE_VERSION=native/' "$SCRIPT_DIR/mailcow.conf"
      sleep 2
  fi
fi
}

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

fix_broken_dnslist_conf() {

# Fixing issue: #6143. To be removed in a later patch

  local file="${SCRIPT_DIR}/data/conf/postfix/dns_blocklists.cf"
    # Check if the file exists
  if [[ ! -f "$file" ]]; then
      return 1
  fi

  # Check if the file contains the autogenerated comment
  if grep -q "# Autogenerated by mailcow" "$file"; then
      # Ask the user if custom changes were made
      echo -e "\e[91mWARNING!!! \e[31mAn old version of dns_blocklists.cf has been detected which may cause a broken postfix upon startup (see: https://github.com/mailcow/mailcow-dockerized/issues/6143)...\e[0m"
      echo -e "\e[31mIf you have any custom settings in there you might copy it away and adapt the changes after the file is regenerated...\e[0m"
      read -p "Do you want to delete the file now and let mailcow regenerate it properly? [y/n]" response
      if [[ "${response}" =~ ^([yY][eE][sS]|[yY])+$ ]]; then
        rm "$file"
        echo -e "\e[32mdns_blocklists.cf has been deleted and will be properly regenerated"
        return 0
      else
        echo -e "\e[35mOk, not deleting it! Please make sure you take a look at postfix upon start then..."
        return 2
      fi
  fi

}

adapt_new_options() {

  CONFIG_ARRAY=(
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
  )

  sed -i --follow-symlinks '$a\' mailcow.conf
  for option in ${CONFIG_ARRAY[@]}; do
    if [[ ${option} == "ADDITIONAL_SAN" ]]; then
      if ! grep -q ${option} mailcow.conf; then
        echo "Adding new option \"${option}\" to mailcow.conf"
        echo "${option}=" >> mailcow.conf
      fi
    elif [[ ${option} == "COMPOSE_PROJECT_NAME" ]]; then
      if ! grep -q ${option} mailcow.conf; then
        echo "Adding new option \"${option}\" to mailcow.conf"
        echo "COMPOSE_PROJECT_NAME=mailcowdockerized" >> mailcow.conf
      fi
    elif [[ ${option} == "DOCKER_COMPOSE_VERSION" ]]; then
      if ! grep -q ${option} mailcow.conf; then
        echo "Adding new option \"${option}\" to mailcow.conf"
        echo "# Used Docker Compose version" >> mailcow.conf
        echo "# Switch here between native (compose plugin) and standalone" >> mailcow.conf
        echo "# For more informations take a look at the mailcow docs regarding the configuration options." >> mailcow.conf
        echo "# Normally this should be untouched but if you decided to use either of those you can switch it manually here." >> mailcow.conf
        echo "# Please be aware that at least one of those variants should be installed on your maschine or mailcow will fail." >> mailcow.conf
        echo "" >> mailcow.conf
        echo "DOCKER_COMPOSE_VERSION=${DOCKER_COMPOSE_VERSION}" >> mailcow.conf
      fi
    elif [[ ${option} == "DOVEADM_PORT" ]]; then
      if ! grep -q ${option} mailcow.conf; then
        echo "Adding new option \"${option}\" to mailcow.conf"
        echo "DOVEADM_PORT=127.0.0.1:19991" >> mailcow.conf
      fi
    elif [[ ${option} == "WATCHDOG_NOTIFY_EMAIL" ]]; then
      if ! grep -q ${option} mailcow.conf; then
        echo "Adding new option \"${option}\" to mailcow.conf"
        echo "WATCHDOG_NOTIFY_EMAIL=" >> mailcow.conf
      fi
    elif [[ ${option} == "LOG_LINES" ]]; then
      if ! grep -q ${option} mailcow.conf; then
        echo "Adding new option \"${option}\" to mailcow.conf"
        echo '# Max log lines per service to keep in Redis logs' >> mailcow.conf
        echo "LOG_LINES=9999" >> mailcow.conf
      fi
    elif [[ ${option} == "IPV4_NETWORK" ]]; then
      if ! grep -q ${option} mailcow.conf; then
        echo "Adding new option \"${option}\" to mailcow.conf"
        echo '# Internal IPv4 /24 subnet, format n.n.n. (expands to n.n.n.0/24)' >> mailcow.conf
        echo "IPV4_NETWORK=172.22.1" >> mailcow.conf
      fi
    elif [[ ${option} == "IPV6_NETWORK" ]]; then
      if ! grep -q ${option} mailcow.conf; then
        echo "Adding new option \"${option}\" to mailcow.conf"
        echo '# Internal IPv6 subnet in fc00::/7' >> mailcow.conf
        echo "IPV6_NETWORK=fd4d:6169:6c63:6f77::/64" >> mailcow.conf
      fi
    elif [[ ${option} == "SQL_PORT" ]]; then
      if ! grep -q ${option} mailcow.conf; then
        echo "Adding new option \"${option}\" to mailcow.conf"
        echo '# Bind SQL to 127.0.0.1 on port 13306' >> mailcow.conf
        echo "SQL_PORT=127.0.0.1:13306" >> mailcow.conf
      fi
    elif [[ ${option} == "API_KEY" ]]; then
      if ! grep -q ${option} mailcow.conf; then
        echo "Adding new option \"${option}\" to mailcow.conf"
        echo '# Create or override API key for web UI' >> mailcow.conf
        echo "#API_KEY=" >> mailcow.conf
      fi
    elif [[ ${option} == "API_KEY_READ_ONLY" ]]; then
      if ! grep -q ${option} mailcow.conf; then
        echo "Adding new option \"${option}\" to mailcow.conf"
        echo '# Create or override read-only API key for web UI' >> mailcow.conf
        echo "#API_KEY_READ_ONLY=" >> mailcow.conf
      fi
    elif [[ ${option} == "API_ALLOW_FROM" ]]; then
      if ! grep -q ${option} mailcow.conf; then
        echo "Adding new option \"${option}\" to mailcow.conf"
        echo '# Must be set for API_KEY to be active' >> mailcow.conf
        echo '# IPs only, no networks (networks can be set via UI)' >> mailcow.conf
        echo "#API_ALLOW_FROM=" >> mailcow.conf
      fi
    elif [[ ${option} == "SNAT_TO_SOURCE" ]]; then
      if ! grep -q ${option} mailcow.conf; then
        echo "Adding new option \"${option}\" to mailcow.conf"
        echo '# Use this IPv4 for outgoing connections (SNAT)' >> mailcow.conf
        echo "#SNAT_TO_SOURCE=" >> mailcow.conf
      fi
    elif [[ ${option} == "SNAT6_TO_SOURCE" ]]; then
      if ! grep -q ${option} mailcow.conf; then
        echo "Adding new option \"${option}\" to mailcow.conf"
        echo '# Use this IPv6 for outgoing connections (SNAT)' >> mailcow.conf
        echo "#SNAT6_TO_SOURCE=" >> mailcow.conf
      fi
    elif [[ ${option} == "MAILDIR_GC_TIME" ]]; then
      if ! grep -q ${option} mailcow.conf; then
        echo "Adding new option \"${option}\" to mailcow.conf"
        echo '# Garbage collector cleanup' >> mailcow.conf
        echo '# Deleted domains and mailboxes are moved to /var/vmail/_garbage/timestamp_sanitizedstring' >> mailcow.conf
        echo '# How long should objects remain in the garbage until they are being deleted? (value in minutes)' >> mailcow.conf
        echo '# Check interval is hourly' >> mailcow.conf
        echo 'MAILDIR_GC_TIME=1440' >> mailcow.conf
      fi
    elif [[ ${option} == "ACL_ANYONE" ]]; then
      if ! grep -q ${option} mailcow.conf; then
        echo "Adding new option \"${option}\" to mailcow.conf"
        echo '# Set this to "allow" to enable the anyone pseudo user. Disabled by default.' >> mailcow.conf
        echo '# When enabled, ACL can be created, that apply to "All authenticated users"' >> mailcow.conf
        echo '# This should probably only be activated on mail hosts, that are used exclusivly by one organisation.' >> mailcow.conf
        echo '# Otherwise a user might share data with too many other users.' >> mailcow.conf
        echo 'ACL_ANYONE=disallow' >> mailcow.conf
      fi
    elif [[ ${option} == "FTS_HEAP" ]]; then
      if ! grep -q ${option} mailcow.conf; then
        echo "Adding new option \"${option}\" to mailcow.conf"
        echo '# Dovecot Indexing (FTS) Process maximum heap size in MB, there is no recommendation, please see Dovecot docs.' >> mailcow.conf
        echo '# Flatcurve is used as FTS Engine. It is supposed to be pretty efficient in CPU and RAM consumption.' >> mailcow.conf
        echo '# Please always monitor your Resource consumption!' >> mailcow.conf
        echo "FTS_HEAP=128" >> mailcow.conf
      fi
    elif [[ ${option} == "SKIP_FTS" ]]; then
      if ! grep -q ${option} mailcow.conf; then
        echo "Adding new option \"${option}\" to mailcow.conf"
        echo '# Skip FTS (Fulltext Search) for Dovecot on low-memory, low-threaded systems or if you simply want to disable it.' >> mailcow.conf
        echo "# Dovecot inside mailcow use Flatcurve as FTS Backend." >> mailcow.conf
        echo "SKIP_FTS=y" >> mailcow.conf
      fi
    elif [[ ${option} == "FTS_PROCS" ]]; then
      if ! grep -q ${option} mailcow.conf; then
        echo "Adding new option \"${option}\" to mailcow.conf"
        echo '# Controls how many processes the Dovecot indexing process can spawn at max.' >> mailcow.conf
        echo '# Too many indexing processes can use a lot of CPU and Disk I/O' >> mailcow.conf
        echo '# Please visit: https://doc.dovecot.org/configuration_manual/service_configuration/#indexer-worker for more informations' >> mailcow.conf
        echo "FTS_PROCS=1" >> mailcow.conf
      fi
    elif [[ ${option} == "ENABLE_SSL_SNI" ]]; then
      if ! grep -q ${option} mailcow.conf; then
        echo "Adding new option \"${option}\" to mailcow.conf"
        echo '# Create seperate certificates for all domains - y/n' >> mailcow.conf
        echo '# this will allow adding more than 100 domains, but some email clients will not be able to connect with alternative hostnames' >> mailcow.conf
        echo '# see https://wiki.dovecot.org/SSL/SNIClientSupport' >> mailcow.conf
        echo "ENABLE_SSL_SNI=n" >> mailcow.conf
      fi
    elif [[ ${option} == "SKIP_SOGO" ]]; then
      if ! grep -q ${option} mailcow.conf; then
        echo "Adding new option \"${option}\" to mailcow.conf"
        echo '# Skip SOGo: Will disable SOGo integration and therefore webmail, DAV protocols and ActiveSync support (experimental, unsupported, not fully implemented) - y/n' >> mailcow.conf
        echo "SKIP_SOGO=n" >> mailcow.conf
      fi
    elif [[ ${option} == "MAILDIR_SUB" ]]; then
      if ! grep -q ${option} mailcow.conf; then
        echo "Adding new option \"${option}\" to mailcow.conf"
        echo '# MAILDIR_SUB defines a path in a users virtual home to keep the maildir in. Leave empty for updated setups.' >> mailcow.conf
        echo "#MAILDIR_SUB=Maildir" >> mailcow.conf
        echo "MAILDIR_SUB=" >> mailcow.conf
      fi
    elif [[ ${option} == "WATCHDOG_NOTIFY_WEBHOOK" ]]; then
      if ! grep -q ${option} mailcow.conf; then
        echo "Adding new option \"${option}\" to mailcow.conf"
        echo '# Send notifications to a webhook URL that receives a POST request with the content type "application/json".' >> mailcow.conf
        echo '# You can use this to send notifications to services like Discord, Slack and others.' >> mailcow.conf
        echo '#WATCHDOG_NOTIFY_WEBHOOK=https://discord.com/api/webhooks/XXXXXXXXXXXXXXXXXXX/XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX' >> mailcow.conf
      fi
    elif [[ ${option} == "WATCHDOG_NOTIFY_WEBHOOK_BODY" ]]; then
      if ! grep -q ${option} mailcow.conf; then
        echo "Adding new option \"${option}\" to mailcow.conf"
        echo '# JSON body included in the webhook POST request. Needs to be in single quotes.' >> mailcow.conf
        echo '# Following variables are available: SUBJECT, BODY' >> mailcow.conf
        WEBHOOK_BODY='{"username": "mailcow Watchdog", "content": "**${SUBJECT}**\n${BODY}"}'
        echo "#WATCHDOG_NOTIFY_WEBHOOK_BODY='${WEBHOOK_BODY}'" >> mailcow.conf
      fi
    elif [[ ${option} == "WATCHDOG_NOTIFY_BAN" ]]; then
      if ! grep -q ${option} mailcow.conf; then
        echo "Adding new option \"${option}\" to mailcow.conf"
        echo '# Notify about banned IP. Includes whois lookup.' >> mailcow.conf
        echo "WATCHDOG_NOTIFY_BAN=y" >> mailcow.conf
      fi
    elif [[ ${option} == "WATCHDOG_NOTIFY_START" ]]; then
      if ! grep -q ${option} mailcow.conf; then
        echo "Adding new option \"${option}\" to mailcow.conf"
        echo '# Send a notification when the watchdog is started.' >> mailcow.conf
        echo "WATCHDOG_NOTIFY_START=y" >> mailcow.conf
      fi
    elif [[ ${option} == "WATCHDOG_SUBJECT" ]]; then
      if ! grep -q ${option} mailcow.conf; then
        echo "Adding new option \"${option}\" to mailcow.conf"
        echo '# Subject for watchdog mails. Defaults to "Watchdog ALERT" followed by the error message.' >> mailcow.conf
        echo "#WATCHDOG_SUBJECT=" >> mailcow.conf
      fi
    elif [[ ${option} == "WATCHDOG_EXTERNAL_CHECKS" ]]; then
      if ! grep -q ${option} mailcow.conf; then
        echo "Adding new option \"${option}\" to mailcow.conf"
        echo '# Checks if mailcow is an open relay. Requires a SAL. More checks will follow.' >> mailcow.conf
        echo '# No data is collected. Opt-in and anonymous.' >> mailcow.conf
        echo '# Will only work with unmodified mailcow setups.' >> mailcow.conf
        echo "WATCHDOG_EXTERNAL_CHECKS=n" >> mailcow.conf
      fi
    elif [[ ${option} == "SOGO_EXPIRE_SESSION" ]]; then
      if ! grep -q ${option} mailcow.conf; then
        echo "Adding new option \"${option}\" to mailcow.conf"
        echo '# SOGo session timeout in minutes' >> mailcow.conf
        echo "SOGO_EXPIRE_SESSION=480" >> mailcow.conf
      fi
    elif [[ ${option} == "REDIS_PORT" ]]; then
      if ! grep -q ${option} mailcow.conf; then
        echo "Adding new option \"${option}\" to mailcow.conf"
        echo "REDIS_PORT=127.0.0.1:7654" >> mailcow.conf
      fi
    elif [[ ${option} == "DOVECOT_MASTER_USER" ]]; then
      if ! grep -q ${option} mailcow.conf; then
        echo "Adding new option \"${option}\" to mailcow.conf"
        echo '# DOVECOT_MASTER_USER and _PASS must _both_ be provided. No special chars.' >> mailcow.conf
        echo '# Empty by default to auto-generate master user and password on start.' >> mailcow.conf
        echo '# User expands to DOVECOT_MASTER_USER@mailcow.local' >> mailcow.conf
        echo '# LEAVE EMPTY IF UNSURE' >> mailcow.conf
        echo "DOVECOT_MASTER_USER=" >> mailcow.conf
      fi
    elif [[ ${option} == "DOVECOT_MASTER_PASS" ]]; then
      if ! grep -q ${option} mailcow.conf; then
        echo "Adding new option \"${option}\" to mailcow.conf"
        echo '# LEAVE EMPTY IF UNSURE' >> mailcow.conf
        echo "DOVECOT_MASTER_PASS=" >> mailcow.conf
      fi
    elif [[ ${option} == "MAILCOW_PASS_SCHEME" ]]; then
      if ! grep -q ${option} mailcow.conf; then
        echo "Adding new option \"${option}\" to mailcow.conf"
        echo '# Password hash algorithm' >> mailcow.conf
        echo '# Only certain password hash algorithm are supported. For a fully list of supported schemes,' >> mailcow.conf
        echo '# see https://docs.mailcow.email/models/model-passwd/' >> mailcow.conf
        echo "MAILCOW_PASS_SCHEME=BLF-CRYPT" >> mailcow.conf
      fi
    elif [[ ${option} == "ADDITIONAL_SERVER_NAMES" ]]; then
      if ! grep -q ${option} mailcow.conf; then
        echo "Adding new option \"${option}\" to mailcow.conf"
        echo '# Additional server names for mailcow UI' >> mailcow.conf
        echo '#' >> mailcow.conf
        echo '# Specify alternative addresses for the mailcow UI to respond to' >> mailcow.conf
        echo '# This is useful when you set mail.* as ADDITIONAL_SAN and want to make sure mail.maildomain.com will always point to the mailcow UI.' >> mailcow.conf
        echo '# If the server name does not match a known site, Nginx decides by best-guess and may redirect users to the wrong web root.' >> mailcow.conf
        echo '# You can understand this as server_name directive in Nginx.' >> mailcow.conf
        echo '# Comma separated list without spaces! Example: ADDITIONAL_SERVER_NAMES=a.b.c,d.e.f' >> mailcow.conf
        echo 'ADDITIONAL_SERVER_NAMES=' >> mailcow.conf
      fi
    elif [[ ${option} == "ACME_CONTACT" ]]; then
      if ! grep -q ${option} mailcow.conf; then
        echo "Adding new option \"${option}\" to mailcow.conf"
        echo '# Lets Encrypt registration contact information' >> mailcow.conf
        echo '# Optional: Leave empty for none' >> mailcow.conf
        echo '# This value is only used on first order!' >> mailcow.conf
        echo '# Setting it at a later point will require the following steps:' >> mailcow.conf
        echo '# https://docs.mailcow.email/troubleshooting/debug-reset_tls/' >> mailcow.conf
        echo 'ACME_CONTACT=' >> mailcow.conf
      fi
    elif [[ ${option} == "WEBAUTHN_ONLY_TRUSTED_VENDORS" ]]; then
      if ! grep -q ${option} mailcow.conf; then
        echo "Adding new option \"${option}\" to mailcow.conf"
        echo "# WebAuthn device manufacturer verification" >> mailcow.conf
        echo '# After setting WEBAUTHN_ONLY_TRUSTED_VENDORS=y only devices from trusted manufacturers are allowed' >> mailcow.conf
        echo '# root certificates can be placed for validation under mailcow-dockerized/data/web/inc/lib/WebAuthn/rootCertificates' >> mailcow.conf
        echo 'WEBAUTHN_ONLY_TRUSTED_VENDORS=n' >> mailcow.conf
      fi
    elif [[ ${option} == "SPAMHAUS_DQS_KEY" ]]; then
      if ! grep -q ${option} mailcow.conf; then
        echo "Adding new option \"${option}\" to mailcow.conf"
        echo "# Spamhaus Data Query Service Key" >> mailcow.conf
        echo '# Optional: Leave empty for none' >> mailcow.conf
        echo '# Enter your key here if you are using a blocked ASN (OVH, AWS, Cloudflare e.g) for the unregistered Spamhaus Blocklist.' >> mailcow.conf
        echo '# If empty, it will completely disable Spamhaus blocklists if it detects that you are running on a server using a blocked AS.' >> mailcow.conf
        echo '# Otherwise it will work as usual.' >> mailcow.conf
        echo 'SPAMHAUS_DQS_KEY=' >> mailcow.conf
      fi
    elif [[ ${option} == "WATCHDOG_VERBOSE" ]]; then
      if ! grep -q ${option} mailcow.conf; then
        echo "Adding new option \"${option}\" to mailcow.conf"
        echo '# Enable watchdog verbose logging' >> mailcow.conf
        echo 'WATCHDOG_VERBOSE=n' >> mailcow.conf
      fi
    elif [[ ${option} == "SKIP_UNBOUND_HEALTHCHECK" ]]; then
      if ! grep -q ${option} mailcow.conf; then
        echo "Adding new option \"${option}\" to mailcow.conf"
        echo '# Skip Unbound (DNS Resolver) Healthchecks (NOT Recommended!) - y/n' >> mailcow.conf
        echo 'SKIP_UNBOUND_HEALTHCHECK=n' >> mailcow.conf
      fi
    elif [[ ${option} == "DISABLE_NETFILTER_ISOLATION_RULE" ]]; then
      if ! grep -q ${option} mailcow.conf; then
        echo "Adding new option \"${option}\" to mailcow.conf"
        echo '# Prevent netfilter from setting an iptables/nftables rule to isolate the mailcow docker network - y/n' >> mailcow.conf
        echo '# CAUTION: Disabling this may expose container ports to other neighbors on the same subnet, even if the ports are bound to localhost' >> mailcow.conf
        echo 'DISABLE_NETFILTER_ISOLATION_RULE=n' >> mailcow.conf
      fi 
    elif ! grep -q ${option} mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo "${option}=n" >> mailcow.conf
    fi
  done
}

migrate_solr_config_options() {

  sed -i --follow-symlinks '$a\' mailcow.conf

  if grep -q "SOLR_HEAP" mailcow.conf; then
    echo "Removing SOLR_HEAP in mailcow.conf"
    sed -i '/# Solr heap size in MB\b/d' mailcow.conf
    sed -i '/# Solr is a prone to run\b/d' mailcow.conf
    sed -i '/SOLR_HEAP\b/d' mailcow.conf
  fi

  if grep -q "SKIP_SOLR" mailcow.conf; then
    echo "Removing SKIP_SOLR in mailcow.conf"
    sed -i '/\bSkip Solr on low-memory\b/d' mailcow.conf
    sed -i '/\bSolr is disabled by default\b/d' mailcow.conf
    sed -i '/\bDisable Solr or\b/d' mailcow.conf
    sed -i '/\bSKIP_SOLR\b/d' mailcow.conf
  fi

  if grep -q "SOLR_PORT" mailcow.conf; then
    echo "Removing SOLR_PORT in mailcow.conf"
    sed -i '/\bSOLR_PORT\b/d' mailcow.conf
  fi

  if grep -q "FLATCURVE_EXPERIMENTAL" mailcow.conf; then
    echo "Removing FLATCURVE_EXPERIMENTAL in mailcow.conf"
    sed -i '/\bFLATCURVE_EXPERIMENTAL\b/d' mailcow.conf
  fi

  solr_volume=$(docker volume ls -qf name=^${COMPOSE_PROJECT_NAME}_solr-vol-1)
  if [[ -n $solr_volume ]]; then
    echo -e "\e[34mSolr has been replaced within mailcow since 2025-01.\nThe volume $solr_volume is unused.\e[0m"
    sleep 1
    if [ ! "$FORCE" ]; then
      read -r -p "Remove $solr_volume? [y/N] " response
      if [[ "$response" =~ ^([yY][eE][sS]|[yY])+$ ]]; then
        echo -e "\e[33mRemoving $solr_volume...\e[0m"
        docker volume rm $solr_volume || echo -e "\e[31mFailed to remove. Remove it manually!\e[0m" && exit
        echo -e "\e[32mSuccessfully removed $solr_volume!\e[0m"
      else
        echo -e "Not removing $solr_volume. Run \`docker volume rm $solr_volume\` manually if needed."
      fi
    else
      echo -e "\e[33mForce removing $solr_volume...\e[0m"
      docker volume rm $solr_volume || echo -e "\e[31mFailed to remove. Remove it manually!\e[0m" && exit
      echo -e "\e[32mSuccessfully removed $solr_volume!\e[0m"
    fi
  fi

  # Delete old fts.conf before forced switch to flatcurve to ensure update is working properly
  FTS_CONF_PATH="${SCRIPT_DIR}/data/conf/dovecot/conf.d/fts.conf"
  if [[ -f "$FTS_CONF_PATH" ]]; then
    if grep -q "Autogenerated by mailcow" "$FTS_CONF_PATH"; then
      rm -rf $FTS_CONF_PATH
    fi
  fi
}

############## End Function Section ##############

# Check permissions
if [ "$(id -u)" -ne "0" ]; then
  echo "You need to be root"
  exit 1
fi

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# Run pre-update-hook
if [ -f "${SCRIPT_DIR}/pre_update_hook.sh" ]; then
  bash "${SCRIPT_DIR}/pre_update_hook.sh"
fi

if [[ "$(uname -r)" =~ ^4\.15\.0-60 ]]; then
  echo "DO NOT RUN mailcow ON THIS UBUNTU KERNEL!";
  echo "Please update to 5.x or use another distribution."
  exit 1
fi

if [[ "$(uname -r)" =~ ^4\.4\. ]]; then
  if grep -q Ubuntu <<< "$(uname -a)"; then
    echo "DO NOT RUN mailcow ON THIS UBUNTU KERNEL!"
    echo "Please update to linux-generic-hwe-16.04 by running \"apt-get install --install-recommends linux-generic-hwe-16.04\""
    exit 1
  fi
  echo "mailcow on a 4.4.x kernel is not supported. It may or may not work, please upgrade your kernel or continue at your own risk."
  read -p "Press any key to continue..." < /dev/tty
fi

# Exit on error and pipefail
set -o pipefail

# Setting high dc timeout
export COMPOSE_HTTP_TIMEOUT=600

# Add /opt/bin to PATH
PATH=$PATH:/opt/bin

umask 0022

# Unset COMPOSE_COMMAND and DOCKER_COMPOSE_VERSION Variable to be on the newest state.
unset COMPOSE_COMMAND
unset DOCKER_COMPOSE_VERSION

for bin in curl docker git awk sha1sum grep cut; do
  if [[ -z $(command -v ${bin}) ]]; then
  echo "Cannot find ${bin}, exiting..."
  exit 1;
  fi
done

# Check Docker Version (need at least 24.X)
docker_version=$(docker -v | grep -oP '\d+\.\d+\.\d+' | cut -d '.' -f 1 | head -1)

if [[ $docker_version -lt 24 ]]; then
  echo -e "\e[31mCannot find Docker with a Version higher or equals 24.0.0\e[0m"
  echo -e "\e[33mmailcow needs a newer Docker version to work properly... continuing on your own risk!\e[0m"
  echo -e "\e[31mPlease update your Docker installation... sleeping 10s\e[0m"
  sleep 10
fi

export LC_ALL=C
DATE=$(date +%Y-%m-%d_%H_%M_%S)
BRANCH="$(cd "${SCRIPT_DIR}"; git rev-parse --abbrev-ref HEAD)"

while (($#)); do
  case "${1}" in
    --check|-c)
      echo "Checking remote code for updates..."
      LATEST_REV=$(git ls-remote --exit-code --refs --quiet https://github.com/mailcow/mailcow-dockerized "${BRANCH}" | cut -f1)
      if [ "$?" -ne 0 ]; then
        echo "A problem occurred while trying to fetch the latest revision from github."
        exit 99
      fi
      if [[ -z $(git log HEAD --pretty=format:"%H" | grep "${LATEST_REV}") ]]; then
        echo -e "Updated code is available.\nThe changes can be found here: https://github.com/mailcow/mailcow-dockerized/commits/master"
        git log --date=short --pretty=format:"%ad - %s" "$(git rev-parse --short HEAD)"..origin/master
        exit 0
      else
        echo "No updates available."
        exit 3
      fi
    ;;
    --check-tags)
      echo "Checking remote tags for updates..."
      LATEST_TAG_REV=$(git ls-remote --exit-code --quiet --tags origin | tail -1 | cut -f1)
      if [ "$?" -ne 0 ]; then
        echo "A problem occurred while trying to fetch the latest tag from github."
        exit 99
      fi
      if [[ -z $(git log HEAD --pretty=format:"%H" | grep "${LATEST_TAG_REV}") ]]; then
        echo -e "New tag is available.\nThe changes can be found here: https://github.com/mailcow/mailcow-dockerized/releases/latest"
        exit 0
      else
        echo "No updates available."
        exit 3
      fi
    ;;
    --ours)
      MERGE_STRATEGY=ours
    ;;
    --skip-start)
      SKIP_START=y
    ;;
    --skip-ping-check)
      SKIP_PING_CHECK=y
    ;;
    --stable)
      CURRENT_BRANCH="$(cd "${SCRIPT_DIR}"; git rev-parse --abbrev-ref HEAD)"
      NEW_BRANCH="master"
    ;;
    --gc)
      echo -e "\e[32mCollecting garbage...\e[0m"
      docker_garbage
      exit 0
    ;;
    --nightly)
      CURRENT_BRANCH="$(cd "${SCRIPT_DIR}"; git rev-parse --abbrev-ref HEAD)"
      NEW_BRANCH="nightly"
    ;;
    --prefetch)
      echo -e "\e[32mPrefetching images...\e[0m"
      prefetch_images
      exit 0
    ;;
    -f|--force)
      echo -e "\e[32mRunning in forced mode...\e[0m"
      FORCE=y
    ;;
    -d|--dev)
      echo -e "\e[32mRunning in Developer mode...\e[0m"
      DEV=y
    ;;
    --help|-h)
    echo './update.sh [-c|--check, --check-tags, --ours, --gc, --nightly, --prefetch, --skip-start, --skip-ping-check, --stable, -f|--force, -d|--dev, -h|--help]

  -c|--check           -   Check for updates and exit (exit codes => 0: update available, 3: no updates)
  --check-tags         -   Check for newer tags and exit (exit codes => 0: newer tag available, 3: no newer tag)
  --ours               -   Use merge strategy option "ours" to solve conflicts in favor of non-mailcow code (local changes over remote changes), not recommended!
  --gc                 -   Run garbage collector to delete old image tags
  --nightly            -   Switch your mailcow updates to the unstable (nightly) branch. FOR TESTING PURPOSES ONLY!!!!
  --prefetch           -   Only prefetch new images and exit (useful to prepare updates)
  --skip-start         -   Do not start mailcow after update
  --skip-ping-check    -   Skip ICMP Check to public DNS resolvers (Use it only if you'\''ve blocked any ICMP Connections to your mailcow machine)
  --stable             -   Switch your mailcow updates to the stable (master) branch. Default unless you changed it with --nightly.
  -f|--force           -   Force update, do not ask questions
  -d|--dev             -   Enables Developer Mode (No Checkout of update.sh for tests)
'
    exit 0
  esac
  shift
done

[[ ! -f mailcow.conf ]] && { echo -e "\e[31mmailcow.conf is missing! Is mailcow installed?\e[0m"; exit 1;}

chmod 600 mailcow.conf
source mailcow.conf

detect_docker_compose_command

fix_broken_dnslist_conf

DOTS=${MAILCOW_HOSTNAME//[^.]};
if [ ${#DOTS} -lt 1 ]; then
  echo -e "\e[31mMAILCOW_HOSTNAME (${MAILCOW_HOSTNAME}) is not a FQDN!\e[0m"
  sleep 1
  echo "Please change it to a FQDN and redeploy the stack with $COMPOSE_COMMAND up -d"
  exit 1
elif [[ "${MAILCOW_HOSTNAME: -1}" == "." ]]; then
  echo "MAILCOW_HOSTNAME (${MAILCOW_HOSTNAME}) is ending with a dot. This is not a valid FQDN!"
  exit 1
elif [ ${#DOTS} -eq 1 ]; then
  echo -e "\e[33mMAILCOW_HOSTNAME (${MAILCOW_HOSTNAME}) does not contain a Subdomain. This is not fully tested and may cause issues.\e[0m"
  echo "Find more information about why this message exists here: https://github.com/mailcow/mailcow-dockerized/issues/1572"
  read -r -p "Do you want to proceed anyway? [y/N] " response
  if [[ "$response" =~ ^([yY][eE][sS]|[yY])+$ ]]; then
    echo "OK. Proceeding."
  else
    echo "OK. Exiting."
    exit 1
  fi
fi

if grep --help 2>&1 | head -n 1 | grep -q -i "busybox"; then echo "BusyBox grep detected, please install gnu grep, \"apk add --no-cache --upgrade grep\""; exit 1; fi
# This will also cover sort
if cp --help 2>&1 | head -n 1 | grep -q -i "busybox"; then echo "BusyBox cp detected, please install coreutils, \"apk add --no-cache --upgrade coreutils\""; exit 1; fi
if sed --help 2>&1 | head -n 1 | grep -q -i "busybox"; then echo "BusyBox sed detected, please install gnu sed, \"apk add --no-cache --upgrade sed\""; exit 1; fi

CONFIG_ARRAY=(
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
  "SKIP_IP_CHECK"
  "ADDITIONAL_SAN"
  "AUTODISCOVER_SAN"
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
  "ENABLE_SSL_SNI"
  "ALLOW_ADMIN_EMAIL_LOGIN"
  "SKIP_HTTP_VERIFICATION"
  "SOGO_EXPIRE_SESSION"
  "REDIS_PORT"
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
  "REDISPASS"
)

detect_bad_asn

sed -i --follow-symlinks '$a\' mailcow.conf
for option in "${CONFIG_ARRAY[@]}"; do
  if [[ ${option} == "ADDITIONAL_SAN" ]]; then
    if ! grep -q "${option}" mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo "${option}=" >> mailcow.conf
    fi
  elif [[ "${option}" == "COMPOSE_PROJECT_NAME" ]]; then
    if ! grep -q "${option}" mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo "COMPOSE_PROJECT_NAME=mailcowdockerized" >> mailcow.conf
    fi
  elif [[ "${option}" == "DOCKER_COMPOSE_VERSION" ]]; then
    if ! grep -q "${option}" mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo "# Used Docker Compose version" >> mailcow.conf
      echo "# Switch here between native (compose plugin) and standalone" >> mailcow.conf
      echo "# For more informations take a look at the mailcow docs regarding the configuration options." >> mailcow.conf
      echo "# Normally this should be untouched but if you decided to use either of those you can switch it manually here." >> mailcow.conf
      echo "# Please be aware that at least one of those variants should be installed on your maschine or mailcow will fail." >> mailcow.conf
      echo "" >> mailcow.conf
      echo "DOCKER_COMPOSE_VERSION=${DOCKER_COMPOSE_VERSION}" >> mailcow.conf
    fi
  elif [[ "${option}" == "DOVEADM_PORT" ]]; then
    if ! grep -q "${option}" mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo "DOVEADM_PORT=127.0.0.1:19991" >> mailcow.conf
    fi
  elif [[ "${option}" == "WATCHDOG_NOTIFY_EMAIL" ]]; then
    if ! grep -q "${option}" mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo "WATCHDOG_NOTIFY_EMAIL=" >> mailcow.conf
    fi
  elif [[ "${option}" == "LOG_LINES" ]]; then
    if ! grep -q "${option}" mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo '# Max log lines per service to keep in Redis logs' >> mailcow.conf
      echo "LOG_LINES=9999" >> mailcow.conf
    fi
  elif [[ "${option}" == "IPV4_NETWORK" ]]; then
    if ! grep -q "${option}" mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo '# Internal IPv4 /24 subnet, format n.n.n. (expands to n.n.n.0/24)' >> mailcow.conf
      echo "IPV4_NETWORK=172.22.1" >> mailcow.conf
    fi
  elif [[ "${option}" == "IPV6_NETWORK" ]]; then
    if ! grep -q "${option}" mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo '# Internal IPv6 subnet in fc00::/7' >> mailcow.conf
      echo "IPV6_NETWORK=fd4d:6169:6c63:6f77::/64" >> mailcow.conf
    fi
  elif [[ "${option}" == "SQL_PORT" ]]; then
    if ! grep -q "${option}" mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo '# Bind SQL to 127.0.0.1 on port 13306' >> mailcow.conf
      echo "SQL_PORT=127.0.0.1:13306" >> mailcow.conf
    fi
  elif [[ "${option}" == "API_KEY" ]]; then
    if ! grep -q "${option}" mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo '# Create or override API key for web UI' >> mailcow.conf
      echo "#API_KEY=" >> mailcow.conf
    fi
  elif [[ "${option}" == "API_KEY_READ_ONLY" ]]; then
    if ! grep -q "${option}" mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo '# Create or override read-only API key for web UI' >> mailcow.conf
      echo "#API_KEY_READ_ONLY=" >> mailcow.conf
    fi
  elif [[ "${option}" == "API_ALLOW_FROM" ]]; then
    if ! grep -q "${option}" mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo '# Must be set for API_KEY to be active' >> mailcow.conf
      echo '# IPs only, no networks (networks can be set via UI)' >> mailcow.conf
      echo "#API_ALLOW_FROM=" >> mailcow.conf
    fi
  elif [[ "${option}" == "SNAT_TO_SOURCE" ]]; then
    if ! grep -q "${option}" mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo '# Use this IPv4 for outgoing connections (SNAT)' >> mailcow.conf
      echo "#SNAT_TO_SOURCE=" >> mailcow.conf
    fi
  elif [[ "${option}" == "SNAT6_TO_SOURCE" ]]; then
    if ! grep -q "${option}" mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo '# Use this IPv6 for outgoing connections (SNAT)' >> mailcow.conf
      echo "#SNAT6_TO_SOURCE=" >> mailcow.conf
    fi
  elif [[ "${option}" == "MAILDIR_GC_TIME" ]]; then
    if ! grep -q "${option}" mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo '# Garbage collector cleanup' >> mailcow.conf
      echo '# Deleted domains and mailboxes are moved to /var/vmail/_garbage/timestamp_sanitizedstring' >> mailcow.conf
      echo '# How long should objects remain in the garbage until they are being deleted? (value in minutes)' >> mailcow.conf
      echo '# Check interval is hourly' >> mailcow.conf
      echo 'MAILDIR_GC_TIME=1440' >> mailcow.conf
    fi
  elif [[ "${option}" == "ACL_ANYONE" ]]; then
    if ! grep -q "${option}" mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo '# Set this to "allow" to enable the anyone pseudo user. Disabled by default.' >> mailcow.conf
      echo '# When enabled, ACL can be created, that apply to "All authenticated users"' >> mailcow.conf
      echo '# This should probably only be activated on mail hosts, that are used exclusivly by one organisation.' >> mailcow.conf
      echo '# Otherwise a user might share data with too many other users.' >> mailcow.conf
      echo 'ACL_ANYONE=disallow' >> mailcow.conf
    fi
  elif [[ "${option}" == "ENABLE_SSL_SNI" ]]; then
    if ! grep -q "${option}" mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo '# Create seperate certificates for all domains - y/n' >> mailcow.conf
      echo '# this will allow adding more than 100 domains, but some email clients will not be able to connect with alternative hostnames' >> mailcow.conf
      echo '# see https://wiki.dovecot.org/SSL/SNIClientSupport' >> mailcow.conf
      echo "ENABLE_SSL_SNI=n" >> mailcow.conf
    fi
  elif [[ "${option}" == "SKIP_SOGO" ]]; then
    if ! grep -q "${option}" mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo '# Skip SOGo: Will disable SOGo integration and therefore webmail, DAV protocols and ActiveSync support (experimental, unsupported, not fully implemented) - y/n' >> mailcow.conf
      echo "SKIP_SOGO=n" >> mailcow.conf
    fi
  elif [[ "${option}" == "MAILDIR_SUB" ]]; then
    if ! grep -q "${option}" mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo '# MAILDIR_SUB defines a path in a users virtual home to keep the maildir in. Leave empty for updated setups.' >> mailcow.conf
      echo "#MAILDIR_SUB=Maildir" >> mailcow.conf
      echo "MAILDIR_SUB=" >> mailcow.conf
    fi
  elif [[ "${option}" == "WATCHDOG_NOTIFY_WEBHOOK" ]]; then
    if ! grep -q "${option}" mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo '# Send notifications to a webhook URL that receives a POST request with the content type "application/json".' >> mailcow.conf
      echo '# You can use this to send notifications to services like Discord, Slack and others.' >> mailcow.conf
      echo '#WATCHDOG_NOTIFY_WEBHOOK=https://discord.com/api/webhooks/XXXXXXXXXXXXXXXXXXX/XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX' >> mailcow.conf
    fi
  elif [[ "${option}" == "WATCHDOG_NOTIFY_WEBHOOK_BODY" ]]; then
    if ! grep -q "${option}" mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo '# JSON body included in the webhook POST request. Needs to be in single quotes.' >> mailcow.conf
      echo '# Following variables are available: SUBJECT, BODY' >> mailcow.conf
      WEBHOOK_BODY='{"username": "mailcow Watchdog", "content": "**${SUBJECT}**\n${BODY}"}'
      echo "#WATCHDOG_NOTIFY_WEBHOOK_BODY='${WEBHOOK_BODY}'" >> mailcow.conf
    fi
  elif [[ "${option}" == "WATCHDOG_NOTIFY_BAN" ]]; then
    if ! grep -q "${option}" mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo '# Notify about banned IP. Includes whois lookup.' >> mailcow.conf
      echo "WATCHDOG_NOTIFY_BAN=y" >> mailcow.conf
    fi
  elif [[ "${option}" == "WATCHDOG_NOTIFY_START" ]]; then
    if ! grep -q "${option}" mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo '# Send a notification when the watchdog is started.' >> mailcow.conf
      echo "WATCHDOG_NOTIFY_START=y" >> mailcow.conf
    fi
  elif [[ "${option}" == "WATCHDOG_SUBJECT" ]]; then
    if ! grep -q "${option}" mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo '# Subject for watchdog mails. Defaults to "Watchdog ALERT" followed by the error message.' >> mailcow.conf
      echo "#WATCHDOG_SUBJECT=" >> mailcow.conf
    fi
  elif [[ "${option}" == "WATCHDOG_EXTERNAL_CHECKS" ]]; then
    if ! grep -q "${option}" mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo '# Checks if mailcow is an open relay. Requires a SAL. More checks will follow.' >> mailcow.conf
      echo '# No data is collected. Opt-in and anonymous.' >> mailcow.conf
      echo '# Will only work with unmodified mailcow setups.' >> mailcow.conf
      echo "WATCHDOG_EXTERNAL_CHECKS=n" >> mailcow.conf
    fi
  elif [[ "${option}" == "SOGO_EXPIRE_SESSION" ]]; then
    if ! grep -q "${option}" mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo '# SOGo session timeout in minutes' >> mailcow.conf
      echo "SOGO_EXPIRE_SESSION=480" >> mailcow.conf
    fi
  elif [[ "${option}" == "REDIS_PORT" ]]; then
    if ! grep -q "${option}" mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo "REDIS_PORT=127.0.0.1:7654" >> mailcow.conf
    fi
  elif [[ "${option}" == "DOVECOT_MASTER_USER" ]]; then
    if ! grep -q "${option}" mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo '# DOVECOT_MASTER_USER and _PASS must _both_ be provided. No special chars.' >> mailcow.conf
      echo '# Empty by default to auto-generate master user and password on start.' >> mailcow.conf
      echo '# User expands to DOVECOT_MASTER_USER@mailcow.local' >> mailcow.conf
      echo '# LEAVE EMPTY IF UNSURE' >> mailcow.conf
      echo "DOVECOT_MASTER_USER=" >> mailcow.conf
    fi
  elif [[ "${option}" == "DOVECOT_MASTER_PASS" ]]; then
    if ! grep -q "${option}" mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo '# LEAVE EMPTY IF UNSURE' >> mailcow.conf
      echo "DOVECOT_MASTER_PASS=" >> mailcow.conf
    fi
  elif [[ "${option}" == "MAILCOW_PASS_SCHEME" ]]; then
    if ! grep -q "${option}" mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo '# Password hash algorithm' >> mailcow.conf
      echo '# Only certain password hash algorithm are supported. For a fully list of supported schemes,' >> mailcow.conf
      echo '# see https://docs.mailcow.email/models/model-passwd/' >> mailcow.conf
      echo "MAILCOW_PASS_SCHEME=BLF-CRYPT" >> mailcow.conf
    fi
  elif [[ "${option}" == "ADDITIONAL_SERVER_NAMES" ]]; then
    if ! grep -q "${option}" mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo '# Additional server names for mailcow UI' >> mailcow.conf
      echo '#' >> mailcow.conf
      echo '# Specify alternative addresses for the mailcow UI to respond to' >> mailcow.conf
      echo '# This is useful when you set mail.* as ADDITIONAL_SAN and want to make sure mail.maildomain.com will always point to the mailcow UI.' >> mailcow.conf
      echo '# If the server name does not match a known site, Nginx decides by best-guess and may redirect users to the wrong web root.' >> mailcow.conf
      echo '# You can understand this as server_name directive in Nginx.' >> mailcow.conf
      echo '# Comma separated list without spaces! Example: ADDITIONAL_SERVER_NAMES=a.b.c,d.e.f' >> mailcow.conf
      echo 'ADDITIONAL_SERVER_NAMES=' >> mailcow.conf
    fi

  elif [[ "${option}" == "AUTODISCOVER_SAN" ]]; then
    if ! grep -q "${option}" mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo '# Obtain certificates for autodiscover.* and autoconfig.* domains.' >> mailcow.conf
      echo '# This can be useful to switch off in case you are in a scenario where a reverse proxy already handles those.' >> mailcow.conf
      echo '# There are mixed scenarios where ports 80,443 are occupied and you do not want to share certs' >> mailcow.conf
      echo '# between services. So acme-mailcow obtains for maildomains and all web-things get handled' >> mailcow.conf
      echo '# in the reverse proxy.' >> mailcow.conf
      echo 'AUTODISCOVER_SAN=y' >> mailcow.conf
    fi

  elif [[ "${option}" == "ACME_CONTACT" ]]; then
    if ! grep -q "${option}" mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo '# Lets Encrypt registration contact information' >> mailcow.conf
      echo '# Optional: Leave empty for none' >> mailcow.conf
      echo '# This value is only used on first order!' >> mailcow.conf
      echo '# Setting it at a later point will require the following steps:' >> mailcow.conf
      echo '# https://docs.mailcow.email/troubleshooting/debug-reset_tls/' >> mailcow.conf
      echo 'ACME_CONTACT=' >> mailcow.conf
    fi
  elif [[ "${option}" == "WEBAUTHN_ONLY_TRUSTED_VENDORS" ]]; then
    if ! grep -q "${option}" mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo "# WebAuthn device manufacturer verification" >> mailcow.conf
      echo '# After setting WEBAUTHN_ONLY_TRUSTED_VENDORS=y only devices from trusted manufacturers are allowed' >> mailcow.conf
      echo '# root certificates can be placed for validation under mailcow-dockerized/data/web/inc/lib/WebAuthn/rootCertificates' >> mailcow.conf
      echo 'WEBAUTHN_ONLY_TRUSTED_VENDORS=n' >> mailcow.conf
    fi
  elif [[ "${option}" == "SPAMHAUS_DQS_KEY" ]]; then
    if ! grep -q "${option}" mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo "# Spamhaus Data Query Service Key" >> mailcow.conf
      echo '# Optional: Leave empty for none' >> mailcow.conf
      echo '# Enter your key here if you are using a blocked ASN (OVH, AWS, Cloudflare e.g) for the unregistered Spamhaus Blocklist.' >> mailcow.conf
      echo '# If empty, it will completely disable Spamhaus blocklists if it detects that you are running on a server using a blocked AS.' >> mailcow.conf
      echo '# Otherwise it will work as usual.' >> mailcow.conf
      echo 'SPAMHAUS_DQS_KEY=' >> mailcow.conf
    fi
  elif [[ "${option}" == "WATCHDOG_VERBOSE" ]]; then
    if ! grep -q "${option}" mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo '# Enable watchdog verbose logging' >> mailcow.conf
      echo 'WATCHDOG_VERBOSE=n' >> mailcow.conf
    fi
  elif [[ "${option}" == "SKIP_UNBOUND_HEALTHCHECK" ]]; then
    if ! grep -q "${option}" mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo '# Skip Unbound (DNS Resolver) Healthchecks (NOT Recommended!) - y/n' >> mailcow.conf
      echo 'SKIP_UNBOUND_HEALTHCHECK=n' >> mailcow.conf
    fi
  elif [[ "${option}" == "DISABLE_NETFILTER_ISOLATION_RULE" ]]; then
    if ! grep -q "${option}" mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo '# Prevent netfilter from setting an iptables/nftables rule to isolate the mailcow docker network - y/n' >> mailcow.conf
      echo '# CAUTION: Disabling this may expose container ports to other neighbors on the same subnet, even if the ports are bound to localhost' >> mailcow.conf
      echo 'DISABLE_NETFILTER_ISOLATION_RULE=n' >> mailcow.conf
    fi
  elif [[ "${option}" == "REDISPASS" ]]; then
    if ! grep -q "${option}" mailcow.conf; then
      echo "Adding new option \"${option}\" to mailcow.conf"
      echo -e '\n# ------------------------------' >> mailcow.conf
      echo '# REDIS configuration' >> mailcow.conf
      echo -e '# ------------------------------\n' >> mailcow.conf
      echo "REDISPASS=$(LC_ALL=C </dev/urandom tr -dc A-Za-z0-9 2> /dev/null | head -c 28)" >> mailcow.conf
    fi
  elif ! grep -q "${option}" mailcow.conf; then
    echo "Adding new option \"${option}\" to mailcow.conf"
    echo "${option}=n" >> mailcow.conf
  fi
done

if [[ ("${SKIP_PING_CHECK}" == "y") ]]; then
echo -e "\e[32mSkipping Ping Check...\e[0m"

else
   echo -en "Checking internet connection... "
   if ! check_online_status; then
      echo -e "\e[31mfailed\e[0m"
      exit 1
   else
      echo -e "\e[32mOK\e[0m"
   fi
fi

if ! [ "$NEW_BRANCH" ]; then
  echo -e "\e[33mDetecting which build your mailcow runs on...\e[0m"
  sleep 1
  if [ "${BRANCH}" == "master" ]; then
    echo -e "\e[32mYou are receiving stable updates (master).\e[0m"
    echo -e "\e[33mTo change that run the update.sh Script one time with the --nightly parameter to switch to nightly builds.\e[0m"

  elif [ "${BRANCH}" == "nightly" ]; then
    echo -e "\e[31mYou are receiving unstable updates (nightly). These are for testing purposes only!!!\e[0m"
    sleep 1
    echo -e "\e[33mTo change that run the update.sh Script one time with the --stable parameter to switch to stable builds.\e[0m"

  else
    echo -e "\e[33mYou are receiving updates from an unsupported branch.\e[0m"
    sleep 1
    echo -e "\e[33mThe mailcow stack might still work but it is recommended to switch to the master branch (stable builds).\e[0m"
    echo -e "\e[33mTo change that run the update.sh Script one time with the --stable parameter to switch to stable builds.\e[0m"
  fi
elif [ "$FORCE" ]; then
  echo -e "\e[31mYou are running in forced mode!\e[0m"
  echo -e "\e[31mA Branch Switch can only be performed manually (monitored).\e[0m"
  echo -e "\e[31mPlease rerun the update.sh Script without the --force/-f parameter.\e[0m"
  sleep 1
elif [ "$NEW_BRANCH" == "master" ] && [ "$CURRENT_BRANCH" != "master" ]; then
  echo -e "\e[33mYou are about to switch your mailcow updates to the stable (master) branch.\e[0m"
  sleep 1
  echo -e "\e[33mBefore you do: Please take a backup of all components to ensure that no data is lost...\e[0m"
  sleep 1
  echo -e "\e[31mWARNING: Please see on GitHub or ask in the community if a switch to master is stable or not.
  In some rear cases an update back to master can destroy your mailcow configuration such as database upgrade, etc.
  Normally an upgrade back to master should be safe during each full release.
  Check GitHub for Database changes and update only if there similar to the full release!\e[0m"
  read -r -p "Are you sure you that want to continue upgrading to the stable (master) branch? [y/N] " response
  if [[ ! "${response}" =~ ^([yY][eE][sS]|[yY])+$ ]]; then
    echo "OK. If you prepared yourself for that please run the update.sh Script with the --stable parameter again to trigger this process here."
    exit 0
  fi
  BRANCH="$NEW_BRANCH"
  DIFF_DIRECTORY=update_diffs
  DIFF_FILE="${DIFF_DIRECTORY}/diff_before_upgrade_to_master_$(date +"%Y-%m-%d-%H-%M-%S")"
  mv diff_before_upgrade* "${DIFF_DIRECTORY}/" 2> /dev/null
  if ! git diff-index --quiet HEAD; then
    echo -e "\e[32mSaving diff to ${DIFF_FILE}...\e[0m"
    mkdir -p "${DIFF_DIRECTORY}"
    git diff "${BRANCH}" --stat > "${DIFF_FILE}"
    git diff "${BRANCH}" >> "${DIFF_FILE}"
  fi
  echo -e "\e[32mSwitching Branch to ${BRANCH}...\e[0m"
  git fetch origin
  git checkout -f "${BRANCH}"

elif [ "$NEW_BRANCH" == "nightly" ] && [ "$CURRENT_BRANCH" != "nightly" ]; then
  echo -e "\e[33mYou are about to switch your mailcow Updates to the unstable (nightly) branch.\e[0m"
  sleep 1
  echo -e "\e[33mBefore you do: Please take a backup of all components to ensure that no Data is lost...\e[0m"
  sleep 1
  echo -e "\e[31mWARNING: A switch to nightly is possible any time. But a switch back (to master) isn't.\e[0m"
  read -r -p "Are you sure you that want to continue upgrading to the unstable (nightly) branch? [y/N] " response
  if [[ ! "${response}" =~ ^([yY][eE][sS]|[yY])+$ ]]; then
    echo "OK. If you prepared yourself for that please run the update.sh Script with the --nightly parameter again to trigger this process here."
    exit 0
  fi
  BRANCH=$NEW_BRANCH
  DIFF_DIRECTORY=update_diffs
  DIFF_FILE=${DIFF_DIRECTORY}/diff_before_upgrade_to_nightly_$(date +"%Y-%m-%d-%H-%M-%S")
  mv diff_before_upgrade* ${DIFF_DIRECTORY}/ 2> /dev/null
  if ! git diff-index --quiet HEAD; then
    echo -e "\e[32mSaving diff to ${DIFF_FILE}...\e[0m"
    mkdir -p ${DIFF_DIRECTORY}
    git diff "${BRANCH}" --stat > "${DIFF_FILE}"
    git diff "${BRANCH}" >> "${DIFF_FILE}"
  fi
  git fetch origin
  git checkout -f "${BRANCH}"
fi

if [ ! "$DEV" ]; then
  echo -e "\e[32mChecking for newer update script...\e[0m"
  SHA1_1="$(sha1sum update.sh)"
  git fetch origin #${BRANCH}
  git checkout "origin/${BRANCH}" update.sh
  SHA1_2=$(sha1sum update.sh)
  if [[ "${SHA1_1}" != "${SHA1_2}" ]]; then
    echo "update.sh changed, please run this script again, exiting."
    chmod +x update.sh
    exit 2
  fi
fi

if [ ! "$FORCE" ]; then
  read -r -p "Are you sure you want to update mailcow: dockerized? All containers will be stopped. [y/N] " response
  if [[ ! "${response}" =~ ^([yY][eE][sS]|[yY])+$ ]]; then
    echo "OK, exiting."
    exit 0
  fi
  migrate_docker_nat
fi

remove_obsolete_nginx_ports

echo -e "\e[32mValidating docker-compose stack configuration...\e[0m"
sed -i 's/HTTPS_BIND:-:/HTTPS_BIND:-/g' docker-compose.yml
sed -i 's/HTTP_BIND:-:/HTTP_BIND:-/g' docker-compose.yml
if ! $COMPOSE_COMMAND config -q; then
  echo -e "\e[31m\nOh no, something went wrong. Please check the error message above.\e[0m"
  exit 1
fi

echo -e "\e[32mChecking for conflicting bridges...\e[0m"
MAILCOW_BRIDGE=$($COMPOSE_COMMAND config | grep -i com.docker.network.bridge.name | cut -d':' -f2)
while read NAT_ID; do
  iptables -t nat -D POSTROUTING "$NAT_ID"
done < <(iptables -L -vn -t nat --line-numbers | grep "$IPV4_NETWORK" | grep -E 'MASQUERADE.*all' | grep -v "${MAILCOW_BRIDGE}" | cut -d' ' -f1)

DIFF_DIRECTORY=update_diffs
DIFF_FILE=${DIFF_DIRECTORY}/diff_before_update_$(date +"%Y-%m-%d-%H-%M-%S")
mv diff_before_update* ${DIFF_DIRECTORY}/ 2> /dev/null
if ! git diff-index --quiet HEAD; then
  echo -e "\e[32mSaving diff to ${DIFF_FILE}...\e[0m"
  mkdir -p ${DIFF_DIRECTORY}
  git diff --stat > "${DIFF_FILE}"
  git diff >> "${DIFF_FILE}"
fi

echo -e "\e[32mPrefetching images...\e[0m"
prefetch_images

echo -e "\e[32mStopping mailcow...\e[0m"
sleep 2
MAILCOW_CONTAINERS=($($COMPOSE_COMMAND ps -q))
$COMPOSE_COMMAND down
echo -e "\e[32mChecking for remaining containers...\e[0m"
sleep 2
for container in "${MAILCOW_CONTAINERS[@]}"; do
  docker rm -f "$container" 2> /dev/null
done

[[ -f data/conf/nginx/ZZZ-ejabberd.conf ]] && rm data/conf/nginx/ZZZ-ejabberd.conf
migrate_solr_config_options
adapt_new_options

# Silently fixing remote url from andryyy to mailcow
# git remote set-url origin https://github.com/mailcow/mailcow-dockerized

DEFAULT_REPO="https://github.com/mailcow/mailcow-dockerized"
CURRENT_REPO=$(git config --get remote.origin.url)
if [ "$CURRENT_REPO" != "$DEFAULT_REPO" ]; then
  echo "The Repository currently used is not the default Mailcow Repository."
  echo "Currently Repository: $CURRENT_REPO"
  echo "Default Repository:   $DEFAULT_REPO"
  if [ ! "$FORCE" ]; then
    read -r -p "Should it be changed back to default? [y/N] " repo_response
    if [[ "$repo_response" =~ ^([yY][eE][sS]|[yY])+$ ]]; then
      git remote set-url origin $DEFAULT_REPO
    fi
  else
      echo "Running in forced mode... setting Repo to default!"
      git remote set-url origin $DEFAULT_REPO
  fi
fi

if [ ! "$DEV" ]; then
  echo -e "\e[32mCommitting current status...\e[0m"
  [[ -z "$(git config user.name)" ]] && git config user.name moo
  [[ -z "$(git config user.email)" ]] && git config user.email moo@cow.moo
  [[ ! -z $(git ls-files data/conf/rspamd/override.d/worker-controller-password.inc) ]] && git rm data/conf/rspamd/override.d/worker-controller-password.inc
  git add -u
  git commit -am "Before update on ${DATE}" > /dev/null
  echo -e "\e[32mFetching updated code from remote...\e[0m"
  git fetch origin #${BRANCH}
  echo -e "\e[32mMerging local with remote code (recursive, strategy: \"${MERGE_STRATEGY:-theirs}\", options: \"patience\"...\e[0m"
  git config merge.defaultToUpstream true
  git merge -X"${MERGE_STRATEGY:-theirs}" -Xpatience -m "After update on ${DATE}"
  # Need to use a variable to not pass return codes of if checks
  MERGE_RETURN=$?
  if [[ ${MERGE_RETURN} == 128 ]]; then
    echo -e "\e[31m\nOh no, what happened?\n=> You most likely added files to your local mailcow instance that were now added to the official mailcow repository. Please move them to another location before updating mailcow.\e[0m"
    exit 1
  elif [[ ${MERGE_RETURN} == 1 ]]; then
    echo -e "\e[93mPotential conflict, trying to fix...\e[0m"
    git status --porcelain | grep -E "UD|DU" | awk '{print $2}' | xargs rm -v
    git add -A
    git commit -m "After update on ${DATE}" > /dev/null
    git checkout .
    echo -e "\e[32mRemoved and recreated files if necessary.\e[0m"
  elif [[ ${MERGE_RETURN} != 0 ]]; then
    echo -e "\e[31m\nOh no, something went wrong. Please check the error message above.\e[0m"
    echo
    echo "Run $COMPOSE_COMMAND up -d to restart your stack without updates or try again after fixing the mentioned errors."
    exit 1
  fi
elif [ "$DEV" ]; then
  echo -e "\e[33mDEVELOPER MODE: Not creating a git diff and commiting it to prevent development stuff within a backup diff...\e[0m"
fi

echo -e "\e[32mFetching new images, if any...\e[0m"
sleep 2
$COMPOSE_COMMAND pull

# Fix missing SSL, does not overwrite existing files
[[ ! -d data/assets/ssl ]] && mkdir -p data/assets/ssl
cp -n -d data/assets/ssl-example/*.pem data/assets/ssl/

echo -e "Checking IPv6 settings... "
if grep -q 'SYSCTL_IPV6_DISABLED=1' mailcow.conf; then
  echo
  echo '!! IMPORTANT !!'
  echo
  echo 'SYSCTL_IPV6_DISABLED was removed due to complications. IPv6 can be disabled by editing "docker-compose.yml" and setting "enable_ipv6: true" to "enable_ipv6: false".'
  echo "This setting will only be active after a complete shutdown of mailcow by running $COMPOSE_COMMAND down followed by $COMPOSE_COMMAND up -d."
  echo
  echo '!! IMPORTANT !!'
  echo
  read -p "Press any key to continue..." < /dev/tty
fi

# Checking for old project name bug
sed -i --follow-symlinks 's#COMPOSEPROJECT_NAME#COMPOSE_PROJECT_NAME#g' mailcow.conf

# Fix Rspamd maps
if [ -f data/conf/rspamd/custom/global_from_blacklist.map ]; then
  mv data/conf/rspamd/custom/global_from_blacklist.map data/conf/rspamd/custom/global_smtp_from_blacklist.map
fi
if [ -f data/conf/rspamd/custom/global_from_whitelist.map ]; then
  mv data/conf/rspamd/custom/global_from_whitelist.map data/conf/rspamd/custom/global_smtp_from_whitelist.map
fi

# Fix deprecated metrics.conf
if [ -f "data/conf/rspamd/local.d/metrics.conf" ]; then
  if [ ! -z "$(git diff --name-only origin/master data/conf/rspamd/local.d/metrics.conf)" ]; then
    echo -e "\e[33mWARNING\e[0m - Please migrate your customizations of data/conf/rspamd/local.d/metrics.conf to actions.conf and groups.conf after this update."
    echo "The deprecated configuration file metrics.conf will be moved to metrics.conf_deprecated after updating mailcow."
  fi
  mv data/conf/rspamd/local.d/metrics.conf data/conf/rspamd/local.d/metrics.conf_deprecated
fi

# Set app_info.inc.php
if [ ${BRANCH} == "master" ]; then
  mailcow_git_version=$(git describe --tags $(git rev-list --tags --max-count=1))
elif [ ${BRANCH} == "nightly" ]; then
  mailcow_git_version=$(git rev-parse --short $(git rev-parse @{upstream}))
  mailcow_last_git_version=""
else
  mailcow_git_version=$(git rev-parse --short HEAD)
  mailcow_last_git_version=""
fi

mailcow_git_commit=$(git rev-parse "origin/${BRANCH}")
mailcow_git_commit_date=$(git log -1 --format=%ci @{upstream} )

if [ $? -eq 0 ]; then
  echo '<?php' > data/web/inc/app_info.inc.php
  echo '  $MAILCOW_GIT_VERSION="'$mailcow_git_version'";' >> data/web/inc/app_info.inc.php
  echo '  $MAILCOW_LAST_GIT_VERSION="";' >> data/web/inc/app_info.inc.php
  echo '  $MAILCOW_GIT_OWNER="mailcow";' >> data/web/inc/app_info.inc.php
  echo '  $MAILCOW_GIT_REPO="mailcow-dockerized";' >> data/web/inc/app_info.inc.php
  echo '  $MAILCOW_GIT_URL="https://github.com/mailcow/mailcow-dockerized";' >> data/web/inc/app_info.inc.php
  echo '  $MAILCOW_GIT_COMMIT="'$mailcow_git_commit'";' >> data/web/inc/app_info.inc.php
  echo '  $MAILCOW_GIT_COMMIT_DATE="'$mailcow_git_commit_date'";' >> data/web/inc/app_info.inc.php
  echo '  $MAILCOW_BRANCH="'$BRANCH'";' >> data/web/inc/app_info.inc.php
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
  echo '  $MAILCOW_BRANCH="'$BRANCH'";' >> data/web/inc/app_info.inc.php
  echo '  $MAILCOW_UPDATEDAT='$(date +%s)';' >> data/web/inc/app_info.inc.php
  echo '?>' >> data/web/inc/app_info.inc.php
  echo -e "\e[33mCannot determine current git repository version...\e[0m"
fi

if [[ ${SKIP_START} == "y" ]]; then
  echo -e "\e[33mNot starting mailcow, please run \"$COMPOSE_COMMAND up -d --remove-orphans\" to start mailcow.\e[0m"
else
  echo -e "\e[32mStarting mailcow...\e[0m"
  sleep 2
  $COMPOSE_COMMAND up -d --remove-orphans
fi

echo -e "\e[32mCollecting garbage...\e[0m"
docker_garbage

# Run post-update-hook
if [ -f "${SCRIPT_DIR}/post_update_hook.sh" ]; then
  bash "${SCRIPT_DIR}/post_update_hook.sh"
fi

# echo "In case you encounter any problem, hard-reset to a state before updating mailcow:"
# echo
# git reflog --color=always | grep "Before update on "
# echo
# echo "Use \"git reset --hard hash-on-the-left\" and run $COMPOSE_COMMAND up -d afterwards."
