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

    # Wait for DNS
    self.wait_for_dns("mailcow.email")

    self.create_dir("/opt/postfix/conf/sql/")

    # Setup Jinja2 Environment and load vars
    self.env = Environment(
      loader=FileSystemLoader('./opt/postfix/conf/config_templates'),
      keep_trailing_newline=True,
      lstrip_blocks=True,
      trim_blocks=True
    )
    with open("/opt/postfix/conf/extra.cf", "r") as f:
      extra_config = f.read()
    extra_vars = {
      "VALID_CERT_DIRS": self.get_valid_cert_dirs(),
      "EXTRA_CF": extra_config
    }
    self.env_vars = self.prepare_template_vars('/overwrites.json', extra_vars)

    print("Set Timezone")
    self.set_timezone()

    print("Set Syslog redis")
    self.set_syslog_redis()

    print("Render config")
    self.render_config("aliases.j2", "/etc/aliases")
    self.render_config("mysql_relay_ne.cf.j2", "/opt/postfix/conf/sql/mysql_relay_ne.cf")
    self.render_config("mysql_relay_recipient_maps.cf.j2", "/opt/postfix/conf/sql/mysql_relay_recipient_maps.cf")
    self.render_config("mysql_tls_policy_override_maps.cf.j2", "/opt/postfix/conf/sql/mysql_tls_policy_override_maps.cf")
    self.render_config("mysql_tls_enforce_in_policy.cf.j2", "/opt/postfix/conf/sql/mysql_tls_enforce_in_policy.cf")
    self.render_config("mysql_sender_dependent_default_transport_maps.cf.j2", "/opt/postfix/conf/sql/mysql_sender_dependent_default_transport_maps.cf")
    self.render_config("mysql_transport_maps.cf.j2", "/opt/postfix/conf/sql/mysql_transport_maps.cf")
    self.render_config("mysql_virtual_resource_maps.cf.j2", "/opt/postfix/conf/sql/mysql_virtual_resource_maps.cf")
    self.render_config("mysql_sasl_passwd_maps_sender_dependent.cf.j2", "/opt/postfix/conf/sql/mysql_sasl_passwd_maps_sender_dependent.cf")
    self.render_config("mysql_sasl_passwd_maps_transport_maps.cf.j2", "/opt/postfix/conf/sql/mysql_sasl_passwd_maps_transport_maps.cf")
    self.render_config("mysql_virtual_alias_domain_maps.cf.j2", "/opt/postfix/conf/sql/mysql_virtual_alias_domain_maps.cf")
    self.render_config("mysql_virtual_alias_maps.cf.j2", "/opt/postfix/conf/sql/mysql_virtual_alias_maps.cf")
    self.render_config("mysql_recipient_bcc_maps.cf.j2", "/opt/postfix/conf/sql/mysql_recipient_bcc_maps.cf")
    self.render_config("mysql_sender_bcc_maps.cf.j2", "/opt/postfix/conf/sql/mysql_sender_bcc_maps.cf")
    self.render_config("mysql_recipient_canonical_maps.cf.j2", "/opt/postfix/conf/sql/mysql_recipient_canonical_maps.cf")
    self.render_config("mysql_virtual_domains_maps.cf.j2", "/opt/postfix/conf/sql/mysql_virtual_domains_maps.cf")
    self.render_config("mysql_virtual_mailbox_maps.cf.j2", "/opt/postfix/conf/sql/mysql_virtual_mailbox_maps.cf")
    self.render_config("mysql_virtual_relay_domain_maps.cf.j2", "/opt/postfix/conf/sql/mysql_virtual_relay_domain_maps.cf")
    self.render_config("mysql_virtual_sender_acl.cf.j2", "/opt/postfix/conf/sql/mysql_virtual_sender_acl.cf")
    self.render_config("mysql_mbr_access_maps.cf.j2", "/opt/postfix/conf/sql/mysql_mbr_access_maps.cf")
    self.render_config("mysql_virtual_spamalias_maps.cf.j2", "/opt/postfix/conf/sql/mysql_virtual_spamalias_maps.cf")
    self.render_config("sni.map.j2", "/opt/postfix/conf/sni.map")
    self.render_config("main.cf.j2", "/opt/postfix/conf/main.cf")

    # Conditional render
    if not Path("/opt/postfix/conf/dns_blocklists.cf").exists():
      self.render_config("dns_blocklists.cf.j2", "/opt/postfix/conf/dns_blocklists.cf")
    if not Path("/opt/postfix/conf/dns_reply.map").exists():
      self.render_config("dns_reply.map.j2", "/opt/postfix/conf/dns_reply.map")
    if not Path("/opt/postfix/conf/custom_postscreen_whitelist.cidr").exists():
      self.render_config("custom_postscreen_whitelist.cidr.j2", "/opt/postfix/conf/custom_postscreen_whitelist.cidr")
    if not Path("/opt/postfix/conf/custom_transport.pcre").exists():
      self.render_config("custom_transport.pcre.j2", "/opt/postfix/conf/custom_transport.pcre")

    # Create SNI Config
    self.run_command(["postmap", "-F", "hash:/opt/postfix/conf/sni.map"])

    # Fix Postfix permissions
    self.set_owner("/opt/postfix/conf/sql", user="root", group="postfix", recursive=True)
    self.set_owner("/opt/postfix/conf/custom_transport.pcre", user="root", group="postfix")
    for cf_file in Path("/opt/postfix/conf/sql").glob("*.cf"):
      self.set_permissions(cf_file, 0o640)
    self.set_permissions("/opt/postfix/conf/custom_transport.pcre", 0o640)
    self.set_owner("/var/spool/postfix/public", user="root", group="postdrop", recursive=True)
    self.set_owner("/var/spool/postfix/maildrop", user="root", group="postdrop", recursive=True)
    self.run_command(["postfix", "set-permissions"], check=False)

    # Checking if there is a leftover of a crashed postfix container before starting a new one
    pid_file = Path("/var/spool/postfix/pid/master.pid")
    if pid_file.exists():
      print(f"Removing stale Postfix PID file: {pid_file}")
      pid_file.unlink()

  def get_valid_cert_dirs(self):
    certs = {}
    base_path = Path("/etc/ssl/mail")
    if not base_path.exists():
      return certs

    for cert_dir in base_path.iterdir():
      if not cert_dir.is_dir():
        continue

      domains_file = cert_dir / "domains"
      cert_file = cert_dir / "cert.pem"
      key_file = cert_dir / "key.pem"

      if not (domains_file.exists() and cert_file.exists() and key_file.exists()):
        continue

      with open(domains_file, "r") as f:
        domains = [line.strip() for line in f if line.strip()]
        if domains:
          certs[str(cert_dir)] = domains

    return certs