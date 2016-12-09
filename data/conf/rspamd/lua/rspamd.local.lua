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

rspamd_config:add_map('http://nginx:8081/settings.php', "settings map", process_map)
