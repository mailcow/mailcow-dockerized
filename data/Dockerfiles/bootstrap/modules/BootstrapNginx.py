from jinja2 import Environment, FileSystemLoader
from modules.BootstrapBase import BootstrapBase
from pathlib import Path
import os
import sys
import time

class Bootstrap(BootstrapBase):
  def bootstrap(self):
    # Connect to MySQL
    self.connect_mysql()

    # wait for Hosts
    php_service = os.getenv("PHPFPMHOST") or "php-fpm-mailcow"
    rspamd_service = os.getenv("RSPAMDHOST") or "rspamd-mailcow"
    sogo_service = os.getenv("SOGOHOST") or os.getenv("IPV4_NETWORK", "172.22.1") + ".248"
    self.wait_for_host(php_service)
    if not self.isYes(os.getenv("SKIP_RSPAMD", False)):
      self.wait_for_host(rspamd_service)
    if not self.isYes(os.getenv("SKIP_SOGO", False)):
      self.wait_for_host(sogo_service)

    # Setup Jinja2 Environment and load vars
    self.env = Environment(
      loader=FileSystemLoader([
        '/etc/nginx/conf.d/custom_templates',
        '/etc/nginx/conf.d/config_templates'
      ]),
      keep_trailing_newline=True,
      lstrip_blocks=True,
      trim_blocks=True
    )
    extra_vars = {
      "VALID_CERT_DIRS": self.get_valid_cert_dirs(),
      'TRUSTED_PROXIES': [item.strip() for item in os.getenv("TRUSTED_PROXIES", "").split(",") if item.strip()],
      'ADDITIONAL_SERVER_NAMES': [item.strip() for item in os.getenv("ADDITIONAL_SERVER_NAMES", "").split(",") if item.strip()],
    }
    self.env_vars = self.prepare_template_vars('/overwrites.json', extra_vars)

    print("Set Timezone")
    self.set_timezone()

    print("Render config")
    self.render_config("nginx.conf.j2", "/etc/nginx/nginx.conf")
    self.render_config("sites-default.conf.j2", "/etc/nginx/includes/sites-default.conf")
    self.render_config("server_name.active.j2", "/etc/nginx/conf.d/server_name.active")
    self.render_config("listen_plain.active.j2", "/etc/nginx/conf.d/listen_plain.active")
    self.render_config("listen_ssl.active.j2", "/etc/nginx/conf.d/listen_ssl.active")

  def get_valid_cert_dirs(self):
    ssl_dir = '/etc/ssl/mail/'
    valid_cert_dirs = []
    for d in os.listdir(ssl_dir):
      full_path = os.path.join(ssl_dir, d)
      if not os.path.isdir(full_path):
        continue

      cert_path = os.path.join(full_path, 'cert.pem')
      key_path = os.path.join(full_path, 'key.pem')
      domains_path = os.path.join(full_path, 'domains')

      if os.path.isfile(cert_path) and os.path.isfile(key_path) and os.path.isfile(domains_path):
        with open(domains_path, 'r') as file:
          domains = file.read().strip()
        domains_list = domains.split()
        if domains_list and os.getenv("MAILCOW_HOSTNAME", "") not in domains_list:
          valid_cert_dirs.append({
            'cert_path': full_path + '/',
            'domains': domains
          })

    return valid_cert_dirs
