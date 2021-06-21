dns {
  enable_dnssec = true;
}
map_watch_interval = 30s;
dns {
  timeout = 6s;
  retransmits = 4;
  sockets = 16;
}
disable_monitoring = true;
