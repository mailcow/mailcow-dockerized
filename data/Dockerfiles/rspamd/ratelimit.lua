--[[
Copyright (c) 2011-2015, Vsevolod Stakhov <vsevolod@highsecure.ru>

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

-- A plugin that implements ratelimits using redis or kvstorage server

local E = {}

-- Default settings for limits, 1-st member is burst, second is rate and the third is numeric type
local settings = {
}
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
local max_delay = 24 * 3600
local use_ip_score = false
local rl_prefix = 'rl'
local ip_score_lower_bound = 10
local ip_score_ham_multiplier = 1.1
local ip_score_spam_divisor = 1.1

local message_func = function(_, limit_type)
  return string.format('Ratelimit "%s" exceeded', limit_type)
end

local rspamd_logger = require "rspamd_logger"
local rspamd_util = require "rspamd_util"
local rspamd_lua_utils = require "lua_util"
local fun = require "fun"

local user_keywords = {'user'}

local limit_parser
local function parse_string_limit(lim)
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
    return t[1] / t[2], t[1]
  end

  rspamd_logger.errx(rspamd_config, 'bad limit: %s', lim)

  return nil
end

--- Parse atime and bucket of limit
local function parse_limits(data)
  local function parse_limit_elt(str)
    local elts = rspamd_str_split(str, ':')
    if not elts or #elts < 2 then
      return {0, 0, 0}
    else
      local atime = tonumber(elts[1])
      local bucket = tonumber(elts[2])
      local ctime = atime

      if elts[3] then
        ctime = tonumber(elts[3])
      end

      if not ctime then
        ctime = atime
      end

      return {atime,bucket,ctime}
    end
  end

  return fun.iter(data):map(function(e)
    if type(e) == 'string' then
      return parse_limit_elt(e)
    else
      return {0, 0, 0}
    end
  end):totable()
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
        return from[1]['addr']
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
        local key_f = string.format(key_s, r['addr'])
        table.insert(rate_keys, key_f)
        total_rcpt = total_rcpt + 1
      end
    end
    return rate_keys
  end
end

--- Check specific limit inside redis
local function check_limits(task, args)

  local key = fun.foldl(function(acc, k) return acc .. k[2] end, '', args)
  local ret
  --- Called when value is got from server
  local function rate_get_cb(err, data)
    if err then
      rspamd_logger.infox(task, 'got error while getting limit: %1', err)
    end
    if not data then return end
    local ntime = rspamd_util.get_time()
    local asn_score,total_asn,
      country_score,total_country,
      ipnet_score,total_ipnet,
      ip_score, total_ip
    if use_ip_score then
      asn_score,total_asn,
        country_score,total_country,
        ipnet_score,total_ipnet,
        ip_score, total_ip = task:get_mempool():get_variable('ip_score',
        'double,double,double,double,double,double,double,double')
    end

    fun.each(function(elt, limit, rtype)
      local bucket = elt[2]
      local rate = limit[2]
      local threshold = limit[1]
      local atime = elt[1]
      local ctime = elt[3]

      if atime == 0 then return end

      if use_ip_score then
        local key_keywords = rspamd_str_split(rtype, '_')
        local has_asn, has_ip = false, false
        for _, v in ipairs(key_keywords) do
          if v == "asn" then has_asn = true end
          if v == "ip" then has_ip = true end
          if has_ip and has_asn then break end
        end
        if has_asn and not has_ip then
          bucket = resize_element(asn_score, total_asn, bucket)
          rate = resize_element(asn_score, total_asn, rate)
        elseif has_ip then
          if total_ip and total_ip > ip_score_lower_bound then
            bucket = resize_element(ip_score, total_ip, bucket)
            rate = resize_element(ip_score, total_ip, rate)
          elseif total_ipnet and total_ipnet > ip_score_lower_bound then
            bucket = resize_element(ipnet_score, total_ipnet, bucket)
            rate = resize_element(ipnet_score, total_ipnet, rate)
          elseif total_asn and total_asn > ip_score_lower_bound then
            bucket = resize_element(asn_score, total_asn, bucket)
            rate = resize_element(asn_score, total_asn, rate)
          elseif total_country and total_country > ip_score_lower_bound then
            bucket = resize_element(country_score, total_country, bucket)
            rate = resize_element(country_score, total_country, rate)
          else
            bucket = resize_element(ip_score, total_ip, bucket)
            rate = resize_element(ip_score, total_ip, rate)
          end
        end
      end

      if atime - ctime > max_delay then
        rspamd_logger.infox(task, 'limit is too old: %1 seconds; ignore it',
          atime - ctime)
      else
        bucket = bucket - rate * (ntime - atime);
        if bucket > 0 then
          if ratelimit_symbol then
            local mult = 2 * rspamd_util.tanh(bucket / (threshold * 2))

            if mult > 0.5 then
              task:insert_result(ratelimit_symbol, mult,
                rtype .. ':' .. string.format('%.2f', mult))
            end
          else
            if bucket > threshold then
              rspamd_logger.infox(task,
                'ratelimit "%s" exceeded: %s elements with %s limit',
                rtype, bucket, threshold)
              task:set_pre_result('soft reject',
                message_func(task, rtype, bucket, threshold))
            end
          end
        end
      end
    end, fun.zip(parse_limits(data), fun.map(function(a) return a[1] end, args),
      fun.map(function(a) return rspamd_str_split(a[2], ":")[2] end, args)))
  end

  ret = rspamd_redis_make_request(task,
    redis_params, -- connect params
    key, -- hash key
    false, -- is write
    rate_get_cb, --callback
    'mget', -- command
    fun.totable(fun.map(function(l) return l[2] end, args)) -- arguments
  )
  if not ret then
    rspamd_logger.errx(task, 'got error connecting to redis')
  end
end

--- Set specific limit inside redis
local function set_limits(task, args)
  local key = fun.foldl(function(acc, k) return acc .. k[2] end, '', args)
  local ret, upstream

  local function rate_set_cb(err)
    if err then
      rspamd_logger.infox(task, 'got error %s when setting ratelimit record on server %s',
        err, upstream:get_addr())
    end
  end
  local function rate_get_cb(err, data)
    if err then
      rspamd_logger.infox(task, 'got error while setting limit: %1', err)
    end
    if not data then return end
    local ntime = rspamd_util.get_time()
    local values = {}
    fun.each(function(elt, limit)
      local bucket = elt[2]
      local rate = limit[1][2]
      local atime = elt[1]
      local ctime = elt[3]

      if atime - ctime > max_delay then
        rspamd_logger.infox(task, 'limit is too old: %1 seconds; start it over',
          atime - ctime)
        bucket = 1
        ctime = ntime
      else
        if bucket > 0 then
          bucket = bucket - rate * (ntime - atime) + 1;
          if bucket < 0 then
            bucket = 1
          end
        else
          bucket = 1
        end
      end

      if ctime == 0 then ctime = ntime end

      local lstr = string.format('%.3f:%.3f:%.3f', ntime, bucket, ctime)
      table.insert(values, {limit[2], max_delay, lstr})
    end, fun.zip(parse_limits(data), fun.iter(args)))

    if #values > 0 then
      local conn
      ret,conn,upstream = rspamd_redis_make_request(task,
        redis_params, -- connect params
        key, -- hash key
        true, -- is write
        rate_set_cb, --callback
        'setex', -- command
        values[1] -- arguments
      )

      if conn then
        fun.each(function(v)
          conn:add_cmd('setex', v)
        end, fun.drop_n(1, values))
      else
        rspamd_logger.errx(task, 'got error while connecting to redis')
      end
    end
  end

  local _
  ret,_,upstream = rspamd_redis_make_request(task,
    redis_params, -- connect params
    key, -- hash key
    false, -- is write
    rate_get_cb, --callback
    'mget', -- command
    fun.totable(fun.map(function(l) return l[2] end, args)) -- arguments
  )
  if not ret then
    rspamd_logger.errx(task, 'got error connecting to redis')
  end
end

--- Check or update ratelimit
local function rate_test_set(task, func)
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
    fun.each(function(r) table.insert(rcpts_user, r['user']) end, rcpts)
    if fun.any(function(r)
      fun.any(function(w) return r == w end, whitelisted_rcpts) end,
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

  local rate_key
  for k in pairs(settings) do
    rate_key = dynamic_rate_key(task, k)
    if rate_key then
      if type(rate_key) == 'table' then
        for _, rk in ipairs(rate_key) do
          if type(settings[k]) == 'table' then
            table.insert(args, {settings[k], rk})
          elseif type(settings[k]) == 'string' and
              (custom_keywords[settings[k]] and type(custom_keywords[settings[k]]['get_limit']) == 'function') then
            local res = custom_keywords[settings[k]]['get_limit'](task)
            if type(res) == 'table' then
              table.insert(args, {res, rate_key})
            elseif type(res) == 'string' then
              local plim, size = parse_string_limit(res)
              if plim then
                table.insert(args, {{size, plim, 1}, rate_key})
              end
            end
          end
        end
      else
        if type(settings[k]) == 'table' then
          table.insert(args, {settings[k], rate_key})
        elseif type(settings[k]) == 'string' and
            (custom_keywords[settings[k]] and type(custom_keywords[settings[k]]['get_limit']) == 'function') then
          local res = custom_keywords[settings[k]]['get_limit'](task)
          if type(res) == 'table' then
            table.insert(args, {res, rate_key})
          elseif type(res) == 'string' then
            local plim, size = parse_string_limit(res)
            if plim then
              table.insert(args, {{size, plim, 1}, rate_key})
            end
          end
        end
      end
    end
  end

  if #args > 0 then
    func(task, args)
  end
end

--- Check limit
local function rate_test(task)
  if rspamd_lua_utils.is_rspamc_or_controller(task) then return end
  rate_test_set(task, check_limits)
end
--- Update limit
local function rate_set(task)
  local action = task:get_metric_action('default')

  if action ~= 'soft reject' then
    if rspamd_lua_utils.is_rspamc_or_controller(task) then return end
    rate_test_set(task, set_limits)
  end
end


--- Parse a single limit description
local function parse_limit(str)
  local params = rspamd_str_split(str, ':')

  local function set_limit(limit, burst, rate)
    limit[1] = tonumber(burst)
    limit[2] = tonumber(rate)
  end

  if #params ~= 3 then
    rspamd_logger.errx(rspamd_config, 'invalid limit definition: ' .. str)
    return
  end

  local key_keywords = rspamd_str_split(params[1], '_')
  for _, k in ipairs(key_keywords) do
    if (custom_keywords[k] and type(custom_keywords[k]['get_value']) == 'function') or
        (keywords[k] and type(keywords[k]['get_value']) == 'function') then
      set_limit(settings[params[1]], params[2], params[3])
    else
      rspamd_logger.errx(rspamd_config, 'invalid limit type: ' .. params[1])
    end
  end
end

local opts = rspamd_config:get_all_opt('ratelimit')
if opts then
  local rates = opts['limit']
  if rates and type(rates) == 'table' then
    fun.each(parse_limit, rates)
  elseif rates and type(rates) == 'string' then
    parse_limit(rates)
  end

  if opts['rates'] and type(opts['rates']) == 'table' then
    -- new way of setting limits
    fun.each(function(t, lim)
      if type(lim) == 'table' then
        settings[t] = lim
      elseif type(lim) == 'string' then
        local plim, size = parse_string_limit(lim)
        if plim then
          settings[t] = {size, plim, 1}
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
  end, fun.filter(function(_, lim)
    return type(lim) == 'string' or
        (type(lim) == 'table' and type(lim[1]) == 'number' and lim[1] > 0)
        or (type(lim) == 'table' and (lim[3]))
  end, settings)))
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

  if opts['max_delay'] then
    max_rcpt = tonumber(opts['max_delay'])
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

  redis_params = rspamd_parse_redis_server('ratelimit')
  if not redis_params then
    rspamd_logger.infox(rspamd_config, 'no servers are specified, disabling module')
  else
    if not ratelimit_symbol and not use_ip_score then
      rspamd_config:register_symbol({
        name = 'RATELIMIT_CHECK',
        callback = rate_test,
        type = 'prefilter',
        priority = 4,
      })
    else
      local symbol
      if not ratelimit_symbol then
        symbol = 'RATELIMIT_CHECK'
      else
        symbol = ratelimit_symbol
      end
      local id = rspamd_config:register_symbol({
        name = symbol,
        callback = rate_test,
      })
      if use_ip_score then
        rspamd_config:register_dependency(id, 'IP_SCORE')
      end
    end
    rspamd_config:register_symbol({
      name = 'RATELIMIT_SET',
      type = 'postfilter',
      priority = 5,
      callback = rate_set,
    })
    for _, v in pairs(custom_keywords) do
      if type(v) == 'table' and type(v['init']) == 'function' then
        v['init']()
      end
    end
  end
end


