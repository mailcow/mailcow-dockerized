local custom_keywords = {}

custom_keywords.mailcow = function(task)
  local rspamd_logger = require "rspamd_logger"
  local dyn_rl_symbol = task:get_symbol("DYN_RL")
  if dyn_rl_symbol then
    local rl_value = dyn_rl_symbol[1].options[1]
    local rl_object = dyn_rl_symbol[1].options[2]
    if rl_value and rl_object then
      rspamd_logger.infox(rspamd_config, "DYN_RL symbol has value %s for object %s, returning %s...", rl_value, rl_object, "rs_dynrl_" .. rl_object)
      return "rs_dynrl_" .. rl_object, rl_value
    end
  end
end

return custom_keywords
