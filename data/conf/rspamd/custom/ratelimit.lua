local custom_keywords = {
  ['customrl'] = {},
}
function custom_keywords.customrl.get_value(task)
  local rspamd_logger = require "rspamd_logger"
  local rspamd_redis = require "rspamd_redis"
  local rspamd_regexp = require "rspamd_regexp"
  local re = rspamd_regexp.create('/^\\s*$/i')
  local envfrom = task:get_from(1)
  local env_from_addr = envfrom[1].addr:lower() -- get smtp from addr in lower case
  local env_from_domain = envfrom[1].domain:lower() -- get smtp from domain in lower case

  local function rlo(object) -- get ratelimited object
    local rlobj = string.format('%s', object)

    local rl_ret, rl_obj = rspamd_redis.make_request_sync({host="172.22.1.249:6379", cmd='HGET', args={'RL_OBJECT', rlobj}, timeout=2.0})

    if rl_ret and rl_obj then
      return rl_obj
    else
      return false
    end
  end

  rl_addr = rlo(env_from_addr)
  rl_domain = rlo(env_from_domain)
  if type(rl_addr) == 'string' and not re:match(rl_addr) then
    rspamd_logger.infox(rspamd_config, "returning ratelimit object for %s", env_from_addr)
    return rl_addr
  elseif type(rl_domain) == 'string' and not re:match(rl_domain) then
    rspamd_logger.infox(rspamd_config, "returning ratelimit object for %s", env_from_domain)
    return rl_domain
  end
end
function custom_keywords.customrl.get_limit(task)
  local rspamd_logger = require "rspamd_logger"
  local rspamd_redis = require "rspamd_redis"
  local rspamd_regexp = require "rspamd_regexp"
  local re = rspamd_regexp.create('/^\\s*$/i')
  local envfrom = task:get_from(1)
  local env_from_addr = envfrom[1].addr:lower() -- get smtp from addr in lower case
  local env_from_domain = envfrom[1].domain:lower() -- get smtp from domain in lower case

  local function rlv(object) -- get ratelimited object
    local rlobj = string.format('%s', object)

    local rl_ret, rl_value = rspamd_redis.make_request_sync({host="172.22.1.249:6379", cmd='HGET', args={'RL_VALUE', rlobj}, timeout=2.0})

    if rl_ret and rl_value then
      return rl_value
    else
      return false
    end
  end

  rl_addr = rlv(env_from_addr)
  rl_domain = rlv(env_from_domain)
  if type(rl_addr) == 'string' and not re:match(rl_addr) then
    rspamd_logger.infox(rspamd_config, "returning ratelimit %s for %s", rl_addr, env_from_addr)
    return rl_addr
  elseif type(rl_domain) == 'string' and not re:match(rl_domain) then
    rspamd_logger.infox(rspamd_config, "returning ratelimit %s for %s", rl_domain, env_from_domain)
    return rl_domain
  end
end
return custom_keywords
