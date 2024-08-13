#!/bin/bash

STATUS_FILE="/tmp/healthcheck_status"
RUNS=0

# Declare log function for logfile to stdout
function log_to_stdout() {
echo "$(date +"%Y-%m-%d %H:%M:%S"): $1"
}

# General Ping function to check general pingability
function check_ping() {
declare -a ipstoping=("1.1.1.1" "8.8.8.8" "9.9.9.9")
local fail_tolerance=1
local failures=0

for ip in "${ipstoping[@]}" ; do
    success=false
    for ((i=1; i<=3; i++)); do
        ping -q -c 3 -w 5 "$ip" > /dev/null
        if [ $? -eq 0 ]; then
            success=true
            break
        else
            log_to_stdout "Healthcheck: Failed to ping $ip on attempt $i. Trying again..."
        fi
    done
    
    if [ "$success" = false ]; then
        log_to_stdout "Healthcheck: Couldn't ping $ip after 3 attempts. Marking this IP as failed."
        ((failures++))
    fi
done

if [ $failures -gt $fail_tolerance ]; then
    log_to_stdout "Healthcheck: Too many ping failures ($fail_tolerance failures allowed, you got $failures failures), marking Healthcheck as unhealthy..."
    return 1
fi

return 0

}

# General DNS Resolve Check against Unbound Resolver himself
function check_dns() {
declare -a domains=("fuzzy.mailcow.email" "github.com" "hub.docker.com")
local fail_tolerance=1
local failures=0

for domain in "${domains[@]}" ; do
    success=false
    for ((i=1; i<=3; i++)); do
        dig_output=$(dig +short +timeout=2 +tries=1 "$domain" @127.0.0.1 2>/dev/null)
        dig_rc=$?

        if [ $dig_rc -ne 0 ] || [ -z "$dig_output" ]; then
            log_to_stdout "Healthcheck: DNS Resolution Failed on attempt $i for $domain! Trying again..."
        else
            success=true
            break
        fi
    done
    
    if [ "$success" = false ]; then
        log_to_stdout "Healthcheck: DNS Resolution not possible after 3 attempts for $domain... Gave up!"
        ((failures++))
    fi
done

if [ $failures -gt $fail_tolerance ]; then
    log_to_stdout "Healthcheck: Too many DNS failures ($fail_tolerance failures allowed, you got $failures failures), marking Healthcheck as unhealthy..."
    return 1
fi

return 0
}

while true; do

    if [[ ${SKIP_UNBOUND_HEALTHCHECK} == "y" ]]; then
    log_to_stdout "Healthcheck: ALL CHECKS WERE SKIPPED! Unbound is healthy!"
    echo "0" > $STATUS_FILE
    sleep 365d
    fi

    # run checks, if check is not returning 0 (return value if check is ok), healthcheck will exit with 1 (marked in docker as unhealthy)
    check_ping
    PING_STATUS=$?

    check_dns
    DNS_STATUS=$?

    if [ $PING_STATUS -ne 0 ] || [ $DNS_STATUS -ne 0 ]; then
        echo "1" > $STATUS_FILE

    else
        echo "0" > $STATUS_FILE
    fi

    sleep 30

done