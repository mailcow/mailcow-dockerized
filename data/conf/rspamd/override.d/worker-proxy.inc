bind_socket = "rspamd:9900";
milter = true;
upstream {
  name = "localhost";
  default = true;
  hosts = "rspamd:11333"
}
.include(try=true; priority=20) "$CONFDIR/override.d/worker-proxy.custom.inc"
