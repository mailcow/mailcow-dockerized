#!/bin/bash
# Tell every live replica of nginx / dovecot / postfix to reload (or restart
# on cert-amount change) via the mailcow-agent control bus. Replaces the old
# dockerapi-based container_id lookup + exec dance.

reload_service() {
  local svc="$1"
  echo "Reloading ${svc} via mailcow-agent..."
  if ! mailcow-agent-cli send "${svc}" reload >/dev/null; then
    echo "Could not publish reload to ${svc}, attempting restart..."
    mailcow-agent-cli send "${svc}" restart >/dev/null || true
  fi
}

restart_service() {
  local svc="$1"
  echo "Restarting ${svc} via mailcow-agent..."
  mailcow-agent-cli send "${svc}" restart >/dev/null || true
}

if [[ "${CERT_AMOUNT_CHANGED}" == "1" ]]; then
  restart_service nginx
  restart_service dovecot
  restart_service postfix
else
  reload_service nginx
  restart_service dovecot
  restart_service postfix
fi
