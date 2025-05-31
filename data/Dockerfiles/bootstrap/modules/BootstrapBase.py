import os
import pwd
import grp
import shutil
import secrets
import string
import subprocess
import time
import socket
import re
import redis
import hashlib
import json
import psutil
import signal
from urllib.parse import quote
from pathlib import Path
import dns.resolver
import mysql.connector

class BootstrapBase:
  def __init__(self, container, service, db_config, redis_config):
    self.container = container
    self.service = service
    self.db_config = db_config
    self.redis_config = redis_config

    self.env = None
    self.env_vars = None
    self.mysql_conn = None
    self.redis_connr = None
    self.redis_connw = None

  def render_config(self, config_dir):
    """
    Renders multiple Jinja2 templates from a config.json file in a given directory.

    Args:
      config_dir (str or Path): Path to the directory containing config.json

    Behavior:
      - Renders each template defined in config.json
      - Writes the result to the specified output path
      - Also copies the rendered file to: <config_dir>/rendered_configs/<relative_output_path>
    """

    config_dir = Path(config_dir)
    config_path = config_dir / "config.json"

    if not config_path.exists():
      print(f"config.json not found in: {config_dir}")
      return

    with config_path.open("r") as f:
      entries = json.load(f)

    for entry in entries:
      template_name = entry["template"]
      output_path = Path(entry["output"])
      clean_blank_lines = entry.get("clean_blank_lines", False)
      if_not_exists = entry.get("if_not_exists", False)

      if if_not_exists and output_path.exists():
        print(f"Skipping {output_path} (already exists)")
        continue

      output_path.parent.mkdir(parents=True, exist_ok=True)

      try:
        template = self.env.get_template(template_name)
      except Exception as e:
        print(f"Template not found: {template_name} ({e})")
        continue

      rendered = template.render(self.env_vars)

      if clean_blank_lines:
        rendered = "\n".join(line for line in rendered.splitlines() if line.strip())

      rendered = rendered.replace('\r\n', '\n').replace('\r', '\n')

      with output_path.open("w") as f:
        f.write(rendered)

      rendered_copy_path = config_dir / "rendered_configs" / output_path.name
      rendered_copy_path.parent.mkdir(parents=True, exist_ok=True)
      self.copy_file(output_path, rendered_copy_path)

      print(f"Rendered {template_name} → {output_path}")

  def prepare_template_vars(self, overwrite_path, extra_vars = None):
    """
    Loads and merges environment variables for Jinja2 templates from multiple sources, and registers custom template filters.

    This method combines variables from:
      1. System environment variables
      2. The MySQL `service_settings` table (filtered by service type if defined)
      3. An optional `extra_vars` dictionary
      4. A JSON overwrite file (if it exists at the given path)

    Also registers custom Jinja2 filters.

    Args:
        overwrite_path (str or Path): Path to a JSON file containing key-value overrides.
        extra_vars (dict, optional): Additional variables to merge into the environment.

    Returns:
        dict: A dictionary containing all resolved template variables.

    Raises:
        Prints errors if database fetch or JSON parsing fails, but does not raise exceptions.
    """

    # 1. setup filters
    self.env.filters['sha1'] = self.sha1_filter
    self.env.filters['urlencode'] = self.urlencode_filter
    self.env.filters['escape_quotes'] = self.escape_quotes_filter

    # 2. Load env vars
    env_vars = dict(os.environ)

    # 3. Load from MySQL
    try:
      cursor = self.mysql_conn.cursor()

      if self.db_config['service_types']:
        placeholders = ','.join(['%s'] * len(self.db_config['service_types']))
        sql = f"SELECT `key`, `value` FROM {self.db_config['service_table']} WHERE `type` IN ({placeholders})"
        cursor.execute(sql, self.db_config['service_types'])
      else:
        cursor.execute(f"SELECT `key`, `value` FROM {self.db_config['service_table']}")

      for key, value in cursor.fetchall():
        env_vars[key] = value

      cursor.close()
    except Exception as e:
      print(f"Failed to fetch DB service settings: {e}")

    # 4. Load extra vars
    if extra_vars:
      env_vars.update(extra_vars)

    # 5. Load overwrites
    overwrite_path = Path(overwrite_path)
    if overwrite_path.exists():
      try:
        with overwrite_path.open("r") as f:
          overwrite_data = json.load(f)
          env_vars.update(overwrite_data)
      except Exception as e:
        print(f"Failed to parse overwrites: {e}")

    return env_vars

  def set_timezone(self):
    """
    Sets the system timezone based on the TZ environment variable.

    If the TZ variable is set, writes its value to /etc/timezone.
    """

    timezone = os.getenv("TZ")
    if timezone:
      with open("/etc/timezone", "w") as f:
        f.write(timezone + "\n")

  def set_syslog_redis(self):
    """
    Reconfigures syslog-ng to use a Redis slave configuration.

    If the REDIS_SLAVEOF_IP environment variable is set, replaces the syslog-ng config
    with the Redis slave-specific config.
    """

    redis_slave_ip = os.getenv("REDIS_SLAVEOF_IP")
    if redis_slave_ip:
      shutil.copy("/etc/syslog-ng/syslog-ng-redis_slave.conf", "/etc/syslog-ng/syslog-ng.conf")

  def rsync_file(self, src, dst, recursive=False, owner=None, mode=None):
    """
    Copies files or directories using rsync, with optional ownership and permissions.

    Args:
        src (str or Path): Source file or directory.
        dst (str or Path): Destination directory.
        recursive (bool): If True, copies contents recursively.
        owner (tuple): Tuple of (user, group) to set ownership.
        mode (int): File mode (e.g., 0o644) to set permissions after sync.
    """

    src_path = Path(src)
    dst_path = Path(dst)
    dst_path.mkdir(parents=True, exist_ok=True)

    rsync_cmd = ["rsync", "-a"]
    if recursive:
      rsync_cmd.append(str(src_path) + "/")
    else:
      rsync_cmd.append(str(src_path))
    rsync_cmd.append(str(dst_path))

    try:
      subprocess.run(rsync_cmd, check=True)
    except Exception as e:
      print(f"Rsync failed: {e}")

    if owner:
      self.set_owner(dst_path, *owner, recursive=True)
    if mode:
      self.set_permissions(dst_path, mode)

  def set_permissions(self, path, mode):
    """
    Sets file or directory permissions.

    Args:
        path (str or Path): Path to the file or directory.
        mode (int): File mode to apply, e.g., 0o644.

    Raises:
        FileNotFoundError: If the path does not exist.
    """

    file_path = Path(path)
    if not file_path.exists():
      raise FileNotFoundError(f"Cannot chmod: {file_path} does not exist")
    os.chmod(file_path, mode)

  def set_owner(self, path, user, group=None, recursive=False):
    """
    Changes ownership of a file or directory.

    Args:
        path (str or Path): Path to the file or directory.
        user (str or int): Username or UID for new owner.
        group (str or int, optional): Group name or GID; defaults to user's group if not provided.
        recursive (bool): If True and path is a directory, ownership is applied recursively.

    Raises:
        FileNotFoundError: If the path does not exist.
    """

    # Resolve UID
    uid = int(user) if str(user).isdigit() else pwd.getpwnam(user).pw_uid
    # Resolve GID
    if group is not None:
      gid = int(group) if str(group).isdigit() else grp.getgrnam(group).gr_gid
    else:
      gid = uid if isinstance(user, int) or str(user).isdigit() else grp.getgrnam(user).gr_gid

    p = Path(path)
    if not p.exists():
      raise FileNotFoundError(f"{path} does not exist")

    if recursive and p.is_dir():
      for sub_path in p.rglob("*"):
        os.chown(sub_path, uid, gid)
    os.chown(p, uid, gid)

  def fix_permissions(self, path, user=None, group=None, mode=None, recursive=False):
    """
    Sets owner and/or permissions on a file or directory.

    Args:
      path (str or Path): Target path.
      user (str|int, optional): Username or UID.
      group (str|int, optional): Group name or GID.
      mode (int, optional): File mode (e.g. 0o644).
      recursive (bool): Apply recursively if path is a directory.
    """

    if user or group:
      self.set_owner(path, user, group, recursive)
    if mode:
      self.set_permissions(path, mode)

  def move_file(self, src, dst, overwrite=True):
    """
    Moves a file from src to dst, optionally overwriting existing files.

    Args:
        src (str or Path): Source file path.
        dst (str or Path): Destination path.
        overwrite (bool): If False, raises error if dst exists.

    Raises:
        FileNotFoundError: If the source file does not exist.
        FileExistsError: If the destination file exists and overwrite is False.
    """

    src_path = Path(src)
    dst_path = Path(dst)

    if not src_path.exists():
      raise FileNotFoundError(f"Source file does not exist: {src}")

    dst_path.parent.mkdir(parents=True, exist_ok=True)

    if dst_path.exists() and not overwrite:
      raise FileExistsError(f"Destination already exists: {dst} (set overwrite=True to overwrite)")

    shutil.move(str(src_path), str(dst_path))

  def copy_file(self, src, dst, overwrite=True):
    """
    Copies a file from src to dst using shutil.

    Args:
      src (str or Path): Source file path.
      dst (str or Path): Destination file path.
      overwrite (bool): Whether to overwrite the destination if it exists.

    Raises:
      FileNotFoundError: If the source file doesn't exist.
      FileExistsError: If the destination exists and overwrite is False.
      IOError: If the copy operation fails.
    """

    src_path = Path(src)
    dst_path = Path(dst)

    if not src_path.is_file():
      raise FileNotFoundError(f"Source file not found: {src_path}")

    if dst_path.exists() and not overwrite:
      raise FileExistsError(f"Destination exists: {dst_path}")

    dst_path.parent.mkdir(parents=True, exist_ok=True)

    shutil.copy2(src_path, dst_path)

  def remove(self, path, recursive=False, wipe_contents=False, exclude=None):
    """
    Removes a file or directory with optional exclusion logic.

    Args:
      path (str or Path): The file or directory path to remove.
      recursive (bool): If True, directories will be removed recursively.
      wipe_contents (bool): If True and path is a directory, only its contents are removed, not the dir itself.
      exclude (list[str], optional): List of filenames to exclude from deletion.

    Raises:
      FileNotFoundError: If the path does not exist.
      ValueError: If a directory is passed without recursive or wipe_contents.
    """


    path = Path(path)
    exclude = set(exclude or [])

    if not path.exists():
      raise FileNotFoundError(f"Cannot remove: {path} does not exist")

    if wipe_contents and path.is_dir():
      for child in path.iterdir():
        if child.name in exclude:
          continue
        if child.is_dir():
          shutil.rmtree(child)
        else:
          child.unlink()
    elif path.is_file():
      if path.name not in exclude:
        path.unlink()
    elif path.is_dir():
      if recursive:
        shutil.rmtree(path)
      else:
        raise ValueError(f"{path} is a directory. Use recursive=True or wipe_contents=True to remove it.")

  def create_dir(self, path):
    """
    Creates a directory if it does not exist.

    If the directory is missing, it will be created along with any necessary parent directories.

    Args:
      path (str or Path): The directory path to create.
    """

    dir_path = Path(path)
    if not dir_path.exists():
      print(f"Creating directory: {dir_path}")
      dir_path.mkdir(parents=True, exist_ok=True)

  def patch_exists(self, target_file, patch_file, reverse=False):
    """
    Checks whether a patch can be applied (or reversed) to a target file.

    Args:
        target_file (str): File to test the patch against.
        patch_file (str): Patch file to apply.
        reverse (bool): If True, checks whether the patch can be reversed.

    Returns:
        bool: True if patch is applicable, False otherwise.
    """

    cmd = ["patch", "-sfN", "--dry-run", target_file, "<", patch_file]
    if reverse:
      cmd.insert(1, "-R")
    try:
      result = subprocess.run(
        " ".join(cmd),
        shell=True,
        stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL
      )
      return result.returncode == 0
    except Exception as e:
      print(f"Patch dry-run failed: {e}")
      return False

  def apply_patch(self, target_file, patch_file, reverse=False):
    """
    Applies a patch file to a target file.

    Args:
        target_file (str): File to be patched.
        patch_file (str): Patch file containing the diff.
        reverse (bool): If True, applies the patch in reverse (rollback).

    Logs:
        Success or failure of the patching operation.
    """

    cmd = ["patch", target_file, "<", patch_file]
    if reverse:
      cmd.insert(0, "-R")
    try:
      subprocess.run(" ".join(cmd), shell=True, check=True)
      print(f"Applied patch {'(reverse)' if reverse else ''} to {target_file}")
    except subprocess.CalledProcessError as e:
      print(f"Patch failed: {e}")

  def isYes(self, value):
    """
    Determines whether a given string represents a "yes"-like value.

    Args:
        value (str): Input string to evaluate.

    Returns:
        bool: True if value is "yes" or "y" (case-insensitive), otherwise False.
    """
    return value.lower() in ["yes", "y"]

  def is_port_open(self, host, port):
    """
    Checks whether a TCP port is open on a given host.

    Args:
        host (str): The hostname or IP address to check.
        port (int): The TCP port number to test.

    Returns:
        bool: True if the port is open and accepting connections, False otherwise.
    """

    with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as sock:
      sock.settimeout(1)
      result = sock.connect_ex((host, port))
      return result == 0

  def resolve_docker_dns_record(self, hostname, record_type="A"):
    """
    Resolves DNS A or AAAA records for a given hostname.

    Args:
        hostname (str): The domain to query.
        record_type (str): "A" for IPv4, "AAAA" for IPv6. Default is "A".

    Returns:
        list[str]: A list of resolved IP addresses.

    Raises:
        Exception: If resolution fails or no results are found.
    """

    try:
      resolver = dns.resolver.Resolver()
      resolver.nameservers = ["127.0.0.11"]
      answers = resolver.resolve(hostname, record_type)
      return [answer.to_text() for answer in answers]
    except Exception as e:
      raise Exception(f"Failed to resolve {record_type} record for {hostname}: {e}")

  def kill_proc(self, process_name):
    """
    Sends SIGTERM to all running processes matching the given name.

    Args:
      process_name (str): Name of the process to terminate.

    Returns:
      int: Number of processes successfully signaled.
    """

    killed = 0
    for proc in psutil.process_iter(['name']):
      try:
        if proc.info['name'] == process_name:
          proc.send_signal(signal.SIGTERM)
          killed += 1
      except (psutil.NoSuchProcess, psutil.AccessDenied):
        continue
    return killed

  def connect_mysql(self, socket=None):
    """
    Establishes a connection to the MySQL database using the provided configuration.

    Continuously retries the connection until the database is reachable. Stores
    the connection in `self.mysql_conn` once successful.

    Logs:
        Connection status and retry errors to stdout.

    Args:
      socket (str, optional): Custom UNIX socket path to override the default.
    """

    print("Connecting to MySQL...")
    config = {
      "host": self.db_config['host'],
      "user": self.db_config['user'],
      "password": self.db_config['password'],
      "database": self.db_config['database'],
      "unix_socket": socket or self.db_config['unix_socket'],
      'connection_timeout': self.db_config['connection_timeout']
    }

    while True:
      try:
        self.mysql_conn = mysql.connector.connect(**config)
        if self.mysql_conn.is_connected():
          print("MySQL is up and ready!")
          break
      except mysql.connector.Error as e:
        print(f"Waiting for MySQL... ({e})")
        time.sleep(2)

  def close_mysql(self):
    """
    Closes the MySQL connection if it's currently open and connected.

    Safe to call even if the connection has already been closed.
    """

    if self.mysql_conn and self.mysql_conn.is_connected():
      self.mysql_conn.close()

  def connect_redis(self, max_retries=10, delay=2):
    """
    Connects to both read and write Redis servers and stores the connections.

    Read server: tries indefinitely until successful.
    Write server: tries up to `max_retries` before giving up.

    Sets:
      self.redis_connr: Redis client for read
      self.redis_connw: Redis client for write
    """

    use_rw = self.redis_config['read_host'] == self.redis_config['write_host'] and self.redis_config['read_port'] == self.redis_config['write_port']

    if use_rw:
      print("Connecting to Redis read server...")
    else:
      print("Connecting to Redis server...")

    while True:
      try:
        clientr = redis.Redis(
          host=self.redis_config['read_host'],
          port=self.redis_config['read_port'],
          password=self.redis_config['password'],
          db=self.redis_config['db'],
          decode_responses=True
        )
        if clientr.ping():
          self.redis_connr = clientr
          print("Redis read server is up and ready!")
          if use_rw:
            break
          else:
            self.redis_connw = clientr
            return
      except redis.RedisError as e:
        print(f"Waiting for Redis read... ({e})")
        time.sleep(delay)


    print("Connecting to Redis write server...")
    for attempt in range(max_retries):
      try:
        clientw = redis.Redis(
          host=self.redis_config['write_host'],
          port=self.redis_config['write_port'],
          password=self.redis_config['password'],
          db=self.redis_config['db'],
          decode_responses=True
        )
        if clientw.ping():
          self.redis_connw = clientw
          print("Redis write server is up and ready!")
          return
      except redis.RedisError as e:
        print(f"Waiting for Redis write... (attempt {attempt + 1}/{max_retries}) ({e})")
        time.sleep(delay)
    print("Redis write server is unreachable.")

  def close_redis(self):
    """
    Closes the Redis read/write connections if open.
    """

    if self.redis_connr:
      try:
        self.redis_connr.close()
      except Exception as e:
        print(f"Error while closing Redis read connection: {e}")
      finally:
        self.redis_connr = None

    if self.redis_connw:
      try:
        self.redis_connw.close()
      except Exception as e:
        print(f"Error while closing Redis write connection: {e}")
      finally:
        self.redis_connw = None

  def wait_for_schema_update(self, init_file_path="init_db.inc.php", check_interval=5):
    """
    Waits until the current database schema version matches the expected version
    defined in a PHP initialization file.

    Compares the `version` value in the `versions` table for `application = 'db_schema'`
    with the `$db_version` value extracted from the specified PHP file.

    Args:
        init_file_path (str): Path to the PHP file containing the expected version string.
        check_interval (int): Time in seconds to wait between version checks.

    Logs:
        Current vs. expected schema versions until they match.
    """

    print("Checking database schema version...")

    while True:
      current_version = self._get_current_db_version()
      expected_version = self._get_expected_schema_version(init_file_path)

      if current_version == expected_version:
        print(f"DB schema is up to date: {current_version}")
        break

      print(f"Waiting for schema update... (DB: {current_version}, Expected: {expected_version})")
      time.sleep(check_interval)

  def wait_for_host(self, host, retry_interval=1.0, count=1):
    """
    Waits for a host to respond to ICMP ping.

    Args:
      host (str): Hostname or IP to ping.
      retry_interval (float): Seconds to wait between pings.
      count (int): Number of ping packets to send per check (default 1).
    """
    while True:
      try:
        result = subprocess.run(
          ["ping", "-c", str(count), host],
          stdout=subprocess.DEVNULL,
          stderr=subprocess.DEVNULL
        )
        if result.returncode == 0:
          print(f"{host} is reachable via ping.")
          break
      except Exception:
        pass
      print(f"Waiting for {host}...")
      time.sleep(retry_interval)

  def wait_for_dns(self, domain, retry_interval=1, timeout=30):
    """
    Waits until the domain resolves via DNS using pure Python (socket).

    Args:
      domain (str): The domain to resolve.
      retry_interval (int): Time (seconds) to wait between attempts.
      timeout (int): Maximum total wait time (seconds).

    Returns:
      bool: True if resolved, False if timed out.
    """

    start = time.time()
    while True:
      try:
        socket.gethostbyname(domain)
        print(f"{domain} is resolving via DNS.")
        return True
      except socket.gaierror:
        pass

      if time.time() - start > timeout:
        print(f"DNS resolution for {domain} timed out.")
        return False

      print(f"Waiting for DNS for {domain}...")
      time.sleep(retry_interval)

  def _get_current_db_version(self):
    """
    Fetches the current schema version from the database.

    Executes a SELECT query on the `versions` table where `application = 'db_schema'`.

    Returns:
        str or None: The current schema version as a string, or None if not found or on error.

    Logs:
        Error message if the query fails.
    """

    try:
      cursor = self.mysql_conn.cursor()
      cursor.execute("SELECT version FROM versions WHERE application = 'db_schema'")
      result = cursor.fetchone()
      cursor.close()
      return result[0] if result else None
    except Exception as e:
      print(f"Error fetching current DB schema version: {e}")
      return None

  def _get_expected_schema_version(self, filepath):
    """
    Extracts the expected database schema version from a PHP initialization file.

    Looks for a line in the form of: `$db_version = "..."` and extracts the version string.

    Args:
        filepath (str): Path to the PHP file containing the `$db_version` definition.

    Returns:
        str or None: The extracted version string, or None if not found or on error.

    Logs:
        Error message if the file cannot be read or parsed.
    """

    try:
      with open(filepath, "r") as f:
        content = f.read()
        match = re.search(r'\$db_version\s*=\s*"([^"]+)"', content)
        if match:
          return match.group(1)
    except Exception as e:
      print(f"Error reading expected schema version from {filepath}: {e}")
    return None

  def rand_pass(self, length=22):
    """
    Generates a secure random password using allowed characters.

    Allowed characters include upper/lowercase letters, digits, underscores, and hyphens.

    Args:
        length (int): Length of the password to generate. Default is 22.

    Returns:
        str: A securely generated random password string.
    """

    allowed_chars = string.ascii_letters + string.digits + "_-"
    return ''.join(secrets.choice(allowed_chars) for _ in range(length))

  def run_command(self, command, check=True, shell=False, input_stream=None, log_output=True):
    """
    Executes a shell command and optionally logs output.

    Args:
      command (str or list): Command to run.
      check (bool): Raise if non-zero exit.
      shell (bool): Run in shell.
      input_stream: stdin stream.
      log_output (bool): If True, print output.

    Returns:
      subprocess.CompletedProcess
    """
    try:
      result = subprocess.run(
        command,
        shell=shell,
        check=check,
        stdin=input_stream,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        text=True
      )
      if log_output:
        if result.stdout:
          print(result.stdout.strip())
        if result.stderr:
          print(result.stderr.strip())
      return result
    except subprocess.CalledProcessError as e:
      print(f"Command failed with exit code {e.returncode}: {e.cmd}")
      print(e.stderr.strip())
      if check:
        raise
      return e

  def sha1_filter(self, value):
    return hashlib.sha1(value.encode()).hexdigest()

  def urlencode_filter(self, value):
    return quote(value, safe='')

  def escape_quotes_filter(self, value):
    return value.replace('"', r'\"')
