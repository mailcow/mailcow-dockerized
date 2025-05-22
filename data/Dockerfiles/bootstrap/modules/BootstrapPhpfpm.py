from jinja2 import Environment, FileSystemLoader
from modules.BootstrapBase import BootstrapBase
from pathlib import Path
import os
import ipaddress
import sys
import time
import platform
import subprocess

class Bootstrap(BootstrapBase):
  def bootstrap(self):
    self.connect_mysql()
    self.connect_redis()

    # Setup Jinja2 Environment and load vars
    self.env = Environment(
      loader=FileSystemLoader([
        '/php-conf/custom_templates',
        '/php-conf/config_templates'
      ]),
      keep_trailing_newline=True,
      lstrip_blocks=True,
      trim_blocks=True
    )
    extra_vars = {
    }
    self.env_vars = self.prepare_template_vars('/overwrites.json', extra_vars)

    print("Set Timezone")
    self.set_timezone()

    # Prepare Redis and MySQL Database
    # TODO: move to dockerapi
    if self.isYes(os.getenv("MASTER", "")):
      print("We are master, preparing...")
      self.prepare_redis()
      self.setup_apikeys(
        os.getenv("API_ALLOW_FROM", "").strip(),
        os.getenv("API_KEY", "").strip(),
        os.getenv("API_KEY_READ_ONLY", "").strip()
      )
      self.setup_mysql_events()


    print("Render config")
    self.render_config("opcache-recommended.ini.j2", "/usr/local/etc/php/conf.d/opcache-recommended.ini")
    self.render_config("pools.conf.j2", "/usr/local/etc/php-fpm.d/z-pools.conf")
    self.render_config("other.ini.j2", "/usr/local/etc/php/conf.d/zzz-other.ini")
    self.render_config("upload.ini.j2", "/usr/local/etc/php/conf.d/upload.ini")
    self.render_config("session_store.ini.j2", "/usr/local/etc/php/conf.d/session_store.ini")
    self.render_config("0081-custom-mailcow.css.j2", "/web/css/build/0081-custom-mailcow.css")

    self.copy_file("/usr/local/etc/php/conf.d/opcache-recommended.ini", "/php-conf/opcache-recommended.ini")
    self.copy_file("/usr/local/etc/php-fpm.d/z-pools.conf", "/php-conf/pools.conf")
    self.copy_file("/usr/local/etc/php/conf.d/zzz-other.ini", "/php-conf/other.ini")
    self.copy_file("/usr/local/etc/php/conf.d/upload.ini", "/php-conf/upload.ini")
    self.copy_file("/usr/local/etc/php/conf.d/session_store.ini", "/php-conf/session_store.ini")

    self.set_owner("/global_sieve", 82, 82, recursive=True)
    self.set_owner("/web/templates/cache", 82, 82, recursive=True)
    self.remove("/web/templates/cache", wipe_contents=True, exclude=[".gitkeep"])

    print("Running DB init...")
    self.run_command(["php", "-c", "/usr/local/etc/php", "-f", "/web/inc/init_db.inc.php"], check=False)

  def prepare_redis(self):
    print("Setting default Redis keys if missing...")

    # Q_RELEASE_FORMAT
    if self.redis_conn.get("Q_RELEASE_FORMAT") is None:
      self.redis_conn.set("Q_RELEASE_FORMAT", "raw")

    # Q_MAX_AGE
    if self.redis_conn.get("Q_MAX_AGE") is None:
      self.redis_conn.set("Q_MAX_AGE", 365)

    # PASSWD_POLICY hash defaults
    if self.redis_conn.hget("PASSWD_POLICY", "length") is None:
      self.redis_conn.hset("PASSWD_POLICY", mapping={
        "length": 6,
        "chars": 0,
        "special_chars": 0,
        "lowerupper": 0,
        "numbers": 0
      })

    # DOMAIN_MAP
    print("Rebuilding DOMAIN_MAP from MySQL...")
    self.redis_conn.delete("DOMAIN_MAP")
    domains = set()
    try:
      cursor = self.mysql_conn.cursor()

      cursor.execute("SELECT domain FROM domain")
      domains.update(row[0] for row in cursor.fetchall())
      cursor.execute("SELECT alias_domain FROM alias_domain")
      domains.update(row[0] for row in cursor.fetchall())

      cursor.close()

      if domains:
        for domain in domains:
          self.redis_conn.hset("DOMAIN_MAP", domain, 1)
        print(f"{len(domains)} domains added to DOMAIN_MAP.")
      else:
        print("No domains found to insert into DOMAIN_MAP.")
    except Exception as e:
      print(f"Failed to rebuild DOMAIN_MAP: {e}")

  def setup_apikeys(self, api_allow_from, api_key_rw, api_key_ro):
    if not api_allow_from or api_allow_from == "invalid":
      return

    print("Validating API_ALLOW_FROM IPs...")
    ip_list = [ip.strip() for ip in api_allow_from.split(",")]
    validated_ips = []

    for ip in ip_list:
      try:
        ipaddress.ip_network(ip, strict=False)
        validated_ips.append(ip)
      except ValueError:
        continue
    if not validated_ips:
      print("No valid IPs found in API_ALLOW_FROM")
      return

    allow_from_str = ",".join(validated_ips)
    cursor = self.mysql_conn.cursor()
    try:
      if api_key_rw and api_key_rw != "invalid":
        print("Setting RW API key...")
        cursor.execute("DELETE FROM api WHERE access = 'rw'")
        cursor.execute(
          "INSERT INTO api (api_key, active, allow_from, access) VALUES (%s, %s, %s, %s)",
          (api_key_rw, 1, allow_from_str, "rw")
        )

      if api_key_ro and api_key_ro != "invalid":
        print("Setting RO API key...")
        cursor.execute("DELETE FROM api WHERE access = 'ro'")
        cursor.execute(
          "INSERT INTO api (api_key, active, allow_from, access) VALUES (%s, %s, %s, %s)",
          (api_key_ro, 1, allow_from_str, "ro")
        )

      self.mysql_conn.commit()
      print("API key(s) set successfully.")
    except Exception as e:
      print(f"Failed to configure API keys: {e}")
      self.mysql_conn.rollback()
    finally:
      cursor.close()

  def setup_mysql_events(self):
    print("Creating scheduled MySQL EVENTS...")

    queries = [
      "DROP EVENT IF EXISTS clean_spamalias;",
      """
      CREATE EVENT clean_spamalias
      ON SCHEDULE EVERY 1 DAY
      DO
      DELETE FROM spamalias WHERE validity < UNIX_TIMESTAMP();
      """,
      "DROP EVENT IF EXISTS clean_oauth2;",
      """
      CREATE EVENT clean_oauth2
      ON SCHEDULE EVERY 1 DAY
      DO
      BEGIN
        DELETE FROM oauth_refresh_tokens WHERE expires < NOW();
        DELETE FROM oauth_access_tokens WHERE expires < NOW();
        DELETE FROM oauth_authorization_codes WHERE expires < NOW();
      END;
      """,
      "DROP EVENT IF EXISTS clean_sasl_log;",
      """
      CREATE EVENT clean_sasl_log
      ON SCHEDULE EVERY 1 DAY
      DO
      BEGIN
        DELETE sasl_log.* FROM sasl_log
          LEFT JOIN (
            SELECT username, service, MAX(datetime) AS lastdate
            FROM sasl_log
            GROUP BY username, service
          ) AS last
          ON sasl_log.username = last.username AND sasl_log.service = last.service
          WHERE datetime < DATE_SUB(NOW(), INTERVAL 31 DAY)
            AND datetime < lastdate;

        DELETE FROM sasl_log
          WHERE username NOT IN (SELECT username FROM mailbox)
            AND datetime < DATE_SUB(NOW(), INTERVAL 31 DAY);
      END;
      """
    ]

    try:
      cursor = self.mysql_conn.cursor()
      for query in queries:
        cursor.execute(query)
      self.mysql_conn.commit()
      cursor.close()
      print("MySQL EVENTS created successfully.")
    except Exception as e:
      print(f"Failed to create MySQL EVENTS: {e}")
      self.mysql_conn.rollback()
