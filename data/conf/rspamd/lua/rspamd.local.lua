rspamd_config.MAILCOW_AUTH = {
	callback = function(task)
		local uname = task:get_user()
		if uname then
			return 1
		end
	end
}

rspamd_config.MAILCOW_MOO = function (task)
	return true
end

modify_subject_map = rspamd_config:add_map({
  url = 'http://172.22.1.251:8081/tags.php',
  type = 'map',
  description = 'Map of users to use subject tags for'
})

auth_domain_map = rspamd_config:add_map({
  url = 'http://172.22.1.251:8081/authoritative.php',
  type = 'map',
  description = 'Map of domains we are authoritative for'
})

rspamd_config.ADD_DELIMITER_TAG = {
  callback = function(task)
    local tag = nil
    local util = require("rspamd_util")
    local rspamd_logger = require "rspamd_logger"
    local user_tagged = task:get_recipients(2)[1]['user']
    local domain = task:get_recipients(1)[1]['domain']
    local user, tag = user_tagged:match("([^+]+)+(.*)")
    local authdomain = auth_domain_map:get_key(domain)

    if tag and authdomain then
      rspamd_logger.infox("domain: %1, tag: %2", domain, tag)
      local user_untagged = user .. '@' .. domain
      rspamd_logger.infox("querying tag settings for user %1", user_untagged)
      if modify_subject_map:get_key(user_untagged) then
        rspamd_logger.infox("found user in map for subject rewrite")
        local sbj = task:get_header('Subject')
        new_sbj = '=?UTF-8?B?' .. tostring(util.encode_base64('[' .. tag .. '] ' .. sbj)) .. '?='
        task:set_rmilter_reply({
          remove_headers = {['Subject'] = 1},
          add_headers = {['Subject'] = new_sbj}
        })
      else
        rspamd_logger.infox("add X-Moo-Tag header")
        task:set_rmilter_reply({
          add_headers = {['X-Moo-Tag'] = 'YES'}
        })
      end
    else
      rspamd_logger.infox("skip delimiter handling for untagged message or authenticated user")
    end
    return false
  end
}

rspamd_config.MRAPTOR = {
  callback = function(task)
    local parts = task:get_parts()
    local rspamd_logger = require "rspamd_logger"
    local rspamd_regexp = require "rspamd_regexp"

    if parts then
      for _,p in ipairs(parts) do
        local mtype,subtype = p:get_type()
        local re = rspamd_regexp.create_cached('/(office|word|excel)/i')
        if re:match(subtype) then
          local content = tostring(p:get_content())
          local filename = p:get_filename()

          local file = os.tmpname()
          f = io.open(file, "a+")
          f:write(content)
          f:close()

          local scan = assert(io.popen('PATH=/usr/bin:/usr/local/bin mraptor ' .. file .. '> /dev/null 2>&1; echo $?', 'r'))
          local result = scan:read('*all')
          local exit_code = string.match(result, "%d+")
          rspamd_logger.infox(exit_code)
          scan:close()

          if exit_code == "20" then
            rspamd_logger.infox("Reject dangerous macro in office file " .. filename)
            task:set_pre_result(rspamd_actions['reject'], 'Dangerous macro in office file ' .. filename)
          end

        end
      end
    end
  end
}
