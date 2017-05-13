rspamd_config.MAILCOW_AUTH = {
	callback = function(task)
		local uname = task:get_user()
		if uname then
			return 1
		end
	end
}

modify_subject_map = rspamd_config:add_map({
  url = 'http://172.22.1.251:8081/tags.php',
  type = 'map',
  description = 'Map of users to use subject tags for'
})

local redis_params
redis_params = rspamd_parse_redis_server('tag_settings')
if redis_params then
rspamd_config:register_symbol({
  name = 'TAG_MOO',
  type = 'postfilter',
  callback = function(task)
    local util = require("rspamd_util")
    local rspamd_logger = require "rspamd_logger"

    local tagged_rcpt = task:get_symbol("TAGGED_RCPT")
    local mailcow_domain = task:get_symbol("RCPT_MAILCOW_DOMAIN")

    local user = task:get_recipients(0)[1]['user']
    local domain = task:get_recipients(0)[1]['domain']
    local rcpt = user .. '@' .. domain


    if tagged_rcpt and mailcow_domain then
      local tag = tagged_rcpt[1].options[1]
      rspamd_logger.infox("found tag: %s", tag)
      local action = task:get_metric_action('default')
      rspamd_logger.infox("metric action now: %s", action)

      if action ~= 'no action' and action ~= 'greylist' then
        rspamd_logger.infox("skipping tag handler for action: %s", action)
        task:set_metric_action('default', action)
        return true
      end

      local wants_subject_tag = task:get_symbol("RCPT_WANTS_SUBJECT_TAG")

      if wants_subject_tag then
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
    end
  end,
  priority = 10
})
end

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
