#!/usr/bin/env bash
# _modules/ipv6_controller.sh

# 1) Check if the host supports IPv6
get_ipv6_support() {
  if grep -qs '^1' /proc/sys/net/ipv6/conf/all/disable_ipv6 2>/dev/null \
    || ! ip -6 route show default &>/dev/null; then
    ENABLE_IPV6_LINE="ENABLE_IPV6=false"
    echo "IPv6 not detected on host – disabling IPv6 support."
  else
    ENABLE_IPV6_LINE="ENABLE_IPV6=true"
    echo "IPv6 detected on host – leaving IPv6 support enabled."
  fi
}

# 2) Ensure Docker daemon.json has the required IPv6 settings
docker_daemon_edit(){
  DOCKER_DAEMON_CONFIG="/etc/docker/daemon.json"
  MISSING=()

  # helper: check for a key/value in the JSON
  _has_kv() { grep -Eq "\"$1\"\s*:\s*$2" "$DOCKER_DAEMON_CONFIG" 2>/dev/null; }

  if [[ -f "$DOCKER_DAEMON_CONFIG" ]]; then
    # Validate JSON syntax if jq is available
    if command -v jq &>/dev/null; then
      if ! jq empty "$DOCKER_DAEMON_CONFIG" &>/dev/null; then
        echo "ERROR: Invalid JSON in $DOCKER_DAEMON_CONFIG – please correct it manually."
        exit 1
      fi
    else
      echo "WARNING: jq not found – JSON syntax not validated."
    fi

    # Check required settings
    ! _has_kv ipv6 true       && MISSING+=("ipv6: true")
    ! grep -Eq '"fixed-cidr-v6"\s*:\s*".+"' "$DOCKER_DAEMON_CONFIG" \
                              && MISSING+=('fixed-cidr-v6: "fd00:dead:beef:c0::/80"')

    # Determine Docker major version
    DOCKER_MAJOR=$(docker version --format '{{.Server.Version}}' 2>/dev/null | cut -d. -f1)
    if [[ -n "$DOCKER_MAJOR" && "$DOCKER_MAJOR" -lt 27 ]]; then
      _has_kv ipv6 true && ! _has_kv ip6tables true \
                              && MISSING+=("ip6tables: true")
      ! _has_kv experimental true \
                              && MISSING+=("experimental: true")
    else
      echo "Docker ≥27 detected – skipping ip6tables/experimental checks."
    fi

    # If anything is missing, offer to auto-fix
    if ((${#MISSING[@]}>0)); then
      echo "Your daemon.json is missing: ${MISSING[*]}"
      read -p "Would you like to update $DOCKER_DAEMON_CONFIG now? [Y/n] " ans
      ans=${ans:-Y}
      if [[ $ans =~ ^[Yy]$ ]]; then
        cp "$DOCKER_DAEMON_CONFIG" "${DOCKER_DAEMON_CONFIG}.bak"
        if command -v jq &>/dev/null; then
          TMP=$(mktemp)
          JQ_FILTER='.ipv6 = true | .["fixed-cidr-v6"] = "fd00:dead:beef:c0::/80"'
          [[ "$DOCKER_MAJOR" && "$DOCKER_MAJOR" -lt 27 ]] \
            && JQ_FILTER+=' | .ip6tables = true | .experimental = true'
          jq "$JQ_FILTER" "$DOCKER_DAEMON_CONFIG" >"$TMP" && mv "$TMP" "$DOCKER_DAEMON_CONFIG"
          echo "daemon.json updated. Restarting Docker..."
          (command -v systemctl &>/dev/null && systemctl restart docker) \
            || service docker restart
          echo "Docker restarted. Please rerun this script."
          exit 1
        else
          echo "Please install jq or manually update daemon.json and restart Docker."
          exit 1
        fi
      else
        ENABLE_IPV6_LINE="ENABLE_IPV6=false"
        echo "User declined update – disabling IPv6 support."
      fi
    fi
  else
    echo "WARNING: $DOCKER_DAEMON_CONFIG not found – skipping Docker config check."
  fi
}

# 3) Wrapper to integrate into both generate_config.sh and update.sh
configure_ipv6() {
  get_ipv6_support

  # Only edit Docker config if IPv6 is enabled on host
  if [[ "$ENABLE_IPV6_LINE" == "ENABLE_IPV6=true" ]]; then
    docker_daemon_edit
  else
    echo "Skipping Docker IPv6 configuration because host does not support IPv6."
  fi

  # Write ENABLE_IPV6 into mailcow.conf (generate_config.sh) or export in current shell (update.sh)
  if [[ -n "$MAILCOW_CONF" && -f "$MAILCOW_CONF" ]]; then
    # generate_config.sh: append or replace in mailcow.conf
    if grep -q '^ENABLE_IPV6=' "$MAILCOW_CONF"; then
      sed -i "s/^ENABLE_IPV6=.*/$ENABLE_IPV6_LINE/" "$MAILCOW_CONF"
    else
      echo "$ENABLE_IPV6_LINE" >> "$MAILCOW_CONF"
    fi
  else
    # update.sh: export into the running environment
    export "$ENABLE_IPV6_LINE"
  fi

  echo "IPv6 configuration complete: $ENABLE_IPV6_LINE"
}