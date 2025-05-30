from jinja2 import Environment, FileSystemLoader
from modules.BootstrapBase import BootstrapBase
import os
import time
import subprocess

class BootstrapMysql(BootstrapBase):
  def bootstrap(self):
    dbuser = "root"
    dbpass = os.getenv("MYSQL_ROOT_PASSWORD", "")
    socket = "/tmp/mysql-temp.sock"

    print("Starting temporary mysqld for upgrade...")
    self.start_temporary(socket)

    self.connect_mysql(socket)

    print("Running mysql_upgrade...")
    self.upgrade_mysql(dbuser, dbpass, socket)
    print("Checking timezone support with CONVERT_TZ...")
    self.check_and_import_timezone_support(dbuser, dbpass, socket)

    print("Shutting down temporary mysqld...")
    self.close_mysql()
    self.stop_temporary(dbuser, dbpass, socket)


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
    }
    self.env_vars = self.prepare_template_vars('/service_config/overwrites.json', extra_vars)

    print("Set Timezone")
    self.set_timezone()

    print("Render config")
    self.render_config("/service_config")

  def start_temporary(self, socket):
    """
    Starts a temporary mysqld process in the background using the given UNIX socket.

    The server is started with networking disabled (--skip-networking).

    Args:
      socket (str): Path to the UNIX socket file for MySQL to listen on.

    Returns:
      subprocess.Popen: The running mysqld process object.
    """

    return subprocess.Popen([
      "mysqld",
      "--user=mysql",
      "--skip-networking",
      f"--socket={socket}"
    ])

  def stop_temporary(self, dbuser, dbpass, socket):
    """
    Shuts down the temporary mysqld instance gracefully.

    Uses mariadb-admin to issue a shutdown command to the running server.

    Args:
      dbuser (str): The MySQL username with shutdown privileges (typically 'root').
      dbpass (str): The password for the MySQL user.
      socket (str): Path to the UNIX socket the server is listening on.
    """

    self.run_command([
      "mariadb-admin",
      "shutdown",
      f"--socket={socket}",
      "-u", dbuser,
      f"-p{dbpass}"
    ])

  def upgrade_mysql(self, dbuser, dbpass, socket, max_retries=5, wait_interval=3):
    """
    Executes mysql_upgrade to check and fix any schema or table incompatibilities.

    Retries the upgrade command if it fails, up to a maximum number of attempts.

    Args:
      dbuser (str): MySQL username with privilege to perform the upgrade.
      dbpass (str): Password for the MySQL user.
      socket (str): Path to the MySQL UNIX socket for local communication.
      max_retries (int): Maximum number of attempts before giving up. Default is 5.
      wait_interval (int): Number of seconds to wait between retries. Default is 3.

    Returns:
      bool: True if upgrade succeeded, False if all attempts failed.
    """

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
      return False

  def check_and_import_timezone_support(self, dbuser, dbpass, socket):
    """
    Checks if MySQL supports timezone conversion (CONVERT_TZ).
    If not, it imports timezone info using mysql_tzinfo_to_sql piped into mariadb.
    """

    try:
      cursor = self.mysql_conn.cursor()
      cursor.execute("SELECT CONVERT_TZ('2019-11-02 23:33:00','Europe/Berlin','UTC')")
      result = cursor.fetchone()
      cursor.close()

      if not result or result[0] is None:
        print("Timezone conversion failed or returned NULL. Importing timezone info...")

        # Use mysql_tzinfo_to_sql piped into mariadb
        tz_dump = subprocess.Popen(
          ["mysql_tzinfo_to_sql", "/usr/share/zoneinfo"],
          stdout=subprocess.PIPE
        )

        self.run_command([
          "mariadb",
          "--socket", socket,
          "-u", dbuser,
          f"-p{dbpass}",
          "mysql"
        ], input_stream=tz_dump.stdout)

        tz_dump.stdout.close()
        tz_dump.wait()

        print("Timezone info successfully imported.")
      else:
        print(f"Timezone support is working. Sample result: {result[0]}")
    except Exception as e:
      print(f"Failed to verify or import timezone info: {e}")
