function auth_password_verify(request, password)
  if request.domain == nil then
    return dovecot.auth.PASSDB_RESULT_USER_UNKNOWN, "No such user"
  end

  local json = require "cjson"
  local ltn12 = require "ltn12"
  local https = require "ssl.https"
  https.TIMEOUT = 30

  local req = {
    username = request.user,
    password = password,
    real_rip = request.real_rip,
    service = request.service
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

  if c ~= 200 and c ~= 401 then
    dovecot.i_info("HTTP request failed with " .. c .. " for user " .. request.user)
    return dovecot.auth.PASSDB_RESULT_INTERNAL_FAILURE, "Upstream error"
  end

  local response_str = table.concat(res)
  local is_response_valid, response_json = pcall(json.decode, response_str)

  if not is_response_valid then
    dovecot.i_info("Invalid JSON received: " .. response_str)
    return dovecot.auth.PASSDB_RESULT_INTERNAL_FAILURE, "Invalid response format"
  end

  if response_json.success == true then
    return dovecot.auth.PASSDB_RESULT_OK, ""
  end

  return dovecot.auth.PASSDB_RESULT_PASSWORD_MISMATCH, "Failed to authenticate"
end

function auth_passdb_lookup(req)
   return dovecot.auth.PASSDB_RESULT_USER_UNKNOWN, ""
end
