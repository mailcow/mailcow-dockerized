#!/bin/bash

trap "exit" INT TERM
trap "kill 0" EXIT

# Prepare
BACKGROUND_TASKS=()

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
}

log_msg() {
  if [[ ${2} != "no_redis" ]]; then
    redis-cli -h redis LPUSH WATCHDOG_LOG "{\"time\":\"$(date +%s)\",\"message\":\"$(printf '%s' "${1}" | \
      tr '%&;$"_[]{}-\r\n' ' ')\"}" > /dev/null
  fi
  echo $(date) $(printf '%s\n' "${1}")
}

function mail_error() {
  [[ -z ${1} ]] && return 1
  [[ -z ${2} ]] && return 2
  RCPT_DOMAIN=$(echo ${1} | awk -F @ {'print $NF'})
  RCPT_MX=$(dig +short ${RCPT_DOMAIN} mx | sort -n | awk '{print $2; exit}')
  if [[ -z ${RCPT_MX} ]]; then
    log_msg "Cannot determine MX for ${1}, skipping email notification..."
    return 1
  fi
  ./smtp-cli --missing-modules-ok \
    --subject="Watchdog: ${2} service hit the error rate limit" \
    --body-plain="Service was restarted, please check your mailcow installation." \
    --to=${1} \
    --from="watchdog@${MAILCOW_HOSTNAME}" \
    --server="${RCPT_MX}" \
    --hello-host=${MAILCOW_HOSTNAME}
  log_msg "Sent notification email to ${1}"
}

get_container_ip() {
  # ${1} is container
  CONTAINER_ID=()
  CONTAINER_IPS=()
  CONTAINER_IP=
  LOOP_C=1
  until [[ ${CONTAINER_IP} =~ ^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$ ]] || [[ ${LOOP_C} -gt 5 ]]; do
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
    host_ip=$(get_container_ip nginx-mailcow)
    err_c_cur=${err_count}
    /usr/lib/nagios/plugins/check_ping -4 -H ${host_ip} -w 2000,10% -c 4000,100% -p2 1>&2; err_count=$(( ${err_count} + $? ))
    /usr/lib/nagios/plugins/check_http -4 -H ${host_ip} -u / -p 8081 1>&2; err_count=$(( ${err_count} + $? ))
    [ ${err_c_cur} -eq ${err_count} ] && [ ! $((${err_count} - 1)) -lt 0 ] && err_count=$((${err_count} - 1)) diff_c=1
    [ ${err_c_cur} -ne ${err_count} ] && diff_c=$(( ${err_c_cur} - ${err_count} ))
    progress "Nginx" ${THRESHOLD} $(( ${THRESHOLD} - ${err_count} )) ${diff_c}
    diff_c=0
    sleep $(( ( RANDOM % 30 )  + 10 ))
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
    host_ip=$(get_container_ip mysql-mailcow)
    err_c_cur=${err_count}
    /usr/lib/nagios/plugins/check_mysql -s /var/run/mysqld/mysqld.sock -u ${DBUSER} -p ${DBPASS} -d ${DBNAME} 1>&2; err_count=$(( ${err_count} + $? ))
    /usr/lib/nagios/plugins/check_mysql_query -s /var/run/mysqld/mysqld.sock -u ${DBUSER} -p ${DBPASS} -d ${DBNAME} -q "SELECT COUNT(*) FROM information_schema.tables" 1>&2; err_count=$(( ${err_count} + $? ))
    [ ${err_c_cur} -eq ${err_count} ] && [ ! $((${err_count} - 1)) -lt 0 ] && err_count=$((${err_count} - 1)) diff_c=1
    [ ${err_c_cur} -ne ${err_count} ] && diff_c=$(( ${err_c_cur} - ${err_count} ))
    progress "MySQL/MariaDB" ${THRESHOLD} $(( ${THRESHOLD} - ${err_count} )) ${diff_c}
    diff_c=0
    sleep $(( ( RANDOM % 30 )  + 10 ))
  done
  return 1
}

sogo_checks() {
  err_count=0
  diff_c=0
  THRESHOLD=20
  # Reduce error count by 2 after restarting an unhealthy container
  trap "[ ${err_count} -gt 1 ] && err_count=$(( ${err_count} - 2 ))" USR1
  while [ ${err_count} -lt ${THRESHOLD} ]; do
    host_ip=$(get_container_ip sogo-mailcow)
    err_c_cur=${err_count}
    /usr/lib/nagios/plugins/check_http -4 -H ${host_ip} -u /SOGo.index/ -p 20000 -R "SOGo\.MainUI" 1>&2; err_count=$(( ${err_count} + $? ))
    [ ${err_c_cur} -eq ${err_count} ] && [ ! $((${err_count} - 1)) -lt 0 ] && err_count=$((${err_count} - 1)) diff_c=1
    [ ${err_c_cur} -ne ${err_count} ] && diff_c=$(( ${err_c_cur} - ${err_count} ))
    progress "SOGo" ${THRESHOLD} $(( ${THRESHOLD} - ${err_count} )) ${diff_c}
    diff_c=0
    sleep $(( ( RANDOM % 30 )  + 10 ))
  done
  return 1
}

postfix_checks() {
  err_count=0
  diff_c=0
  THRESHOLD=16
  # Reduce error count by 2 after restarting an unhealthy container
  trap "[ ${err_count} -gt 1 ] && err_count=$(( ${err_count} - 2 ))" USR1
  while [ ${err_count} -lt ${THRESHOLD} ]; do
	host_ip=$(get_container_ip postfix-mailcow)
    err_c_cur=${err_count}
    /usr/lib/nagios/plugins/check_smtp -4 -H ${host_ip} -p 589 -f "watchdog@invalid" -C "RCPT TO:null@localhost" -C DATA -C . -R 250 1>&2; err_count=$(( ${err_count} + $? ))
    /usr/lib/nagios/plugins/check_smtp -4 -H ${host_ip} -p 589 -S 1>&2; err_count=$(( ${err_count} + $? ))
    [ ${err_c_cur} -eq ${err_count} ] && [ ! $((${err_count} - 1)) -lt 0 ] && err_count=$((${err_count} - 1)) diff_c=1
    [ ${err_c_cur} -ne ${err_count} ] && diff_c=$(( ${err_c_cur} - ${err_count} ))
    progress "Postfix" ${THRESHOLD} $(( ${THRESHOLD} - ${err_count} )) ${diff_c}
    diff_c=0
    sleep $(( ( RANDOM % 30 )  + 10 ))
  done
  return 1
}

dovecot_checks() {
  err_count=0
  diff_c=0
  THRESHOLD=24
  # Reduce error count by 2 after restarting an unhealthy container
  trap "[ ${err_count} -gt 1 ] && err_count=$(( ${err_count} - 2 ))" USR1
  while [ ${err_count} -lt ${THRESHOLD} ]; do
    host_ip=$(get_container_ip dovecot-mailcow)
    err_c_cur=${err_count}
    /usr/lib/nagios/plugins/check_smtp -4 -H ${host_ip} -p 24 -f "watchdog@invalid" -C "RCPT TO:<watchdog@invalid>" -L -R "User doesn't exist" 1>&2; err_count=$(( ${err_count} + $? ))
    /usr/lib/nagios/plugins/check_imap -4 -H ${host_ip} -p 993 -S -e "OK " 1>&2; err_count=$(( ${err_count} + $? ))
    /usr/lib/nagios/plugins/check_imap -4 -H ${host_ip} -p 143 -e "OK " 1>&2; err_count=$(( ${err_count} + $? ))
    /usr/lib/nagios/plugins/check_tcp -4 -H ${host_ip} -p 10001 -e "VERSION" 1>&2; err_count=$(( ${err_count} + $? ))
    /usr/lib/nagios/plugins/check_tcp -4 -H ${host_ip} -p 4190 -e "Dovecot ready" 1>&2; err_count=$(( ${err_count} + $? ))
    [ ${err_c_cur} -eq ${err_count} ] && [ ! $((${err_count} - 1)) -lt 0 ] && err_count=$((${err_count} - 1)) diff_c=1
    [ ${err_c_cur} -ne ${err_count} ] && diff_c=$(( ${err_c_cur} - ${err_count} ))
    progress "Dovecot" ${THRESHOLD} $(( ${THRESHOLD} - ${err_count} )) ${diff_c}
    diff_c=0
    sleep $(( ( RANDOM % 30 )  + 10 ))
  done
  return 1
}

phpfpm_checks() {
  err_count=0
  diff_c=0
  THRESHOLD=10
  # Reduce error count by 2 after restarting an unhealthy container
  trap "[ ${err_count} -gt 1 ] && err_count=$(( ${err_count} - 2 ))" USR1
  while [ ${err_count} -lt ${THRESHOLD} ]; do
    host_ip=$(get_container_ip php-fpm-mailcow)
    err_c_cur=${err_count}
    nc -z ${host_ip} 9001 ; err_count=$(( ${err_count} + ($? * 2)))
    nc -z ${host_ip} 9002 ; err_count=$(( ${err_count} + ($? * 2)))
    /usr/lib/nagios/plugins/check_ping -4 -H ${host_ip} -w 2000,10% -c 4000,100% -p2 1>&2; err_count=$(( ${err_count} + $? ))
    [ ${err_c_cur} -eq ${err_count} ] && [ ! $((${err_count} - 1)) -lt 0 ] && err_count=$((${err_count} - 1)) diff_c=1
    [ ${err_c_cur} -ne ${err_count} ] && diff_c=$(( ${err_c_cur} - ${err_count} ))
    progress "PHP-FPM" ${THRESHOLD} $(( ${THRESHOLD} - ${err_count} )) ${diff_c}
    diff_c=0
    sleep $(( ( RANDOM % 30 )  + 10 ))
  done
  return 1
}

rspamd_checks() {
  err_count=0
  diff_c=0
  THRESHOLD=10
  # Reduce error count by 2 after restarting an unhealthy container
  trap "[ ${err_count} -gt 1 ] && err_count=$(( ${err_count} - 2 ))" USR1
  while [ ${err_count} -lt ${THRESHOLD} ]; do
    host_ip=$(get_container_ip rspamd-mailcow)
    err_c_cur=${err_count}
    SCORE=$(/usr/bin/curl -s --data-binary @- --unix-socket /var/lib/rspamd/rspamd.sock http://rspamd/scan -d '
To: null@localhost
From: watchdog@localhost

Empty
' | jq -rc .required_score)
    if [[ ${SCORE} != "9999" ]]; then
      echo "Rspamd settings check failed" 1>&2
      err_count=$(( ${err_count} + 1))
    else
      echo "Rspamd settings check succeeded" 1>&2
    fi
    /usr/lib/nagios/plugins/check_ping -4 -H ${host_ip} -w 2000,10% -c 4000,100% -p2 1>&2; err_count=$(( ${err_count} + $? ))
    [ ${err_c_cur} -eq ${err_count} ] && [ ! $((${err_count} - 1)) -lt 0 ] && err_count=$((${err_count} - 1)) diff_c=1
    [ ${err_c_cur} -ne ${err_count} ] && diff_c=$(( ${err_c_cur} - ${err_count} ))
    progress "Rspamd" ${THRESHOLD} $(( ${THRESHOLD} - ${err_count} )) ${diff_c}
    diff_c=0
    sleep $(( ( RANDOM % 30 )  + 10 ))
  done
  return 1
}

# Create watchdog agents
(
while true; do
  if ! nginx_checks; then
    log_msg "Nginx hit error limit"
    [[ ! -z ${WATCHDOG_NOTIFY_EMAIL} ]] && mail_error "${WATCHDOG_NOTIFY_EMAIL}" "nginx-mailcow"
    echo nginx-mailcow > /tmp/com_pipe
  fi
done
) &
BACKGROUND_TASKS+=($!)

(
while true; do
  if ! mysql_checks; then
    log_msg "MySQL hit error limit"
    [[ ! -z ${WATCHDOG_NOTIFY_EMAIL} ]] && mail_error "${WATCHDOG_NOTIFY_EMAIL}" "mysql-mailcow"
    echo mysql-mailcow > /tmp/com_pipe
  fi
done
) &
BACKGROUND_TASKS+=($!)

(
while true; do
  if ! phpfpm_checks; then
    log_msg "PHP-FPM hit error limit"
    [[ ! -z ${WATCHDOG_NOTIFY_EMAIL} ]] && mail_error "${WATCHDOG_NOTIFY_EMAIL}" "php-fpm-mailcow"
    echo php-fpm-mailcow > /tmp/com_pipe
  fi
done
) &
BACKGROUND_TASKS+=($!)

(
while true; do
  if ! sogo_checks; then
    log_msg "SOGo hit error limit"
    [[ ! -z ${WATCHDOG_NOTIFY_EMAIL} ]] && mail_error "${WATCHDOG_NOTIFY_EMAIL}" "sogo-mailcow"
    echo sogo-mailcow > /tmp/com_pipe
  fi
done
) &
BACKGROUND_TASKS+=($!)

(
while true; do
  if ! postfix_checks; then
    log_msg "Postfix hit error limit"
    [[ ! -z ${WATCHDOG_NOTIFY_EMAIL} ]] && mail_error "${WATCHDOG_NOTIFY_EMAIL}" "postfix-mailcow"
    echo postfix-mailcow > /tmp/com_pipe
  fi
done
) &
BACKGROUND_TASKS+=($!)

(
while true; do
  if ! dovecot_checks; then
    log_msg "Dovecot hit error limit"
    [[ ! -z ${WATCHDOG_NOTIFY_EMAIL} ]] && mail_error "${WATCHDOG_NOTIFY_EMAIL}" "dovecot-mailcow"
    echo dovecot-mailcow > /tmp/com_pipe
  fi
done
) &
BACKGROUND_TASKS+=($!)

(
while true; do
  if ! rspamd_checks; then
    log_msg "Rspamd hit error limit"
    [[ ! -z ${WATCHDOG_NOTIFY_EMAIL} ]] && mail_error "${WATCHDOG_NOTIFY_EMAIL}" "rspamd-mailcow"
    echo rspamd-mailcow > /tmp/com_pipe
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
  read com_pipe_answer </tmp/com_pipe
  if [[ ${com_pipe_answer} =~ .+-mailcow ]]; then
    kill -STOP ${BACKGROUND_TASKS[*]}
    sleep 3
    CONTAINER_ID=$(curl --silent --insecure https://dockerapi/containers/json | jq -r ".[] | {name: .Config.Labels[\"com.docker.compose.service\"], id: .Id}" | jq -rc "select( .name | tostring | contains(\"${com_pipe_answer}\")) | .id")
    if [[ ! -z ${CONTAINER_ID} ]]; then
      log_msg "Sending restart command to ${CONTAINER_ID}..."
      curl --silent --insecure -XPOST https://dockerapi/containers/${CONTAINER_ID}/restart
    fi
    log_msg "Wait for restarted container to settle and continue watching..."
    sleep 30s
    kill -CONT ${BACKGROUND_TASKS[*]}
    kill -USR1 ${BACKGROUND_TASKS[*]}
  fi
done
