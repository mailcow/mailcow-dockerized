from jinja2 import Environment, FileSystemLoader
from modules.BootstrapBase import BootstrapBase
from pathlib import Path
import os
import sys
import time
import platform
import subprocess

class Bootstrap(BootstrapBase):
  def bootstrap(self):
    self.upgrade_mysql()

    # Setup Jinja2 Environment and load vars
    self.env = Environment(
      loader=FileSystemLoader('./etc/mysql/conf.d/config_templates'),
      keep_trailing_newline=True,
      lstrip_blocks=True,
      trim_blocks=True
    )
    extra_vars = {
    }
    self.env_vars = self.prepare_template_vars('/overwrites.json', extra_vars)

    print("Set Timezone")
    self.set_timezone()

    print("Render config")
    self.render_config("my.cnf.j2", "/etc/mysql/conf.d/my.cnf")

  def upgrade_mysql(self, max_retries=5, wait_interval=3):
    """
    Runs mysql_upgrade in a controlled way using run_command.
    Starts mysqld in background, upgrades, shuts down, then restarts in foreground.
    """

    dbuser = "root"
    dbpass = os.getenv("MYSQL_ROOT_PASSWORD", "")
    socket = "/var/run/mysqld/mysqld.sock"

    print("Starting temporary mysqld for upgrade...")
    temp_proc = subprocess.Popen([
      "mysqld",
      "--user=mysql",
      "--skip-networking",
      f"--socket={socket}"
    ])

    self.connect_mysql()

    print("Running mysql_upgrade...")
    retries = 0
    while retries < max_retries:
      result = self.run_command([
        "mysql_upgrade",
        "-u", dbuser,
        f"-p{dbpass}",
        f"--socket={socket}"
      ], check=False)

      if result.returncode == 0:
        print("mysql_upgrade completed successfully.")
        break
      else:
        print(f"mysql_upgrade failed (try {retries+1}/{max_retries})")
        retries += 1
        time.sleep(wait_interval)
    else:
      print("mysql_upgrade failed after all retries.")
      temp_proc.terminate()
      return False

    print("Shutting down temporary mysqld...")
    self.run_command([
      "mariadb-admin",
      "shutdown",
      f"--socket={socket}",
      "-u", dbuser,
      f"-p{dbpass}"
    ])
