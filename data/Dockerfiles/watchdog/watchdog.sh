#!/bin/bash

trap "exit" INT TERM
trap "kill 0" EXIT
PARENT_PID=$$

# Prepare
BACKGROUND_TASKS=()

# Skip watchdog?
if [[ "${USE_WATCHDOG}" =~ ^([nN][oO])+$ ]]; then
  echo "Skipping watchdog, sleeping..."
  sleep 365d
  exit 0
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
  percent=$(( 200 * ${CURRENT} / ${TOTAL} % 2 + 100 * ${CURRENT} / ${TOTAL} ))
  completed=$(( ${percent} / 2 ))
  remaining=$(( 50 - ${completed} ))
  echo -ne "$(date) Health level: "
  echo -n "["
  printf "%0.s>" $(seq ${completed})
  [[ ${remaining} != 0 ]] && printf "%0.s." $(seq ${remaining})
  echo -en "] ${percent}% - Service: ${SERVICE}, health trend: "
  [[ ${DIFF} =~ ^-[1-9] ]] && echo -en "\e[31mnegative \e[0m" || echo -en "\e[32mpositive \e[0m"
  echo "(${DIFF})"
}

# Check functions
nginx_checks() {
  err_count=0
  diff_c=0
  THRESHOLD=16
  # Reduce error count by 2 after restarting an unhealthy container
  trap "[ ${err_count} -gt 1 ] && err_count=$(( ${err_count} - 2 ))" USR1
  while [ ${err_count} -lt ${THRESHOLD} ]; do
    err_c_cur=${err_count}
    /usr/lib/nagios/plugins/check_ping -H nginx-mailcow -w 2000,10% -c 4000,100% -p2 1>&2; err_count=$(( ${err_count} + $? ))
    /usr/lib/nagios/plugins/check_http -H nginx-mailcow -u / -p 8081 1>&2; err_count=$(( ${err_count} + $? ))
    sleep $(( ( RANDOM % 30 )  + 10 ))
    [ ${err_c_cur} -eq ${err_count} ] && [ ! $((${err_count} - 1)) -lt 0 ] && err_count=$((${err_count} - 1)) diff_c=1
    [ ${err_c_cur} -ne ${err_count} ] && diff_c=$(( ${err_c_cur} - ${err_count} ))
    progress "Nginx" ${THRESHOLD} $(( ${THRESHOLD} - ${err_count} )) ${diff_c}
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
    err_c_cur=${err_count}
    /usr/lib/nagios/plugins/check_mysql -H mysql-mailcow -P 3306 -u ${DBUSER} -p ${DBPASS} -d ${DBNAME} 1>&2; err_count=$(( ${err_count} + $? ))
    /usr/lib/nagios/plugins/check_mysql_query -H mysql-mailcow -P 3306 -u ${DBUSER} -p ${DBPASS} -d ${DBNAME} -q "SELECT COUNT(*) FROM information_schema.tables" 1>&2; err_count=$(( ${err_count} + $? ))
    sleep $(( ( RANDOM % 30 )  + 10 ))
    [ ${err_c_cur} -eq ${err_count} ] && [ ! $((${err_count} - 1)) -lt 0 ] && err_count=$((${err_count} - 1)) diff_c=1
    [ ${err_c_cur} -ne ${err_count} ] && diff_c=$(( ${err_c_cur} - ${err_count} ))
    progress "MySQL/MariaDB" ${THRESHOLD} $(( ${THRESHOLD} - ${err_count} )) ${diff_c}
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
    err_c_cur=${err_count}
    /usr/lib/nagios/plugins/check_http -H sogo-mailcow -u /WebServerResources/css/theme-default.css -p 9192 -R md-default-theme 1>&2; err_count=$(( ${err_count} + $? ))
    /usr/lib/nagios/plugins/check_http -H sogo-mailcow -u /SOGo.index/ -p 20000 -R "SOGo\sGroupware" 1>&2; err_count=$(( ${err_count} + $? ))
    /usr/lib/nagios/plugins/check_http -H nginx-mailcow -u /SOGo/ -p 443 --ssl -R "Bad Gateway" --invert-regex 1>&2; err_count=$(( ${err_count} + $? ))
    /usr/lib/nagios/plugins/check_http -H nginx-mailcow -u /SOGo/ -p 80 -R "Bad Gateway" --invert-regex 1>&2; err_count=$(( ${err_count} + $? ))
    sleep $(( ( RANDOM % 30 )  + 10 ))
    [ ${err_c_cur} -eq ${err_count} ] && [ ! $((${err_count} - 1)) -lt 0 ] && err_count=$((${err_count} - 1)) diff_c=1
    [ ${err_c_cur} -ne ${err_count} ] && diff_c=$(( ${err_c_cur} - ${err_count} ))
    progress "SOGo" ${THRESHOLD} $(( ${THRESHOLD} - ${err_count} )) ${diff_c}
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
    err_c_cur=${err_count}
    /usr/lib/nagios/plugins/check_smtp -H postfix-mailcow -p 25 1>&2; err_count=$(( ${err_count} + $? ))
    /usr/lib/nagios/plugins/check_smtp -H postfix-mailcow -p 588 -f watchdog -C "RCPT TO:null@localhost" -C DATA -C . -R 250 1>&2; err_count=$(( ${err_count} + $? ))
    /usr/lib/nagios/plugins/check_smtp -H postfix-mailcow -p 587 -S 1>&2; err_count=$(( ${err_count} + $? ))
    sleep $(( ( RANDOM % 30 )  + 10 ))
    [ ${err_c_cur} -eq ${err_count} ] && [ ! $((${err_count} - 1)) -lt 0 ] && err_count=$((${err_count} - 1)) diff_c=1
    [ ${err_c_cur} -ne ${err_count} ] && diff_c=$(( ${err_c_cur} - ${err_count} ))
    progress "Postfix" ${THRESHOLD} $(( ${THRESHOLD} - ${err_count} )) ${diff_c}
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
    err_c_cur=${err_count}
    /usr/lib/nagios/plugins/check_smtp -H dovecot-mailcow -p 24 -f "watchdog" -C "RCPT TO:<watchdog@invalid>" -L -R "User doesn't exist" 1>&2; err_count=$(( ${err_count} + $? ))
    /usr/lib/nagios/plugins/check_imap -H dovecot-mailcow -p 993 -S -e "OK " 1>&2; err_count=$(( ${err_count} + $? ))
    /usr/lib/nagios/plugins/check_imap -H dovecot-mailcow -p 143 -e "OK " 1>&2; err_count=$(( ${err_count} + $? ))
    /usr/lib/nagios/plugins/check_tcp -H dovecot-mailcow -p 10001 -e "VERSION" 1>&2; err_count=$(( ${err_count} + $? ))
    /usr/lib/nagios/plugins/check_tcp -H dovecot-mailcow -p 4190 -e "Dovecot ready" 1>&2; err_count=$(( ${err_count} + $? ))
    sleep $(( ( RANDOM % 30 )  + 10 ))
    [ ${err_c_cur} -eq ${err_count} ] && [ ! $((${err_count} - 1)) -lt 0 ] && err_count=$((${err_count} - 1)) diff_c=1
    [ ${err_c_cur} -ne ${err_count} ] && diff_c=$(( ${err_c_cur} - ${err_count} ))
    progress "Dovecot" ${THRESHOLD} $(( ${THRESHOLD} - ${err_count} )) ${diff_c}
  done
  return 1
}

phpfpm_checks() {
  err_count=0
  diff_c=0
  THRESHOLD=12
  # Reduce error count by 2 after restarting an unhealthy container
  trap "[ ${err_count} -gt 1 ] && err_count=$(( ${err_count} - 2 ))" USR1
  while [ ${err_count} -lt ${THRESHOLD} ]; do
    err_c_cur=${err_count}
    cgi-fcgi -bind -connect php-fpm-mailcow:9000 | grep PHP 1>&2; err_count=$(( ${err_count} + ($? * 2)))
    /usr/lib/nagios/plugins/check_ping -H php-fpm-mailcow -w 2000,10% -c 4000,100% -p2 1>&2; err_count=$(( ${err_count} + $? ))
    /usr/lib/nagios/plugins/check_http -H nginx-mailcow -u /settings.php -p 8081 -r "settings \{" 1>&2; err_count=$(( ${err_count} + ($? * 2)))
    sleep $(( ( RANDOM % 30 )  + 10 ))
    [ ${err_c_cur} -eq ${err_count} ] && [ ! $((${err_count} - 1)) -lt 0 ] && err_count=$((${err_count} - 1)) diff_c=1
    [ ${err_c_cur} -ne ${err_count} ] && diff_c=$(( ${err_c_cur} - ${err_count} ))
    progress "PHP-FPM" ${THRESHOLD} $(( ${THRESHOLD} - ${err_count} )) ${diff_c}
  done
  return 1
}

dns_checks() {
  err_count=0
  diff_c=0
  THRESHOLD=28
  # Reduce error count by 2 after restarting an unhealthy container
  trap "[ ${err_count} -gt 1 ] && err_count=$(( ${err_count} - 2 ))" USR1
  while [ ${err_count} -lt ${THRESHOLD} ]; do
    err_c_cur=${err_count}
    /usr/lib/nagios/plugins/check_dns -H google.com 1>&2; err_count=$(( ${err_count} + ($? * 2)))
    /usr/lib/nagios/plugins/check_dns -s $(dig unbound-mailcow +short A) -H google.com 1>&2; err_count=$(( ${err_count} + ($? * 2)))
    /usr/lib/nagios/plugins/check_dns -s $(dig unbound-mailcow +short AAAA) -H google.com 1>&2; err_count=$(( ${err_count} + ($? * 2)))
    dig +dnssec org. @172.22.1.254 | grep -E 'flags:.+ad' 1>&2; err_count=$(( ${err_count} + ($? * 2)))
    sleep $(( ( RANDOM % 30 )  + 10 ))
    [ ${err_c_cur} -eq ${err_count} ] && [ ! $((${err_count} - 1)) -lt 0 ] && err_count=$((${err_count} - 1)) diff_c=1
    [ ${err_c_cur} -ne ${err_count} ] && diff_c=$(( ${err_c_cur} - ${err_count} ))
    progress "Unbound" ${THRESHOLD} $(( ${THRESHOLD} - ${err_count} )) ${diff_c}
  done
  return 1
}

# Create watchdog agents
(
while true; do
  if ! nginx_checks; then
    echo -e "\e[31m$(date) - Nginx hit error limit\e[0m"
    echo nginx-mailcow > /tmp/com_pipe
  fi
done
) &
BACKGROUND_TASKS+=($!)

(
while true; do
  if ! mysql_checks; then
    echo -e "\e[31m$(date) - MySQL hit error limit\e[0m"
    echo mysql-mailcow > /tmp/com_pipe
  fi
done
) &
BACKGROUND_TASKS+=($!)

(
while true; do
  if ! phpfpm_checks; then
    echo -e "\e[31m$(date) - PHP-FPM hit error limit\e[0m"
    echo php-fpm-mailcow > /tmp/com_pipe
  fi
done
) &
BACKGROUND_TASKS+=($!)

(
while true; do
  if ! sogo_checks; then
    echo -e "\e[31m$(date) - SOGo hit error limit\e[0m"
    echo sogo-mailcow > /tmp/com_pipe
  fi
done
) &
BACKGROUND_TASKS+=($!)

(
while true; do
  if ! postfix_checks; then
    echo -e "\e[31m$(date) - Postfix hit error limit\e[0m"
    echo postfix-mailcow > /tmp/com_pipe
  fi
done
) &
BACKGROUND_TASKS+=($!)

(
while true; do
  if ! dovecot_checks; then
    echo -e "\e[31m$(date) - Dovecot hit error limit\e[0m"
    echo dovecot-mailcow > /tmp/com_pipe
  fi
done
) &
BACKGROUND_TASKS+=($!)

(
while true; do
  if ! dns_checks; then
    echo -e "\e[31m$(date) - Unbound hit error limit\e[0m"
    echo unbound-mailcow > /tmp/com_pipe
  fi
done
) &
BACKGROUND_TASKS+=($!)

# Monitor watchdog agents, stop script when agents fails and wait for respawn by Docker (restart:always:n)
(
while true; do
  for bg_task in ${BACKGROUND_TASKS[*]}; do
    if ! kill -0 ${bg_task} 21>&2; then
      echo "Worker ${bg_task} died, stopping watchdog and waiting for respawn..."
      kill -TERM ${PARENT_PID}
    fi
    sleep 1
  done
done
) &

# Restart container when threshold limit reached
while true; do
  CONTAINER_ID=
  read com_pipe_answer </tmp/com_pipe
  if [[ ${com_pipe_answer} =~ .+-mailcow ]]; then
    kill -STOP ${BACKGROUND_TASKS[*]}
    sleep 3
    CONTAINER_ID=$(curl --silent --unix-socket /var/run/docker.sock http/containers/json?all=1 | jq -rc "map(select(.Names[] | contains (\"${com_pipe_answer}\"))) | .[] .Id")
    if [[ ! -z ${CONTAINER_ID} ]]; then
      echo "Sending restart command to ${CONTAINER_ID}..."
      curl --silent --unix-socket /var/run/docker.sock -XPOST http/containers/${CONTAINER_ID}/restart
    fi
    echo "Wait for restarted container to settle and continue watching..."
    sleep 30s
    kill -CONT ${BACKGROUND_TASKS[*]}
    kill -USR1 ${BACKGROUND_TASKS[*]}
  fi
done
