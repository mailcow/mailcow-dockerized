rspamd_config.MAILCOW_AUTH = {
	callback = function(task)
		local uname = task:get_user()
		if uname then
			return 1
		end
	end
}

rspamd_config:register_symbol({
  name = 'TAG_MOO',
  type = 'postfilter',
  callback = function(task)
    local util = require("rspamd_util")
    local rspamd_logger = require "rspamd_logger"

    local tagged_rcpt = task:get_symbol("TAGGED_RCPT")
    local mailcow_domain = task:get_symbol("RCPT_MAILCOW_DOMAIN")

    if tagged_rcpt and tagged_rcpt[1].options and mailcow_domain then
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
      local wants_subfolder_tag = task:get_symbol("RCPT_WANTS_SUBFOLDER_TAG")

      if wants_subject_tag then
        rspamd_logger.infox("user wants subject modified for tagged mail")
        local sbj = task:get_header('Subject')
        new_sbj = '=?UTF-8?B?' .. tostring(util.encode_base64('[' .. tag .. '] ' .. sbj)) .. '?='
        task:set_milter_reply({
          remove_headers = {['Subject'] = 1},
          add_headers = {['Subject'] = new_sbj}
        })
      elseif wants_subfolder_tag then
        rspamd_logger.infox("Add X-Moo-Tag header")
        task:set_milter_reply({
          add_headers = {['X-Moo-Tag'] = 'YES'}
        })
      end
    end
  end,
  priority = 11
})

rspamd_config:register_symbol({
  name = 'DYN_RL_CHECK',
  type = 'prefilter',
  callback = function(task)
    local util = require("rspamd_util")
    local redis_params = rspamd_parse_redis_server('dyn_rl')
    local rspamd_logger = require "rspamd_logger"
    local envfrom = task:get_from(1)
    if not envfrom then
      return false
    end
    local env_from_domain = envfrom[1].domain:lower() -- get smtp from domain in lower case
    local env_from_addr = envfrom[1].addr:lower() -- get smtp from addr in lower case

    local function redis_cb_user(err, data)

      if err or type(data) ~= 'string' then
        rspamd_logger.infox(rspamd_config, "dynamic ratelimit request for user %s returned invalid or empty data (\"%s\") or error (\"%s\") - trying dynamic ratelimit for domain...", env_from_addr, data, err)

        local function redis_key_cb_domain(err, data)
          if err or type(data) ~= 'string' then
            rspamd_logger.infox(rspamd_config, "dynamic ratelimit request for domain %s returned invalid or empty data (\"%s\") or error (\"%s\")", env_from_domain, data, err)
          else
            rspamd_logger.infox(rspamd_config, "found dynamic ratelimit in redis for domain %s with value %s", env_from_domain, data)
            task:insert_result('DYN_RL', 0.0, data)
          end
        end

        local redis_ret_domain = rspamd_redis_make_request(task,
          redis_params, -- connect params
          env_from_domain, -- hash key
          false, -- is write
          redis_key_cb_domain, --callback
          'HGET', -- command
          {'RL_VALUE', env_from_domain} -- arguments
        )
        if not redis_ret_domain then
          rspamd_logger.infox(rspamd_config, "cannot make request to load ratelimit for domain")
        end
      else
        rspamd_logger.infox(rspamd_config, "found dynamic ratelimit in redis for user %s with value %s", env_from_addr, data)
        task:insert_result('DYN_RL', 0.0, data)
      end

    end

    local redis_ret_user = rspamd_redis_make_request(task,
      redis_params, -- connect params
      env_from_addr, -- hash key
      false, -- is write
      redis_cb_user, --callback
      'HGET', -- command
      {'RL_VALUE', env_from_addr} -- arguments
    )
    if not redis_ret_user then
      rspamd_logger.infox(rspamd_config, "cannot make request to load ratelimit for user")
    end
    return true
  end,
  priority = 20
})

rspamd_config:register_symbol({
  name = 'NO_LOG_STAT',
  type = 'postfilter',
  callback = function(task)
    local from = task:get_header('From')
    if from and (from == 'monitoring-system@everycloudtech.us' or from == 'watchdog@localhost') then
      task:set_flag('no_log')
      task:set_flag('no_stat')
    end
  end
})
