#!/usr/bin/env bash

SCRIPT_DIR="$( cd "$( dirname "$0" )" && pwd )"
WORKING_DIR=${SCRIPT_DIR}/postwhite_tmp
SPFTOOLS_DIR=${WORKING_DIR}/spf-tools
POSTWHITE_DIR=${WORKING_DIR}/postwhite
POSTWHITE_CONF=${POSTWHITE_DIR}/postwhite.conf

CUSTOM_HOSTS='"web.de gmx.net mail.de freenet.de arcor.de unity-mail.de protonmail.ch ionos.com strato.com t-online.de"'
STATIC_HOSTS=(
    "49.12.4.251 permit # checks.mailcow.email"
    "2a01:4f8:c17:7906::10 permit # checks.mailcow.email"
)

mkdir ${SCRIPT_DIR}/postwhite_tmp
git clone https://github.com/spf-tools/spf-tools.git ${SPFTOOLS_DIR}
git clone https://github.com/stevejenkins/postwhite.git ${POSTWHITE_DIR}

function set_config() {
    sudo sed -i "s@^\($1\s*=\s*\).*\$@\1$2@" ${POSTWHITE_CONF}
}

set_config custom_hosts "${CUSTOM_HOSTS}"
set_config reload_postfix no
set_config postfixpath /.
set_config spftoolspath ${WORKING_DIR}/spf-tools
set_config whitelist .${SCRIPT_DIR}/../data/conf/postfix/postscreen_access.cidr
set_config yahoo_static_hosts ${POSTWHITE_DIR}/yahoo_static_hosts.txt

#Fix URL for Yahoo!: https://github.com/stevejenkins/postwhite/issues/59
sudo sed -i \
      -e 's#yahoo_url="https://help.yahoo.com/kb/SLN23997.html"#yahoo_url="https://senders.yahooinc.com/outbound-mail-servers/"#' \
      -e 's#echo "ipv6:$line";#echo "ipv6:$line" | grep -v "ipv6:::";#' \
      -e 's#`command -v wget`#`command -v skip-wget`#' \
      ${POSTWHITE_DIR}/scrape_yahoo

cd ${POSTWHITE_DIR}
./postwhite ${POSTWHITE_CONF}

( IFS=$'\n'; echo "${STATIC_HOSTS[*]}" >> "${SCRIPT_DIR}/../data/conf/postfix/postscreen_access.cidr")

rm -r ${WORKING_DIR}