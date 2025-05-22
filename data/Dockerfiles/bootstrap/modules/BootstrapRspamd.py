from jinja2 import Environment, FileSystemLoader
from modules.BootstrapBase import BootstrapBase
from pathlib import Path
import os
import sys
import time
import platform

class Bootstrap(BootstrapBase):
  def bootstrap(self):
    # Connect to MySQL
    self.connect_mysql()

    # Connect to MySQL
    self.connect_redis()

    # get dovecot ips
    dovecot_v4 = []
    dovecot_v6 = []
    while not dovecot_v4 and not dovecot_v6:
      try:
        dovecot_v4 = self.resolve_docker_dns_record("dovecot-mailcow", "A")
        dovecot_v6 = self.resolve_docker_dns_record("dovecot-mailcow", "AAAA")
      except Exception as e:
        print(e)
      if not dovecot_v4 and not dovecot_v6:
        print("Waiting for Dovecot IPs...")
        time.sleep(3)

    # get rspamd ips
    rspamd_v4 = []
    rspamd_v6 = []
    while not rspamd_v4 and not rspamd_v6:
      try:
        rspamd_v4 = self.resolve_docker_dns_record("rspamd-mailcow", "A")
        rspamd_v6 = self.resolve_docker_dns_record("rspamd-mailcow", "AAAA")
      except Exception:
        print(e)
      if not rspamd_v4 and not rspamd_v6:
        print("Waiting for Rspamd IPs...")
        time.sleep(3)

    # wait for Services
    services = [
      ["php-fpm-mailcow", 9001],
      ["php-fpm-mailcow", 9002]
    ]
    for service in services:
      while not self.is_port_open(service[0], service[1]):
        print(f"Waiting for {service[0]} on port {service[1]}...")
        time.sleep(1)
      print(f"Service {service[0]} on port {service[1]} is ready!")

    for dir_path in ["/etc/rspamd/plugins.d", "/etc/rspamd/custom"]:
      Path(dir_path).mkdir(parents=True, exist_ok=True)
    for file_path in ["/etc/rspamd/rspamd.conf.local", "/etc/rspamd/rspamd.conf.override"]:
      Path(file_path).touch(exist_ok=True)
    self.set_permissions("/var/lib/rspamd", 0o755)


    # Setup Jinja2 Environment and load vars
    self.env = Environment(
      loader=FileSystemLoader([
        '/service_config/custom_templates',
        '/service_config/config_templates'
      ]),
      keep_trailing_newline=True,
      lstrip_blocks=True,
      trim_blocks=True
    )
    extra_vars = {
      "DOVECOT_V4": dovecot_v4[0],
      "DOVECOT_V6": dovecot_v6[0],
      "RSPAMD_V4": rspamd_v4[0],
      "RSPAMD_V6": rspamd_v6[0],
    }
    self.env_vars = self.prepare_template_vars('/overwrites.json', extra_vars)

    print("Set Timezone")
    self.set_timezone()

    print("Render config")
    self.render_config("/service_config")

    # Fix missing default global maps, if any
    # These exists in mailcow UI and should not be removed
    files = [
      "/etc/rspamd/custom/global_mime_from_blacklist.map",
      "/etc/rspamd/custom/global_rcpt_blacklist.map",
      "/etc/rspamd/custom/global_smtp_from_blacklist.map",
      "/etc/rspamd/custom/global_mime_from_whitelist.map",
      "/etc/rspamd/custom/global_rcpt_whitelist.map",
      "/etc/rspamd/custom/global_smtp_from_whitelist.map",
      "/etc/rspamd/custom/bad_languages.map",
      "/etc/rspamd/custom/sa-rules",
      "/etc/rspamd/custom/dovecot_trusted.map",
      "/etc/rspamd/custom/rspamd_trusted.map",
      "/etc/rspamd/custom/mailcow_networks.map",
      "/etc/rspamd/custom/ip_wl.map",
      "/etc/rspamd/custom/fishy_tlds.map",
      "/etc/rspamd/custom/bad_words.map",
      "/etc/rspamd/custom/bad_asn.map",
      "/etc/rspamd/custom/bad_words_de.map",
      "/etc/rspamd/custom/bulk_header.map",
      "/etc/rspamd/custom/bad_header.map"
    ]
    for file in files:
      path = Path(file)
      path.parent.mkdir(parents=True, exist_ok=True)
      path.touch(exist_ok=True)

    # Fix permissions
    paths_rspamd = [
      "/var/lib/rspamd",
      "/etc/rspamd/local.d",
      "/etc/rspamd/override.d",
      "/etc/rspamd/rspamd.conf.local",
      "/etc/rspamd/rspamd.conf.override",
      "/etc/rspamd/plugins.d"
    ]
    for path in paths_rspamd:
      self.set_owner(path, "_rspamd", "_rspamd", recursive=True)
    self.set_owner("/etc/rspamd/custom", "_rspamd", "_rspamd")
    self.set_permissions("/etc/rspamd/custom", 0o755)

    custom_path = Path("/etc/rspamd/custom")
    for child in custom_path.iterdir():
      if child.is_file():
        self.set_owner(child, 82, 82)
        self.set_permissions(child, 0o644)

    # Provide additional lua modules
    arch = platform.machine()
    self.run_command(["ln", "-s", f"/usr/lib/{arch}-linux-gnu/liblua5.1-cjson.so.0.0.0", "/usr/lib/rspamd/cjson.so"], check=False)
