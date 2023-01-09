#!/usr/bin/env bash
[[ -f mailcow.conf ]] && source mailcow.conf
[[ -f ../mailcow.conf ]] && source ../mailcow.conf

read -r -p "Are you sure you want to reset learned hashes from Rspamd (fuzzy, bayes, neural)? [y/N] " response
response=${response,,}    # tolower
if [[ "$response" =~ ^(yes|y)$ ]]; then
  _engine="${MAILCOW_CONTAINER_ENGINE}"

  echo "Working, please wait..."
  REDIS_ID=$(${_engine} ps -qf name=redis-mailcow)
  RSPAMD_ID=$(${_engine} ps -qf name=rspamd-mailcow)

  if [ -z ${REDIS_ID} ] || [ -z ${RSPAMD_ID} ]; then
    echo "Cannot determine Redis or Rspamd container ID"
    exit 1
  else
    echo "Stopping Rspamd container"
    ${_engine} stop ${RSPAMD_ID}
    echo "LUA will return nil when it succeeds or print a warning/error when it fails."
    echo "Deleting all RS* keys - if any"
    ${_engine} exec -it ${REDIS_ID} redis-cli EVAL "for _,k in ipairs(redis.call('keys', ARGV[1])) do redis.call('del', k) end" 0 'RS*'
    echo "Deleting all BAYES* keys - if any"
    ${_engine} exec -it ${REDIS_ID} redis-cli EVAL "for _,k in ipairs(redis.call('keys', ARGV[1])) do redis.call('del', k) end" 0 'BAYES*'
    echo "Deleting all learned* keys - if any"
    ${_engine} exec -it ${REDIS_ID} redis-cli EVAL "for _,k in ipairs(redis.call('keys', ARGV[1])) do redis.call('del', k) end" 0 'learned*'
    echo "Deleting all fuzzy* keys - if any"
    ${_engine} exec -it ${REDIS_ID} redis-cli EVAL "for _,k in ipairs(redis.call('keys', ARGV[1])) do redis.call('del', k) end" 0 'fuzzy*'
    echo "Deleting all tRFANN* keys - if any"
    ${_engine} exec -it ${REDIS_ID} redis-cli EVAL "for _,k in ipairs(redis.call('keys', ARGV[1])) do redis.call('del', k) end" 0 'tRFANN*'
    echo "Starting Rspamd container"
    ${_engine} start ${RSPAMD_ID}
  fi
fi
