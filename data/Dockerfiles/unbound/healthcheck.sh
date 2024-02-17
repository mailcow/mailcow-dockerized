#!/bin/bash

# Skip Unbound (DNS Resolver) Healthchecks (NOT Recommended!)
if [[ "${SKIP_UNBOUND_HEALTHCHECK}" =~ ^([yY][eE][sS]|[yY])+$ ]]; then
    SKIP_UNBOUND_HEALTHCHECK=y
fi

# Declare log function for logfile inside container
function log_to_file() {
    echo "$(date +"%Y-%m-%d %H:%M:%S"): $1" > /var/log/healthcheck.log
}

# General Ping function to check general pingability
function check_ping() {
    declare -a ipstoping=("1.1.1.1" "8.8.8.8" "9.9.9.9")

    for ip in "${ipstoping[@]}" ; do
            ping -q -c 3 -w 5 "$ip"
            if [ $? -ne 0 ]; then
                log_to_file "Healthcheck: Couldn't ping $ip for 5 seconds... Gave up!"
                log_to_file "Please check your internet connection or firewall rules to fix this error, because a simple ping test should always go through from the unbound container!"
                return 1
            fi
    done

    log_to_file "Healthcheck: Ping Checks WORKING properly!"
    return 0
}

# General DNS Resolve Check against Unbound Resolver himself
function check_dns() {
    declare -a domains=("mailcow.email" "github.com" "hub.docker.com")

    for domain in "${domains[@]}" ; do
        for ((i=1; i<=3; i++)); do
            dig +short +timeout=2 +tries=1 "$domain" @127.0.0.1 > /dev/null
        if [ $? -ne 0 ]; then
            log_to_file "Healthcheck: DNS Resolution Failed on $i attempt! Trying again..."
            if [ $i -eq 3 ]; then
                log_to_file "Healthcheck: DNS Resolution not possible after $i attempts... Gave up!"
                log_to_file "Maybe check your outbound firewall, as it needs to resolve DNS over TCP AND UDP!"
                return 1
            fi
        fi
        done
    done

    log_to_file "Healthcheck: DNS Resolver WORKING properly!"
    return 0
    
}

if [[ ${SKIP_UNBOUND_HEALTHCHECK} == "y" ]]; then
    log_to_file "Healthcheck: ALL CHECKS WERE SKIPPED! Unbound is healthy!"
    exit 0
fi

# run checks, if check is not returning 0 (return value if check is ok), healthcheck will exit with 1 (marked in docker as unhealthy)
check_ping

if [ $? -ne 0 ]; then
    exit 1
fi

check_dns

if [ $? -ne 0 ]; then
    exit 1
fi

log_to_file "Healthcheck: ALL CHECKS WERE SUCCESSFUL! Unbound is healthy!"
exit 0