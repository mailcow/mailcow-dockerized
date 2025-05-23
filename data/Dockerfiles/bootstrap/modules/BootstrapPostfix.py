from jinja2 import Environment, FileSystemLoader
from modules.BootstrapBase import BootstrapBase
from pathlib import Path

class BootstrapPostfix(BootstrapBase):
  def bootstrap(self):
    # Connect to MySQL
    self.connect_mysql()

    # Wait for DNS
    self.wait_for_dns("mailcow.email")

    self.create_dir("/opt/postfix/conf/sql/")

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
    extra_config_path = Path("/opt/postfix/conf/extra.cf")
    extra_config = extra_config_path.read_text() if extra_config_path.exists() else ""
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
    self.render_config("/service_config")

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