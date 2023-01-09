#!/bin/bash
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

source ${SCRIPT_DIR}/../mailcow.conf

if [ "${DOCKER_COMPOSE_VERSION}" == "standalone" ]; then
    _compose="${MAILCOW_DOCKER_COMPOSE:-"docker-compose"}"

    LATEST_COMPOSE=$(curl -Ls -w %{url_effective} -o /dev/null https://github.com/docker/compose/releases/latest) # redirect to latest release
    LATEST_COMPOSE=${LATEST_COMPOSE##*/v} #get the latest version from the redirect, excluding the "v" prefix
    COMPOSE_VERSION=$(${_compose} version --short)
    if [[ "$LATEST_COMPOSE" != "$COMPOSE_VERSION" ]]; then
        echo -e "\e[33mA new docker-compose Version is available: $LATEST_COMPOSE\e[0m"
        echo -e "\e[33mYour Version of '${_compose}' is: $COMPOSE_VERSION\e[0m"
    else
        echo -e "\e[32mYour docker-compose Version is up to date! Not updating it...\e[0m"
        exit 0
    fi
    read -r -p "Do you want to update your docker-compose Version? It will automatic upgrade your docker-compose installation (recommended)? [y/N] " updatecomposeresponse
    if [[ ! "${updatecomposeresponse}" =~ ^([yY][eE][sS]|[yY])+$ ]]; then
        echo "OK, not updating docker-compose."
        exit 0
    fi
    echo -e "\e[32mFetching new docker-compose (standalone) version...\e[0m"
    echo -e "\e[32mTrying to determine GLIBC version...\e[0m"
    if ldd --version > /dev/null; then
        GLIBC_V=$(ldd --version | grep -E '(GLIBC|GNU libc)' | rev | cut -d ' ' -f1 | rev | cut -d '.' -f2)
        if [ ! -z "${GLIBC_V}" ] && [ ${GLIBC_V} -gt 27 ]; then
            DC_DL_SUFFIX=
        else
            DC_DL_SUFFIX=legacy
        fi
    else
        DC_DL_SUFFIX=legacy
    fi
    sleep 1
    if [[ "${_compose}" == "docker-compose" ]] && [[ $(command -v pip 2>&1) && $(pip list --local 2>&1 | grep -v DEPRECATION | grep -c docker-compose) == 1 || $(command -v pip3 2>&1) && $(pip3 list --local 2>&1 | grep -v DEPRECATION | grep -c docker-compose) == 1 ]]; then
        echo -e "\e[33mFound a docker-compose Version installed with pip!\e[0m"
        echo -e "\e[31mPlease uninstall the pip Version of docker-compose since it doesn't support Versions higher than 1.29.2.\e[0m"
        sleep 2
        echo -e "\e[33mExiting...\e[0m"
        exit 1
        #prevent breaking a working docker-compose installed with pip
        #when ${_compose} is set to a non-default value, don't complain, since in some cases both v1 and v2 are used
    elif [[ $(curl -sL -w "%{http_code}" https://github.com/docker/compose/releases/latest -o /dev/null) == "200" ]]; then
        LATEST_COMPOSE=$(curl -Ls -w %{url_effective} -o /dev/null https://github.com/docker/compose/releases/latest) # redirect to latest release
        LATEST_COMPOSE=${LATEST_COMPOSE##*/} #get the latest version from the redirect, including the "v" prefix
        COMPOSE_VERSION=$(${_compose} version --short)
        if [[ "$LATEST_COMPOSE" != "$COMPOSE_VERSION" ]]; then
            COMPOSE_PATH=$(command -v ${_compose})
            if [[ -w ${COMPOSE_PATH} ]]; then
                curl -#L https://github.com/docker/compose/releases/download/${LATEST_COMPOSE}/docker-compose-$(uname -s)-$(uname -m) > $COMPOSE_PATH
                RC=$?
                if [[ $RC -eq 0 ]]; then
                    chmod +x $COMPOSE_PATH
                    echo -e "\e[32mYour Docker Compose (standalone) has been updated to: $LATEST_COMPOSE\e[0m"
                    exit 0
                else
                    echo -e "\e[31mError with updating $COMPOSE_PATH, please retry...\e[0m"
                    exit 1
                fi
            else
                echo -e "\e[33mWARNING: $COMPOSE_PATH is not writable, but new version $LATEST_COMPOSE is available (installed: $COMPOSE_VERSION)\e[0m"
                exit 1
            fi
        fi
    else
        echo -e "\e[33mCannot determine latest docker-compose version, skipping...\e[0m"
        exit 1
    fi

elif [ "${DOCKER_COMPOSE_VERSION}" == "native" ]; then
    echo -e "\e[31mYou are using the native Docker Compose Plugin. This Script is for the standalone Docker Compose Version only.\e[0m"
    sleep 2
    echo -e "\e[33mNotice: You'll have to update this Compose Version via your Package Manager manually!\e[0m"
    exit 1

else
    echo -e "\e[31mCan not read DOCKER_COMPOSE_VERSION variable from mailcow.conf! Is your mailcow up to date? Exiting...\e[0m"
    exit 1
fi
