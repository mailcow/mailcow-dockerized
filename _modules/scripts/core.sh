#!/usr/bin/env bash
# _modules/scripts/core.sh
# THIS SCRIPT IS DESIGNED TO BE RUNNING BY MAILCOW SCRIPTS ONLY!
# DO NOT, AGAIN, NOT TRY TO RUN THIS SCRIPT STANDALONE!!!!!!

# ANSI color for red errors
RED='\e[31m'
GREEN='\e[32m'
YELLOW='\e[33m'
BLUE='\e[34m'
MAGENTA='\e[35m'
LIGHT_RED='\e[91m'
LIGHT_GREEN='\e[92m'
NC='\e[0m'

caller="${BASH_SOURCE[1]##*/}"

get_installed_tools(){
    for bin in openssl curl docker git awk sha1sum grep cut jq; do
        if [[ -z $(which ${bin}) ]]; then echo "Cannot find ${bin}, exiting..."; exit 1; fi
    done

    if grep --help 2>&1 | head -n 1 | grep -q -i "busybox"; then echo -e "${LIGHT_RED}BusyBox grep detected, please install gnu grep, \"apk add --no-cache --upgrade grep\"${NC}"; exit 1; fi
    # This will also cover sort
    if cp --help 2>&1 | head -n 1 | grep -q -i "busybox"; then echo -e "${LIGHT_RED}BusyBox cp detected, please install coreutils, \"apk add --no-cache --upgrade coreutils\"${NC}"; exit 1; fi
    if sed --help 2>&1 | head -n 1 | grep -q -i "busybox"; then echo -e "${LIGHT_RED}BusyBox sed detected, please install gnu sed, \"apk add --no-cache --upgrade sed\"${NC}"; exit 1; fi
}

get_docker_version(){
    # Check Docker Version (need at least 24.X)
    docker_version=$(docker version --format '{{.Server.Version}}' | cut -d '.' -f 1)
}

get_compose_type(){
    if docker compose > /dev/null 2>&1; then
        if docker compose version --short | grep -e "^2." -e "^v2." > /dev/null 2>&1; then
            COMPOSE_VERSION=native
            COMPOSE_COMMAND="docker compose"
            if [[ "$caller" == "update.sh" ]]; then
                sed -i 's/^DOCKER_COMPOSE_VERSION=.*/DOCKER_COMPOSE_VERSION=native/' "$SCRIPT_DIR/mailcow.conf"
            fi
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
            COMPOSE_COMMAND="docker-compose"
            if [[ "$caller" == "update.sh" ]]; then
                sed -i 's/^DOCKER_COMPOSE_VERSION=.*/DOCKER_COMPOSE_VERSION=standalone/' "$SCRIPT_DIR/mailcow.conf"
            fi
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
  COMPOSE_IMAGES=($(grep -oP "image: \K(ghcr\.io/)?mailcow.+" "${SCRIPT_DIR}/docker-compose.yml"))

  for existing_image in $(docker images --format "{{.ID}}:{{.Repository}}:{{.Tag}}" | grep -E '(mailcow/|ghcr\.io/mailcow/)'); do
      ID=$(echo "$existing_image" | cut -d ':' -f 1)
      REPOSITORY=$(echo "$existing_image" | cut -d ':' -f 2)
      TAG=$(echo "$existing_image" | cut -d ':' -f 3)

      if [[ "$REPOSITORY" == "mailcow/backup" || "$REPOSITORY" == "ghcr.io/mailcow/backup" ]]; then
          if [[ "$TAG" != "<none>" ]]; then
              continue
          fi
      fi

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

      if [ -z "$FORCE" ]; then
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