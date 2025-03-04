#!/usr/bin/env bash

read -r -p "Are you sure you want to reset learned hashes from Rspamd (fuzzy, bayes, neural)? [y/N] " response
response=${response,,}    # tolower
if [[ "$response" =~ ^(yes|y)$ ]]; then
  echo "Working, please wait..."
  VALKEY_ID=$(docker ps -qf name=valkey-mailcow)
  RSPAMD_ID=$(docker ps -qf name=rspamd-mailcow)

  if [ -z ${VALKEY_ID} ] || [ -z ${RSPAMD_ID} ]; then
    echo "Cannot determine Valkey or Rspamd container ID"
    exit 1
  else
    echo "Stopping Rspamd container"
    docker stop ${RSPAMD_ID}
    echo "LUA will return nil when it succeeds or print a warning/error when it fails."
    echo "Deleting all RS* keys - if any"
    docker exec -it ${VALKEY_ID} redis-cli -a ${VALKEYPASS} --no-auth-warning EVAL "for _,k in ipairs(redis.call('keys', ARGV[1])) do redis.call('del', k) end" 0 'RS*'
    echo "Deleting all BAYES* keys - if any"
    docker exec -it ${VALKEY_ID} redis-cli -a ${VALKEYPASS} --no-auth-warning EVAL "for _,k in ipairs(redis.call('keys', ARGV[1])) do redis.call('del', k) end" 0 'BAYES*'
    echo "Deleting all learned* keys - if any"
    docker exec -it ${VALKEY_ID} redis-cli -a ${VALKEYPASS} --no-auth-warning EVAL "for _,k in ipairs(redis.call('keys', ARGV[1])) do redis.call('del', k) end" 0 'learned*'
    echo "Deleting all fuzzy* keys - if any"
    docker exec -it ${VALKEY_ID} redis-cli -a ${VALKEYPASS} --no-auth-warning EVAL "for _,k in ipairs(redis.call('keys', ARGV[1])) do redis.call('del', k) end" 0 'fuzzy*'
    echo "Deleting all tRFANN* keys - if any"
    docker exec -it ${VALKEY_ID} redis-cli -a ${VALKEYPASS} --no-auth-warning EVAL "for _,k in ipairs(redis.call('keys', ARGV[1])) do redis.call('del', k) end" 0 'tRFANN*'
    echo "Starting Rspamd container"
    docker start ${RSPAMD_ID}
  fi
fi
