rspamd_config.MAILCOW_AUTH = {
	callback = function(task)
		local uname = task:get_user()
		if uname then
			return 1
		end
	end
}

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
        task:set_milter_reply({
          remove_headers = {['Subject'] = 1},
          add_headers = {['Subject'] = new_sbj}
        })
      else
        rspamd_logger.infox("Add X-Moo-Tag header")
        task:set_milter_reply({
          add_headers = {['X-Moo-Tag'] = 'YES'}
        })
      end
    end
  end,
  priority = 11
})
end

