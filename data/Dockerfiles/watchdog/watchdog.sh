#!/bin/bash

if [ "${DEV_MODE}" != "n" ]; then
  echo -e "\e[31mEnabled Debug Mode\e[0m"
  set -x
fi

trap "exit" INT TERM
trap "kill 0" EXIT

# Prepare
BACKGROUND_TASKS=()
echo "Waiting for containers to settle..."
for i in {30..1}; do
  echo "${i}"
  sleep 1
done

if [[ "${USE_WATCHDOG}" =~ ^([nN][oO]|[nN])+$ ]]; then
  echo -e "$(date) - USE_WATCHDOG=n, skipping watchdog..."
  sleep 365d
  exec $(readlink -f "$0")
fi

if [[ "${WATCHDOG_VERBOSE}" =~ ^([yY][eE][sS]|[yY])+$ ]]; then
  SMTP_VERBOSE="--verbose"
  CURL_VERBOSE="--verbose"
  set -xv
else
  SMTP_VERBOSE=""
  CURL_VERBOSE=""
  exec 2>/dev/null
fi

# Checks pipe their corresponding container name in this pipe
if [[ ! -p /tmp/com_pipe ]]; then
  mkfifo /tmp/com_pipe
fi

# Wait for containers
while ! mariadb-admin status --skip-ssl --socket=/var/run/mysqld/mysqld.sock -u${DBUSER} -p${DBPASS} --silent; do
  echo "Waiting for SQL..."
  sleep 2
done

# Do not attempt to write to slave
if [[ ! -z ${REDIS_SLAVEOF_IP} ]]; then
  REDIS_CMDLINE="redis-cli -h ${REDIS_SLAVEOF_IP} -p ${REDIS_SLAVEOF_PORT} -a ${REDISPASS} --no-auth-warning"
else
  REDIS_CMDLINE="redis-cli -h redis -p 6379 -a ${REDISPASS} --no-auth-warning"
fi

until [[ $(${REDIS_CMDLINE} PING) == "PONG" ]]; do
  echo "Waiting for Redis..."
  sleep 2
done

${REDIS_CMDLINE} DEL F2B_RES > /dev/null

# Common functions
get_ipv6(){
  local IPV6=
  local IPV6_SRCS=
  local TRY=
  IPV6_SRCS[0]="ip6.mailcow.email"
  IPV6_SRCS[1]="ip6.nevondo.com"
  until [[ ! -z ${IPV6} ]] || [[ ${TRY} -ge 10 ]]; do
    IPV6=$(curl --connect-timeout 3 -m 10 -L6s ${IPV6_SRCS[$RANDOM % ${#IPV6_SRCS[@]} ]} | grep "^\([0-9a-fA-F]\{0,4\}:\)\{1,7\}[0-9a-fA-F]\{0,4\}$")
    [[ ! -z ${TRY} ]] && sleep 1
    TRY=$((TRY+1))
  done
  echo ${IPV6}
}

array_diff() {
  # https://stackoverflow.com/questions/2312762, Alex Offshore
  eval local ARR1=\(\"\${$2[@]}\"\)
  eval local ARR2=\(\"\${$3[@]}\"\)
  local IFS=$'\n'
  mapfile -t $1 < <(comm -23 <(echo "${ARR1[*]}" | sort) <(echo "${ARR2[*]}" | sort))
}

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
  ${REDIS_CMDLINE} LPUSH WATCHDOG_LOG "{\"time\":\"$(date +%s)\",\"service\":\"${SERVICE}\",\"lvl\":\"${PERCENT}\",\"hpnow\":\"${CURRENT}\",\"hptotal\":\"${TOTAL}\",\"hpdiff\":\"${DIFF}\"}" > /dev/null
  log_msg "${SERVICE} health level: ${PERCENT}% (${CURRENT}/${TOTAL}), health trend: ${DIFF}" no_redis
  # Return 10 to indicate a dead service
  [ ${CURRENT} -le 0 ] && return 10
}

log_msg() {
  if [[ ${2} != "no_redis" ]]; then
    ${REDIS_CMDLINE} LPUSH WATCHDOG_LOG "{\"time\":\"$(date +%s)\",\"message\":\"$(printf '%s' "${1}" | \
      tr '\r\n%&;$"_[]{}-' ' ')\"}" > /dev/null
  fi
  echo $(date) $(printf '%s\n' "${1}")
}

function notify_error() {
  # Check if one of the notification options is enabled
  [[ -z ${WATCHDOG_NOTIFY_EMAIL} ]] && [[ -z ${WATCHDOG_NOTIFY_WEBHOOK} ]] && return 0
  THROTTLE=
  [[ -z ${1} ]] && return 1
  # If exists, body will be the content of "/tmp/${1}", even if ${2} is set
  [[ -z ${2} ]] && BODY="Service was restarted on $(date), please check your mailcow installation." || BODY="$(date) - ${2}"
  # If exists, mail will be throttled by argument in seconds
  [[ ! -z ${3} ]] && THROTTLE=${3}
  if [[ ! -z ${THROTTLE} ]]; then
    TTL_LEFT="$(${REDIS_CMDLINE} TTL THROTTLE_${1} 2> /dev/null)"
    if [[ "${TTL_LEFT}" == "-2" ]]; then
      # Delay key not found, setting a delay key now
      ${REDIS_CMDLINE} SET THROTTLE_${1} 1 EX ${THROTTLE}
    else
      log_msg "Not sending notification email now, blocked for ${TTL_LEFT} seconds..."
      return 1
    fi
  fi
  WATCHDOG_NOTIFY_EMAIL=$(echo "${WATCHDOG_NOTIFY_EMAIL}" | sed 's/"//;s|"$||')
  # Some exceptions for subject and body formats
  if [[ ${1} == "fail2ban" ]]; then
    SUBJECT="${BODY}"
    BODY="Please see netfilter-mailcow for more details and triggered rules."
  else
    SUBJECT="${WATCHDOG_SUBJECT}: ${1}"
  fi

  # Send mail notification if enabled
  if [[ ! -z ${WATCHDOG_NOTIFY_EMAIL} ]]; then
    IFS=',' read -r -a MAIL_RCPTS <<< "${WATCHDOG_NOTIFY_EMAIL}"
    for rcpt in "${MAIL_RCPTS[@]}"; do
      RCPT_DOMAIN=
      RCPT_MX=
      RCPT_DOMAIN=$(echo ${rcpt} | awk -F @ {'print $NF'})
      CHECK_FOR_VALID_MX=$(dig +short ${RCPT_DOMAIN} mx)
      if [[ -z ${CHECK_FOR_VALID_MX} ]]; then
        log_msg "Cannot determine MX for ${rcpt}, skipping email notification..."
        return 1
      fi
      [ -f "/tmp/${1}" ] && BODY="/tmp/${1}"
      timeout 10s ./smtp-cli --missing-modules-ok \
        "${SMTP_VERBOSE}" \
        --charset=UTF-8 \
        --subject="${SUBJECT}" \
        --body-plain="${BODY}" \
        --add-header="X-Priority: 1" \
        --to=${rcpt} \
        --from="watchdog@${MAILCOW_HOSTNAME}" \
        --hello-host=${MAILCOW_HOSTNAME} \
        --ipv4
      if [[ $? -eq 1 ]]; then # exit code 1 is fine
        log_msg "Sent notification email to ${rcpt}"
      else
        if [[ "${SMTP_VERBOSE}" == "" ]]; then
          log_msg "Error while sending notification email to ${rcpt}. You can enable verbose logging by setting 'WATCHDOG_VERBOSE=y' in mailcow.conf."
        else
          log_msg "Error while sending notification email to ${rcpt}."
        fi
      fi
    done
  fi

  # Send webhook notification if enabled
  if [[ ! -z ${WATCHDOG_NOTIFY_WEBHOOK} ]]; then
    if [[ -z ${WATCHDOG_NOTIFY_WEBHOOK_BODY} ]]; then
      log_msg "No webhook body set, skipping webhook notification..."
      return 1
    fi

    # Escape subject and body (https://stackoverflow.com/a/2705678)
    ESCAPED_SUBJECT=$(echo ${SUBJECT} | sed -e 's/[\/&]/\\&/g')
    ESCAPED_BODY=$(echo ${BODY} | sed -e 's/[\/&]/\\&/g')

    # Replace subject and body placeholders
    WEBHOOK_BODY=$(echo ${WATCHDOG_NOTIFY_WEBHOOK_BODY} | sed -e "s/\$SUBJECT\|\${SUBJECT}/$ESCAPED_SUBJECT/g" -e "s/\$BODY\|\${BODY}/$ESCAPED_BODY/g")

    # POST to webhook
    curl -X POST -H "Content-Type: application/json" ${CURL_VERBOSE} -d "${WEBHOOK_BODY}" ${WATCHDOG_NOTIFY_WEBHOOK}

    log_msg "Sent notification using webhook"
  fi
}


# One-time check
if grep -qi "$(echo ${IPV6_NETWORK} | cut -d: -f1-3)" <<< "$(ip a s)"; then
  if [[ -z "$(get_ipv6)" ]]; then
    notify_error "ipv6-config" "enable_ipv6 is true in docker-compose.yml, but an IPv6 link could not be established. Please verify your IPv6 connection."
  fi
fi

external_checks() {
  err_count=0
  diff_c=0
  THRESHOLD=${EXTERNAL_CHECKS_THRESHOLD}
  # Reduce error count by 2 after restarting an unhealthy container
  GUID=$(mariadb --skip-ssl -u${DBUSER} -p${DBPASS} ${DBNAME} -e "SELECT version FROM versions WHERE application = 'GUID'" -BN)
  trap "[ ${err_count} -gt 1 ] && err_count=$(( ${err_count} - 2 ))" USR1
  while [ ${err_count} -lt ${THRESHOLD} ]; do
    err_c_cur=${err_count}
    CHECK_REPONSE="$(curl --connect-timeout 3 -m 10 -4 -s https://checks.mailcow.email -X POST -dguid=${GUID} 2> /dev/null)"
    if [[ ! -z "${CHECK_REPONSE}" ]] && [[ "$(echo ${CHECK_REPONSE} | jq -r .response)" == "critical" ]]; then
      echo ${CHECK_REPONSE} | jq -r .out > /tmp/external_checks
      err_count=$(( ${err_count} + 1 ))
    fi
    CHECK_REPONSE6="$(curl --connect-timeout 3 -m 10 -6 -s https://checks.mailcow.email -X POST -dguid=${GUID} 2> /dev/null)"
    if [[ ! -z "${CHECK_REPONSE6}" ]] && [[ "$(echo ${CHECK_REPONSE6} | jq -r .response)" == "critical" ]]; then
      echo ${CHECK_REPONSE} | jq -r .out > /tmp/external_checks
      err_count=$(( ${err_count} + 1 ))
    fi
    [ ${err_c_cur} -eq ${err_count} ] && [ ! $((${err_count} - 1)) -lt 0 ] && err_count=$((${err_count} - 1)) diff_c=1
    [ ${err_c_cur} -ne ${err_count} ] && diff_c=$(( ${err_c_cur} - ${err_count} ))
    progress "External checks" ${THRESHOLD} $(( ${THRESHOLD} - ${err_count} )) ${diff_c}
    if [[ $? == 10 ]]; then
      diff_c=0
      sleep 60
    else
      diff_c=0
      sleep $(( ( RANDOM % 20 ) + 1800 ))
    fi
  done
  return 1
}

cert_checks() {
  err_count=0
  diff_c=0
  THRESHOLD=7
  # Reduce error count by 2 after restarting an unhealthy container
  trap "[ ${err_count} -gt 1 ] && err_count=$(( ${err_count} - 2 ))" USR1
  while [ ${err_count} -lt ${THRESHOLD} ]; do
    touch /tmp/certcheck; echo "$(tail -50 /tmp/certcheck)" > /tmp/certcheck
    err_c_cur=${err_count}
    /usr/lib/nagios/plugins/check_smtp -H postfix -p 589 -4 -S -D 7 2>> /tmp/certcheck 1>&2; err_count=$(( ${err_count} + $? ))
    /usr/lib/nagios/plugins/check_imap -H dovecot -p 993 -4 -S -D 7 2>> /tmp/certcheck 1>&2; err_count=$(( ${err_count} + $? ))
    [ ${err_c_cur} -eq ${err_count} ] && [ ! $((${err_count} - 1)) -lt 0 ] && err_count=$((${err_count} - 1)) diff_c=1
    [ ${err_c_cur} -ne ${err_count} ] && diff_c=$(( ${err_c_cur} - ${err_count} ))
    progress "Primary certificate expiry check" ${THRESHOLD} $(( ${THRESHOLD} - ${err_count} )) ${diff_c}
    # Always sleep 5 minutes, mail notifications are limited
    sleep 300
  done
  return 1
}


ratelimit_checks() {
  err_count=0
  diff_c=0
  THRESHOLD=${RATELIMIT_THRESHOLD}
  RL_LOG_STATUS=$(redis-cli -h redis -a ${REDISPASS} --no-auth-warning LRANGE RL_LOG 0 0 | jq .qid)
  # Reduce error count by 2 after restarting an unhealthy container
  trap "[ ${err_count} -gt 1 ] && err_count=$(( ${err_count} - 2 ))" USR1
  while [ ${err_count} -lt ${THRESHOLD} ]; do
    err_c_cur=${err_count}
    RL_LOG_STATUS_PREV=${RL_LOG_STATUS}
    RL_LOG_STATUS=$(redis-cli -h redis -a ${REDISPASS} --no-auth-warning LRANGE RL_LOG 0 0 | jq .qid)
    if [[ ${RL_LOG_STATUS_PREV} != ${RL_LOG_STATUS} ]]; then
      err_count=$(( ${err_count} + 1 ))
      echo 'Last 10 applied ratelimits (may overlap with previous reports).' > /tmp/ratelimit
      echo 'Full ratelimit buckets can be emptied by deleting the ratelimit hash from within mailcow UI (see /debug -> Protocols -> Ratelimit):' >> /tmp/ratelimit
      echo >> /tmp/ratelimit
      redis-cli --raw -h redis -a ${REDISPASS} --no-auth-warning LRANGE RL_LOG 0 10 | jq . >> /tmp/ratelimit
    fi
    [ ${err_c_cur} -eq ${err_count} ] && [ ! $((${err_count} - 1)) -lt 0 ] && err_count=$((${err_count} - 1)) diff_c=1
    [ ${err_c_cur} -ne ${err_count} ] && diff_c=$(( ${err_c_cur} - ${err_count} ))
    progress "Ratelimit" ${THRESHOLD} $(( ${THRESHOLD} - ${err_count} )) ${diff_c}
    if [[ $? == 10 ]]; then
      diff_c=0
      sleep 1
    else
      diff_c=0
      sleep $(( ( RANDOM % 60 ) + 20 ))
    fi
  done
  return 1
}

mailq_checks() {
  err_count=0
  diff_c=0
  THRESHOLD=${MAILQ_THRESHOLD}
  # Reduce error count by 2 after restarting an unhealthy container
  trap "[ ${err_count} -gt 1 ] && err_count=$(( ${err_count} - 2 ))" USR1
  while [ ${err_count} -lt ${THRESHOLD} ]; do
    touch /tmp/mail_queue_status; echo "$(tail -50 /tmp/mail_queue_status)" > /tmp/mail_queue_status
    MAILQ_LOG_STATUS=$(find /var/spool/postfix/deferred -type f | wc -l)
    echo "Mail queue contains ${MAILQ_LOG_STATUS} items (critical limit is ${MAILQ_CRIT}) at $(date)" >> /tmp/mail_queue_status
    err_c_cur=${err_count}
    if [ ${MAILQ_LOG_STATUS} -ge ${MAILQ_CRIT} ]; then
      err_count=$(( ${err_count} + 1 ))
      echo "Mail queue contains ${MAILQ_LOG_STATUS} items (critical limit is ${MAILQ_CRIT}) at $(date)" >> /tmp/mail_queue_status
    fi
    [ ${err_c_cur} -eq ${err_count} ] && [ ! $((${err_count} - 1)) -lt 0 ] && err_count=$((${err_count} - 1)) diff_c=1
    [ ${err_c_cur} -ne ${err_count} ] && diff_c=$(( ${err_c_cur} - ${err_count} ))
    progress "Mail queue" ${THRESHOLD} $(( ${THRESHOLD} - ${err_count} )) ${diff_c}
    if [[ $? == 10 ]]; then
      diff_c=0
      sleep 60
    else
      diff_c=0
      sleep $(( ( RANDOM % 60 ) + 20 ))
    fi
  done
  return 1
}

fail2ban_checks() {
  err_count=0
  diff_c=0
  THRESHOLD=${FAIL2BAN_THRESHOLD}
  F2B_LOG_STATUS=($(${REDIS_CMDLINE} --raw HKEYS F2B_ACTIVE_BANS))
  F2B_RES=
  # Reduce error count by 2 after restarting an unhealthy container
  trap "[ ${err_count} -gt 1 ] && err_count=$(( ${err_count} - 2 ))" USR1
  while [ ${err_count} -lt ${THRESHOLD} ]; do
    err_c_cur=${err_count}
    F2B_LOG_STATUS_PREV=(${F2B_LOG_STATUS[@]})
    F2B_LOG_STATUS=($(${REDIS_CMDLINE} --raw HKEYS F2B_ACTIVE_BANS))
    array_diff F2B_RES F2B_LOG_STATUS F2B_LOG_STATUS_PREV
    if [[ ! -z "${F2B_RES}" ]]; then
      err_count=$(( ${err_count} + 1 ))
      echo -n "${F2B_RES[@]}" | tr -cd "[a-fA-F0-9.:/] " | timeout 3s ${REDIS_CMDLINE} -x SET F2B_RES > /dev/null
      if [ $? -ne 0 ]; then
         ${REDIS_CMDLINE} -x DEL F2B_RES
      fi
    fi
    [ ${err_c_cur} -eq ${err_count} ] && [ ! $((${err_count} - 1)) -lt 0 ] && err_count=$((${err_count} - 1)) diff_c=1
    [ ${err_c_cur} -ne ${err_count} ] && diff_c=$(( ${err_c_cur} - ${err_count} ))
    progress "Fail2ban" ${THRESHOLD} $(( ${THRESHOLD} - ${err_count} )) ${diff_c}
    if [[ $? == 10 ]]; then
      diff_c=0
      sleep 1
    else
      diff_c=0
      sleep $(( ( RANDOM % 60 ) + 20 ))
    fi
  done
  return 1
}

acme_checks() {
  err_count=0
  diff_c=0
  THRESHOLD=${ACME_THRESHOLD}
  ACME_LOG_STATUS=$(redis-cli -h redis -a ${REDISPASS} --no-auth-warning GET ACME_FAIL_TIME)
  if [[ -z "${ACME_LOG_STATUS}" ]]; then
    ${REDIS_CMDLINE} SET ACME_FAIL_TIME 0
    ACME_LOG_STATUS=0
  fi
  # Reduce error count by 2 after restarting an unhealthy container
  trap "[ ${err_count} -gt 1 ] && err_count=$(( ${err_count} - 2 ))" USR1
  while [ ${err_count} -lt ${THRESHOLD} ]; do
    err_c_cur=${err_count}
    ACME_LOG_STATUS_PREV=${ACME_LOG_STATUS}
    ACME_LC=0
    until [[ ! -z ${ACME_LOG_STATUS} ]] || [ ${ACME_LC} -ge 3 ]; do
      ACME_LOG_STATUS=$(redis-cli -h redis -a ${REDISPASS} --no-auth-warning GET ACME_FAIL_TIME 2> /dev/null)
      sleep 3
      ACME_LC=$((ACME_LC+1))
    done
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
      sleep $(( ( RANDOM % 60 ) + 20 ))
    fi
  done
  return 1
}



# Notify about start
if [[ ${WATCHDOG_NOTIFY_START} =~ ^([yY][eE][sS]|[yY])+$ ]]; then
  notify_error "watchdog-mailcow" "Watchdog started monitoring mailcow."
fi

# Health checks run inside each container (mailcow-agent healthcheck + heartbeat).
# We just read the per-node health field from Redis and restart on N consecutive fails.
REDIS_HOST="${REDIS_SLAVEOF_IP:-redis-mailcow}"
REDIS_PORT="${REDIS_SLAVEOF_PORT:-6379}"
REDIS_CMDLINE_FULL="redis-cli -h ${REDIS_HOST} -p ${REDIS_PORT} -a ${REDISPASS} --no-auth-warning"

HEALTH_WATCHED_SERVICES=(
  postfix dovecot sogo rspamd nginx
  clamd unbound olefy phpfpm postfix-tlspol
)

declare -A HEALTH_FAIL_COUNT
HEALTH_FAIL_THRESHOLD=3

[[ "${SKIP_SOGO}" =~ ^([yY][eE][sS]|[yY])+$ ]] && HEALTH_WATCHED_SERVICES=("${HEALTH_WATCHED_SERVICES[@]/sogo}")
[[ "${SKIP_CLAMD}" =~ ^([yY][eE][sS]|[yY])+$ ]] && HEALTH_WATCHED_SERVICES=("${HEALTH_WATCHED_SERVICES[@]/clamd}")
[[ "${SKIP_OLEFY}" =~ ^([yY][eE][sS]|[yY])+$ ]] && HEALTH_WATCHED_SERVICES=("${HEALTH_WATCHED_SERVICES[@]/olefy}")

(
# Counters are per-node in an associative array reset on restart, so absorb USR1
# instead of dying (other tasks trap it to decrement their own err_count).
trap '' USR1
declare -A HEALTH_FAIL_COUNT
while true; do
  for svc in "${HEALTH_WATCHED_SERVICES[@]}"; do
    [[ -z "$svc" ]] && continue
    nodes=$(${REDIS_CMDLINE_FULL} ZRANGEBYSCORE "mailcow.nodes.${svc}" "$(( $(date +%s) - 30 ))" "+inf" 2>/dev/null)
    [[ -z "${nodes}" ]] && continue
    while IFS= read -r node; do
      [[ -z "${node}" ]] && continue
      health=$(${REDIS_CMDLINE_FULL} HGET "mailcow.node.${svc}.${node}" health 2>/dev/null)
      key="${svc}|${node}"
      if [[ "${health}" == "fail" ]]; then
        HEALTH_FAIL_COUNT[$key]=$(( ${HEALTH_FAIL_COUNT[$key]:-0} + 1 ))
        if [[ ${HEALTH_FAIL_COUNT[$key]} -ge ${HEALTH_FAIL_THRESHOLD} ]]; then
          detail=$(${REDIS_CMDLINE_FULL} HGET "mailcow.node.${svc}.${node}" health_detail 2>/dev/null)
          log_msg "Service ${svc} node ${node} unhealthy (${detail:-no detail}) — sending restart"
          echo "${svc}-mailcow|${node}" > /tmp/com_pipe
          HEALTH_FAIL_COUNT[$key]=0
        fi
      else
        HEALTH_FAIL_COUNT[$key]=0
      fi
    done <<< "${nodes}"
  done
  sleep 15
done
) &
PID=$!
echo "Spawned registry-based health monitor with PID ${PID}"
BACKGROUND_TASKS+=(${PID})

if [[ ${WATCHDOG_EXTERNAL_CHECKS} =~ ^([yY][eE][sS]|[yY])+$ ]]; then
(
while true; do
  if ! external_checks; then
    log_msg "External checks hit error limit"
    echo external_checks > /tmp/com_pipe
  fi
done
) &
PID=$!
echo "Spawned external_checks with PID ${PID}"
BACKGROUND_TASKS+=(${PID})
fi

(
while true; do
  if ! mailq_checks; then
    log_msg "Mail queue hit error limit"
    echo mail_queue_status > /tmp/com_pipe
  fi
done
) &
PID=$!
echo "Spawned mailq_checks with PID ${PID}"
BACKGROUND_TASKS+=(${PID})

(
while true; do
  if ! ratelimit_checks; then
    log_msg "Ratelimit hit error limit"
    echo ratelimit > /tmp/com_pipe
  fi
done
) &
PID=$!
echo "Spawned ratelimit_checks with PID ${PID}"
BACKGROUND_TASKS+=(${PID})

(
while true; do
  if ! fail2ban_checks; then
    log_msg "Fail2ban hit error limit"
    echo fail2ban > /tmp/com_pipe
  fi
done
) &
PID=$!
echo "Spawned fail2ban_checks with PID ${PID}"
BACKGROUND_TASKS+=(${PID})

(
while true; do
  if ! cert_checks; then
    log_msg "Cert check hit error limit"
    echo certcheck > /tmp/com_pipe
  fi
done
) &
PID=$!
echo "Spawned cert_checks with PID ${PID}"
BACKGROUND_TASKS+=(${PID})

(
while true; do
  if ! acme_checks; then
    log_msg "ACME client hit error limit"
    echo acme-mailcow > /tmp/com_pipe
  fi
done
) &
PID=$!
echo "Spawned acme_checks with PID ${PID}"
BACKGROUND_TASKS+=(${PID})

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

# Pause background checks while Redis (the control bus) is unreachable, otherwise
# we'd flag every service as unhealthy at once.
(
REDIS_HOST="${REDIS_SLAVEOF_IP:-redis-mailcow}"
REDIS_PORT="${REDIS_SLAVEOF_PORT:-6379}"
ping_bus() { redis-cli -h "${REDIS_HOST}" -p "${REDIS_PORT}" -a "${REDISPASS}" --no-auth-warning ping > /dev/null 2>&1; }
while true; do
  while ping_bus; do
    sleep 3
  done
  log_msg "Cannot reach redis-mailcow (control bus), waiting to recover..."
  kill -STOP ${BACKGROUND_TASKS[*]}
  until ping_bus; do
    sleep 3
  done
  kill -CONT ${BACKGROUND_TASKS[*]}
  kill -USR1 ${BACKGROUND_TASKS[*]}
done
) &

# Actions when threshold limit is reached
while true; do
  CONTAINER_ID=
  HAS_INITDB=
  read com_pipe_answer </tmp/com_pipe
  if [ -s "/tmp/${com_pipe_answer}" ]; then
    cat "/tmp/${com_pipe_answer}"
  fi
  if [[ ${com_pipe_answer} == "ratelimit" ]]; then
    log_msg "At least one ratelimit was applied"
    notify_error "${com_pipe_answer}"
  elif [[ ${com_pipe_answer} == "mail_queue_status" ]]; then
    log_msg "Mail queue status is critical"
    notify_error "${com_pipe_answer}"
  elif [[ ${com_pipe_answer} == "external_checks" ]]; then
    log_msg "Your mailcow is an open relay!"
    # Define $2 to override message text, else print service was restarted at ...
    notify_error "${com_pipe_answer}" "Please stop mailcow now and check your network configuration!"
  elif [[ ${com_pipe_answer} == "mysql_repl_checks" ]]; then
    log_msg "MySQL replication is not working properly"
    # Define $2 to override message text, else print service was restarted at ...
    # Once mail per 10 minutes
    notify_error "${com_pipe_answer}" "Please check the SQL replication status" 600
  elif [[ ${com_pipe_answer} == "dovecot_repl_checks" ]]; then
    log_msg "Dovecot replication is not working properly"
    # Define $2 to override message text, else print service was restarted at ...
    # Once mail per 10 minutes
    notify_error "${com_pipe_answer}" "Please check the Dovecot replicator status" 600
  elif [[ ${com_pipe_answer} == "certcheck" ]]; then
    log_msg "Certificates are about to expire"
    # Define $2 to override message text, else print service was restarted at ...
    # Only mail once a day
    notify_error "${com_pipe_answer}" "Please renew your certificate" 86400
  elif [[ ${com_pipe_answer} == "acme-mailcow" ]]; then
    log_msg "acme-mailcow did not complete successfully"
    # Define $2 to override message text, else print service was restarted at ...
    notify_error "${com_pipe_answer}" "Please check acme-mailcow for further information."
  elif [[ ${com_pipe_answer} == "fail2ban" ]]; then
    F2B_RES=($(timeout 4s ${REDIS_CMDLINE} --raw GET F2B_RES 2> /dev/null))
    if [[ ! -z "${F2B_RES}" ]]; then
      ${REDIS_CMDLINE} DEL F2B_RES > /dev/null
      host=
      for host in "${F2B_RES[@]}"; do
        log_msg "Banned ${host}"
        rm /tmp/fail2ban 2> /dev/null
        timeout 2s whois "${host}" > /tmp/fail2ban
        [[ ${WATCHDOG_NOTIFY_BAN} =~ ^([yY][eE][sS]|[yY])+$ ]] && notify_error "${com_pipe_answer}" "IP ban: ${host}"
      done
    fi
  elif [[ ${com_pipe_answer} =~ .+-mailcow ]]; then
    kill -STOP ${BACKGROUND_TASKS[*]}
    sleep 10
    # "<service>-mailcow|<node>" restarts a single replica; bare "<service>-mailcow"
    # broadcasts the restart to every replica of the service.
    AGENT_NODE=""
    AGENT_SVC="${com_pipe_answer%-mailcow}"
    if [[ "${com_pipe_answer}" == *"|"* ]]; then
      AGENT_NODE="${com_pipe_answer#*|}"
      AGENT_SVC="${com_pipe_answer%|*}"
      AGENT_SVC="${AGENT_SVC%-mailcow}"
    fi
    STARTED_AT_RAW=$(redis-cli -h "${REDIS_SLAVEOF_IP:-redis-mailcow}" -p "${REDIS_SLAVEOF_PORT:-6379}" -a "${REDISPASS}" --no-auth-warning HGET "mailcow.node.${AGENT_SVC}.${AGENT_NODE:-$(hostname)}" started_at 2>/dev/null)
    S_RUNNING=999
    if [[ -n "${STARTED_AT_RAW}" ]]; then
      S_RUNNING=$(( $(date +%s) - $(date -d "${STARTED_AT_RAW}" +%s 2>/dev/null || echo 0) ))
    fi
    if [ ${S_RUNNING} -lt 360 ]; then
      log_msg "Container is running for less than 360 seconds, skipping action..."
    else
      if [[ -n "${AGENT_NODE}" ]]; then
        log_msg "Sending restart to ${AGENT_SVC} node ${AGENT_NODE} via control bus..."
        mailcow-agent-cli send "${AGENT_SVC}" restart "{\"target_node\":\"${AGENT_NODE}\"}" >/dev/null || true
      else
        log_msg "Sending restart broadcast to ${AGENT_SVC} via control bus..."
        mailcow-agent-cli send "${AGENT_SVC}" restart >/dev/null || true
      fi
      notify_error "${com_pipe_answer}"
      log_msg "Wait for restarted container to settle and continue watching..."
      sleep 35
    fi
    kill -CONT ${BACKGROUND_TASKS[*]}
    sleep 1
    kill -USR1 ${BACKGROUND_TASKS[*]}
  fi
done

