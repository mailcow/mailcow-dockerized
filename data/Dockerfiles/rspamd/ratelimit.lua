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

local E = {}
local N = 'ratelimit'
local redis_params
-- Senders that are considered as bounce
local settings = {
  bounce_senders = { 'postmaster', 'mailer-daemon', '', 'null', 'fetchmail-daemon', 'mdaemon' },
-- Do not check ratelimits for these recipients
  whitelisted_rcpts = { 'postmaster', 'mailer-daemon' },
  prefix = 'RL',
  ham_factor_rate = 1.01,
  spam_factor_rate = 0.99,
  ham_factor_burst = 1.02,
  spam_factor_burst = 0.98,
  max_rate_mult = 5,
  max_bucket_mult = 10,
  expire = 60 * 60 * 24 * 2, -- 2 days by default
  limits = {},
  allow_local = false,
}

-- Checks bucket, updating it if needed
-- KEYS[1] - prefix to update, e.g. RL_<triplet>_<seconds>
-- KEYS[2] - current time in milliseconds
-- KEYS[3] - bucket leak rate (messages per millisecond)
-- KEYS[4] - bucket burst
-- KEYS[5] - expire for a bucket
-- return 1 if message should be ratelimited and 0 if not
-- Redis keys used:
--   l - last hit
--   b - current burst
--   dr - current dynamic rate multiplier (*10000)
--   db - current dynamic burst multiplier (*10000)
local bucket_check_script = [[
  local last = redis.call('HGET', KEYS[1], 'l')
  local now = tonumber(KEYS[2])
  local dynr, dynb = 0, 0
  if not last then
    -- New bucket
    redis.call('HSET', KEYS[1], 'l', KEYS[2])
    redis.call('HSET', KEYS[1], 'b', '0')
    redis.call('HSET', KEYS[1], 'dr', '10000')
    redis.call('HSET', KEYS[1], 'db', '10000')
    redis.call('EXPIRE', KEYS[1], KEYS[5])
    return {0, 0, 1, 1}
  end

  last = tonumber(last)
  local burst = tonumber(redis.call('HGET', KEYS[1], 'b'))
  -- Perform leak
  if burst > 0 then
   if last < tonumber(KEYS[2]) then
    local rate = tonumber(KEYS[3])
    dynr = tonumber(redis.call('HGET', KEYS[1], 'dr')) / 10000.0
    rate = rate * dynr
    local leaked = ((now - last) * rate)
    burst = burst - leaked
    redis.call('HINCRBYFLOAT', KEYS[1], 'b', -(leaked))
   end
  else
   burst = 0
   redis.call('HSET', KEYS[1], 'b', '0')
  end

  dynb = tonumber(redis.call('HGET', KEYS[1], 'db')) / 10000.0

  if (burst + 1) * dynb > tonumber(KEYS[4]) then
   return {1, tostring(burst), tostring(dynr), tostring(dynb)}
  end

  return {0, tostring(burst), tostring(dynr), tostring(dynb)}
]]
local bucket_check_id


-- Updates a bucket
-- KEYS[1] - prefix to update, e.g. RL_<triplet>_<seconds>
-- KEYS[2] - current time in milliseconds
-- KEYS[3] - dynamic rate multiplier
-- KEYS[4] - dynamic burst multiplier
-- KEYS[5] - max dyn rate (min: 1/x)
-- KEYS[6] - max burst rate (min: 1/x)
-- KEYS[7] - expire for a bucket
-- Redis keys used:
--   l - last hit
--   b - current burst
--   dr - current dynamic rate multiplier
--   db - current dynamic burst multiplier
local bucket_update_script = [[
  local last = redis.call('HGET', KEYS[1], 'l')
  local now = tonumber(KEYS[2])
  if not last then
    -- New bucket
    redis.call('HSET', KEYS[1], 'l', KEYS[2])
    redis.call('HSET', KEYS[1], 'b', '1')
    redis.call('HSET', KEYS[1], 'dr', '10000')
    redis.call('HSET', KEYS[1], 'db', '10000')
    redis.call('EXPIRE', KEYS[1], KEYS[7])
    return {1, 1, 1}
  end

  local burst = tonumber(redis.call('HGET', KEYS[1], 'b'))
  local db = tonumber(redis.call('HGET', KEYS[1], 'db')) / 10000
  local dr = tonumber(redis.call('HGET', KEYS[1], 'dr')) / 10000

  if dr < tonumber(KEYS[5]) and dr > 1.0 / tonumber(KEYS[5]) then
    dr = dr * tonumber(KEYS[3])
    redis.call('HSET', KEYS[1], 'dr', tostring(math.floor(dr * 10000)))
  end

  if db < tonumber(KEYS[6]) and db > 1.0 / tonumber(KEYS[6]) then
    db = db * tonumber(KEYS[4])
    redis.call('HSET', KEYS[1], 'db', tostring(math.floor(db * 10000)))
  end

  redis.call('HINCRBYFLOAT', KEYS[1], 'b', 1)
  redis.call('HSET', KEYS[1], 'l', KEYS[2])
  redis.call('EXPIRE', KEYS[1], KEYS[7])

  return {tostring(burst), tostring(dr), tostring(db)}
]]
local bucket_update_id

-- message_func(task, limit_type, prefix, bucket)
local message_func = function(_, limit_type, _, _)
  return string.format('Ratelimit "%s" exceeded', limit_type)
end

local rspamd_logger = require "rspamd_logger"
local rspamd_util = require "rspamd_util"
local rspamd_lua_utils = require "lua_util"
local lua_redis = require "lua_redis"
local fun = require "fun"
local lua_maps = require "lua_maps"
local lua_util = require "lua_util"
local rspamd_hash = require "rspamd_cryptobox_hash"


local function load_scripts(cfg, ev_base)
  bucket_check_id = lua_redis.add_redis_script(bucket_check_script, redis_params)
  bucket_update_id = lua_redis.add_redis_script(bucket_update_script, redis_params)
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

local function parse_limit(name, data)
  local buckets = {}
  if type(data) == 'table' then
    -- 3 cases here:
    --  * old limit in format [burst, rate]
    --  * vector of strings in Andrew's string format
    --  * proper bucket table
    if #data == 2 and tonumber(data[1]) and tonumber(data[2]) then
      -- Old style ratelimit
      rspamd_logger.warnx(rspamd_config, 'old style ratelimit for %s', name)
      if tonumber(data[1]) > 0 and tonumber(data[2]) > 0 then
        table.insert(buckets, {
          burst = data[1],
          rate = data[2]
        })
      elseif data[1] ~= 0 then
        rspamd_logger.warnx(rspamd_config, 'invalid numbers for %s', name)
      else
        rspamd_logger.infox(rspamd_config, 'disable limit %s, burst is zero', name)
      end
    else
      -- Recursively map parse_limit and flatten the list
      fun.each(function(l)
        -- Flatten list
        for _,b in ipairs(l) do table.insert(buckets, b) end
      end, fun.map(function(d) return parse_limit(d, name) end, data))
    end
  elseif type(data) == 'string' then
    local rep_rate, burst = parse_string_limit(data)

    if rep_rate and burst then
      table.insert(buckets, {
        burst = burst,
        rate = 1.0 / rep_rate -- reciprocal
      })
    end
  end

  -- Filter valid
  return fun.totable(fun.filter(function(val)
    return type(val.burst) == 'number' and type(val.rate) == 'number'
  end, buckets))
end

--- Check whether this addr is bounce
local function check_bounce(from)
  return fun.any(function(b) return b == from end, settings.bounce_senders)
end

local keywords = {
  ['ip'] = {
    ['get_value'] = function(task)
      local ip = task:get_ip()
      if ip and ip:is_valid() then return tostring(ip) end
      return nil
    end,
  },
  ['rip'] = {
    ['get_value'] = function(task)
      local ip = task:get_ip()
      if ip and ip:is_valid() and not ip:is_local() then return tostring(ip) end
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
    ['get_value'] = function(task)
      return task:get_principal_recipient()
    end,
  },
}

local function gen_rate_key(task, rtype, bucket)
  local key_t = {tostring(lua_util.round(100000.0 / bucket.burst))}
  local key_keywords = lua_util.str_split(rtype, '_')
  local have_user = false

  for _, v in ipairs(key_keywords) do
    local ret

    if keywords[v] and type(keywords[v]['get_value']) == 'function' then
      ret = keywords[v]['get_value'](task)
    end
    if not ret then return nil end
    if v == 'user' then have_user = true end
    if type(ret) ~= 'string' then ret = tostring(ret) end
    table.insert(key_t, ret)
  end

  if have_user and not task:get_user() then
    return nil
  end

  return table.concat(key_t, ":")
end

local function make_prefix(redis_key, name, bucket)
  local hash_len = 24
  if hash_len > #redis_key then hash_len = #redis_key end
  local hash = settings.prefix ..
      string.sub(rspamd_hash.create(redis_key):base32(), 1, hash_len)
  -- Fill defaults
  if not bucket.spam_factor_rate then
    bucket.spam_factor_rate = settings.spam_factor_rate
  end
  if not bucket.ham_factor_rate then
    bucket.ham_factor_rate = settings.ham_factor_rate
  end
  if not bucket.spam_factor_burst then
    bucket.spam_factor_burst = settings.spam_factor_burst
  end
  if not bucket.ham_factor_burst then
    bucket.ham_factor_burst = settings.ham_factor_burst
  end

  return {
    bucket = bucket,
    name = name,
    hash = hash
  }
end

local function limit_to_prefixes(task, k, v, prefixes)
  local n = 0
  for _,bucket in ipairs(v) do
    local prefix = gen_rate_key(task, k, bucket)

    if prefix then
      prefixes[prefix] = make_prefix(prefix, k, bucket)
      n = n + 1
    end
  end

  return n
end

local function ratelimit_cb(task)
  if not settings.allow_local and
          rspamd_lua_utils.is_rspamc_or_controller(task) then return end

  -- Get initial task data
  local ip = task:get_from_ip()
  if ip and ip:is_valid() and settings.whitelisted_ip then
    if settings.whitelisted_ip:get_key(ip) then
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

    if fun.any(function(r) return settings.whitelisted_rcpts:get_key(r) end, rcpts_user) then
      rspamd_logger.infox(task, 'skip ratelimit for whitelisted recipient')
      return
    end
  end
  -- Get user (authuser)
  if settings.whitelisted_user then
    local auser = task:get_user()
    if settings.whitelisted_user:get_key(auser) then
      rspamd_logger.infox(task, 'skip ratelimit for whitelisted user')
      return
    end
  end
  -- Now create all ratelimit prefixes
  local prefixes = {}
  local nprefixes = 0

  for k,v in pairs(settings.limits) do
    nprefixes = nprefixes + limit_to_prefixes(task, k, v, prefixes)
  end

  for k, hdl in pairs(settings.custom_keywords or E) do
    local ret, redis_key, bd = pcall(hdl, task)

    if ret then
      local bucket = parse_limit(k, bd)
      if bucket[1] then
        prefixes[redis_key] = make_prefix(redis_key, k, bucket[1])
      end
      nprefixes = nprefixes + 1
    else
      rspamd_logger.errx(task, 'cannot call handler for %s: %s',
          k, redis_key)
    end
  end

  local function gen_check_cb(prefix, bucket, lim_name)
    return function(err, data)
      if err then
        rspamd_logger.errx('cannot check limit %s: %s %s', prefix, err, data)
      elseif type(data) == 'table' and data[1] and data[1] == 1 then
        -- set symbol only and do NOT soft reject
        if settings.symbol then
          task:insert_result(settings.symbol, 0.0, lim_name .. "(" .. prefix .. ")")
          rspamd_logger.infox(task,
              'set_symbol_only: ratelimit "%s(%s)" exceeded, (%s / %s): %s (%s:%s dyn)',
              lim_name, prefix,
              bucket.burst, bucket.rate,
              data[2], data[3], data[4])
          return
        -- set INFO symbol and soft reject
        elseif settings.info_symbol then
          task:insert_result(settings.info_symbol, 1.0,
              lim_name .. "(" .. prefix .. ")")
        end
        rspamd_logger.infox(task,
            'ratelimit "%s(%s)" exceeded, (%s / %s): %s (%s:%s dyn)',
            lim_name, prefix,
            bucket.burst, bucket.rate,
            data[2], data[3], data[4])
        task:set_pre_result('soft reject',
                message_func(task, lim_name, prefix, bucket))
      end
    end
  end

  -- Don't do anything if pre-result has been already set
  if task:has_pre_result() then return end

  if nprefixes > 0 then
    -- Save prefixes to the cache to allow update
    task:cache_set('ratelimit_prefixes', prefixes)
    local now = rspamd_util.get_time()
    now = lua_util.round(now * 1000.0) -- Get milliseconds
    -- Now call check script for all defined prefixes

    for pr,value in pairs(prefixes) do
      local bucket = value.bucket
      local rate = (bucket.rate) / 1000.0 -- Leak rate in messages/ms
      rspamd_logger.debugm(N, task, "check limit %s:%s -> %s (%s/%s)",
          value.name, pr, value.hash, bucket.burst, bucket.rate)
      lua_redis.exec_redis_script(bucket_check_id,
              {key = value.hash, task = task, is_write = true},
              gen_check_cb(pr, bucket, value.name),
              {value.hash, tostring(now), tostring(rate), tostring(bucket.burst),
                  tostring(settings.expire)})
    end
  end
end

local function ratelimit_update_cb(task)
  local prefixes = task:cache_get('ratelimit_prefixes')

  if prefixes then
    if task:has_pre_result() then
      -- Already rate limited/greylisted, do nothing
      rspamd_logger.debugm(N, task, 'pre-action has been set, do not update')
      return
    end

    local is_spam = not (task:get_metric_action() == 'no action')

    -- Update each bucket
    for k, v in pairs(prefixes) do
      local bucket = v.bucket
      local function update_bucket_cb(err, data)
        if err then
          rspamd_logger.errx(task, 'cannot update rate bucket %s: %s',
                  k, err)
        else
          rspamd_logger.debugm(N, task,
              "updated limit %s:%s -> %s (%s/%s), burst: %s, dyn_rate: %s, dyn_burst: %s",
              v.name, k, v.hash,
              bucket.burst, bucket.rate,
              data[1], data[2], data[3])
        end
      end
      local now = rspamd_util.get_time()
      now = lua_util.round(now * 1000.0) -- Get milliseconds
      local mult_burst = bucket.ham_factor_burst or 1.0
      local mult_rate = bucket.ham_factor_burst or 1.0

      if is_spam then
        mult_burst = bucket.spam_factor_burst or 1.0
        mult_rate = bucket.spam_factor_rate or 1.0
      end

      lua_redis.exec_redis_script(bucket_update_id,
              {key = v.hash, task = task, is_write = true},
              update_bucket_cb,
              {v.hash, tostring(now), tostring(mult_rate), tostring(mult_burst),
               tostring(settings.max_rate_mult), tostring(settings.max_bucket_mult),
               tostring(settings.expire)})
    end
  end
end

local opts = rspamd_config:get_all_opt(N)
if opts then

  settings = lua_util.override_defaults(settings, opts)

  if opts['limit'] then
    rspamd_logger.errx(rspamd_config, 'Legacy ratelimit config format no longer supported')
  end

  if opts['rates'] and type(opts['rates']) == 'table' then
    -- new way of setting limits
    fun.each(function(t, lim)
      local buckets = parse_limit(t, lim)

      if buckets and #buckets > 0 then
        settings.limits[t] = buckets
      end
    end, opts['rates'])
  end

  local enabled_limits = fun.totable(fun.map(function(t)
    return t
  end, settings.limits))
  rspamd_logger.infox(rspamd_config,
          'enabled rate buckets: [%1]', table.concat(enabled_limits, ','))

  -- Ret, ret, ret: stupid legacy stuff:
  -- If we have a string with commas then load it as as static map
  -- otherwise, apply normal logic of Rspamd maps

  local wrcpts = opts['whitelisted_rcpts']
  if type(wrcpts) == 'string' then
    if string.find(wrcpts, ',') then
      settings.whitelisted_rcpts = lua_maps.rspamd_map_add_from_ucl(
        lua_util.rspamd_str_split(wrcpts, ','), 'set', 'Ratelimit whitelisted rcpts')
    else
      settings.whitelisted_rcpts = lua_maps.rspamd_map_add_from_ucl(wrcpts, 'set',
        'Ratelimit whitelisted rcpts')
    end
  elseif type(opts['whitelisted_rcpts']) == 'table' then
    settings.whitelisted_rcpts = lua_maps.rspamd_map_add_from_ucl(wrcpts, 'set',
      'Ratelimit whitelisted rcpts')
  else
    -- Stupid default...
    settings.whitelisted_rcpts = lua_maps.rspamd_map_add_from_ucl(
        settings.whitelisted_rcpts, 'set', 'Ratelimit whitelisted rcpts')
  end

  if opts['whitelisted_ip'] then
    settings.whitelisted_ip = lua_maps.rspamd_map_add('ratelimit', 'whitelisted_ip', 'radix',
      'Ratelimit whitelist ip map')
  end

  if opts['whitelisted_user'] then
    settings.whitelisted_user = lua_maps.rspamd_map_add('ratelimit', 'whitelisted_user', 'set',
      'Ratelimit whitelist user map')
  end

  settings.custom_keywords = {}
  if opts['custom_keywords'] then
    local ret, res_or_err = pcall(loadfile(opts['custom_keywords']))

    if ret then
      opts['custom_keywords'] = {}
      if type(res_or_err) == 'table' then
        for k,hdl in pairs(res_or_err) do
          settings['custom_keywords'][k] = hdl
        end
      elseif type(res_or_err) == 'function' then
        settings['custom_keywords']['custom'] = res_or_err
      end
    else
      rspamd_logger.errx(rspamd_config, 'cannot execute %s: %s',
          opts['custom_keywords'], res_or_err)
      settings['custom_keywords'] = {}
    end
  end

  if opts['message_func'] then
    message_func = assert(load(opts['message_func']))()
  end

  redis_params = lua_redis.parse_redis_server('ratelimit')

  if not redis_params then
    rspamd_logger.infox(rspamd_config, 'no servers are specified, disabling module')
    lua_util.disable_module(N, "redis")
  else
    local s = {
      type = 'prefilter,nostat',
      name = 'RATELIMIT_CHECK',
      priority = 7,
      callback = ratelimit_cb,
      flags = 'empty',
    }

    if settings.symbol then
      s.name = settings.symbol
    elseif settings.info_symbol then
      s.name = settings.info_symbol
    end

    rspamd_config:register_symbol(s)
    rspamd_config:register_symbol {
      type = 'idempotent',
      name = 'RATELIMIT_UPDATE',
      callback = ratelimit_update_cb,
    }
  end
end

rspamd_config:add_on_load(function(cfg, ev_base, worker)
  load_scripts(cfg, ev_base)
end)
