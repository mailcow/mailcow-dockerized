--[[
Copyright (c) 2016, Andrew Lewis <nerf@judo.za.org>
Copyright (c) 2016, Vsevolod Stakhov <vsevolod@highsecure.ru>

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

local rspamd_logger = require "rspamd_logger"
local rspamd_util = require "rspamd_util"

if confighelp then
  return
end

local settings = {
  allow_envfrom_empty = true,
  allow_hdrfrom_mismatch = false,
  allow_hdrfrom_mismatch_local = false,
  allow_hdrfrom_mismatch_sign_networks = false,
  allow_hdrfrom_multiple = false,
  allow_username_mismatch = false,
  auth_only = true,
  domain = {},
  path = string.format('%s/%s/%s', rspamd_paths['DBDIR'], 'dkim', '$domain.$selector.key'),
  sign_local = true,
  selector = 'dkim',
  symbol = 'DKIM_SIGNED',
  try_fallback = true,
  use_domain = 'header',
  use_esld = true,
  use_redis = false,
  key_prefix = 'dkim_keys', -- default hash name
}

local E = {}
local N = 'dkim_signing'
local redis_params

local function simple_template(tmpl, keys)
  local lpeg = require "lpeg"

  local var_lit = lpeg.P { lpeg.R("az") + lpeg.R("AZ") + lpeg.R("09") + "_" }
  local var = lpeg.P { (lpeg.P("$") / "") * ((var_lit^1) / keys) }
  local var_braced = lpeg.P { (lpeg.P("${") / "") * ((var_lit^1) / keys) * (lpeg.P("}") / "") }

  local template_grammar = lpeg.Cs((var + var_braced + 1)^0)

  return lpeg.match(template_grammar, tmpl)
end

local function dkim_signing_cb(task)
  local is_local, is_sign_networks
  local auser = task:get_user()
  local ip = task:get_from_ip()
  if ip and ip:is_local() then
    is_local = true
  end
  if settings.auth_only and not auser then
    if (settings.sign_networks and settings.sign_networks:get_key(ip)) then
      is_sign_networks = true
      rspamd_logger.debugm(N, task, 'mail is from address in sign_networks')
    elseif settings.sign_local and is_local then
      rspamd_logger.debugm(N, task, 'mail is from local address')
    else
      rspamd_logger.debugm(N, task, 'ignoring unauthenticated mail')
      return
    end
  end
  local efrom = task:get_from('smtp')
  if not settings.allow_envfrom_empty and
      #(((efrom or E)[1] or E).addr or '') == 0 then
    rspamd_logger.debugm(N, task, 'empty envelope from not allowed')
    return false
  end
  local hfrom = task:get_from('mime')
  if not settings.allow_hdrfrom_multiple and (hfrom or E)[2] then
    rspamd_logger.debugm(N, task, 'multiple header from not allowed')
    return false
  end
  local dkim_domain
  local hdom = ((hfrom or E)[1] or E).domain
  local edom = ((efrom or E)[1] or E).domain
  if hdom then
    hdom = hdom:lower()
  end
  if edom then
    edom = edom:lower()
  end
  if settings.use_domain_sign_networks and is_sign_networks then
    if settings.use_domain_sign_networks == 'header' then
      dkim_domain = hdom
    else
      dkim_domain = edom
    end
  elseif settings.use_domain_local and is_local then
    if settings.use_domain_local == 'header' then
      dkim_domain = hdom
    else
      dkim_domain = edom
    end
  else
    if settings.use_domain == 'header' then
      dkim_domain = hdom
    else
      dkim_domain = edom
    end
  end
  if not dkim_domain then
    rspamd_logger.debugm(N, task, 'could not extract dkim domain')
    return false
  end
  if settings.use_esld then
    dkim_domain = rspamd_util.get_tld(dkim_domain)
    if settings.use_domain == 'envelope' and hdom then
      hdom = rspamd_util.get_tld(hdom)
    elseif settings.use_domain == 'header' and edom then
      edom = rspamd_util.get_tld(edom)
    end
  end
  if edom and hdom and not settings.allow_hdrfrom_mismatch and hdom ~= edom then
    if settings.allow_hdrfrom_mismatch_local and is_local then
      rspamd_logger.debugm(N, task, 'domain mismatch allowed for local IP: %1 != %2', hdom, edom)
    elseif settings.allow_hdrfrom_mismatch_sign_networks and is_sign_networks then
      rspamd_logger.debugm(N, task, 'domain mismatch allowed for sign_networks: %1 != %2', hdom, edom)
    else
      rspamd_logger.debugm(N, task, 'domain mismatch not allowed: %1 != %2', hdom, edom)
      return false
    end
  end
  if auser and not settings.allow_username_mismatch then
    local udom = string.match(auser, '.*@(.*)')
    if not udom then
      rspamd_logger.debugm(N, task, 'couldnt find domain in username')
      return false
    end
    if settings.use_esld then
      udom = rspamd_util.get_tld(udom)
    end
    if udom ~= dkim_domain then
      rspamd_logger.debugm(N, task, 'user domain mismatch')
      return false
    end
  end
  local p = {}
  if settings.domain[dkim_domain] then
    p.selector = settings.domain[dkim_domain].selector
    p.key = settings.domain[dkim_domain].path
  end
  if not (p.key and p.selector) and not
    (settings.try_fallback or settings.use_redis or settings.selector_map or settings.path_map) then
    rspamd_logger.debugm(N, task, 'dkim unconfigured and fallback disabled')
    return false
  end
  if not p.key then
    if not settings.use_redis then
      p.key = settings.path
    end
  end
  if not p.selector then
    p.selector = settings.selector
  end
  p.domain = dkim_domain

  if settings.selector_map then
    local data = settings.selector_map:get_key(dkim_domain)
    if data then
      p.selector = data
    end
  end
  if settings.path_map then
    local data = settings.path_map:get_key(dkim_domain)
    if data then
      p.key = data
    end
  end

  if settings.use_redis then
    local function try_redis_key(selector)
      p.key = nil
      p.selector = selector
      local rk = string.format('%s.%s', p.selector, p.domain)
      local function redis_key_cb(err, data)
        if err or type(data) ~= 'string' then
          rspamd_logger.infox(rspamd_config, "cannot make request to load DKIM key for %s: %s",
            rk, err)
        else
          p.rawkey = data
          if rspamd_plugins.dkim.sign(task, p) then
            task:insert_result(settings.symbol, 1.0)
          end
        end
      end
      local ret = rspamd_redis_make_request(task,
        redis_params, -- connect params
        rk, -- hash key
        false, -- is write
        redis_key_cb, --callback
        'HGET', -- command
        {settings.key_prefix, rk} -- arguments
      )
      if not ret then
        rspamd_logger.infox(rspamd_config, "cannot make request to load DKIM key for %s", rk)
      end
    end
    if settings.selector_prefix then
      rspamd_logger.infox(rspamd_config, "Using selector prefix %s for domain %s", settings.selector_prefix, p.domain);
      local function redis_selector_cb(err, data)
        if err or type(data) ~= 'string' then
          rspamd_logger.infox(rspamd_config, "cannot make request to load DKIM selector for domain %s: %s", p.domain, err)
        else
          try_redis_key(data)
        end
      end
      local ret = rspamd_redis_make_request(task,
        redis_params, -- connect params
        p.domain, -- hash key
        false, -- is write
        redis_selector_cb, --callback
        'HGET', -- command
        {settings.selector_prefix, p.domain} -- arguments
      )
      if not ret then
        rspamd_logger.infox(rspamd_config, "cannot make request to load DKIM selector for %s", p.domain)
      end
    else
      if not p.selector then
        rspamd_logger.errx(task, 'No selector specified')
        return false
      end
      try_redis_key(p.selector)
    end
  else
    if (p.key and p.selector) then
      p.key = simple_template(p.key, {domain = p.domain, selector = p.selector})
      return rspamd_plugins.dkim.sign(task, p)
    else
      rspamd_logger.infox(task, 'key path or dkim selector unconfigured; no signing')
      return false
    end
  end
end

local opts =  rspamd_config:get_all_opt('dkim_signing')
if not opts then return end
for k,v in pairs(opts) do
  if k == 'sign_networks' then
    settings[k] = rspamd_map_add(N, k, 'radix', 'DKIM signing networks')
  elseif k == 'path_map' then
    settings[k] = rspamd_map_add(N, k, 'map', 'Paths to DKIM signing keys')
  elseif k == 'selector_map' then
    settings[k] = rspamd_map_add(N, k, 'map', 'DKIM selectors')
  else
    settings[k] = v
  end
end
if not (settings.use_redis or settings.path or settings.domain or settings.path_map or settings.selector_map) then
  rspamd_logger.infox(rspamd_config, 'mandatory parameters missing, disable dkim signing')
  return
end
if settings.use_redis then
  redis_params = rspamd_parse_redis_server('dkim_signing')

  if not redis_params then
    rspamd_logger.errx(rspamd_config, 'no servers are specified, but module is configured to load keys from redis, disable dkim signing')
    return
  end
end
if settings.use_domain ~= 'header' and settings.use_domain ~= 'envelope' then
  rspamd_logger.errx(rspamd_config, "Value for 'use_domain' is invalid")
  settings.use_domain = 'header'
end

rspamd_config:register_symbol({
  name = settings['symbol'],
  callback = dkim_signing_cb
})
