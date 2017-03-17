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

rspamd_config:register_symbol({
  name = 'TAG_MOO',
  type = 'postfilter',
  callback = function(task)
    local util = require("rspamd_util")
    local rspamd_logger = require "rspamd_logger"

    local tagged_rcpt = task:get_symbol("TAGGED_RCPT")
    local user = task:get_recipients(0)[1]['user']
    local domain = task:get_recipients(0)[1]['domain']
    local rcpt = user .. '@' .. domain
    local authdomain = auth_domain_map:get_key(domain)

    if tagged_rcpt then
      local tag = tagged_rcpt[1].options[1]
      rspamd_logger.infox("found tag: %s", tag)
      local action = task:get_metric_action('default')
      rspamd_logger.infox("metric action now: %s", action)

      if action ~= 'no action' and action ~= 'greylist' then
        rspamd_logger.infox("skipping tag handler for action: %s", action)
        task:set_metric_action('default', action)
        return true
      end

      if authdomain then
        rspamd_logger.infox("found mailcow domain %s", domain)
        rspamd_logger.infox("querying tag settings for user %s", rcpt)

        if modify_subject_map:get_key(rcpt) then
          rspamd_logger.infox("user wants subject modified for tagged mail")
          local sbj = task:get_header('Subject')
          new_sbj = '=?UTF-8?B?' .. tostring(util.encode_base64('[' .. tag .. '] ' .. sbj)) .. '?='
          task:set_rmilter_reply({
            remove_headers = {['Subject'] = 1},
            add_headers = {['Subject'] = new_sbj}
          })
        else
          rspamd_logger.infox("Add X-Moo-Tag header")
          task:set_rmilter_reply({
            add_headers = {['X-Moo-Tag'] = 'YES'}
          })
        end
      else
        rspamd_logger.infox("skip delimiter handling for unknown domain")
      end
      return false
    end
  end,
  priority = 10
})

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
