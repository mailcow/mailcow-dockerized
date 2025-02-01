#!/bin/sh

copy_cert() {
  mkdir -p "/shared/ssl/${MAILCOW_HOSTNAME}"
  cp "$private_key_path" /shared/ssl/key.pem
  cp "$certificate_path" /shared/ssl/cert.pem
}

init() {
  COMPOSE_PROJECT_NAME_LOWER=$(echo "$COMPOSE_PROJECT_NAME" | tr '[:upper:]' '[:lower:]')
  CONTAINERS=$(wget --no-check-certificate -O - "https://dockerapi.${COMPOSE_PROJECT_NAME}_mailcow-network/containers/json" 2>/dev/null)
  NGINX=$(echo "${CONTAINERS}" | jq -r '.[] | {name: .Config.Labels["com.docker.compose.service"], project: .Config.Labels["com.docker.compose.project"], id: .Id}' | jq -rc 'select( .name | tostring | contains("nginx-mailcow")) | select( .project | tostring | contains("'"${COMPOSE_PROJECT_NAME_LOWER}"'")) | .id' | tr "\n" " ")
  DOVECOT=$(echo "${CONTAINERS}" | jq -r '.[] | {name: .Config.Labels["com.docker.compose.service"], project: .Config.Labels["com.docker.compose.project"], id: .Id}' | jq -rc 'select( .name | tostring | contains("dovecot-mailcow")) | select( .project | tostring | contains("'"${COMPOSE_PROJECT_NAME_LOWER}"'")) | .id' | tr "\n" " ")
  POSTFIX=$(echo "${CONTAINERS}" | jq -r '.[] | {name: .Config.Labels["com.docker.compose.service"], project: .Config.Labels["com.docker.compose.project"], id: .Id}' | jq -rc 'select( .name | tostring | contains("postfix-mailcow")) | select( .project | tostring | contains("'"${COMPOSE_PROJECT_NAME_LOWER}"'")) | .id' | tr "\n" " ")

  # Ensure trimmed content when calling $NGINX etc.
  NGINX=$(echo "$NGINX" | sed 's/[[:space:]]*$//')
  DOVECOT=$(echo "$DOVECOT" | sed 's/[[:space:]]*$//')
  POSTFIX=$(echo "$POSTFIX" | sed 's/[[:space:]]*$//')
}

reload_nginx() {
  log "Reloading Nginx..."
  NGINX_RELOAD_RET=$(wget --no-check-certificate -O - https://dockerapi.${COMPOSE_PROJECT_NAME}_mailcow-network/containers/${NGINX}/exec --post-data='{"cmd":"reload", "task":"nginx"}' --header='Content-Type:application/json' | jq -r .type)
  [ "${NGINX_RELOAD_RET}" != "success" ] && {
    log "Could not reload Nginx, restarting container..."
    restart_container "${NGINX}"
  }
}

reload_dovecot() {
  log "Reloading Dovecot..."
  DOVECOT_RELOAD_RET=$(wget --no-check-certificate -O - "https://dockerapi.${COMPOSE_PROJECT_NAME}_mailcow-network/containers/${DOVECOT}/exec" --post-data='{"cmd":"reload", "task":"dovecot"}' --header='Content-Type:application/json' | jq -r .type)
  [ "${DOVECOT_RELOAD_RET}" != "success" ] && {
    log "Could not reload Dovecot, restarting container..."
    restart_container "${DOVECOT}"
  }
}

reload_postfix() {
  log "Reloading Postfix..."
  POSTFIX_RELOAD_RET=$(wget --no-check-certificate -O - "https://dockerapi.${COMPOSE_PROJECT_NAME}_mailcow-network/containers/${POSTFIX}/exec" --post-data='{"cmd":"reload", "task":"postfix"}' --header='Content-Type:application/json' | jq -r .type)
  [ "${POSTFIX_RELOAD_RET}" != "success" ] && {
    log "Could not reload Postfix, restarting container..."
    restart_container" ${POSTFIX}"
  }
}

restart_container() {
  for container in $*; do
    log "Restarting ${container}..."
    C_REST_OUT=$(wget --no-check-certificate -O - "https://dockerapi.${COMPOSE_PROJECT_NAME}_mailcow-network/containers/${container}/restart" --post-data='' | jq -r '.msg')
    log "${C_REST_OUT}"
  done
}

restart() {
  #reload_nginx - no need, caddy is reverse proxy, not using nginx ssl
  #reload_dovecot
  log "Restarting postfix"
  restart_container "${DOVECOT}"
  #reload_postfix
  log "Restarting postfix"
  restart_container "${POSTFIX}"
}

log() {
  timestamp=$(date +"%Y/%m/%d %H:%M:%S.%3N")
  echo "$timestamp $@" | tee -a /var/log/caddy/cert-reload.log
}

identifier=$1
certificate_path=$2
private_key_path=$3

if [ "$identifier" = "$MAILCOW_HOSTNAME" ]; then
  log "New certificate issued for $MAILCOW_HOSTNAME"
  log "identifier = $identifier"
  log "certificate_path = $certificate_path"
  log "private_key_path = $private_key_path"
  init
  copy_cert
  restart & # run in background, caddy kills tasks > 30s by default
else
  log "Ignoring cert with identifer $identifier"
fi
