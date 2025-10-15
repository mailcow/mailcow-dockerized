function auth_password_verify(request, password)
  request.domain = request.auth_user:match("@(.+)") or nil
  if request.domain == nil then
    return dovecot.auth.PASSDB_RESULT_USER_UNKNOWN, "No such user"
  end

  local json = require "cjson"
  local ltn12 = require "ltn12"
  local https = require "ssl.https"
  https.TIMEOUT = 30

  local req = {
    username = request.auth_user,
    password = password,
    real_rip = request.remote_ip,
    service = request.protocol
  }
  local req_json = json.encode(req)
  local res = {}

  local b, c = https.request {
    method = "POST",
    url = "https://nginx:9082",
    source = ltn12.source.string(req_json),
    headers = {
      ["content-type"] = "application/json",
      ["content-length"] = tostring(#req_json)
    },
    sink = ltn12.sink.table(res),
    insecure = true
  }

  -- Returning PASSDB_RESULT_PASSWORD_MISMATCH will reset the user's auth cache entry.
  -- Returning PASSDB_RESULT_INTERNAL_FAILURE keeps the existing cache entry,
  -- even if the TTL has expired. Useful to avoid cache eviction during backend issues.
  if c ~= 200 and c ~= 401 then
    return dovecot.auth.PASSDB_RESULT_PASSWORD_MISMATCH, "Upstream error"
  end

  local response_str = table.concat(res)
  local is_response_valid, response_json = pcall(json.decode, response_str)

  if not is_response_valid then
    dovecot.i_info("Invalid JSON received: " .. response_str)
    return dovecot.auth.PASSDB_RESULT_PASSWORD_MISMATCH, "Invalid response format"
  end

  if response_json.success == true then
    return dovecot.auth.PASSDB_RESULT_OK, { msg = "" }
  end

  return dovecot.auth.PASSDB_RESULT_PASSWORD_MISMATCH, "Failed to authenticate"
end

function auth_passdb_lookup(req)
   return dovecot.auth.PASSDB_RESULT_USER_UNKNOWN, ""
end

function auth_passdb_get_cache_key()
  return "%{protocol}:%{user | username}\t:%{password}"
end