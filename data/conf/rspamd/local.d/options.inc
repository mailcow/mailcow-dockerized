dns {
  enable_dnssec = true;
}
map_watch_interval = 30s;
disable_monitoring = true;
# In case a task times out (like DNS lookup), soft reject the message
# instead of silently accepting the message without further processing.
soft_reject_on_timeout = true;
local_addrs = /etc/rspamd/custom/mailcow_networks.map;
