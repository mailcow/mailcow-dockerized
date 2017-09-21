bind_socket = "rspamd:9900";
milter = true;
upstream {
  name = "localhost";
  default = true;
  hosts = "rspamd:11333"
}
