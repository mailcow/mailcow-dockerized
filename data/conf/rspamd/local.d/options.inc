dns {
  enable_dnssec = true;
}
map_watch_interval = 5min;
dns {
  timeout = 4s;
  retransmits = 2;
}
disable_monitoring = true;
lua_gc_step = 10;
lua_gc_pause = 10;
