#!/usr/bin/env bash
# _modules/scripts/ipv6_controller.sh
# THIS SCRIPT IS DESIGNED TO BE RUNNING BY MAILCOW SCRIPTS ONLY!
# DO NOT, AGAIN, NOT TRY TO RUN THIS SCRIPT STANDALONE!!!!!!

# 1) Check if the host supports IPv6
get_ipv6_support() {
  if grep -qs '^1' /proc/sys/net/ipv6/conf/all/disable_ipv6 2>/dev/null \
    || ! ip -6 route show default &>/dev/null; then
    DETECTED_IPV6=false
    echo -e "${YELLOW}IPv6 not detected on host – ${LIGHT_RED}disabling IPv6 support${YELLOW}.${NC}"
  else
    DETECTED_IPV6=true
    echo -e "IPv6 detected on host – ${LIGHT_GREEN}leaving IPv6 support enabled${YELLOW}.${NC}"
  fi
}

# 2) Ensure Docker daemon.json has (or create) the required IPv6 settings
docker_daemon_edit(){
  DOCKER_DAEMON_CONFIG="/etc/docker/daemon.json"
  DOCKER_MAJOR=$(docker version --format '{{.Server.Version}}' 2>/dev/null | cut -d. -f1)
  MISSING=()

  _has_kv() { grep -Eq "\"$1\"\s*:\s*$2" "$DOCKER_DAEMON_CONFIG" 2>/dev/null; }

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
    ! _has_kv ipv6 true       && MISSING+=("ipv6: true")
    ! grep -Eq '"fixed-cidr-v6"\s*:\s*".+"' "$DOCKER_DAEMON_CONFIG" \
                              && MISSING+=('fixed-cidr-v6: "fd00:dead:beef:c0::/80"')
    if [[ -n "$DOCKER_MAJOR" && "$DOCKER_MAJOR" -le 27 ]]; then
      _has_kv ipv6 true && ! _has_kv ip6tables true && MISSING+=("ip6tables: true")
      ! _has_kv experimental true                 && MISSING+=("experimental: true")
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
          JQ_FILTER='.ipv6 = true | .["fixed-cidr-v6"] = "fd00:dead:beef:c0::/80"'
          [[ "$DOCKER_MAJOR" && "$DOCKER_MAJOR" -lt 27 ]] \
            && JQ_FILTER+=' | .ip6tables = true | .experimental = true'
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
      if [[ -n "$DOCKER_MAJOR" && "$DOCKER_MAJOR" -lt 27 ]]; then
        cat > "$DOCKER_DAEMON_CONFIG" <<EOF
{
  "ipv6": true,
  "fixed-cidr-v6": "fd00:dead:beef:c0::/80",
  "ip6tables": true,
  "experimental": true
}
EOF
      else
        cat > "$DOCKER_DAEMON_CONFIG" <<EOF
{
  "ipv6": true,
  "fixed-cidr-v6": "fd00:dead:beef:c0::/80"
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
  elif [[ -z "$MAILCOW_CONF" ]] && [[ ! -z "${ENABLE_IPV6:-}" ]]; then
    MANUAL_SETTING="$ENABLE_IPV6"
  else
    MANUAL_SETTING=""
  fi

  get_ipv6_support

  # if user manually set it, check for mismatch
  if [[ -n "$MANUAL_SETTING" ]]; then
    if [[ "$MANUAL_SETTING" == "false" && "$DETECTED_IPV6" == "true" ]]; then
      echo -e "${RED}ERROR: You have ENABLE_IPV6=false but your host and Docker support IPv6.${NC}"
      echo -e "${RED}This can create an open relay. Please set ENABLE_IPV6=true in your mailcow.conf and re-run.${NC}"
      exit 1
    elif [[ "$MANUAL_SETTING" == "true" && "$DETECTED_IPV6" == "false" ]]; then
      echo -e "${RED}ERROR: You have ENABLE_IPV6=true but your host does not support IPv6.${NC}"
      echo -e "${RED}Please disable or fix your host/Docker IPv6 support, or set ENABLE_IPV6=false.${NC}"
      exit 1
    else
      return
    fi
  fi

  # no manual override: proceed to set or export
  if [[ "$DETECTED_IPV6" == "true" ]]; then
    docker_daemon_edit
  else
    echo "Skipping Docker IPv6 configuration because host does not support IPv6."
  fi

  # now write into mailcow.conf or export
  if [[ -n "$MAILCOW_CONF" && -f "$MAILCOW_CONF" ]]; then
    LINE="ENABLE_IPV6=$DETECTED_IPV6"
    if grep -q '^ENABLE_IPV6=' "$MAILCOW_CONF"; then
      sed -i "s/^ENABLE_IPV6=.*/$LINE/" "$MAILCOW_CONF"
    else
      echo "$LINE" >> "$MAILCOW_CONF"
    fi
  else
    export IPV6_BOOL="$DETECTED_IPV6"
  fi

  echo "IPv6 configuration complete: ENABLE_IPV6=$DETECTED_IPV6"
}