local custom_keywords = {
  ['customrl'] = {},
}

function custom_keywords.customrl.get_value(task)
  local rspamd_logger = require "rspamd_logger"
  if task:has_symbol('DYN_RL') then
    rspamd_logger.infox(rspamd_config, "task has a dynamic ratelimit symbol, processing...")
    return "check"
  else
    rspamd_logger.infox(rspamd_config, "task has no dynamic ratelimit symbol, skipping...")
    return
  end
end
function custom_keywords.customrl.get_limit(task)
  local rspamd_logger = require "rspamd_logger"
  local dyn_rl_symbol = task:get_symbol("DYN_RL")
  if dyn_rl_symbol then
    local rl_value = dyn_rl_symbol[1].options[1]
    rspamd_logger.infox(rspamd_config, "dynamic ratelimit symbol has option %s, returning...", rl_value)
    return rl_value
  end
end
-- returning custom keywords
return custom_keywords