#!/usr/bin/env bash
# _modules/scripts/ipv6_controller.sh
# THIS SCRIPT IS DESIGNED TO BE RUNNING BY MAILCOW SCRIPTS ONLY!
# DO NOT, AGAIN, NOT TRY TO RUN THIS SCRIPT STANDALONE!!!!!!

# 1) Check if the host supports IPv6
get_ipv6_support() {
  # ---- helper: probe external IPv6 connectivity without DNS ----
  _probe_ipv6_connectivity() {
    # Use literal, always-on IPv6 echo responders (no DNS required)
    local PROBE_IPS=("2001:4860:4860::8888" "2606:4700:4700::1111")
    local ip rc=1

    for ip in "${PROBE_IPS[@]}"; do
      if command -v ping6 &>/dev/null; then
        ping6 -c1 -W2 "$ip" &>/dev/null || ping6 -c1 -w2 "$ip" &>/dev/null
        rc=$?
      elif command -v ping &>/dev/null; then
        ping -6 -c1 -W2 "$ip" &>/dev/null || ping -6 -c1 -w2 "$ip" &>/dev/null
        rc=$?
      else
        rc=1
      fi
      [[ $rc -eq 0 ]] && return 0
    done
    return 1
  }

  if [[ ! -f /proc/net/if_inet6 ]] || grep -qs '^1' /proc/sys/net/ipv6/conf/all/disable_ipv6 2>/dev/null; then
    DETECTED_IPV6=false
    echo -e "${YELLOW}IPv6 not detected on host – ${LIGHT_RED}IPv6 is administratively disabled${YELLOW}.${NC}"
    return
  fi

  if ip -6 route show default 2>/dev/null | grep -qE '^default'; then
    echo -e "${YELLOW}Default IPv6 route found – testing external IPv6 connectivity...${NC}"
    if _probe_ipv6_connectivity; then
      DETECTED_IPV6=true
      echo -e "IPv6 detected on host – ${LIGHT_GREEN}leaving IPv6 support enabled${YELLOW}.${NC}"
    else
      DETECTED_IPV6=false
      echo -e "${YELLOW}Default IPv6 route present but external IPv6 connectivity failed – ${LIGHT_RED}disabling IPv6 support${YELLOW}.${NC}"
    fi
    return
  fi

  if ip -6 addr show scope global 2>/dev/null | grep -q 'inet6'; then
    DETECTED_IPV6=false
    echo -e "${YELLOW}Global IPv6 address present but no default route – ${LIGHT_RED}disabling IPv6 support${YELLOW}.${NC}"
    return
  fi

  if ip -6 addr show scope link 2>/dev/null | grep -q 'inet6'; then
    echo -e "${YELLOW}Only link-local IPv6 addresses found – testing external IPv6 connectivity...${NC}"
    if _probe_ipv6_connectivity; then
      DETECTED_IPV6=true
      echo -e "External IPv6 connectivity available – ${LIGHT_GREEN}leaving IPv6 support enabled${YELLOW}.${NC}"
    else
      DETECTED_IPV6=false
      echo -e "${YELLOW}Only link-local IPv6 present and no external connectivity – ${LIGHT_RED}disabling IPv6 support${YELLOW}.${NC}"
    fi
    return
  fi

  DETECTED_IPV6=false
  echo -e "${YELLOW}IPv6 not detected on host – ${LIGHT_RED}disabling IPv6 support${YELLOW}.${NC}"
}

# 2) Ensure Docker daemon.json has (or create) the required IPv6 settings
docker_daemon_edit(){
  DOCKER_DAEMON_CONFIG="/etc/docker/daemon.json"
  DOCKER_MAJOR=$(docker version --format '{{.Server.Version}}' 2>/dev/null | cut -d. -f1)
  MISSING=()

  _has_kv() { grep -Eq "\"$1\"[[:space:]]*:[[:space:]]*$2" "$DOCKER_DAEMON_CONFIG" 2>/dev/null; }

  if [[ -f "$DOCKER_DAEMON_CONFIG" ]]; then

    # reject empty or whitespace-only file immediately
    if [[ ! -s "$DOCKER_DAEMON_CONFIG" ]] || ! grep -Eq '[{}]' "$DOCKER_DAEMON_CONFIG"; then
      echo -e "${RED}ERROR: $DOCKER_DAEMON_CONFIG exists but is empty or contains no JSON braces – please initialize it with valid JSON (e.g. {}).${NC}"
      exit 1
    fi

    # Validate JSON if jq is present
    if command -v jq &>/dev/null && ! jq empty "$DOCKER_DAEMON_CONFIG" &>/dev/null; then
      echo -e "${RED}ERROR: Invalid JSON in $DOCKER_DAEMON_CONFIG – please correct manually.${NC}"
      exit 1
    fi

    # Gather missing keys
    ! _has_kv ipv6 true && MISSING+=("ipv6: true")

    # For Docker < 28, keep requiring fixed-cidr-v6 (default bridge needs it on old engines)
    if [[ -n "$DOCKER_MAJOR" && "$DOCKER_MAJOR" -lt 28 ]]; then
      ! grep -Eq '"fixed-cidr-v6"[[:space:]]*:[[:space:]]*".+"' "$DOCKER_DAEMON_CONFIG" \
                                && MISSING+=('fixed-cidr-v6: "fd00:dead:beef:c0::/80"')
    fi

    # For Docker < 27, ip6tables needed and was tied to experimental in older releases
    if [[ -n "$DOCKER_MAJOR" && "$DOCKER_MAJOR" -lt 27 ]]; then
      _has_kv ipv6 true && ! _has_kv ip6tables true && MISSING+=("ip6tables: true")
      ! _has_kv experimental true && MISSING+=("experimental: true")
    fi

    # Fix if needed
    if ((${#MISSING[@]}>0)); then
      echo -e "${MAGENTA}Your daemon.json is missing: ${YELLOW}${MISSING[*]}${NC}"
      if [[ -n "$FORCE" ]]; then
        ans=Y
      else
        read -p "Would you like to update $DOCKER_DAEMON_CONFIG now? [Y/n] " ans
        ans=${ans:-Y}
      fi

      if [[ $ans =~ ^[Yy]$ ]]; then
        cp "$DOCKER_DAEMON_CONFIG" "${DOCKER_DAEMON_CONFIG}.bak"
        if command -v jq &>/dev/null; then
          TMP=$(mktemp)
          # Base filter: ensure ipv6 = true
          JQ_FILTER='.ipv6 = true'

          # Add fixed-cidr-v6 only for Docker < 28
          if [[ -n "$DOCKER_MAJOR" && "$DOCKER_MAJOR" -lt 28 ]]; then
            JQ_FILTER+=' | .["fixed-cidr-v6"] = (.["fixed-cidr-v6"] // "fd00:dead:beef:c0::/80")'
          fi

          # Add ip6tables/experimental only for Docker < 27
          if [[ -n "$DOCKER_MAJOR" && "$DOCKER_MAJOR" -lt 27 ]]; then
            JQ_FILTER+=' | .ip6tables = true | .experimental = true'
          fi

          jq "$JQ_FILTER" "$DOCKER_DAEMON_CONFIG" >"$TMP" && mv "$TMP" "$DOCKER_DAEMON_CONFIG"
          echo -e "${LIGHT_GREEN}daemon.json updated. Restarting Docker...${NC}"
          (command -v systemctl &>/dev/null && systemctl restart docker) || service docker restart
          echo -e "${YELLOW}Docker restarted.${NC}"
        else
          echo -e "${RED}Please install jq or manually update daemon.json and restart Docker.${NC}"
          exit 1
        fi
      else
        echo -e "${YELLOW}User declined Docker update – please insert these changes manually:${NC}"
        echo "${MISSING[*]}"
        exit 1
      fi
    fi

  else
    # Create new daemon.json if missing
    if [[ -n "$FORCE" ]]; then
      ans=Y
    else
      read -p "$DOCKER_DAEMON_CONFIG not found. Create it with IPv6 settings? [Y/n] " ans
      ans=${ans:-Y}
    fi

    if [[ $ans =~ ^[Yy]$ ]]; then
      mkdir -p "$(dirname "$DOCKER_DAEMON_CONFIG")"
      if [[ -n "$DOCKER_MAJOR" && "$DOCKER_MAJOR" -lt 27 ]]; then
        cat > "$DOCKER_DAEMON_CONFIG" <<EOF
{
  "ipv6": true,
  "fixed-cidr-v6": "fd00:dead:beef:c0::/80",
  "ip6tables": true,
  "experimental": true
}
EOF
      elif [[ -n "$DOCKER_MAJOR" && "$DOCKER_MAJOR" -lt 28 ]]; then
        cat > "$DOCKER_DAEMON_CONFIG" <<EOF
{
  "ipv6": true,
  "fixed-cidr-v6": "fd00:dead:beef:c0::/80"
}
EOF
      else
        # Docker 28+: ipv6 works without fixed-cidr-v6
        cat > "$DOCKER_DAEMON_CONFIG" <<EOF
{
  "ipv6": true
}
EOF
      fi
      echo -e "${GREEN}Created $DOCKER_DAEMON_CONFIG with IPv6 settings.${NC}"
      echo "Restarting Docker..."
      (command -v systemctl &>/dev/null && systemctl restart docker) || service docker restart
      echo "Docker restarted."
    else
      echo "User declined to create daemon.json – please manually merge the docker daemon with these configs:"
      echo "${MISSING[*]}"
      exit 1
    fi
  fi
}

# 3) Main wrapper for generate_config.sh and update.sh
configure_ipv6() {
  # detect manual override if mailcow.conf is present
  if [[ -n "$MAILCOW_CONF" && -f "$MAILCOW_CONF" ]] && grep -q '^ENABLE_IPV6=' "$MAILCOW_CONF"; then
    MANUAL_SETTING=$(grep '^ENABLE_IPV6=' "$MAILCOW_CONF" | cut -d= -f2)
  elif [[ -z "$MAILCOW_CONF" ]] && [[ -n "${ENABLE_IPV6:-}" ]]; then
    MANUAL_SETTING="$ENABLE_IPV6"
  else
    MANUAL_SETTING=""
  fi

  get_ipv6_support

  # if user manually set it, check for mismatch
  if [[ "$DETECTED_IPV6" != "true" ]]; then
    if [[ -n "$MAILCOW_CONF" && -f "$MAILCOW_CONF" ]]; then
      if grep -q '^ENABLE_IPV6=' "$MAILCOW_CONF"; then
        sed -i 's/^ENABLE_IPV6=.*/ENABLE_IPV6=false/' "$MAILCOW_CONF"
      else
        echo "ENABLE_IPV6=false" >> "$MAILCOW_CONF"
      fi
    else
      export IPV6_BOOL=false
    fi
    echo "Skipping Docker IPv6 configuration because host does not support IPv6."
    echo "Make sure to check if your docker daemon.json does not include \"enable_ipv6\": true if you do not want IPv6."
    echo "IPv6 configuration complete: ENABLE_IPV6=false"
    sleep 2
    return
  fi

  docker_daemon_edit

  if [[ -n "$MAILCOW_CONF" && -f "$MAILCOW_CONF" ]]; then
    if grep -q '^ENABLE_IPV6=' "$MAILCOW_CONF"; then
      sed -i 's/^ENABLE_IPV6=.*/ENABLE_IPV6=true/' "$MAILCOW_CONF"
    else
      echo "ENABLE_IPV6=true" >> "$MAILCOW_CONF"
    fi
  else
    export IPV6_BOOL=true
  fi

  echo "IPv6 configuration complete: ENABLE_IPV6=true"
}