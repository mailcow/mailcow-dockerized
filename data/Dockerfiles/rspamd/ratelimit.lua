--[[
Copyright (c) 2011-2017, Vsevolod Stakhov <vsevolod@highsecure.ru>
Copyright (c) 2016-2017, Andrew Lewis <nerf@judo.za.org>

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

    http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.
]]--

if confighelp then
  return
end

-- A plugin that implements ratelimits using redis

local E, settings = {}, {}
local N = 'ratelimit'
-- Senders that are considered as bounce
local bounce_senders = {'postmaster', 'mailer-daemon', '', 'null', 'fetchmail-daemon', 'mdaemon'}
-- Do not check ratelimits for these recipients
local whitelisted_rcpts = {'postmaster', 'mailer-daemon'}
local whitelisted_ip
local whitelisted_user
local max_rcpt = 5
local redis_params
local ratelimit_symbol
-- Do not delay mail after 1 day
local use_ip_score = false
local rl_prefix = 'RL'
local ip_score_lower_bound = 10
local ip_score_ham_multiplier = 1.1
local ip_score_spam_divisor = 1.1
local limits_hash

local message_func = function(_, limit_type)
  return string.format('Ratelimit "%s" exceeded', limit_type)
end

local rspamd_logger = require "rspamd_logger"
local rspamd_util = require "rspamd_util"
local rspamd_lua_utils = require "lua_util"
local lua_redis = require "lua_redis"
local fun = require "fun"

local user_keywords = {'user'}

local redis_script_sha
local redis_script = [[local bucket
local limited = false
local buckets = {}
local queue_id = table.remove(ARGV)
local now = table.remove(ARGV)

local argi = 0
for i = 1, #KEYS do
  local key = KEYS[i]
  local period = tonumber(ARGV[argi+1])
  local limit = tonumber(ARGV[argi+2])
  if not buckets[key] then
    buckets[key] = {
      max_period = period,
      limits = { {period, limit} },
    }
  else
    table.insert(buckets[key].limits, {period, limit})
    if period > buckets[key].max_period then
      buckets[key].max_period = period
    end
  end
  argi = argi + 2
end

for k, v in pairs(buckets) do
  local maxp = v.max_period
  redis.call('ZREMRANGEBYSCORE', k, '-inf', now - maxp)
  for _, lim in ipairs(v.limits) do
    local period = lim[1]
    local limit = lim[2]
    local rate
    if period == maxp then
      rate = redis.call('ZCARD', k)
    else
      rate = redis.call('ZCOUNT', k, now - period, '+inf')
    end
    if rate and rate >= limit then
      limited = true
      bucket = k
    end
  end
  redis.call('EXPIRE', k, maxp)
  if limited then break end
end

if not limited then
  for k in pairs(buckets) do
    redis.call('ZADD', k, now, queue_id)
  end
end

return {limited, bucket}]]

local redis_script_symbol = [[local limited = false
local buckets, results = {}, {}
local queue_id = table.remove(ARGV)
local now = table.remove(ARGV)

local argi = 0
for i = 1, #KEYS do
  local key = KEYS[i]
  local period = tonumber(ARGV[argi+1])
  local limit = tonumber(ARGV[argi+2])
  if not buckets[key] then
    buckets[key] = {
      max_period = period,
      limits = { {period, limit} },
    }
  else
    table.insert(buckets[key].limits, {period, limit})
    if period > buckets[key].max_period then
      buckets[key].max_period = period
    end
  end
  argi = argi + 2
end

for k, v in pairs(buckets) do
  local maxp = v.max_period
  redis.call('ZREMRANGEBYSCORE', k, '-inf', now - maxp)
  for _, lim in ipairs(v.limits) do
    local period = lim[1]
    local limit = lim[2]
    local rate
    if period == maxp then
      rate = redis.call('ZCARD', k)
    else
      rate = redis.call('ZCOUNT', k, now - period, '+inf')
    end
    if rate then
      local mult = 2 * math.tanh(rate / (limit * 2))
      if mult >= 0.5 then
        table.insert(results, {k, tostring(mult)})
      end
    end
  end
  redis.call('ZADD', k, now, queue_id)
  redis.call('EXPIRE', k, maxp)
end

return results]]

local function load_scripts(cfg, ev_base)
  local function rl_script_cb(err, data)
    if err then
      rspamd_logger.errx(cfg, 'Script loading failed: ' .. err)
    elseif type(data) == 'string' then
      redis_script_sha = data
    end
  end
  local script
  if ratelimit_symbol then
    script = redis_script_symbol
  else
    script = redis_script
  end
  lua_redis.redis_make_request_taskless(
    ev_base,
    cfg,
    redis_params,
    nil, -- key
    true, -- is write
    rl_script_cb, --callback
    'SCRIPT', -- command
    {'LOAD', script}
  )
end

local limit_parser
local function parse_string_limit(lim, no_error)
  local function parse_time_suffix(s)
    if s == 's' then
      return 1
    elseif s == 'm' then
      return 60
    elseif s == 'h' then
      return 3600
    elseif s == 'd' then
      return 86400
    end
  end
  local function parse_num_suffix(s)
    if s == '' then
      return 1
    elseif s == 'k' then
      return 1000
    elseif s == 'm' then
      return 1000000
    elseif s == 'g' then
      return 1000000000
    end
  end
  local lpeg = require "lpeg"

  if not limit_parser then
    local digit = lpeg.R("09")
    limit_parser = {}
    limit_parser.integer =
    (lpeg.S("+-") ^ -1) *
            (digit   ^  1)
    limit_parser.fractional =
    (lpeg.P(".")   ) *
            (digit ^ 1)
    limit_parser.number =
    (limit_parser.integer *
            (limit_parser.fractional ^ -1)) +
            (lpeg.S("+-") * limit_parser.fractional)
    limit_parser.time = lpeg.Cf(lpeg.Cc(1) *
            (limit_parser.number / tonumber) *
            ((lpeg.S("smhd") / parse_time_suffix) ^ -1),
      function (acc, val) return acc * val end)
    limit_parser.suffixed_number = lpeg.Cf(lpeg.Cc(1) *
            (limit_parser.number / tonumber) *
            ((lpeg.S("kmg") / parse_num_suffix) ^ -1),
      function (acc, val) return acc * val end)
    limit_parser.limit = lpeg.Ct(limit_parser.suffixed_number *
            (lpeg.S(" ") ^ 0) * lpeg.S("/") * (lpeg.S(" ") ^ 0) *
            limit_parser.time)
  end
  local t = lpeg.match(limit_parser.limit, lim)

  if t and t[1] and t[2] and t[2] ~= 0 then
    return t[2], t[1]
  end

  if not no_error then
    rspamd_logger.errx(rspamd_config, 'bad limit: %s', lim)
  end

  return nil
end

local function resize_element(x_score, x_total, element)
  local x_ip_score
  if not x_total then x_total = 0 end
  if x_total < ip_score_lower_bound or x_total <= 0 then
    x_score = 1
  else
    x_score = x_score / x_total
  end
  if x_score > 0 then
    x_ip_score = x_score / ip_score_spam_divisor
    element = element * rspamd_util.tanh(2.718281 * x_ip_score)
  elseif x_score < 0 then
    x_ip_score = ((1 + (x_score * -1)) * ip_score_ham_multiplier)
    element = element * x_ip_score
  end
  return element
end

--- Check whether this addr is bounce
local function check_bounce(from)
  return fun.any(function(b) return b == from end, bounce_senders)
end

local custom_keywords = {}

local keywords = {
  ['ip'] = {
    ['get_value'] = function(task)
      local ip = task:get_ip()
      if ip and ip:is_valid() then return ip end
      return nil
    end,
  },
  ['rip'] = {
    ['get_value'] = function(task)
      local ip = task:get_ip()
      if ip and ip:is_valid() and not ip:is_local() then return ip end
      return nil
    end,
  },
  ['from'] = {
    ['get_value'] = function(task)
      local from = task:get_from(0)
      if ((from or E)[1] or E).addr then
        return string.lower(from[1]['addr'])
      end
      return nil
    end,
  },
  ['bounce'] = {
    ['get_value'] = function(task)
      local from = task:get_from(0)
      if not ((from or E)[1] or E).user then
        return '_'
      end
      if check_bounce(from[1]['user']) then return '_' else return nil end
    end,
  },
  ['asn'] = {
    ['get_value'] = function(task)
      local asn = task:get_mempool():get_variable('asn')
      if not asn then
        return nil
      else
        return asn
      end
    end,
  },
  ['user'] = {
    ['get_value'] = function(task)
      local auser = task:get_user()
      if not auser then
        return nil
      else
        return auser
      end
    end,
  },
  ['to'] = {
    ['get_value'] = function()
      return '%s' -- 'to' is special
    end,
  },
}

local function dynamic_rate_key(task, rtype)
  local key_t = {rl_prefix, rtype}
  local key_keywords = rspamd_str_split(rtype, '_')
  local have_to, have_user = false, false
  for _, v in ipairs(key_keywords) do
    if (custom_keywords[v] and type(custom_keywords[v]['condition']) == 'function') then
      if not custom_keywords[v]['condition']() then return nil end
    end
    local ret
    if custom_keywords[v] and type(custom_keywords[v]['get_value']) == 'function' then
      ret = custom_keywords[v]['get_value'](task)
    elseif keywords[v] and type(keywords[v]['get_value']) == 'function' then
      ret = keywords[v]['get_value'](task)
    end
    if not ret then return nil end
    for _, uk in ipairs(user_keywords) do
      if v == uk then have_user = true end
      if have_user then break end
    end
    if v == 'to' then have_to = true end
    if type(ret) ~= 'string' then ret = tostring(ret) end
    table.insert(key_t, ret)
  end
  if (not have_user) and task:get_user() then
    return nil
  end
  if not have_to then
    return table.concat(key_t, ":")
  else
    local rate_keys = {}
    local rcpts = task:get_recipients(0)
    if not ((rcpts or E)[1] or E).addr then
      return nil
    end
    local key_s = table.concat(key_t, ":")
    local total_rcpt = 0
    for _, r in ipairs(rcpts) do
      if r['addr'] and total_rcpt < max_rcpt then
        local key_f = string.format(key_s, string.lower(r['addr']))
        table.insert(rate_keys, key_f)
        total_rcpt = total_rcpt + 1
      end
    end
    return rate_keys
  end
end

local function process_buckets(task, buckets)
  if not buckets then return end
  local function rl_redis_cb(err, data)
    if err then
      rspamd_logger.infox(task, 'got error while setting limit: %1', err)
    end
    if not data then return end
    if data[1] == 1 then
      rspamd_logger.infox(task,
        'ratelimit "%s" exceeded',
        data[2])
      task:set_pre_result('soft reject',
        message_func(task, data[2]))
    end
  end
  local function rl_symbol_redis_cb(err, data)
    if err then
      rspamd_logger.infox(task, 'got error while setting limit: %1', err)
    end
    if not data then return end
    for i, b in ipairs(data) do
      task:insert_result(ratelimit_symbol, b[2], string.format('%s:%s:%s', i, b[1], b[2]))
    end
  end
  local redis_cb = rl_redis_cb
  if ratelimit_symbol then redis_cb = rl_symbol_redis_cb end
  local args = {redis_script_sha, #buckets}
  for _, bucket in ipairs(buckets) do
    table.insert(args, bucket[2])
  end
  for _, bucket in ipairs(buckets) do
    if use_ip_score then
      local asn_score,total_asn,
        country_score,total_country,
        ipnet_score,total_ipnet,
        ip_score, total_ip = task:get_mempool():get_variable('ip_score',
        'double,double,double,double,double,double,double,double')
      local key_keywords = rspamd_str_split(bucket[2], '_')
      local has_asn, has_ip = false, false
      for _, v in ipairs(key_keywords) do
        if v == "asn" then has_asn = true end
        if v == "ip" then has_ip = true end
        if has_ip and has_asn then break end
      end
      if has_asn and not has_ip then
        bucket[1][2] = resize_element(asn_score, total_asn, bucket[1][2])
      elseif has_ip then
        if total_ip and total_ip > ip_score_lower_bound then
          bucket[1][2] = resize_element(ip_score, total_ip, bucket[1][2])
        elseif total_ipnet and total_ipnet > ip_score_lower_bound then
          bucket[1][2] = resize_element(ipnet_score, total_ipnet, bucket[1][2])
        elseif total_asn and total_asn > ip_score_lower_bound then
          bucket[1][2] = resize_element(asn_score, total_asn, bucket[1][2])
        elseif total_country and total_country > ip_score_lower_bound then
          bucket[1][2] = resize_element(country_score, total_country, bucket[1][2])
        else
          bucket[1][2] = resize_element(ip_score, total_ip, bucket[1][2])
        end
      end
    end
    table.insert(args, bucket[1][1])
    table.insert(args, bucket[1][2])
  end
  table.insert(args, rspamd_util.get_time())
  table.insert(args, task:get_queue_id() or task:get_uid())
  local ret = rspamd_redis_make_request(task,
    redis_params, -- connect params
    nil, -- hash key
    true, -- is write
    redis_cb, --callback
    'evalsha', -- command
    args -- arguments
  )
  if not ret then
    rspamd_logger.errx(task, 'got error connecting to redis')
  end
end

local function ratelimit_cb(task)
  if rspamd_lua_utils.is_rspamc_or_controller(task) then return end
  local args = {}
  -- Get initial task data
  local ip = task:get_from_ip()
  if ip and ip:is_valid() and whitelisted_ip then
    if whitelisted_ip:get_key(ip) then
      -- Do not check whitelisted ip
      rspamd_logger.infox(task, 'skip ratelimit for whitelisted IP')
      return
    end
  end
  -- Parse all rcpts
  local rcpts = task:get_recipients()
  local rcpts_user = {}
  if rcpts then
    fun.each(function(r)
      fun.each(function(type) table.insert(rcpts_user, r[type]) end, {'user', 'addr'})
    end, rcpts)
    if fun.any(
      function(r)
        if fun.any(function(w) return r == w end, whitelisted_rcpts) then return true end
      end,
      rcpts_user) then

      rspamd_logger.infox(task, 'skip ratelimit for whitelisted recipient')
      return
    end
  end
  -- Get user (authuser)
  if whitelisted_user then
    local auser = task:get_user()
    if whitelisted_user:get_key(auser) then
      rspamd_logger.infox(task, 'skip ratelimit for whitelisted user')
      return
    end
  end

  local redis_keys = {}
  local redis_keys_rev = {}
  local function collect_redis_keys()
    local function collect_cb(err, data)
      if err then
        rspamd_logger.errx(task, 'redis error: %1', err)
      else
        for i, d in ipairs(data) do
          if type(d) == 'string' then
            local plim, size = parse_string_limit(d)
            if plim then
              table.insert(args, {{plim, size}, redis_keys_rev[i]})
            end
          end
        end
        return process_buckets(task, args)
      end
    end
    local params, method
    if limits_hash then
      params = {limits_hash, rspamd_lua_utils.unpack(redis_keys)}
      method = 'HMGET'
    else
      method = 'MGET'
      params = redis_keys
    end
    local requested_keys = rspamd_redis_make_request(task,
      redis_params, -- connect params
      nil, -- hash key
      true, -- is write
      collect_cb, --callback
      method, -- command
      params -- arguments
    )
    if not requested_keys then
      rspamd_logger.errx(task, 'got error connecting to redis')
      return process_buckets(task, args)
    end
  end

  local rate_key
  for k in pairs(settings) do
    rate_key = dynamic_rate_key(task, k)
    if rate_key then
      if type(rate_key) == 'table' then
        for _, rk in ipairs(rate_key) do
          if type(settings[k]) == 'string' and
              (custom_keywords[settings[k]] and type(custom_keywords[settings[k]]['get_limit']) == 'function') then
            local res = custom_keywords[settings[k]]['get_limit'](task)
            if type(res) == 'string' then res = {res} end
            for _, r in ipairs(res) do
              local plim, size = parse_string_limit(r, true)
              if plim then
                table.insert(args, {{plim, size}, rk})
              else
                local rkey = string.match(settings[k], 'redis:(.*)')
                if rkey then
                  table.insert(redis_keys, rkey)
                  redis_keys_rev[#redis_keys] = rk
                else
                  rspamd_logger.infox(task, "Don't know what to do with limit: %1", settings[k])
                end
              end
            end
          end
        end
      else
        if type(settings[k]) == 'string' and
          (custom_keywords[settings[k]] and type(custom_keywords[settings[k]]['get_limit']) == 'function') then
          local res = custom_keywords[settings[k]]['get_limit'](task)
          if type(res) == 'string' then res = {res} end
          for _, r in ipairs(res) do
            local plim, size = parse_string_limit(r, true)
            if plim then
              table.insert(args, {{plim, size}, rate_key})
            else
              local rkey = string.match(r, 'redis:(.*)')
              if rkey then
                table.insert(redis_keys, rkey)
                redis_keys_rev[#redis_keys] = rate_key
              else
                rspamd_logger.infox(task, "Don't know what to do with limit: %1", settings[k])
              end
            end
          end
        elseif type(settings[k]) == 'table' then
          for _, rl in ipairs(settings[k]) do
            table.insert(args, {{rl[1], rl[2]}, rate_key})
          end
        elseif type(settings[k]) == 'string' then
          local rkey = string.match(settings[k], 'redis:(.*)')
          if rkey then
            table.insert(redis_keys, rkey)
            redis_keys_rev[#redis_keys] = rate_key
          else
            rspamd_logger.infox(task, "Don't know what to do with limit: %1", settings[k])
          end
        end
      end
    end
  end

  if redis_keys[1] then
    return collect_redis_keys()
  else
    return process_buckets(task, args)
  end
end

local opts = rspamd_config:get_all_opt(N)
if opts then
  if opts['limit'] then
    rspamd_logger.errx(rspamd_config, 'Legacy ratelimit config format no longer supported')
  end

  if opts['rates'] and type(opts['rates']) == 'table' then
    -- new way of setting limits
    fun.each(function(t, lim)
      if type(lim) == 'table' then
        settings[t] = {}
        fun.each(function(l)
          local plim, size = parse_string_limit(l)
          if plim then
            table.insert(settings[t], {plim, size})
          end
        end, lim)
      elseif type(lim) == 'string' then
        local plim, size = parse_string_limit(lim)
        if plim then
          settings[t] = { {plim, size} }
        end
      end
    end, opts['rates'])
  end

  if opts['dynamic_rates'] and type(opts['dynamic_rates']) == 'table' then
    fun.each(function(t, lim)
      if type(lim) == 'string' then
        settings[t] = lim
      end
    end, opts['dynamic_rates'])
  end

  local enabled_limits = fun.totable(fun.map(function(t)
    return t
  end, settings))
  rspamd_logger.infox(rspamd_config, 'enabled rate buckets: [%1]', table.concat(enabled_limits, ','))

  if opts['whitelisted_rcpts'] and type(opts['whitelisted_rcpts']) == 'string' then
    whitelisted_rcpts = rspamd_str_split(opts['whitelisted_rcpts'], ',')
  elseif type(opts['whitelisted_rcpts']) == 'table' then
    whitelisted_rcpts = opts['whitelisted_rcpts']
  end

  if opts['whitelisted_ip'] then
    whitelisted_ip = rspamd_map_add('ratelimit', 'whitelisted_ip', 'radix',
      'Ratelimit whitelist ip map')
  end

  if opts['whitelisted_user'] then
    whitelisted_user = rspamd_map_add('ratelimit', 'whitelisted_user', 'set',
      'Ratelimit whitelist user map')
  end

  if opts['symbol'] then
    -- We want symbol instead of pre-result
    ratelimit_symbol = opts['symbol']
  end

  if opts['max_rcpt'] then
    max_rcpt = tonumber(opts['max_rcpt'])
  end

  if opts['use_ip_score'] then
    use_ip_score = true
    local ip_score_opts = rspamd_config:get_all_opt('ip_score')
    if ip_score_opts and ip_score_opts['lower_bound'] then
      ip_score_lower_bound = ip_score_opts['lower_bound']
    end
  end

  if opts['custom_keywords'] then
    custom_keywords = dofile(opts['custom_keywords'])
  end

  if opts['user_keywords'] then
    user_keywords = opts['user_keywords']
  end

  if opts['message_func'] then
    message_func = assert(load(opts['message_func']))()
  end

  if opts['limits_hash'] then
    limits_hash = opts['limits_hash']
  end

  redis_params = rspamd_parse_redis_server('ratelimit')
  if not redis_params then
    rspamd_logger.infox(rspamd_config, 'no servers are specified, disabling module')
  else
    local s = {
      type = 'prefilter,nostat',
      name = 'RATELIMIT_CHECK',
      priority = 4,
      callback = ratelimit_cb,
    }
    if use_ip_score then
      s.type = 'normal'
    end
    if ratelimit_symbol then
      s.name = ratelimit_symbol
    end
    local id = rspamd_config:register_symbol(s)
    if use_ip_score then
      rspamd_config:register_dependency(id, 'IP_SCORE')
    end
    for _, v in pairs(custom_keywords) do
      if type(v) == 'table' and type(v['init']) == 'function' then
        v['init']()
      end
    end
  end
end
rspamd_config:add_on_load(function(cfg, ev_base, worker)
  load_scripts(cfg, ev_base)
end)
