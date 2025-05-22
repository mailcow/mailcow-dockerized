from jinja2 import Environment, FileSystemLoader
from modules.BootstrapBase import BootstrapBase
from pathlib import Path
import os
import sys
import time

class Bootstrap(BootstrapBase):
  def bootstrap(self):
    # Skip SOGo if set
    if self.isYes(os.getenv("SKIP_SOGO", "")):
      print("SKIP_SOGO is set, skipping SOGo startup...")
      time.sleep(365 * 24 * 60 * 60)
      sys.exit(1)

    # Connect to MySQL
    self.connect_mysql()

    # Wait until port is free
    while self.is_port_open("sogo-mailcow", 20000):
      print("Port 20000 still in use — terminating sogod...")
      self.kill_proc("sogod")
      time.sleep(3)

    # Wait for schema to update to expected version
    self.wait_for_schema_update(init_file_path="init_db.inc.php")

    # Setup Jinja2 Environment and load vars
    self.env = Environment(
      loader=FileSystemLoader("/etc/sogo/config_templates"),
      keep_trailing_newline=True,
      lstrip_blocks=True,
      trim_blocks=True
    )
    extra_vars = {
      "SQL_DOMAINS": self.get_domains(),
      "IAM_SETTINGS": self.get_identity_provider_settings()
    }
    self.env_vars = self.prepare_template_vars('/overwrites.json', extra_vars)

    print("Set Timezone")
    self.set_timezone()

    print("Set Syslog redis")
    self.set_syslog_redis()

    print("Render config")
    self.render_config("sogod.plist.j2", "/var/lib/sogo/GNUstep/Defaults/sogod.plist")
    self.render_config("UIxTopnavToolbar.wox.j2", "/usr/lib/GNUstep/SOGo/Templates/UIxTopnavToolbar.wox")

    print("Fix permissions")
    self.set_owner("/var/lib/sogo", "sogo", "sogo", recursive=True)
    self.set_permissions("/var/lib/sogo/GNUstep/Defaults/sogod.plist", 0o600)

    # Rename custom logo
    logo_src = Path("/etc/sogo/sogo-full.svg")
    if logo_src.exists():
      print("Set Logo")
      self.move_file(logo_src, "/etc/sogo/custom-fulllogo.svg")

    # Rsync web content
    print("Syncing web content")
    self.rsync_file("/usr/lib/GNUstep/SOGo/", "/sogo_web/", recursive=True)

    # Chown backup path
    self.set_owner("/sogo_backup", "sogo", "sogo", recursive=True)

  def get_domains(self):
    """
    Retrieves a list of domains and their GAL (Global Address List) status.

    Executes a SQL query to select:
      - `domain`
      - a human-readable GAL status ("YES" or "NO")
      - `ldap_gal` as a boolean (True/False)

    Returns:
      list[dict]: A list of dicts with keys: domain, gal_status, ldap_gal.
                  Example: [{"domain": "example.com", "gal_status": "YES", "ldap_gal": True}]

    Logs:
      Error messages if the query fails.
    """

    query = """
      SELECT domain,
             CASE gal WHEN '1' THEN 'YES' ELSE 'NO' END AS gal_status,
             ldap_gal = 1 AS ldap_gal
      FROM domain;
    """
    try:
      cursor = self.mysql_conn.cursor()
      cursor.execute(query)
      result = cursor.fetchall()
      cursor.close()

      return [
        {
          "domain": row[0],
          "gal_status": row[1],
          "ldap_gal": bool(row[2])
        }
        for row in result
      ]
    except Exception as e:
      print(f"Error fetching domains: {e}")
      return []

  def get_identity_provider_settings(self):
    """
    Retrieves all key-value identity provider settings.

    Returns:
      dict: Settings in the format { key: value }

    Logs:
      Error messages if the query fails.
    """
    query = "SELECT `key`, `value` FROM identity_provider;"
    try:
      cursor = self.mysql_conn.cursor()
      cursor.execute(query)
      result = cursor.fetchall()
      cursor.close()

      iam_settings = {row[0]: row[1] for row in result}

      if iam_settings['authsource'] == "ldap":
        protocol = "ldaps" if iam_settings.get("use_ssl") else "ldap"
        starttls = "/????!StartTLS" if iam_settings.get("use_tls") else ""
        iam_settings['ldap_url'] = f"{protocol}://{iam_settings['host']}:{iam_settings['port']}{starttls}"

      return iam_settings
    except Exception as e:
      print(f"Error fetching identity provider settings: {e}")
      return {}