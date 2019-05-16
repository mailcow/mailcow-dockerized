#!/bin/bash

trap "exit" INT TERM
trap "kill 0" EXIT

# Prepare
BACKGROUND_TASKS=()
echo "Waiting for containers to settle..."
sleep 10

if [[ "${USE_WATCHDOG}" =~ ^([nN][oO]|[nN])+$ ]]; then
  echo -e "$(date) - USE_WATCHDOG=n, skipping watchdog..."
  sleep 365d
  exec $(readlink -f "$0")
fi

# Checks pipe their corresponding container name in this pipe
if [[ ! -p /tmp/com_pipe ]]; then
  mkfifo /tmp/com_pipe
fi

# Common functions
progress() {
  SERVICE=${1}
  TOTAL=${2}
  CURRENT=${3}
  DIFF=${4}
  [[ -z ${DIFF} ]] && DIFF=0
  [[ -z ${TOTAL} || -z ${CURRENT} ]] && return
  [[ ${CURRENT} -gt ${TOTAL} ]] && return
  [[ ${CURRENT} -lt 0 ]] && CURRENT=0
  PERCENT=$(( 200 * ${CURRENT} / ${TOTAL} % 2 + 100 * ${CURRENT} / ${TOTAL} ))
  redis-cli -h redis LPUSH WATCHDOG_LOG "{\"time\":\"$(date +%s)\",\"service\":\"${SERVICE}\",\"lvl\":\"${PERCENT}\",\"hpnow\":\"${CURRENT}\",\"hptotal\":\"${TOTAL}\",\"hpdiff\":\"${DIFF}\"}" > /dev/null
  log_msg "${SERVICE} health level: ${PERCENT}% (${CURRENT}/${TOTAL}), health trend: ${DIFF}" no_redis
  # Return 10 to indicate a dead service
  [ ${CURRENT} -le 0 ] && return 10
}

log_msg() {
  if [[ ${2} != "no_redis" ]]; then
    redis-cli -h redis LPUSH WATCHDOG_LOG "{\"time\":\"$(date +%s)\",\"message\":\"$(printf '%s' "${1}" | \
      tr '\r\n%&;$"_[]{}-' ' ')\"}" > /dev/null
  fi
  echo $(date) $(printf '%s\n' "${1}")
}

function mail_error() {
  [[ -z ${1} ]] && return 1
  [[ -z ${2} ]] && BODY="Service was restarted on $(date), please check your mailcow installation." || BODY="$(date) - ${2}"
  WATCHDOG_NOTIFY_EMAIL=$(echo "${WATCHDOG_NOTIFY_EMAIL}" | sed 's/"//;s|"$||')
  IFS=',' read -r -a MAIL_RCPTS <<< "${WATCHDOG_NOTIFY_EMAIL}"
  for rcpt in "${MAIL_RCPTS[@]}"; do
    RCPT_DOMAIN=
    RCPT_MX=
    RCPT_DOMAIN=$(echo ${rcpt} | awk -F @ {'print $NF'})
    RCPT_MX=$(dig +short ${RCPT_DOMAIN} mx | sort -n | awk '{print $2; exit}')
    if [[ -z ${RCPT_MX} ]]; then
      log_msg "Cannot determine MX for ${rcpt}, skipping email notification..."
      return 1
    fi
    [[ ${1} == "watchdog-mailcow" ]] && SUBJECT="Watchdog started" || SUBJECT="Watchdog: ${1} hit the error rate limit"
    [ -f "/tmp/${1}" ] && ATTACH="--attach /tmp/${1}@text/plain" || ATTACH=
    ./smtp-cli --missing-modules-ok \
      --subject="${SUBJECT}" \
      --body-plain="${BODY}" \
      --to=${rcpt} \
      --from="watchdog@${MAILCOW_HOSTNAME}" \
      --server="${RCPT_MX}" \
      --hello-host=${MAILCOW_HOSTNAME} \
      ${ATTACH}
    log_msg "Sent notification email to ${rcpt}"
  done
}

get_container_ip() {
  # ${1} is container
  CONTAINER_ID=()
  CONTAINER_IPS=()
  CONTAINER_IP=
  LOOP_C=1
  until [[ ${CONTAINER_IP} =~ ^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$ ]] || [[ ${LOOP_C} -gt 5 ]]; do
    if [ ${IP_BY_DOCKER_API} -eq 0 ]; then
      CONTAINER_IP=$(dig a "${1}" +short)
    else
      sleep 0.5
      # get long container id for exact match
      CONTAINER_ID=($(curl --silent --insecure https://dockerapi/containers/json | jq -r ".[] | {name: .Config.Labels[\"com.docker.compose.service\"], id: .Id}" | jq -rc "select( .name | tostring == \"${1}\") | .id"))
      # returned id can have multiple elements (if scaled), shuffle for random test
      CONTAINER_ID=($(printf "%s\n" "${CONTAINER_ID[@]}" | shuf))
      if [[ ! -z ${CONTAINER_ID} ]]; then
        for matched_container in "${CONTAINER_ID[@]}"; do
          CONTAINER_IPS=($(curl --silent --insecure https://dockerapi/containers/${matched_container}/json | jq -r '.NetworkSettings.Networks[].IPAddress')) 
          for ip_match in "${CONTAINER_IPS[@]}"; do
            # grep will do nothing if one of these vars is empty
            [[ -z ${ip_match} ]] && continue
            [[ -z ${IPV4_NETWORK} ]] && continue
            # only return ips that are part of our network
            if ! grep -q ${IPV4_NETWORK} <(echo ${ip_match}); then
              continue
            else
              CONTAINER_IP=${ip_match}
              break
            fi
          done
          [[ ! -z ${CONTAINER_IP} ]] && break
        done
      fi
    fi
    LOOP_C=$((LOOP_C + 1))
  done
  [[ ${LOOP_C} -gt 5 ]] && echo 240.0.0.0 || echo ${CONTAINER_IP}
}

nginx_checks() {
  err_count=0
  diff_c=0
  THRESHOLD=16
  # Reduce error count by 2 after restarting an unhealthy container
  trap "[ ${err_count} -gt 1 ] && err_count=$(( ${err_count} - 2 ))" USR1
  while [ ${err_count} -lt ${THRESHOLD} ]; do
    touch /tmp/nginx-mailcow; echo "$(tail -50 /tmp/nginx-mailcow)" > /tmp/nginx-mailcow
    host_ip=$(get_container_ip nginx-mailcow)
    err_c_cur=${err_count}
    /usr/lib/nagios/plugins/check_http -4 -H ${host_ip} -u / -p 8081 2>> /tmp/nginx-mailcow 1>&2; err_count=$(( ${err_count} + $? ))
    [ ${err_c_cur} -eq ${err_count} ] && [ ! $((${err_count} - 1)) -lt 0 ] && err_count=$((${err_count} - 1)) diff_c=1
    [ ${err_c_cur} -ne ${err_count} ] && diff_c=$(( ${err_c_cur} - ${err_count} ))
    progress "Nginx" ${THRESHOLD} $(( ${THRESHOLD} - ${err_count} )) ${diff_c}
    if [[ $? == 10 ]]; then
      diff_c=0
      sleep 1
    else
      diff_c=0
      sleep $(( ( RANDOM % 30 )  + 10 ))
    fi
  done
  return 1
}

unbound_checks() {
  err_count=0
  diff_c=0
  THRESHOLD=8
  # Reduce error count by 2 after restarting an unhealthy container
  trap "[ ${err_count} -gt 1 ] && err_count=$(( ${err_count} - 2 ))" USR1
  while [ ${err_count} -lt ${THRESHOLD} ]; do
    touch /tmp/unbound-mailcow; echo "$(tail -50 /tmp/unbound-mailcow)" > /tmp/unbound-mailcow
    host_ip=$(get_container_ip unbound-mailcow)
    err_c_cur=${err_count}
    /usr/lib/nagios/plugins/check_dns -s ${host_ip} -H stackoverflow.com 2>> /tmp/unbound-mailcow 1>&2; err_count=$(( ${err_count} + $? ))
    DNSSEC=$(dig com +dnssec | egrep 'flags:.+ad')
    if [[ -z ${DNSSEC} ]]; then
      echo "DNSSEC failure" 2>> /tmp/unbound-mailcow 1>&2
      err_count=$(( ${err_count} + 1))
    else
      echo "DNSSEC check succeeded" 2>> /tmp/unbound-mailcow 1>&2
    fi
    [ ${err_c_cur} -eq ${err_count} ] && [ ! $((${err_count} - 1)) -lt 0 ] && err_count=$((${err_count} - 1)) diff_c=1
    [ ${err_c_cur} -ne ${err_count} ] && diff_c=$(( ${err_c_cur} - ${err_count} ))
    progress "Unbound" ${THRESHOLD} $(( ${THRESHOLD} - ${err_count} )) ${diff_c}
    if [[ $? == 10 ]]; then
      diff_c=0
      sleep 1
    else
      diff_c=0
      sleep $(( ( RANDOM % 30 )  + 10 ))
    fi
  done
  return 1
}

mysql_checks() {
  err_count=0
  diff_c=0
  THRESHOLD=12
  # Reduce error count by 2 after restarting an unhealthy container
  trap "[ ${err_count} -gt 1 ] && err_count=$(( ${err_count} - 2 ))" USR1
  while [ ${err_count} -lt ${THRESHOLD} ]; do
    touch /tmp/mysql-mailcow; echo "$(tail -50 /tmp/mysql-mailcow)" > /tmp/mysql-mailcow
    host_ip=$(get_container_ip mysql-mailcow)
    err_c_cur=${err_count}
    /usr/lib/nagios/plugins/check_mysql -s /var/run/mysqld/mysqld.sock -u ${DBUSER} -p ${DBPASS} -d ${DBNAME} 2>> /tmp/mysql-mailcow 1>&2; err_count=$(( ${err_count} + $? ))
    /usr/lib/nagios/plugins/check_mysql_query -s /var/run/mysqld/mysqld.sock -u ${DBUSER} -p ${DBPASS} -d ${DBNAME} -q "SELECT COUNT(*) FROM information_schema.tables" 2>> /tmp/mysql-mailcow 1>&2; err_count=$(( ${err_count} + $? ))
    [ ${err_c_cur} -eq ${err_count} ] && [ ! $((${err_count} - 1)) -lt 0 ] && err_count=$((${err_count} - 1)) diff_c=1
    [ ${err_c_cur} -ne ${err_count} ] && diff_c=$(( ${err_c_cur} - ${err_count} ))
    progress "MySQL/MariaDB" ${THRESHOLD} $(( ${THRESHOLD} - ${err_count} )) ${diff_c}
    if [[ $? == 10 ]]; then
      diff_c=0
      sleep 1
    else
      diff_c=0
      sleep $(( ( RANDOM % 30 )  + 10 ))
    fi
  done
  return 1
}

sogo_checks() {
  err_count=0
  diff_c=0
  THRESHOLD=10
  # Reduce error count by 2 after restarting an unhealthy container
  trap "[ ${err_count} -gt 1 ] && err_count=$(( ${err_count} - 2 ))" USR1
  while [ ${err_count} -lt ${THRESHOLD} ]; do
    touch /tmp/sogo-mailcow; echo "$(tail -50 /tmp/sogo-mailcow)" > /tmp/sogo-mailcow
    host_ip=$(get_container_ip sogo-mailcow)
    err_c_cur=${err_count}
    /usr/lib/nagios/plugins/check_http -4 -H ${host_ip} -u /SOGo.index/ -p 20000 -R "SOGo\.MainUI" 2>> /tmp/sogo-mailcow 1>&2; err_count=$(( ${err_count} + $? ))
    [ ${err_c_cur} -eq ${err_count} ] && [ ! $((${err_count} - 1)) -lt 0 ] && err_count=$((${err_count} - 1)) diff_c=1
    [ ${err_c_cur} -ne ${err_count} ] && diff_c=$(( ${err_c_cur} - ${err_count} ))
    progress "SOGo" ${THRESHOLD} $(( ${THRESHOLD} - ${err_count} )) ${diff_c}
    if [[ $? == 10 ]]; then
      diff_c=0
      sleep 1
    else
      diff_c=0
      sleep $(( ( RANDOM % 30 )  + 10 ))
    fi
  done
  return 1
}

postfix_checks() {
  err_count=0
  diff_c=0
  THRESHOLD=8
  # Reduce error count by 2 after restarting an unhealthy container
  trap "[ ${err_count} -gt 1 ] && err_count=$(( ${err_count} - 2 ))" USR1
  while [ ${err_count} -lt ${THRESHOLD} ]; do
    touch /tmp/postfix-mailcow; echo "$(tail -50 /tmp/postfix-mailcow)" > /tmp/postfix-mailcow
    host_ip=$(get_container_ip postfix-mailcow)
    err_c_cur=${err_count}
    /usr/lib/nagios/plugins/check_smtp -4 -H ${host_ip} -p 589 -f "watchdog@invalid" -C "RCPT TO:null@localhost" -C DATA -C . -R 250 2>> /tmp/postfix-mailcow 1>&2; err_count=$(( ${err_count} + $? ))
    /usr/lib/nagios/plugins/check_smtp -4 -H ${host_ip} -p 589 -S 2>> /tmp/postfix-mailcow 1>&2; err_count=$(( ${err_count} + $? ))
    [ ${err_c_cur} -eq ${err_count} ] && [ ! $((${err_count} - 1)) -lt 0 ] && err_count=$((${err_count} - 1)) diff_c=1
    [ ${err_c_cur} -ne ${err_count} ] && diff_c=$(( ${err_c_cur} - ${err_count} ))
    progress "Postfix" ${THRESHOLD} $(( ${THRESHOLD} - ${err_count} )) ${diff_c}
    if [[ $? == 10 ]]; then
      diff_c=0
      sleep 1
    else
      diff_c=0
      sleep $(( ( RANDOM % 30 )  + 10 ))
    fi
  done
  return 1
}

clamd_checks() {
  err_count=0
  diff_c=0
  THRESHOLD=15
  # Reduce error count by 2 after restarting an unhealthy container
  trap "[ ${err_count} -gt 1 ] && err_count=$(( ${err_count} - 2 ))" USR1
  while [ ${err_count} -lt ${THRESHOLD} ]; do
    touch /tmp/clamd-mailcow; echo "$(tail -50 /tmp/clamd-mailcow)" > /tmp/clamd-mailcow
    host_ip=$(get_container_ip clamd-mailcow)
    err_c_cur=${err_count}
    /usr/lib/nagios/plugins/check_clamd -4 -H ${host_ip} 2>> /tmp/clamd-mailcow 1>&2; err_count=$(( ${err_count} + $? ))
    [ ${err_c_cur} -eq ${err_count} ] && [ ! $((${err_count} - 1)) -lt 0 ] && err_count=$((${err_count} - 1)) diff_c=1
    [ ${err_c_cur} -ne ${err_count} ] && diff_c=$(( ${err_c_cur} - ${err_count} ))
    progress "Clamd" ${THRESHOLD} $(( ${THRESHOLD} - ${err_count} )) ${diff_c}
    if [[ $? == 10 ]]; then
      diff_c=0
      sleep 1
    else
      diff_c=0
      sleep $(( ( RANDOM % 30 )  + 30 ))
    fi
  done
  return 1
}

dovecot_checks() {
  err_count=0
  diff_c=0
  THRESHOLD=20
  # Reduce error count by 2 after restarting an unhealthy container
  trap "[ ${err_count} -gt 1 ] && err_count=$(( ${err_count} - 2 ))" USR1
  while [ ${err_count} -lt ${THRESHOLD} ]; do
    touch /tmp/dovecot-mailcow; echo "$(tail -50 /tmp/dovecot-mailcow)" > /tmp/dovecot-mailcow
    host_ip=$(get_container_ip dovecot-mailcow)
    err_c_cur=${err_count}
    /usr/lib/nagios/plugins/check_smtp -4 -H ${host_ip} -p 24 -f "watchdog@invalid" -C "RCPT TO:<watchdog@invalid>" -L -R "User doesn't exist" 2>> /tmp/dovecot-mailcow 1>&2; err_count=$(( ${err_count} + $? ))
    /usr/lib/nagios/plugins/check_imap -4 -H ${host_ip} -p 993 -S -e "OK " 2>> /tmp/dovecot-mailcow 1>&2; err_count=$(( ${err_count} + $? ))
    /usr/lib/nagios/plugins/check_imap -4 -H ${host_ip} -p 143 -e "OK " 2>> /tmp/dovecot-mailcow 1>&2; err_count=$(( ${err_count} + $? ))
    /usr/lib/nagios/plugins/check_tcp -4 -H ${host_ip} -p 10001 -e "VERSION" 2>> /tmp/dovecot-mailcow 1>&2; err_count=$(( ${err_count} + $? ))
    /usr/lib/nagios/plugins/check_tcp -4 -H ${host_ip} -p 4190 -e "Dovecot ready" 2>> /tmp/dovecot-mailcow 1>&2; err_count=$(( ${err_count} + $? ))
    [ ${err_c_cur} -eq ${err_count} ] && [ ! $((${err_count} - 1)) -lt 0 ] && err_count=$((${err_count} - 1)) diff_c=1
    [ ${err_c_cur} -ne ${err_count} ] && diff_c=$(( ${err_c_cur} - ${err_count} ))
    progress "Dovecot" ${THRESHOLD} $(( ${THRESHOLD} - ${err_count} )) ${diff_c}
    if [[ $? == 10 ]]; then
      diff_c=0
      sleep 1
    else
      diff_c=0
      sleep $(( ( RANDOM % 30 )  + 10 ))
    fi
  done
  return 1
}

phpfpm_checks() {
  err_count=0
  diff_c=0
  THRESHOLD=5
  # Reduce error count by 2 after restarting an unhealthy container
  trap "[ ${err_count} -gt 1 ] && err_count=$(( ${err_count} - 2 ))" USR1
  while [ ${err_count} -lt ${THRESHOLD} ]; do
    touch /tmp/php-fpm-mailcow; echo "$(tail -50 /tmp/php-fpm-mailcow)" > /tmp/php-fpm-mailcow
    host_ip=$(get_container_ip php-fpm-mailcow)
    err_c_cur=${err_count}
    /usr/lib/nagios/plugins/check_tcp -H ${host_ip} -p 9001 2>> /tmp/php-fpm-mailcow 1>&2; err_count=$(( ${err_count} + $? ))
    /usr/lib/nagios/plugins/check_tcp -H ${host_ip} -p 9002 2>> /tmp/php-fpm-mailcow 1>&2; err_count=$(( ${err_count} + $? ))
    [ ${err_c_cur} -eq ${err_count} ] && [ ! $((${err_count} - 1)) -lt 0 ] && err_count=$((${err_count} - 1)) diff_c=1
    [ ${err_c_cur} -ne ${err_count} ] && diff_c=$(( ${err_c_cur} - ${err_count} ))
    progress "PHP-FPM" ${THRESHOLD} $(( ${THRESHOLD} - ${err_count} )) ${diff_c}
    if [[ $? == 10 ]]; then
      diff_c=0
      sleep 1
    else
      diff_c=0
      sleep $(( ( RANDOM % 30 )  + 10 ))
    fi
  done
  return 1
}

ratelimit_checks() {
  err_count=0
  diff_c=0
  THRESHOLD=1
  RL_LOG_STATUS=$(redis-cli -h redis LRANGE RL_LOG 0 0 | jq .qid)
  # Reduce error count by 2 after restarting an unhealthy container
  trap "[ ${err_count} -gt 1 ] && err_count=$(( ${err_count} - 2 ))" USR1
  while [ ${err_count} -lt ${THRESHOLD} ]; do
    err_c_cur=${err_count}
    RL_LOG_STATUS_PREV=${RL_LOG_STATUS}
    RL_LOG_STATUS=$(redis-cli -h redis LRANGE RL_LOG 0 0 | jq .qid)
    if [[ ${RL_LOG_STATUS_PREV} != ${RL_LOG_STATUS} ]]; then
      err_count=$(( ${err_count} + 1 ))
    fi
    [ ${err_c_cur} -eq ${err_count} ] && [ ! $((${err_count} - 1)) -lt 0 ] && err_count=$((${err_count} - 1)) diff_c=1
    [ ${err_c_cur} -ne ${err_count} ] && diff_c=$(( ${err_c_cur} - ${err_count} ))
    progress "Ratelimit" ${THRESHOLD} $(( ${THRESHOLD} - ${err_count} )) ${diff_c}
    if [[ $? == 10 ]]; then
      diff_c=0
      sleep 1
    else
      diff_c=0
      sleep $(( ( RANDOM % 30 )  + 10 ))
    fi
  done
  return 1
}

acme_checks() {
  err_count=0
  diff_c=0
  THRESHOLD=1
  ACME_LOG_STATUS=$(redis-cli -h redis GET ACME_FAIL_TIME)
  if [[ -z "${ACME_LOG_STATUS}" ]]; then
    redis-cli -h redis SET ACME_FAIL_TIME 0
    ACME_LOG_STATUS=0
  fi
  # Reduce error count by 2 after restarting an unhealthy container
  trap "[ ${err_count} -gt 1 ] && err_count=$(( ${err_count} - 2 ))" USR1
  while [ ${err_count} -lt ${THRESHOLD} ]; do
    err_c_cur=${err_count}
    ACME_LOG_STATUS_PREV=${ACME_LOG_STATUS}
    ACME_LOG_STATUS=$(redis-cli -h redis GET ACME_FAIL_TIME)
    if [[ ${ACME_LOG_STATUS_PREV} != ${ACME_LOG_STATUS} ]]; then
      err_count=$(( ${err_count} + 1 ))
    fi
    [ ${err_c_cur} -eq ${err_count} ] && [ ! $((${err_count} - 1)) -lt 0 ] && err_count=$((${err_count} - 1)) diff_c=1
    [ ${err_c_cur} -ne ${err_count} ] && diff_c=$(( ${err_c_cur} - ${err_count} ))
    progress "ACME" ${THRESHOLD} $(( ${THRESHOLD} - ${err_count} )) ${diff_c}
    if [[ $? == 10 ]]; then
      diff_c=0
      sleep 1
    else
      diff_c=0
      sleep $(( ( RANDOM % 30 )  + 10 ))
    fi
  done
  return 1
}

ipv6nat_checks() {
  err_count=0
  diff_c=0
  THRESHOLD=1
  # Reduce error count by 2 after restarting an unhealthy container
  trap "[ ${err_count} -gt 1 ] && err_count=$(( ${err_count} - 2 ))" USR1
  while [ ${err_count} -lt ${THRESHOLD} ]; do
    err_c_cur=${err_count}
    CONTAINERS=$(curl --silent --insecure https://dockerapi/containers/json)
    IPV6NAT_CONTAINER_ID=$(echo ${CONTAINERS} | jq -r ".[] | {name: .Config.Labels[\"com.docker.compose.service\"], id: .Id}" | jq -rc "select( .name | tostring | contains(\"ipv6nat-mailcow\")) | .id")
    if [[ ! -z ${IPV6NAT_CONTAINER_ID} ]]; then
      LATEST_STARTED="$(echo ${CONTAINERS} | jq -r ".[] | {name: .Config.Labels[\"com.docker.compose.service\"], StartedAt: .State.StartedAt}" | jq -rc "select( .name | tostring | contains(\"ipv6nat-mailcow\") | not)" | jq -rc .StartedAt | xargs -n1 date +%s -d | sort | tail -n1)"
      LATEST_IPV6NAT="$(echo ${CONTAINERS} | jq -r ".[] | {name: .Config.Labels[\"com.docker.compose.service\"], StartedAt: .State.StartedAt}" | jq -rc "select( .name | tostring | contains(\"ipv6nat-mailcow\"))" | jq -rc .StartedAt | xargs -n1 date +%s -d | sort | tail -n1)"
      DIFFERENCE_START_TIME=$(expr ${LATEST_IPV6NAT} - ${LATEST_STARTED} 2>/dev/null)
      if [[ "${DIFFERENCE_START_TIME}" -lt 30 ]]; then
        err_count=$(( ${err_count} + 1 ))
      fi
    fi
    [ ${err_c_cur} -eq ${err_count} ] && [ ! $((${err_count} - 1)) -lt 0 ] && err_count=$((${err_count} - 1)) diff_c=1
    [ ${err_c_cur} -ne ${err_count} ] && diff_c=$(( ${err_c_cur} - ${err_count} ))
    progress "IPv6 NAT" ${THRESHOLD} $(( ${THRESHOLD} - ${err_count} )) ${diff_c}
    if [[ $? == 10 ]]; then
      diff_c=0
      sleep 1
    else
      diff_c=0
      sleep 300
    fi
  done
  return 1
}


rspamd_checks() {
  err_count=0
  diff_c=0
  THRESHOLD=5
  # Reduce error count by 2 after restarting an unhealthy container
  trap "[ ${err_count} -gt 1 ] && err_count=$(( ${err_count} - 2 ))" USR1
  while [ ${err_count} -lt ${THRESHOLD} ]; do
    touch /tmp/rspamd-mailcow; echo "$(tail -50 /tmp/rspamd-mailcow)" > /tmp/rspamd-mailcow
    host_ip=$(get_container_ip rspamd-mailcow)
    err_c_cur=${err_count}
    SCORE=$(echo 'To: null@localhost
From: watchdog@localhost

Empty
' | usr/bin/curl -s --data-binary @- --unix-socket /var/lib/rspamd/rspamd.sock http://rspamd/scan | jq -rc .required_score)
    if [[ ${SCORE} != "9999" ]]; then
      echo "Rspamd settings check failed" 2>> /tmp/rspamd-mailcow 1>&2
      err_count=$(( ${err_count} + 1))
    else
      echo "Rspamd settings check succeeded" 2>> /tmp/rspamd-mailcow 1>&2
    fi
    [ ${err_c_cur} -eq ${err_count} ] && [ ! $((${err_count} - 1)) -lt 0 ] && err_count=$((${err_count} - 1)) diff_c=1
    [ ${err_c_cur} -ne ${err_count} ] && diff_c=$(( ${err_c_cur} - ${err_count} ))
    progress "Rspamd" ${THRESHOLD} $(( ${THRESHOLD} - ${err_count} )) ${diff_c}
    diff_c=0
    sleep $(( ( RANDOM % 30 )  + 10 ))
  done
  return 1
}

# Notify about start
[[ ! -z ${WATCHDOG_NOTIFY_EMAIL} ]] && mail_error "watchdog-mailcow" "Watchdog started monitoring mailcow."

# Create watchdog agents
(
while true; do
  if ! nginx_checks; then
    log_msg "Nginx hit error limit"
    echo nginx-mailcow > /tmp/com_pipe
  fi
done
) &
BACKGROUND_TASKS+=($!)

(
while true; do
  if ! mysql_checks; then
    log_msg "MySQL hit error limit"
    echo mysql-mailcow > /tmp/com_pipe
  fi
done
) &
BACKGROUND_TASKS+=($!)

(
while true; do
  if ! phpfpm_checks; then
    log_msg "PHP-FPM hit error limit"
    echo php-fpm-mailcow > /tmp/com_pipe
  fi
done
) &
BACKGROUND_TASKS+=($!)

(
while true; do
  if ! sogo_checks; then
    log_msg "SOGo hit error limit"
    echo sogo-mailcow > /tmp/com_pipe
  fi
done
) &
BACKGROUND_TASKS+=($!)

if [ ${CHECK_UNBOUND} -eq 1 ]; then
(
while true; do
  if ! unbound_checks; then
    log_msg "Unbound hit error limit"
    echo unbound-mailcow > /tmp/com_pipe
  fi
done
) &
BACKGROUND_TASKS+=($!)
fi

if [[ "${SKIP_CLAMD}" =~ ^([nN][oO]|[nN])+$ ]]; then
(
while true; do
  if ! clamd_checks; then
    log_msg "Clamd hit error limit"
    echo clamd-mailcow > /tmp/com_pipe
  fi
done
) &
BACKGROUND_TASKS+=($!)
fi

(
while true; do
  if ! postfix_checks; then
    log_msg "Postfix hit error limit"
    echo postfix-mailcow > /tmp/com_pipe
  fi
done
) &
BACKGROUND_TASKS+=($!)

(
while true; do
  if ! dovecot_checks; then
    log_msg "Dovecot hit error limit"
    echo dovecot-mailcow > /tmp/com_pipe
  fi
done
) &
BACKGROUND_TASKS+=($!)

(
while true; do
  if ! rspamd_checks; then
    log_msg "Rspamd hit error limit"
    echo rspamd-mailcow > /tmp/com_pipe
  fi
done
) &
BACKGROUND_TASKS+=($!)

(
while true; do
  if ! ratelimit_checks; then
    log_msg "Ratelimit hit error limit"
    echo ratelimit > /tmp/com_pipe
  fi
done
) &
BACKGROUND_TASKS+=($!)

(
while true; do
  if ! acme_checks; then
    log_msg "ACME client hit error limit"
    echo acme-mailcow > /tmp/com_pipe
  fi
done
) &
BACKGROUND_TASKS+=($!)

(
while true; do
  if ! ipv6nat_checks; then
    log_msg "IPv6 NAT warning: ipv6nat-mailcow container was not started at least 30s after siblings (not an error)"
    echo ipv6nat-mailcow > /tmp/com_pipe
  fi
done
) &
BACKGROUND_TASKS+=($!)

# Monitor watchdog agents, stop script when agents fails and wait for respawn by Docker (restart:always:n)
(
while true; do
  for bg_task in ${BACKGROUND_TASKS[*]}; do
    if ! kill -0 ${bg_task} 1>&2; then
      log_msg "Worker ${bg_task} died, stopping watchdog and waiting for respawn..."
      kill -TERM 1
    fi
    sleep 10
  done
done
) &

# Monitor dockerapi
(
while true; do
  while nc -z dockerapi 443; do
    sleep 3
  done
  log_msg "Cannot find dockerapi-mailcow, waiting to recover..."
  kill -STOP ${BACKGROUND_TASKS[*]}
  until nc -z dockerapi 443; do
    sleep 3
  done
  kill -CONT ${BACKGROUND_TASKS[*]}
  kill -USR1 ${BACKGROUND_TASKS[*]}
done
) &

# Restart container when threshold limit reached
while true; do
  CONTAINER_ID=
  HAS_INITDB=
  read com_pipe_answer </tmp/com_pipe
  if [ -s "/tmp/${com_pipe_answer}" ]; then
    cat "/tmp/${com_pipe_answer}"
  fi
  if [[ ${com_pipe_answer} == "ratelimit" ]]; then
    log_msg "At least one ratelimit was applied"
    [[ ! -z ${WATCHDOG_NOTIFY_EMAIL} ]] && mail_error "${com_pipe_answer}" "Please see mailcow UI logs for further information."
  elif [[ ${com_pipe_answer} == "acme-mailcow" ]]; then
    log_msg "acme-mailcow did not complete successfully"
    [[ ! -z ${WATCHDOG_NOTIFY_EMAIL} ]] && mail_error "${com_pipe_answer}" "Please check acme-mailcow for further information."
  elif [[ ${com_pipe_answer} =~ .+-mailcow ]] || [[ ${com_pipe_answer} == "ipv6nat-mailcow" ]]; then
    kill -STOP ${BACKGROUND_TASKS[*]}
    sleep 3
    CONTAINER_ID=$(curl --silent --insecure https://dockerapi/containers/json | jq -r ".[] | {name: .Config.Labels[\"com.docker.compose.service\"], id: .Id}" | jq -rc "select( .name | tostring | contains(\"${com_pipe_answer}\")) | .id")
    if [[ ! -z ${CONTAINER_ID} ]]; then
      if [[ "${com_pipe_answer}" == "php-fpm-mailcow" ]]; then
        HAS_INITDB=$(curl --silent --insecure -XPOST https://dockerapi/containers/${CONTAINER_ID}/top | jq '.msg.Processes[] | contains(["php -c /usr/local/etc/php -f /web/inc/init_db.inc.php"])' | grep true)
      fi
      S_RUNNING=$(($(date +%s) - $(curl --silent --insecure https://dockerapi/containers/${CONTAINER_ID}/json | jq .State.StartedAt | xargs -n1 date +%s -d)))
      if [ ${S_RUNNING} -lt 120 ]; then
        log_msg "Container is running for less than 120 seconds, skipping action..."
      elif [[ ! -z ${HAS_INITDB} ]]; then
        log_msg "Database is being initialized by php-fpm-mailcow, not restarting but delaying checks for a minute..."
        sleep 60
      else
        log_msg "Sending restart command to ${CONTAINER_ID}..."
        curl --silent --insecure -XPOST https://dockerapi/containers/${CONTAINER_ID}/restart
        if [[ ${com_pipe_answer} != "ipv6nat-mailcow" ]]; then
          [[ ! -z ${WATCHDOG_NOTIFY_EMAIL} ]] && mail_error "${com_pipe_answer}"
        fi
        log_msg "Wait for restarted container to settle and continue watching..."
        sleep 35
      fi
    fi
    kill -CONT ${BACKGROUND_TASKS[*]}
    kill -USR1 ${BACKGROUND_TASKS[*]}
  fi
done
