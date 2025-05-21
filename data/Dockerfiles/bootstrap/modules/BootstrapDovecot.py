from jinja2 import Environment, FileSystemLoader
from modules.BootstrapBase import BootstrapBase
from pathlib import Path
import os
import sys
import time
import pwd
import hashlib

class Bootstrap(BootstrapBase):
  def bootstrap(self):
    # Connect to MySQL
    self.connect_mysql()
    self.wait_for_schema_update()

    # Connect to Redis
    self.connect_redis()
    self.redis_conn.set("DOVECOT_REPL_HEALTH", 1)

    # Wait for DNS
    self.wait_for_dns("mailcow.email")

    # Create missing directories
    self.create_dir("/etc/dovecot/sql/")
    self.create_dir("/etc/dovecot/auth/")
    self.create_dir("/var/vmail/_garbage")
    self.create_dir("/var/vmail/sieve")
    self.create_dir("/etc/sogo")
    self.create_dir("/var/volatile")

    # Setup Jinja2 Environment and load vars
    self.env = Environment(
      loader=FileSystemLoader('./etc/dovecot/config_templates'),
      keep_trailing_newline=True,
      lstrip_blocks=True,
      trim_blocks=True
    )
    extra_vars = {
      "VALID_CERT_DIRS": self.get_valid_cert_dirs(),
      "RAND_USER": self.rand_pass(),
      "RAND_PASS": self.rand_pass(),
      "RAND_PASS2": self.rand_pass(),
      "ENV_VARS": dict(os.environ)
    }
    self.env_vars = self.prepare_template_vars('/overwrites.json', extra_vars)
    # Escape DBPASS
    self.env_vars['DBPASS'] = self.env_vars['DBPASS'].replace('"', r'\"')
    # Set custom filters
    self.env.filters['sha1'] = self.sha1_filter

    print("Set Timezone")
    self.set_timezone()

    print("Render config")
    self.render_config("dovecot-dict-sql-quota.conf.j2", "/etc/dovecot/sql/dovecot-dict-sql-quota.conf")
    self.render_config("dovecot-dict-sql-userdb.conf.j2", "/etc/dovecot/sql/dovecot-dict-sql-userdb.conf")
    self.render_config("dovecot-dict-sql-sieve_before.conf.j2", "/etc/dovecot/sql/dovecot-dict-sql-sieve_before.conf")
    self.render_config("dovecot-dict-sql-sieve_after.conf.j2", "/etc/dovecot/sql/dovecot-dict-sql-sieve_after.conf")
    self.render_config("mail_plugins.j2", "/etc/dovecot/mail_plugins")
    self.render_config("mail_plugins_imap.j2", "/etc/dovecot/mail_plugins_imap")
    self.render_config("mail_plugins_lmtp.j2", "/etc/dovecot/mail_plugins_lmtp")
    self.render_config("global_sieve_after.sieve.j2", "/var/vmail/sieve/global_sieve_after.sieve")
    self.render_config("global_sieve_before.sieve.j2", "/var/vmail/sieve/global_sieve_before.sieve")
    self.render_config("dovecot-master.passwd.j2", "/etc/dovecot/dovecot-master.passwd")
    self.render_config("dovecot-master.userdb.j2", "/etc/dovecot/dovecot-master.userdb")
    self.render_config("sieve.creds.j2", "/etc/sogo/sieve.creds")
    self.render_config("sogo-sso.pass.j2", "/etc/phpfpm/sogo-sso.pass")
    self.render_config("cron.creds.j2", "/etc/sogo/cron.creds")
    self.render_config("source_env.sh.j2", "/source_env.sh")
    self.render_config("maildir_gc.sh.j2", "/usr/local/bin/maildir_gc.sh")
    self.render_config("dovecot.conf.j2", "/etc/dovecot/dovecot.conf")

    files = [
      "/etc/dovecot/mail_plugins",
      "/etc/dovecot/mail_plugins_imap",
      "/etc/dovecot/mail_plugins_lmtp",
      "/templates/quarantine.tpl"
    ]
    for file in files:
      self.set_permissions(file, 0o644)

    try:
      # Migrate old sieve_after file
      self.move_file("/etc/dovecot/sieve_after", "/var/vmail/sieve/global_sieve_after.sieve")
    except Exception as e:
      pass
    try:
      # Cleanup random user maildirs
      self.remove("/var/vmail/mailcow.local", wipe_contents=True)
    except Exception as e:
      pass
    try:
      # Cleanup PIDs
      self.remove("/tmp/quarantine_notify.pid")
    except Exception as e:
      pass
    try:
      self.remove("/var/run/dovecot/master.pid")
    except Exception as e:
      pass

    # Check permissions of vmail/index/garbage directories.
    # Do not do this every start-up, it may take a very long time. So we use a stat check here.
    files = [
      "/var/vmail",
      "/var/vmail/_garbage",
      "/var/vmail_index"
    ]
    for file in files:
      path = Path(file)
      try:
        stat_info = path.stat()
        current_user = pwd.getpwuid(stat_info.st_uid).pw_name

        if current_user != "vmail":
          print(f"Ownership of {path} is {current_user}, fixing to vmail:vmail...")
          self.set_owner(path, user="vmail", group="vmail", recursive=True)
        else:
          print(f"Ownership of {path} is already correct (vmail)")
      except Exception as e:
          print(f"Error checking ownership of {path}: {e}")

    # Compile sieve scripts
    files = [
      "/var/vmail/sieve/global_sieve_before.sieve",
      "/var/vmail/sieve/global_sieve_after.sieve",
      "/usr/lib/dovecot/sieve/report-spam.sieve",
      "/usr/lib/dovecot/sieve/report-ham.sieve",
    ]
    for file in files:
      self.run_command(["sievec", file], check=False)

    # Fix permissions
    for path in Path("/etc/dovecot/sql").glob("*.conf"):
      self.set_owner(path, "root", "root")
      self.set_permissions(path, 0o640)

    files = [
      "/etc/dovecot/auth/passwd-verify.lua",
      *Path("/etc/dovecot/sql").glob("dovecot-dict-sql-sieve*"),
      *Path("/etc/dovecot/sql").glob("dovecot-dict-sql-quota*")
    ]
    for file in files:
      self.set_owner(file, "root", "dovecot")

    self.set_permissions("/etc/dovecot/auth/passwd-verify.lua", 0o640)

    for file in ["/var/vmail/sieve", "/var/volatile", "/var/vmail_index"]:
      self.set_owner(file, "vmail", "vmail", recursive=True)

    self.run_command(["adduser", "vmail", "tty"])
    self.run_command(["chmod", "g+rw", "/dev/console"])
    self.set_owner("/dev/console", "root", "tty")
    files = [
      "/usr/lib/dovecot/sieve/rspamd-pipe-ham",
      "/usr/lib/dovecot/sieve/rspamd-pipe-spam",
      "/usr/local/bin/imapsync_runner.pl",
      "/usr/local/bin/imapsync",
      "/usr/local/bin/trim_logs.sh",
      "/usr/local/bin/sa-rules.sh",
      "/usr/local/bin/clean_q_aged.sh",
      "/usr/local/bin/maildir_gc.sh",
      "/usr/local/sbin/stop-supervisor.sh",
      "/usr/local/bin/quota_notify.py",
      "/usr/local/bin/repl_health.sh",
      "/usr/local/bin/optimize-fts.sh"
    ]
    for file in files:
      self.set_permissions(file, 0o755)

    # Collect SA rules once now
    self.run_command(["/usr/local/bin/sa-rules.sh"], check=False)

    self.generate_mail_crypt_keys()
    self.cleanup_imapsync_jobs()
    self.generate_guid_version()

  def get_valid_cert_dirs(self):
    """
    Returns a mapping of domains to their certificate directory path.

    Example:
        {
            "example.com": "/etc/ssl/mail/example.com/",
            "www.example.com": "/etc/ssl/mail/example.com/"
        }
    """
    sni_map = {}
    base_path = Path("/etc/ssl/mail")
    if not base_path.exists():
      return sni_map

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
        for domain in domains:
          sni_map[domain] = str(cert_dir)

    return sni_map

  def generate_mail_crypt_keys(self):
    """
    Ensures mail_crypt EC keypair exists. Generates if missing. Adjusts permissions.
    """

    key_dir = Path("/mail_crypt")
    priv_key = key_dir / "ecprivkey.pem"
    pub_key = key_dir / "ecpubkey.pem"

    # Generate keys if they don't exist or are empty
    if not priv_key.exists() or priv_key.stat().st_size == 0 or \
      not pub_key.exists() or pub_key.stat().st_size == 0:
      self.run_command(
        "openssl ecparam -name prime256v1 -genkey | openssl pkey -out /mail_crypt/ecprivkey.pem",
        shell=True
      )
      self.run_command(
        "openssl pkey -in /mail_crypt/ecprivkey.pem -pubout -out /mail_crypt/ecpubkey.pem",
        shell=True
      )

    # Set ownership to UID 401 (dovecot)
    self.set_owner(priv_key, user='401')
    self.set_owner(pub_key, user='401')

  def cleanup_imapsync_jobs(self):
    """
    Cleans up stale imapsync locks and resets running status in the database.

    Deletes the imapsync_busy.lock file if present and sets `is_running` to 0
    in the `imapsync` table, if it exists.

    Logs:
      Any issues with file operations or SQL execution.
    """

    lock_file = Path("/tmp/imapsync_busy.lock")
    if lock_file.exists():
      try:
        lock_file.unlink()
      except Exception as e:
        print(f"Failed to remove lock file: {e}")

    try:
      cursor = self.mysql_conn.cursor()
      cursor.execute("SHOW TABLES LIKE 'imapsync'")
      result = cursor.fetchone()
      if result:
        cursor.execute("UPDATE imapsync SET is_running='0'")
        self.mysql_conn.commit()
      cursor.close()
    except Exception as e:
      print(f"Error updating imapsync table: {e}")

  def generate_guid_version(self):
    """
    Waits for the `versions` table to be created, then generates a GUID
    based on the mail hostname and Dovecot's public key and inserts it
    into the `versions` table.

    If the key or hash is missing or malformed, marks it as INVALID.
    """

    try:
      result = self.run_command(["doveconf", "-P"], check=True)
      pubkey_path = None
      for line in result.stdout.splitlines():
        if "mail_crypt_global_public_key" in line:
          parts = line.split('<')
          if len(parts) > 1:
            pubkey_path = parts[1].strip()
            break

      if pubkey_path and Path(pubkey_path).exists():
        with open(pubkey_path, "rb") as key_file:
          pubkey_data = key_file.read()

        hostname = self.env_vars.get("MAILCOW_HOSTNAME", "mailcow.local").encode("utf-8")
        concat = hostname + pubkey_data
        guid = hashlib.sha256(concat).hexdigest()

        if len(guid) == 64:
          version_value = guid
        else:
          version_value = "INVALID"

        cursor = self.mysql_conn.cursor()
        cursor.execute(
          "REPLACE INTO versions (application, version) VALUES (%s, %s)",
          ("GUID", version_value)
        )
        self.mysql_conn.commit()
        cursor.close()
      else:
        print("Public key not found or unreadable. GUID not generated.")
    except Exception as e:
      print(f"Failed to generate or store GUID: {e}")
