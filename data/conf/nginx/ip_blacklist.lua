-- original source https://gist.github.com/chrisboulton/6043871
--
-- a quick LUA access script for nginx to check IP addresses against an
-- `ip_blacklist` set in Redis, and if a match is found send a HTTP 403.
--
-- allows for a common blacklist to be shared between a bunch of nginx
-- web servers using a remote redis instance. lookups are cached for a
-- configurable period of time.
--
-- also requires lua-resty-redis from:
--   https://github.com/agentzh/lua-resty-redis
--
-- your nginx http context should contain something similar to the
-- below: 
--
--   lua_shared_dict ip_blacklist_cache 10m;
--
-- you can then use the below (adjust path where necessary) to check
-- against the blacklist in a http, server, location, if context:
--
-- access_by_lua_file /etc/nginx/conf.d/ip_blacklist.lua;
--
-- chris boulton, @surfichris

local redis_host    = "redis"
local redis_port    = 6379

-- connection timeout for redis in ms. don't set this too high!
local redis_timeout = 200

-- cache lookups for this many seconds
local cache_ttl     = 60

-- end configuration

local ip                 = ngx.var.remote_addr
local ip_blacklist_cache = ngx.shared.ip_blacklist_cache

-- setup a local cache
if cache_ttl > 0 then
  -- lookup the value in the cache
  local cache_result = ip_blacklist_cache:get(ip)
  if cache_result then
    ngx.log(ngx.DEBUG, "ip_blacklist: found result in cache for "..ip.." -> "..cache_result)

    if cache_result == 0 then
    ngx.log(ngx.DEBUG, "ip_blacklist: (cache) no result found for "..ip)
      return
    end

    ngx.log(ngx.INFO, "ip_blacklist: (cache) "..ip.." is blacklisted")
    return ngx.exit(ngx.HTTP_FORBIDDEN)
  end
end

-- helper ip utils
local iputils = require "resty.iputils"
iputils.enable_lrucache()

-- lookup against redis
local resty = require "resty.redis"
local redis = resty:new()

redis:set_timeout(redis_timeout)

local connected, err = redis:connect(redis_host, redis_port)
if not connected then
  ngx.log(ngx.ERR, "ip_blacklist: could not connect to redis @"..redis_host..": "..err)
  return
end

-- check for active bans
local result, err = redis:hkeys("F2B_ACTIVE_BANS")
local active_bans = iputils.parse_cidrs(result)
-- load perm bans
local result, err = redis:hkeys("F2B_PERM_BANS")
local perm_bans = iputils.parse_cidrs(result)

local ban = 0
if iputils.ip_in_cidrs(ip, active_bans) or iputils.ip_in_cidrs(ip, perm_bans) then
  ban = 1
end

-- cache the result from redis
if cache_ttl > 0 then
  ip_blacklist_cache:set(ip, ban, cache_ttl)
end

redis:set_keepalive(10000, 2)
if ban == 0 then
  ngx.log(ngx.INFO, "ip_blacklist: no result found for "..ip)
  return
end

ngx.log(ngx.INFO, "ip_blacklist: "..ip.." is blacklisted")
return ngx.exit(ngx.HTTP_FORBIDDEN)
