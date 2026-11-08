#!/usr/bin/python3
# Backup runner of the restic container (daemon, tini is PID 1): runs the jobs of backup_job one at a time (backup,
# check, prune, init and restores), queues the daily backup of the schedule in backup_setting and keeps the state and
# the snapshot cache (backup_snapshot) up to date. backup.php (nginx:8081) exports the domains and merges the restored
# settings (and creates deleted domains and mailboxes), sogo_restore.py merges the SOGo data, restore_runner.py in the
# dovecot container restores mails, IMAP ACLs and Sieve scripts batch by batch.

import base64
import collections
import datetime
import fcntl
import gzip
import hashlib
import hmac
import json
import os
import platform
import re
import secrets
import shutil
import signal
import smtplib
import socket
import stat
import subprocess
import sys
import tempfile
import threading
import time
import traceback
import urllib.error
import urllib.parse
import urllib.request
from email.message import EmailMessage
from email.utils import formatdate, make_msgid
import MySQLdb
import MySQLdb.cursors
import nacl.exceptions
import nacl.public
import sogo_restore

poll_interval = 10
lock_file = '/cache/runner.lock'
cache_dir = '/cache/restic'
ssh_dir = '/cache/ssh'
ssh_key = ssh_dir + '/id_ed25519'
known_hosts = ssh_dir + '/known_hosts'
# random id of this installation (in the volume, not the database: a database restored on another server gets a new
# one); snapshots carry it as tag instance:<id>, forget and prune only touch the own snapshots
instance_file = '/cache/instance_id'
# X25519 key pair of the stored secrets: PHP seals them with the public key (state_secrets_pubkey), only this
# container can open them; a restored database on another server needs the secrets entered again
secrets_key_file = '/cache/secrets_key'
secret_keys = ('password', 's3_secret_key', 'rest_password')
sealed_prefix = 'sealed:v1:'
secrets_error = 'the stored credentials cannot be decrypted by this backup container (another server or a new backup ' \
  'volume): enter the repository password and the target credentials again in the backup settings'
# the mailcow directory (read-only, with .git) is /mailcow/install; dump, export and manifest are written next to it
# in the container (restic stores absolute paths)
staging_dir = '/mailcow'
install_dir = staging_dir + '/install'
dump_dir = staging_dir + '/mysql'
export_dir = staging_dir + '/export'
# what a backup holds (RESTIC_BACKUP_MODE in mailcow.conf): all; data without the mailcow directory (its configuration
# may hold keys of an operator); config without mails (database, exports, Redis, mail crypt keys, mailcow.conf and
# data/conf). Snapshots of data and config carry the tag mode:<mode>
backup_modes = ('all', 'data', 'config')
# restore components that need the mails (not in config snapshots)
mail_components = {'mails', 'imap_acl', 'sogo', 'sieve_activate'}
# a local repository inside the mailcow directory is not backed up: this file marks it (only with its header, which
# holds the instance id, so a file of the same name elsewhere excludes nothing)
repo_marker = '.mailcow-restic-repository'
restore_dir = '/restore'
repository_root = '/repository'
backup_url = 'http://nginx:8081/backup.php'
max_log = 1000000
keep_jobs_days = 180
# requests to backup.php are tried this often
tries = 3
# unreadable files listed in the result of a backup
max_errors = 50
# a progress line in the job log at most this often (seconds)
progress_interval = 300
# restic retries an unreachable repository for many minutes, the connection test gives up earlier (seconds)
connect_timeout = 120
# mailboxes per batch of a restore: the staging of a batch is deleted before the next one
restore_batch = 20
# aliases per restore_aliases request
alias_chunk = 500
# a batch waits this long for the dovecot runner to take it, whose heartbeat may then be this old (seconds)
pickup_timeout = 1800
dovecot_timeout = 900
# free space kept besides what a restore needs
space_reserve = 256 << 20
# uid and gid of sogo in the sogo container (sogo-tool reads the staged backup files)
sogo_uid = 999
# a mail when the last successful backup is older than the longest gap of the schedule plus this (seconds), once a day
stale_margin = 12 * 3600
# lines of restic and ssh errors that tell the reason
error_hints = ('Fatal:', 'Host key', 'Permission denied', 'Connection refused', 'timed out', 'no such host',
  'Could not resolve', 'No route to host', 'Access Denied', 'AccessDenied', 'Unauthorized', 'NoSuchBucket')
# restore errors domain admins see instead of repository details
repo_error_generic = 'the backup repository could not be read (details in the log of the restic container)'

defaults = {
  'enabled': '0', 'target': 's3', 's3_endpoint': 'https://s3.amazonaws.com', 's3_bucket': '', 's3_path': '',
  's3_region': '', 's3_access_key': '', 's3_secret_key': '', 's3_path_style': '0', 'sftp_host': '', 'sftp_user': '',
  'sftp_path': '', 'sftp_port': '22', 'sftp_public_key': '', 'sftp_host_key': '', 'rest_url': '', 'rest_user': '',
  'rest_password': '', 'local_path': 'mailcow', 'password': '', 'password_confirmed': '0',
  'schedule': '[{"days":[0,1,2,3,4,5,6],"time":"01:00"}]', 'keep_hourly': '0', 'keep_daily': '7', 'keep_weekly': '4', 'keep_monthly': '12',
  'keep_yearly': '0', 'prune_weekday': '0', 'max_unused': '10%', 'check_subset': '30', 'include_index': '1',
  'include_fts': '1', 'limit_upload': '0', 'notify_email': '',
}
# restore components merged by backup.php, sogo_restore.py and the dovecot runner (filters: backup.php and dovecot)
php_components = {'filters', 'aliases', 'groups', 'sender_acl', 'mail_policies', 'retention_rules', 'policies', 'spam',
  'bcc', 'syncjobs', 'app_passwords', 'tfa', 'settings', 'password'}
dovecot_components = {'mails', 'imap_acl', 'filters', 'sieve_activate'}

domain_re = re.compile(r'[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?)+')
local_re = re.compile(r'[^@/\s\x00]+')

hostname = os.environ.get('MAILCOW_HOSTNAME') or socket.getfqdn()
token = hmac.new(os.environ.get('DBPASS', '').encode(), b'backup', hashlib.sha256).hexdigest()

conn = None
stopping = False
# the running job and its process, for the heartbeat, a cancel and a stop of the container
current = {'job': None, 'proc': None, 'interrupt': None}
last_cleanup = 0


class JobError(Exception):
  pass

# restic or the repository failed: details are not shown to domain admins
class RepoError(JobError):
  pass

# the job was ended by someone else (the dovecot runner failed it)
class JobGone(Exception):
  pass

# the container stops while the dovecot runner works on a batch of the job
class JobDetached(Exception):
  pass


def num(value):
  try:
    return int(value)
  except (TypeError, ValueError):
    return 0

def connect():
  return MySQLdb.connect(unix_socket='/var/run/mysqld/mysqld.sock', user=os.environ['DBUSER'],
    passwd=os.environ['DBPASS'], db=os.environ['DBNAME'], charset='utf8mb4', autocommit=True)

# Reconnect when the server dropped the connection (jobs can outlast wait_timeout)
def query(sql, args=None):
  global conn
  try:
    conn.ping()
  except (AttributeError, MySQLdb.Error):
    conn = connect()
  cur = conn.cursor(MySQLdb.cursors.DictCursor)
  cur.execute(sql, args)
  return cur

def secrets_private_key():
  try:
    return nacl.public.PrivateKey(base64.b64decode(read_private(secrets_key_file).strip()))
  except (OSError, ValueError, nacl.exceptions.CryptoError):
    return None

# Creates the key pair on the first start of the container
def secrets_setup():
  key = secrets_private_key()
  if key is None:
    key = nacl.public.PrivateKey.generate()
    write_private(secrets_key_file, base64.b64encode(bytes(key)) + b'\n')
  return key

def seal(public_key, value):
  return sealed_prefix + base64.b64encode(nacl.public.SealedBox(public_key).encrypt(value.encode())).decode()

def unseal(private_key, value):
  return nacl.public.SealedBox(private_key).decrypt(base64.b64decode(value[len(sealed_prefix):])).decode()

# The settings with defaults and opened secrets; secrets this container cannot open are empty and _secrets_error is set
def settings():
  s = dict(defaults)
  for row in query("SELECT name, value FROM backup_setting"):
    s[row['name']] = row['value'] or ''
  key = None
  for name in secret_keys:
    if not s[name].startswith(sealed_prefix):
      continue
    key = key or secrets_private_key()
    try:
      if key is None:
        raise ValueError('no key')
      s[name] = unseal(key, s[name])
    except (ValueError, TypeError, UnicodeDecodeError, nacl.exceptions.CryptoError):
      s[name] = ''
      s['_secrets_error'] = secrets_error
  return s

def set_setting(name, value):
  query("INSERT INTO backup_setting (name, value) VALUES (%s, %s) ON DUPLICATE KEY UPDATE value = VALUES(value)",
    (name, str(value)))

def instance_id():
  try:
    with open(instance_file) as f:
      value = f.read().strip()
    if re.fullmatch(r'[0-9a-f]{16}', value):
      return value
  except OSError:
    pass
  value = secrets.token_hex(8)
  write_private(instance_file, value.encode())
  return value

def instance_tag():
  return 'instance:' + instance_id()

# Content of the marker file of the local repository; restic excludes a folder only with this header
def marker_header():
  return 'mailcow restic repository ' + instance_id()

def configured_mode():
  return os.environ.get('RESTIC_BACKUP_MODE', '').strip().lower()[:32] or 'all'

def backup_mode():
  mode = configured_mode()
  if mode not in backup_modes:
    raise JobError('RESTIC_BACKUP_MODE in mailcow.conf must be all, data or config, not "%s"' % mode)
  return mode

def mailbox_valid(username):
  local, _, domain = username.rpartition('@')
  return bool(local_re.fullmatch(local)) and local not in ('.', '..') and bool(domain_re.fullmatch(domain))

# restic include patterns are globs
def glob_escape(text):
  return re.sub(r'([*?\[\]\\])', r'\\\1', text)

def local_time(value):
  # restic times are RFC 3339 with up to nanoseconds, stored in the local time zone like the other DATETIME columns
  m = re.match(r'(\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d)(?:\.\d+)?(Z|[+-]\d\d:\d\d)$', value or '')
  if not m:
    return None
  stamp = datetime.datetime.fromisoformat(m.group(1) + m.group(2).replace('Z', '+00:00'))
  return stamp.astimezone().strftime('%Y-%m-%d %H:%M:%S')

def tail(lines, size=1000):
  return '\n'.join(line for line in lines if line.strip())[-size:]

def mib(size):
  return '%.1f MiB' % (size / 1048576)

def gib(size):
  return '%.1f GiB' % (size / 1073741824)


class Job:
  def __init__(self, row):
    self.id, self.type, self.requested_by = row['id'], row['type'], row['requested_by']
    try:
      self.params = json.loads(row['params'] or '{}')
    except ValueError:
      self.params = {}
    if not isinstance(self.params, dict):
      self.params = {}
    self.result = {}
    self.warnings = []
    # restores of domain admins: no repository details in their log, errors and results
    self.restricted = self.type == 'restore' and self.params.get('requested_role', 'admin') != 'admin'
    # called with every heartbeat while restic runs, returns a line for the log or None
    self.progress = None

  def log(self, text):
    print('backup job %d (%s): %s' % (self.id, self.type, text), flush=True)
    line = '%s %s\n' % (time.strftime('%Y-%m-%d %H:%M:%S'), text)
    query("UPDATE backup_job SET log = RIGHT(CONCAT(IFNULL(log, ''), %s), %s) WHERE id = %s", (line, max_log, self.id))

  def step(self, name):
    query("UPDATE backup_job SET step = %s WHERE id = %s", (name, self.id))

  def warn(self, text):
    self.warnings.append(text)
    self.log('warning: ' + text)

  def save(self):
    if self.warnings:
      self.result['warnings'] = self.warnings
    query("UPDATE backup_job SET result = %s WHERE id = %s", (json.dumps(self.result), self.id))


# Repository string, options and environment of a target; secrets only go to the environment of restic
class Repository:
  def __init__(self, s):
    if s.get('_secrets_error'):
      raise JobError(s['_secrets_error'])
    if not s['password']:
      raise JobError('no repository password is set')
    self.env = base_env()
    self.env.update(RESTIC_PASSWORD=s['password'], RESTIC_CACHE_DIR=cache_dir)
    self.options = []
    self.sftp = False
    target = s['target']
    if target == 's3':
      endpoint = s['s3_endpoint'].strip().rstrip('/')
      bucket, path = s['s3_bucket'].strip().strip('/'), s['s3_path'].strip().strip('/')
      if not re.fullmatch(r'https?://[^/\s@]+', endpoint):
        raise JobError('the S3 endpoint must be a URL like https://s3.example.com')
      if not re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9._-]*', bucket):
        raise JobError('the S3 bucket is missing or invalid')
      if '..' in path.split('/'):
        raise JobError('the S3 path must not contain ..')
      if not s['s3_access_key'] or not s['s3_secret_key']:
        raise JobError('the S3 access key or secret key is missing')
      self.name = 's3:%s/%s' % (endpoint, bucket) + ('/' + path if path else '')
      self.env.update(AWS_ACCESS_KEY_ID=s['s3_access_key'], AWS_SECRET_ACCESS_KEY=s['s3_secret_key'])
      if s['s3_region'].strip():
        self.options += ['-o', 's3.region=' + s['s3_region'].strip()]
      if s['s3_path_style'] == '1':
        self.options += ['-o', 's3.bucket-lookup=path']
    elif target == 'sftp':
      host, user, path = s['sftp_host'].strip(), s['sftp_user'].strip(), s['sftp_path'].strip()
      port = num(s['sftp_port'])
      # these go into the ssh command line: no spaces, nothing ssh could take for an option
      if not re.fullmatch(r'[A-Za-z0-9](?:[A-Za-z0-9.-]*[A-Za-z0-9])?|[0-9A-Fa-f:.]*:[0-9A-Fa-f:.]*', host):
        raise JobError('the SFTP host is missing or invalid')
      if not re.fullmatch(r'[A-Za-z0-9_][A-Za-z0-9._-]*', user):
        raise JobError('the SFTP user is missing or invalid')
      if not 0 < port < 65536:
        raise JobError('the SFTP port is invalid')
      if not path or '..' in path.split('/'):
        raise JobError('the SFTP path is missing or contains ..')
      self.sftp = True
      self.strict = bool(s['sftp_host_key'].strip())
      self.name = 'sftp:%s@%s:%s' % (user, '[%s]' % host if ':' in host else host, path)
      command = ['ssh', '-F', '/dev/null', '-i', ssh_key, '-o', 'IdentitiesOnly=yes', '-o', 'BatchMode=yes',
        '-o', 'UserKnownHostsFile=' + known_hosts, '-o', 'GlobalKnownHostsFile=/dev/null',
        '-o', 'StrictHostKeyChecking=' + ('yes' if self.strict else 'accept-new'), '-o', 'LogLevel=ERROR',
        '-o', 'ConnectTimeout=30', '-o', 'ServerAliveInterval=60', '-o', 'ServerAliveCountMax=5',
        '-p', str(port), '-l', user, '-s', '--', host, 'sftp']
      self.options += ['-o', 'sftp.command=' + ' '.join(command)]
    elif target == 'rest':
      url = s['rest_url'].strip()
      parts = urllib.parse.urlsplit(url)
      if parts.scheme not in ('http', 'https') or not parts.hostname:
        raise JobError('the REST server URL must look like https://host:8000/path')
      if parts.username or parts.password:
        raise JobError('the REST server URL must not contain credentials, they belong into user and password')
      self.name = 'rest:' + url
      if s['rest_user']:
        self.env.update(RESTIC_REST_USERNAME=s['rest_user'], RESTIC_REST_PASSWORD=s['rest_password'])
    elif target == 'local':
      path = s['local_path'].strip().strip('/')
      if not re.fullmatch(r'[A-Za-z0-9._-]+(/[A-Za-z0-9._-]+)*', path) or '..' in path.split('/'):
        raise JobError('the local path must be a subdirectory of the repository folder without ..')
      self.name = repository_root + '/' + path
    else:
      raise JobError('no backup target is configured')
    self.env['RESTIC_REPOSITORY'] = self.name
    if num(s['limit_upload']) > 0:
      self.options += ['--limit-upload', str(num(s['limit_upload']))]

  # known_hosts comes from the settings: empty = the next connection accepts the key of the host
  def prepare(self, s):
    if self.sftp:
      with open(known_hosts, 'w') as f:
        f.write(s['sftp_host_key'].strip() + '\n' if self.strict else '')

  # store the host key accepted on the first connection
  def remember_host(self, s):
    if self.sftp and not self.strict:
      try:
        with open(known_hosts) as f:
          key = f.read().strip()
      except OSError:
        key = ''
      if key:
        set_setting('sftp_host_key', key)
        s['sftp_host_key'] = key
        self.strict = True


def base_env():
  env = {k: os.environ[k] for k in ('PATH', 'TZ', 'LANG', 'LC_ALL') if k in os.environ}
  env['HOME'] = '/root'
  return env

# state_runner_seen every poll_interval, in its own thread and connection: also while a call blocks the runner
class Heartbeat(threading.Thread):
  def __init__(self):
    super().__init__(daemon=True)

  def run(self):
    db = None
    while not stopping:
      try:
        if db is None:
          db = connect()
        db.cursor().execute("""INSERT INTO backup_setting (name, value) VALUES ('state_runner_seen', %s)
          ON DUPLICATE KEY UPDATE value = VALUES(value)""", (str(int(time.time())),))
      except MySQLdb.Error:
        db = None
      time.sleep(poll_interval)

# Checks while a job runs (cancel, stop, progress) and the cleanup of the restore staging; returns why the running
# job has to stop
def tick():
  global last_cleanup
  try:
    if time.monotonic() - last_cleanup >= 60:
      last_cleanup = time.monotonic()
      clean_restore()
    job = current['job']
    if job:
      row = query("SELECT state FROM backup_job WHERE id = %s", (job.id,)).fetchone()
      if row and row['state'] == 'cancelled':
        return 'cancelled'
      if job.progress:
        line = job.progress()
        if line:
          job.log(line)
  except MySQLdb.Error as e:
    print('backup runner: database error: %s' % e, file=sys.stderr)
  return 'stopped' if stopping else None

def interrupted_error():
  return JobError('cancelled' if current['interrupt'] == 'cancelled' else 'interrupted: the container was stopped')

# Last heartbeat of the dovecot runner (unix time, 0 before the first)
def restore_seen():
  row = query("SELECT value FROM backup_setting WHERE name = 'state_restore_seen'").fetchone()
  return num(row['value']) if row else 0

# Heartbeat in long phases without restic: stops the job when it was cancelled or the container stops
def check_cancel():
  reason = tick()
  if reason:
    current['interrupt'] = current['interrupt'] or reason
    raise interrupted_error()

# The reason in the error output of restic (and ssh): hint lines first, retry lines only once
def error_text(err):
  lines = []
  for line in err:
    line = line.strip()
    if line.startswith('{'):
      try:
        message = json.loads(line)
        line = (message.get('message') or '') if isinstance(message, dict) else line
      except ValueError:
        pass
    line = re.sub(r', retrying after [^:]*:', ':', re.sub(r'^subprocess ssh: ', '', line))
    # user names in URLs (the passwords are masked by restic)
    line = re.sub(r'//[^/@\s]+@', '//', line)
    if line and line not in lines:
      lines.append(line)
  hints = [line for line in lines if any(hint in line for hint in error_hints)]
  return '\n'.join((hints or lines)[-6:])[:1000]

# Run a command, heartbeat every poll_interval. stdout lines go to on_line or are returned, stderr lines to on_err and
# the end of stderr is kept. timeout: interrupt it after that many seconds (RepoError)
def execute(cmd, env, on_line=None, capture=True, timeout=None, on_err=None):
  proc = subprocess.Popen(cmd, env=env, stdin=subprocess.DEVNULL,
    stdout=subprocess.PIPE if on_line or capture else subprocess.DEVNULL, stderr=subprocess.PIPE)
  out, err = [], collections.deque(maxlen=40)

  def read_out():
    for line in proc.stdout:
      if on_line:
        on_line(line)
      else:
        out.append(line)

  def read_err():
    for line in proc.stderr:
      if on_err:
        on_err(line)
      err.append(line.decode('utf-8', 'replace').rstrip())

  readers = [threading.Thread(target=read_err, daemon=True)]
  if proc.stdout:
    readers.append(threading.Thread(target=read_out, daemon=True))
  for reader in readers:
    reader.start()
  current['proc'] = proc
  signalled, deadline = None, time.monotonic() + timeout if timeout else None
  try:
    while True:
      try:
        proc.wait(timeout=poll_interval if not deadline else max(1, min(poll_interval, deadline - time.monotonic())))
        break
      except subprocess.TimeoutExpired:
        pass
      reason = tick()
      if not reason and deadline and time.monotonic() >= deadline:
        reason = 'timeout'
      if reason and not signalled:
        # restic removes its lock on SIGINT
        if reason != 'timeout':
          current['interrupt'] = current['interrupt'] or reason
        signalled = time.monotonic()
        proc.send_signal(signal.SIGINT)
      elif signalled and time.monotonic() - signalled > 60:
        proc.kill()
  finally:
    current['proc'] = None
  for reader in readers:
    reader.join()
  if current['interrupt']:
    raise interrupted_error()
  if signalled:
    raise RepoError('no answer within %d s: %s' % (timeout, error_text(err)))
  return proc.returncode, b''.join(out), list(err)

def restic_error(rc, err):
  known = {10: 'the repository does not exist', 11: 'the repository is locked by another process',
    12: 'wrong repository password'}
  text = error_text(err)
  return known.get(rc, 'restic failed with exit code %d' % rc) + (': ' + text if text else '')

# restic with the repository; a lock error removes stale locks once and tries again
def restic(job, repo, args, on_line=None, capture=True, env=None, timeout=None, on_err=None):
  cmd = ['restic'] + repo.options + args
  run_env = dict(repo.env, **(env or {}))
  for attempt in (1, 2):
    rc, out, err = execute(cmd, run_env, on_line, capture, timeout, on_err)
    if rc != 11 or attempt == 2:
      break
    job.log('the repository is locked, removing stale locks')
    execute(['restic'] + repo.options + ['unlock'], repo.env)
  return rc, out, err

def restic_json(job, repo, args):
  rc, out, err = restic(job, repo, args)
  if rc != 0:
    raise RepoError(restic_error(rc, err))
  try:
    return json.loads(out or b'null')
  except ValueError:
    raise RepoError('unexpected output of restic %s' % args[0])

def restic_version():
  try:
    return subprocess.run(['restic', 'version'], capture_output=True, text=True, timeout=30).stdout.split()[1]
  except (OSError, IndexError, subprocess.SubprocessError):
    return ''

# Use the repository: a changed target resets the state, create = initialize it if it does not exist yet
def open_repository(job, s, repo, create):
  if s.get('state_repository') != repo.name:
    if s.get('state_repository'):
      job.log('the repository changed, its state and the snapshot list are reset')
    query("DELETE FROM backup_snapshot")
    for name, value in (('state_initialized', 0), ('state_repo_size', ''), ('state_repo_stats', ''),
        ('state_repo_id', ''), ('state_repository', repo.name)):
      set_setting(name, value)
      s[name] = str(value)
  if s.get('state_initialized') == '1' and job.type != 'init':
    return
  job.step('connect')
  rc, out, err = restic(job, repo, ['cat', 'config'], timeout=connect_timeout)
  if rc == 10 and create:
    job.log('the repository does not exist, creating it at %s' % repo.name)
    job.step('init')
    rc, out, err = restic(job, repo, ['init'], timeout=connect_timeout)
    if rc != 0:
      raise RepoError('the repository could not be created: ' + restic_error(rc, err))
    job.result['created'] = True
    rc, out, err = restic(job, repo, ['cat', 'config'])
  if rc != 0:
    raise RepoError(restic_error(rc, err))
  try:
    config = json.loads(out)
  except ValueError:
    raise RepoError('unexpected output of restic cat config')
  repo.remember_host(s)
  set_setting('state_initialized', 1)
  set_setting('state_repo_id', config.get('id', ''))
  s['state_initialized'] = '1'
  if not job.restricted:
    job.result['repository'] = {'id': config.get('id', ''), 'version': config.get('version')}
  job.log('repository %s is ready' % ('' if job.restricted else '%s (format %s)' % (repo.name, config.get('version'))))

def keep_args(s):
  args = []
  for period in ('hourly', 'daily', 'weekly', 'monthly', 'yearly'):
    count = num(s['keep_' + period])
    if count > 0:
      args += ['--keep-' + period, str(count)]
  return args

def max_unused(s):
  value = s['max_unused'].strip()
  return value if re.fullmatch(r'\d+(\.\d+)?%|\d+[kKmMgGtT]?|unlimited', value) else defaults['max_unused']

def forget(job, s, repo, prune):
  keep = keep_args(s)
  if keep:
    # only the snapshots of this installation, in one group (also after changes of hostname, paths or backup mode)
    args = ['forget', '--tag', 'mailcow,' + instance_tag(), '--group-by', ''] + keep
  elif prune:
    args = ['prune']
  else:
    job.log('no retention policy, all snapshots are kept')
    return
  if prune:
    args = ['--cleanup-cache'] + args + ['--max-unused', max_unused(s)] + (['--prune'] if keep else [])
    rc, out, err = restic(job, repo, args)
    if rc != 0:
      raise RepoError(restic_error(rc, err))
    job.result['output'] = out.decode('utf-8', 'replace').splitlines()[-30:]
    job.log('prune finished')
    return
  groups = restic_json(job, repo, args + ['--json']) or []
  removed = sum(len(group.get('remove') or []) for group in groups)
  job.result['forget'] = {'removed': removed}
  job.log('forget: %d snapshots removed by the retention policy' % removed)

# The domains of a snapshot made by mailcow with their creation time, from its manifest: {domain: unix time}
def snapshot_domains(job, repo, snapshot):
  if 'mailcow' not in (snapshot.get('tags') or []):
    return None
  rc, out, err = restic(job, repo, ['dump', snapshot['id'], staging_dir + '/manifest.json'])
  try:
    created = json.loads(out)['domains_created'] if rc == 0 else None
    return json.dumps({domain: created[domain] for domain in sorted(created)}) if isinstance(created, dict) else None
  except (ValueError, KeyError, TypeError):
    return None

# Replaces the snapshot cache; content (gzip JSON {domain: {username: name}}) of known snapshots is kept
def sync_snapshots(job, repo):
  snapshots = restic_json(job, repo, ['snapshots', '--json']) or []
  known = {row['id']: row['domains'] for row in query("SELECT id, domains FROM backup_snapshot")}
  for snapshot in snapshots:
    sid = snapshot['id']
    domains = known[sid] if sid in known else snapshot_domains(job, repo, snapshot)
    query("""INSERT INTO backup_snapshot (id, short_id, time, hostname, tags, summary, domains)
      VALUES (%s, %s, %s, %s, %s, %s, %s) ON DUPLICATE KEY UPDATE short_id = VALUES(short_id), time = VALUES(time),
      hostname = VALUES(hostname), tags = VALUES(tags), summary = VALUES(summary), domains = VALUES(domains)""",
      (sid, snapshot.get('short_id') or sid[:8], local_time(snapshot.get('time')), snapshot.get('hostname') or '',
      ','.join(snapshot.get('tags') or [])[:255],
      json.dumps(snapshot['summary']) if snapshot.get('summary') else None, domains))
  gone = set(known) - {snapshot['id'] for snapshot in snapshots}
  for sid in gone:
    query("DELETE FROM backup_snapshot WHERE id = %s", (sid,))
  job.result['snapshots'] = len(snapshots)

def repo_stats(job, repo):
  try:
    stats = restic_json(job, repo, ['stats', '--json', '--mode', 'raw-data']) or {}
  except JobError as e:
    job.warn('the repository size could not be read: %s' % e)
    return
  set_setting('state_repo_size', num(stats.get('total_size')))
  set_setting('state_repo_stats', json.dumps(stats))

def unlink(path):
  try:
    os.unlink(path)
  except OSError:
    pass

def clean_staging():
  shutil.rmtree(dump_dir, ignore_errors=True)
  shutil.rmtree(export_dir, ignore_errors=True)
  unlink(staging_dir + '/manifest.json')

def write_private(path, data):
  fd = os.open(path, os.O_WRONLY | os.O_CREAT | os.O_TRUNC | os.O_NOFOLLOW, 0o600)
  with os.fdopen(fd, 'wb') as f:
    f.write(data)

def read_private(path):
  fd = os.open(path, os.O_RDONLY | os.O_NOFOLLOW)
  with os.fdopen(fd, 'rb') as f:
    return f.read()

def dump_database(job):
  os.makedirs(dump_dir, mode=0o700, exist_ok=True)
  os.chmod(dump_dir, 0o700)
  # credentials in an option file, not in argv
  fd, cnf = tempfile.mkstemp(prefix='client.', suffix='.cnf')
  quote = lambda value: '"%s"' % value.replace('\\', '\\\\').replace('"', '\\"')
  try:
    with os.fdopen(fd, 'w') as f:
      f.write('[client]\nuser=%s\npassword=%s\nsocket=/var/run/mysqld/mysqld.sock\nskip-ssl\n' % (
        quote(os.environ['DBUSER']), quote(os.environ['DBPASS'])))
    started = time.monotonic()
    db = os.environ['DBNAME']
    dump = ['mariadb-dump', '--defaults-extra-file=' + cnf, '--single-transaction', '--hex-blob',
      '--default-character-set=utf8mb4']
    # the service API keys of mailcow.conf stay out (php-fpm adds them again at every start)
    rc, _, err = execute(dump + ['--routines', '--triggers', '--events', '--ignore-table=%s.api' % db,
      '--result-file=' + dump_dir + '/mailcow.sql', db], base_env(), capture=False)
    if rc == 0:
      rc, _, err = execute(dump + ['--where=service = 0', '--result-file=' + dump_dir + '/api.sql', db, 'api'], base_env(),
        capture=False)
    if rc == 0:
      with open(dump_dir + '/mailcow.sql', 'ab') as out, open(dump_dir + '/api.sql', 'rb') as part:
        shutil.copyfileobj(part, out)
  finally:
    unlink(cnf)
    unlink(dump_dir + '/api.sql')
  if rc != 0:
    raise JobError('the database dump failed: ' + tail(err))
  size = os.path.getsize(dump_dir + '/mailcow.sql')
  job.result['dump_bytes'] = size
  job.log('database dump: %d bytes in %d s' % (size, time.monotonic() - started))

def request(url, body=None):
  data = None if body is None else json.dumps(body).encode()
  req = urllib.request.Request(url, data=data, headers={'Content-Type': 'application/json', 'X-Backup-Token': token},
    method='GET' if body is None else 'POST')
  with urllib.request.urlopen(req, timeout=600) as response:
    return response.read()

# Retries on connection errors and server errors, not on refused requests
def retried(url, body=None):
  for attempt in range(1, tries + 1):
    try:
      return request(url, body)
    except urllib.error.HTTPError as e:
      if e.code < 500 or attempt == tries:
        raise
    except (OSError, ValueError):
      if attempt == tries:
        raise
    time.sleep(5 * attempt)

def post(body):
  answer = json.loads(retried(backup_url, body))
  return answer.get('result') if isinstance(answer, dict) and isinstance(answer.get('result'), dict) else {}

def http_error(e):
  if isinstance(e, urllib.error.HTTPError):
    try:
      detail = e.read(300).decode('utf-8', 'replace').strip()
    except OSError:
      detail = ''
    return 'HTTP %d%s' % (e.code, ': ' + detail if detail else '')
  return str(e)

# Export of one domain, page by page (after the last username of the previous page), into one file with all mailboxes;
# returns {username: name}
def export_domain(path, domain):
  names, pages, opened, query_args = {}, 0, False, {'export': domain}
  fd = os.open(path, os.O_WRONLY | os.O_CREAT | os.O_TRUNC | os.O_NOFOLLOW, 0o600)
  with os.fdopen(fd, 'w', encoding='utf-8') as f:
    while query_args is not None:
      check_cancel()
      data = json.loads(retried(backup_url + '?' + urllib.parse.urlencode(query_args)))
      if not isinstance(data, dict):
        raise ValueError('no JSON object')
      if not opened:
        # version, domain, domain_data, aliases of the first page, then the mailboxes of all pages
        head = json.dumps({k: v for k, v in data.items() if k not in ('mailboxes', 'next_after')})
        f.write(head[:-1] + (', ' if len(head) > 2 else '') + '"mailboxes": {')
        opened = True
      for username, section in (data.get('mailboxes') or {}).items():
        if username in names:
          continue
        f.write((', ' if names else '') + json.dumps(username) + ': ' + json.dumps(section))
        mailbox = section.get('mailbox') if isinstance(section, dict) else None
        names[username] = str((mailbox or {}).get('name') or '') if isinstance(mailbox, dict) else ''
      pages += 1
      after = data.get('next_after')
      if isinstance(after, str) and after and after != query_args.get('after') and pages < 100000:
        query_args = {'export': domain, 'after': after}
      else:
        query_args = None
    f.write('}}')
  return names

# Export of every domain by backup.php (/mailcow/export/<domain>.json); failures are warnings, the mails are saved anyway.
# Returns {domain: {username: name}} of the exported domains
def export_domains(job):
  folder = export_dir
  os.makedirs(folder, mode=0o700, exist_ok=True)
  content = {}
  try:
    listed = json.loads(retried(backup_url + '?domains=1'))['domains']
  except Exception as e:
    job.warn('the domain list for the export could not be fetched, the snapshot has no export: %s' % http_error(e))
    job.result['export'] = {'domains': 0, 'failed': ['*']}
    return content
  exported, failed = 0, []
  for domain in listed:
    if not isinstance(domain, str) or not domain_re.fullmatch(domain):
      failed.append(str(domain))
      continue
    try:
      content[domain] = export_domain('%s/%s.json' % (folder, domain), domain)
      exported += 1
    except JobError:
      raise
    except Exception as e:
      failed.append(domain)
      unlink('%s/%s.json' % (folder, domain))
      job.log('export of %s failed: %s' % (domain, http_error(e)))
  if failed:
    job.warn('the export failed for %d domains: %s' % (len(failed), ', '.join(failed[:20])))
  job.result['export'] = {'domains': exported, 'failed': failed}
  job.log('export: %d domains' % exported)
  return content

# Content of a snapshot for the restore page: {"domains": {domain: {"created": unix time, "mailboxes": {username: name}}}};
# mailboxes of domains without export come from the database
def snapshot_content(exported):
  domains = {}
  for row in query("SELECT domain, UNIX_TIMESTAMP(created) AS created FROM domain").fetchall():
    domain = row['domain']
    mailboxes = exported.get(domain)
    if mailboxes is None:
      mailboxes = {m['username']: m['name'] or '' for m in query("SELECT username, name FROM mailbox WHERE domain = %s",
        (domain,)).fetchall()}
    domains[domain] = {'created': num(row['created']), 'mailboxes': mailboxes}
  return {'domains': domains}

# A fresh dump.rdb: BGSAVE and wait for LASTSAVE to change; the older dump is backed up if that fails
def save_redis(job):
  try:
    with socket.create_connection(('redis', 6379), timeout=30) as sock:
      stream = sock.makefile('rwb')

      def command(*args):
        stream.write(b'*%d\r\n' % len(args) + b''.join(b'$%d\r\n%s\r\n' % (len(a), a) for a in (x.encode() for x in args)))
        stream.flush()
        reply = stream.readline()
        if reply[:1] == b'-':
          raise RuntimeError(reply[1:].strip().decode('utf-8', 'replace'))
        return reply[1:].strip()

      if os.environ.get('REDISPASS'):
        command('AUTH', os.environ['REDISPASS'])
      last = command('LASTSAVE')
      try:
        command('BGSAVE')
      except RuntimeError as e:
        # a save in progress also ends with a new LASTSAVE
        if 'progress' not in str(e):
          raise
      deadline = time.monotonic() + 600
      while command('LASTSAVE') == last:
        if time.monotonic() > deadline:
          raise RuntimeError('timeout')
        check_cancel()
        time.sleep(1)
  except JobError:
    raise
  except Exception as e:
    job.warn('redis did not save a fresh dump, the last one is backed up: %s' % e)

# Version of the mailcow directory: git commit, branch, locally changed files, image tags
def install_info():
  info = {}

  def git(*args):
    result = subprocess.run(['git', '-c', 'safe.directory=*', '--no-optional-locks', '-C', install_dir] + list(args),
      capture_output=True, text=True, timeout=300)
    return result.stdout if result.returncode == 0 else None

  try:
    info['commit'] = (git('rev-parse', 'HEAD') or '').strip()
    info['branch'] = (git('rev-parse', '--abbrev-ref', 'HEAD') or '').strip()
    status = git('status', '--porcelain', '--untracked-files=all')
    if status is not None:
      changed = [line for line in status.splitlines() if line.strip()]
      info['changed'] = changed[:1000]
      info['changed_count'] = len(changed)
  except (OSError, subprocess.SubprocessError):
    pass
  try:
    with open(install_dir + '/data/web/inc/app_info.inc.php', encoding='utf-8', errors='replace') as f:
      m = re.search(r'\$MAILCOW_GIT_VERSION="([^"]*)"', f.read())
      if m:
        info['version'] = m.group(1)
  except OSError:
    pass
  images, service = {}, None
  try:
    with open(install_dir + '/docker-compose.yml', encoding='utf-8', errors='replace') as f:
      for line in f:
        m = re.match(r'^    ([A-Za-z0-9_.-]+):\s*$', line)
        if m:
          service = m.group(1)
        m = re.match(r'^\s+image:\s*(\S+)', line)
        if m and service:
          images[service] = m.group(1)
  except OSError:
    pass
  info['images'] = images
  return info

def db_version():
  row = query("SELECT version FROM versions WHERE application = 'db_schema'").fetchone()
  return row['version'] if row else ''

def write_manifest(job, mode):
  rows = query("SELECT domain, UNIX_TIMESTAMP(created) AS created FROM domain").fetchall()
  domains = sorted(row['domain'] for row in rows)
  install = install_info()
  # without the directory only its version
  if mode == 'data':
    install = {key: install[key] for key in ('commit', 'branch', 'version') if key in install}
  manifest = {'created': int(time.time()), 'hostname': hostname, 'db_version': db_version(), 'restic': restic_version(),
    'domains': domains, 'domains_created': {row['domain']: num(row['created']) for row in rows}, 'arch': platform.machine(),
    'instance': instance_id(), 'mode': mode, 'install': install}
  write_private(staging_dir + '/manifest.json', json.dumps(manifest).encode())
  job.result['domains'] = len(domains)

# What a backup of the mode holds: paths and excludes
def backup_paths(s, mode):
  staged = [dump_dir, export_dir, staging_dir + '/manifest.json']
  if mode == 'config':
    return ['/mail_crypt', '/redis'] + staged + [install_dir + '/mailcow.conf', install_dir + '/data/conf'], []
  paths, excludes = ['/var/vmail'], []
  if s['include_index'] == '1':
    paths.append('/var/vmail_index')
    if s['include_fts'] != '1':
      excludes += ['--exclude', '/var/vmail_index/**/fts-flatcurve']
  # data: of the mailcow directory only mailcow.conf (data/conf may hold keys of the operator)
  paths += ['/mail_crypt', '/redis', '/rspamd', '/sogo_backup'] + ([staging_dir] if mode == 'all' else staged + [install_dir + '/mailcow.conf'])
  return paths, excludes

def run_backup(job, s, repo, mode):
  args = ['backup', '--json', '--host', hostname, '--tag', 'mailcow', '--tag', instance_tag()] + \
    ([] if mode == 'all' else ['--tag', 'mode:' + mode]) + [
    '--exclude', '/var/vmail/_garbage',
    # the mailcow directory without the local repository, the template cache and editor files
    '--exclude-if-present', '%s:%s' % (repo_marker, marker_header()),
    '--exclude', install_dir + '/data/web/templates/cache/*', '--exclude', '!' + install_dir + '/data/web/templates/cache/.gitkeep',
    '--exclude', install_dir + '/**/*.swp', '--exclude', install_dir + '/**/*~']
  local = os.environ.get('RESTIC_LOCAL_PATH', '').strip()
  if local and not local.startswith('/') and '..' not in local.split('/'):
    args += ['--exclude', os.path.normpath(os.path.join(install_dir, local))]
  paths, excludes = backup_paths(s, mode)
  args += excludes
  state = {'summary': None, 'errors': [], 'count': 0, 'status': None, 'logged': time.monotonic()}

  # runs in the reader threads, no database access here; errors come on stderr
  def on_line(line):
    try:
      message = json.loads(line)
    except ValueError:
      return
    kind = message.get('message_type') if isinstance(message, dict) else None
    if kind == 'summary':
      state['summary'] = message
    elif kind == 'status':
      state['status'] = message
    elif kind == 'error':
      state['count'] += 1
      if len(state['errors']) < max_errors:
        state['errors'].append({'item': message.get('item', ''), 'error': (message.get('error') or {}).get('message', '')})

  def progress():
    status = state['status']
    if not status or time.monotonic() - state['logged'] < progress_interval:
      return None
    state['logged'] = time.monotonic()
    return 'backup: %d%% done, %d of %d files, %d MiB of %d MiB' % (100 * (status.get('percent_done') or 0),
      status.get('files_done', 0), status.get('total_files', 0), status.get('bytes_done', 0) >> 20,
      status.get('total_bytes', 0) >> 20)

  job.progress = progress
  try:
    rc, _, err = restic(job, repo, args + paths, on_line=on_line, env={'RESTIC_PROGRESS_FPS': '0.1'}, on_err=on_line)
  finally:
    job.progress = None
  summary = state['summary']
  if rc not in (0, 3) or not summary:
    raise RepoError(restic_error(rc, err))
  job.result['snapshot'] = summary.get('snapshot_id')
  job.result['summary'] = summary
  if state['errors']:
    job.result['file_errors'] = state['errors']
  if rc == 3:
    job.warn('%d files could not be read, the snapshot misses them' % max(state['count'], 1))
  job.log('snapshot %s: %d new and %d changed files, %d MiB added, %d MiB processed' % (
    (summary.get('snapshot_id') or '')[:8], summary.get('files_new', 0), summary.get('files_changed', 0),
    summary.get('data_added', 0) >> 20, summary.get('total_bytes_processed', 0) >> 20))

# Free space for a local repository (the default volume shares the disk with the mail data, a full disk stops mail):
# what the backup adds (twice the data added by the last snapshot of this installation, else an estimate of everything)
# must leave a reserve of max(2 GiB, 5 % of the filesystem)
def local_space_needed(added, mails, database, dump):
  # twice the data the last snapshot added; for the first backup everything plus 1 GiB
  if added is not None:
    return max(1 << 30, 2 * added)
  return mails + database + dump + (1 << 30)

# as in restore_runner.py of the dovecot image (also mib and count_errors; tests/python/backup compares them)
def disk_reserve(total):
  return max(2 << 30, total // 20)

def check_local_space(job, s):
  if s['target'] != 'local':
    return
  fs = os.statvfs(repository_root)
  free, total = fs.f_bavail * fs.f_frsize, fs.f_blocks * fs.f_frsize
  added, mails, database, dump = None, 0, 0, 0
  row = query("""SELECT summary FROM backup_snapshot WHERE FIND_IN_SET(%s, tags) AND summary IS NOT NULL
    ORDER BY time DESC LIMIT 1""", (instance_tag(),)).fetchone()
  if row:
    try:
      added = int(json.loads(row['summary']).get('data_added'))
    except (ValueError, TypeError, AttributeError):
      added = None
  if added is None:
    # first backup: mails, database, the last dump if there is one
    mails = num(query("SELECT IFNULL(SUM(bytes), 0) AS bytes FROM quota2").fetchone()['bytes'])
    database = num(query("""SELECT IFNULL(SUM(data_length + index_length), 0) AS bytes FROM information_schema.tables
      WHERE table_schema = DATABASE()""").fetchone()['bytes'])
    for row in query("""SELECT result FROM backup_job WHERE type = 'backup' AND state = 'done' AND result IS NOT NULL
        ORDER BY id DESC LIMIT 1"""):
      try:
        dump = num(json.loads(row['result']).get('dump_bytes'))
      except (ValueError, AttributeError):
        pass
  needed = local_space_needed(added, mails, database, dump)
  reserve = disk_reserve(total)
  text = '%s free, about %s needed plus %s reserve' % (gib(free), gib(needed), gib(reserve))
  if free - needed < reserve:
    raise JobError('not enough free space for the local repository: ' + text)
  job.log('local repository: ' + text)

def job_backup(job, s, repo):
  set_setting('state_last_backup', job.id)
  mode = backup_mode()
  check_local_space(job, s)
  open_repository(job, s, repo, create=True)
  try:
    job.step('dump')
    dump_database(job)
    job.step('export')
    exported = export_domains(job)
    save_redis(job)
    write_manifest(job, mode)
    job.step('backup')
    run_backup(job, s, repo, mode)
  finally:
    clean_staging()
  # the data is safe from here on: the snapshot gets its row with the content at once, forget and the snapshot list
  # are warnings when they fail
  set_setting('state_last_success', int(time.time()))
  store_snapshot(job, snapshot_content(exported), mode)
  job.save()
  try:
    job.step('forget')
    forget(job, s, repo, prune=False)
  except RepoError as e:
    job.warn('forget failed, the snapshot is saved: %s' % e)
  try:
    job.step('snapshots')
    sync_snapshots(job, repo)
  except RepoError as e:
    job.warn('the snapshot list could not be refreshed: %s' % e)
  # the exact size is read after prunes; here the data the snapshot added is counted
  if not s.get('state_repo_size'):
    job.step('stats')
    repo_stats(job, repo)
  else:
    set_setting('state_repo_size', num(s['state_repo_size']) + num((job.result.get('summary') or {}).get('data_added_packed')
      or (job.result.get('summary') or {}).get('data_added')))

# Row of the new snapshot with its content (the restore page lists domains and mailboxes from it); the snapshot sync
# fills in the rest and keeps the content
def store_snapshot(job, content, mode):
  summary = job.result.get('summary') or {}
  sid = job.result.get('snapshot')
  if not sid:
    return
  query("""INSERT INTO backup_snapshot (id, short_id, time, hostname, tags, summary, domains, content)
    VALUES (%s, %s, %s, %s, %s, %s, %s, %s) ON DUPLICATE KEY UPDATE content = VALUES(content)""",
    (sid, sid[:8], local_time(summary.get('backup_start')) or time.strftime('%Y-%m-%d %H:%M:%S'), hostname,
    ','.join(['mailcow', instance_tag()] + ([] if mode == 'all' else ['mode:' + mode])), json.dumps(summary),
    json.dumps({domain: content['domains'][domain].get('created') for domain in sorted(content['domains'])}),
    gzip.compress(json.dumps(content).encode())))

def job_init(job, s, repo):
  open_repository(job, s, repo, create=True)
  job.step('snapshots')
  sync_snapshots(job, repo)
  job.step('stats')
  repo_stats(job, repo)

def job_check(job, s, repo):
  open_repository(job, s, repo, create=False)
  job.step('check')
  parts = num(s['check_subset'])
  args = ['check', '--with-cache']
  if parts > 0:
    # each run reads the next part of the data
    part = num(s.get('state_check_part')) % parts + 1
    args.append('--read-data-subset=%d/%d' % (part, parts))
    job.result['subset'] = '%d/%d' % (part, parts)
  rc, out, err = restic(job, repo, args)
  job.result['output'] = out.decode('utf-8', 'replace').splitlines()[-30:]
  if rc != 0:
    raise RepoError(restic_error(rc, err))
  if parts > 0:
    set_setting('state_check_part', part)
  job.log('check finished' + (' (part %s)' % job.result['subset'] if parts > 0 else ''))

def job_prune(job, s, repo):
  open_repository(job, s, repo, create=False)
  job.step('prune')
  forget(job, s, repo, prune=True)
  job.step('snapshots')
  sync_snapshots(job, repo)
  job.step('stats')
  repo_stats(job, repo)

# Restore staging: /restore/<id> belongs to root and only lets others pass; vmail and sogo get their own subtrees
def make_dir(path, uid=0, gid=0, mode=0o711):
  os.mkdir(path, mode)
  os.chown(path, uid, gid, follow_symlinks=False)
  os.chmod(path, mode)

def component_error(text):
  return {'added': 0, 'skipped': 0, 'errors': [text]}

def free_space(job, path, need, what):
  free = shutil.disk_usage(path).free
  if need + space_reserve > free:
    raise JobError('not enough free disk space for %s: %s needed, %s free' % (what, mib(need + space_reserve), mib(free)))

# Sizes of the files below the given directories of a snapshot (bytes per directory); listed directories that do not
# exist are missing in the result
def snapshot_sizes(job, repo, snap_id, dirs):
  sizes = {}
  if not dirs:
    return sizes
  wanted = set(dirs)

  def on_line(line):
    try:
      node = json.loads(line)
    except ValueError:
      return
    path = node.get('path') if isinstance(node, dict) else None
    if not isinstance(path, str):
      return
    # the directory given on the command line the path is in
    parts = path.split('/')
    for depth in range(len(parts), 1, -1):
      prefix = '/'.join(parts[:depth])
      if prefix in wanted:
        sizes[prefix] = sizes.get(prefix, 0) + (num(node.get('size')) if node.get('type') == 'file' else 0)
        break

  rc, _, err = restic(job, repo, ['ls', '--json', '--recursive', snap_id] + dirs, on_line=on_line)
  if rc != 0:
    raise RepoError('the snapshot could not be listed: ' + restic_error(rc, err))
  return sizes

# Names of the entries of a directory of a snapshot
def snapshot_names(job, repo, snap_id, folder):
  names = set()

  def on_line(line):
    try:
      node = json.loads(line)
    except ValueError:
      return
    path = node.get('path') if isinstance(node, dict) else None
    if isinstance(path, str) and path.startswith(folder + '/'):
      names.add(path[len(folder) + 1:].split('/')[0])

  rc, _, err = restic(job, repo, ['ls', '--json', snap_id, folder], on_line=on_line)
  if rc != 0:
    raise RepoError('the snapshot could not be listed: ' + restic_error(rc, err))
  return names

def restic_restore(job, repo, snap_id, target, includes, need, what):
  free_space(job, restore_dir, need, what)
  args = []
  for include in includes:
    args += ['--include', include]
  rc, _, err = restic(job, repo, ['restore', snap_id, '--target', target] + args, capture=False)
  if rc != 0:
    raise RepoError('the files could not be restored: ' + restic_error(rc, err))

# The exports of the domains (password hashes): read into memory, the files are deleted at once
def load_exports(job, repo, snap_id, target, domains):
  paths = ['/mailcow/export/%s.json' % d for d in domains]
  sizes = snapshot_sizes(job, repo, snap_id, ['/mailcow/export'])
  folder = target + '/export'
  make_dir(folder, mode=0o700)
  exports = {}
  try:
    restic_restore(job, repo, snap_id, folder, [glob_escape(p) for p in paths], sizes.get('/mailcow/export', 0) * 2,
      'the export of the snapshot')
    for domain, path in zip(domains, paths):
      try:
        data = json.loads(read_private(folder + path))
        exports[domain] = data if isinstance(data, dict) else None
      except (OSError, ValueError):
        exports[domain] = None
      if exports[domain]:
        section_aliases(exports[domain])
  finally:
    shutil.rmtree(folder, ignore_errors=True)
  return exports

# The aliases naming the mailboxes come once with the first page of an export: into the sections, as backup.php expects them
def section_aliases(export):
  sections = export.get('mailboxes') if isinstance(export.get('mailboxes'), dict) else {}
  for username, rows in (export.pop('mailbox_aliases', None) or {}).items():
    if isinstance(sections.get(username), dict) and isinstance(rows, list):
      sections[username]['aliases'] = (sections[username].get('aliases') or []) + rows

# The domain (with alias domains, DKIM and its settings) before its mailboxes; without it the restore fails
def restore_domain(job, domain, export, mailboxes, results):
  data = (export or {}).get('domain_data')
  if not isinstance(data, dict):
    job.result['domain'] = component_error('the snapshot has no data of the domain')
  else:
    try:
      job.result['domain'] = post({'action': 'restore_domain', 'job': job.id, 'data': data}).get('domain') or \
        component_error('no result')
    except Exception as e:
      job.result['domain'] = component_error('the restore request failed: %s' % http_error(e))
  if query("SELECT 1 FROM domain WHERE domain = %s", (domain,)).fetchone():
    job.log('domain %s: %s' % (domain, 'created' if job.result['domain'].get('created') else 'exists'))
    return
  for username in mailboxes:
    results[username] = {'error': 'the domain could not be created'}
  job.save()
  raise JobError('the domain %s could not be created: %s' % (domain, '; '.join(job.result['domain'].get('errors') or [])))

# Aliases of the domain (scope domain), in chunks; the own alias of a mailbox comes with the mailbox
def restore_aliases(job, export):
  mailboxes = (export or {}).get('mailboxes') or {}
  aliases = [a for a in (export or {}).get('aliases') or [] if not (isinstance(a, dict) and str(a.get('address') or '').lower() in mailboxes)]
  total = {'added': 0, 'skipped': 0, 'errors': []}
  for i in range(0, len(aliases), alias_chunk):
    check_cancel()
    try:
      part = post({'action': 'restore_aliases', 'job': job.id, 'aliases': aliases[i:i + alias_chunk]}).get('aliases') or {}
    except Exception as e:
      part = component_error('the restore request failed: %s' % http_error(e))
    total['added'] += num(part.get('added'))
    total['skipped'] += num(part.get('skipped'))
    total['errors'] += [str(e) for e in part.get('errors') or []]
  job.result['aliases'] = total
  job.save()
  job.log('aliases of the domain: %d added, %d skipped, %d errors' % (total['added'], total['skipped'], len(total['errors'])))

# backup.php creates a missing mailbox and merges the settings of each mailbox of a batch
def restore_settings(job, batch, sections, components, results):
  create = set(job.params.get('create_mailboxes') or [])
  for username in batch:
    result = results[username]
    if result.get('error') or not (components or username in create):
      continue
    check_cancel()
    section = sections.get(username)
    if not isinstance(section, dict):
      for component in components:
        result[component] = component_error('the snapshot has no export of this mailbox')
      if username in create:
        result['mailbox'] = component_error('the snapshot has no export of this mailbox, it cannot be created')
      job.log('%s: no export in the snapshot' % username)
      continue
    try:
      for component, value in post({'action': 'restore', 'job': job.id, 'username': username, 'data': section}).items():
        result[component] = value
      job.log('%s: %s' % (username, 'created and settings restored' if (result.get('mailbox') or {}).get('created')
        else 'settings restored'))
    except Exception as e:
      for component in components:
        result[component] = component_error('the restore request failed: %s' % http_error(e))
      job.log('%s: the restore request failed: %s' % (username, http_error(e)))
    job.save()

# sogo_restore.py resets calendars, address books and SOGo preferences of each mailbox to the backup (sogo-tool
# restore in the sogo container through dockerapi)
def restore_sogo(job, sogo_dir, batch, results):
  for username in batch:
    check_cancel()
    db = connect()
    try:
      results[username]['sogo'] = sogo_restore.restore(db, sogo_dir, username,
        lambda text: job.log('%s: %s' % (username, text)))
    except Exception as e:
      results[username]['sogo'] = component_error('the SOGo restore failed: %s' % e)
    finally:
      db.close()
    job.save()

# sogo-tool backup files are world-readable and hold password hashes: only the sogo user reads them, no symlinks
def protect_sogo_files(folder):
  os.chown(folder, sogo_uid, sogo_uid, follow_symlinks=False)
  os.chmod(folder, 0o700)
  for name in os.listdir(folder):
    path = os.path.join(folder, name)
    mode = os.lstat(path).st_mode
    if stat.S_ISREG(mode):
      os.chown(path, sogo_uid, sogo_uid, follow_symlinks=False)
      os.chmod(path, 0o600)
    elif stat.S_ISDIR(mode):
      shutil.rmtree(path, ignore_errors=True)
    else:
      unlink(path)

# Hands a batch to the dovecot runner (step 'mails') and waits until it is done with it ('mails_done')
def handover(job, batch_dir, acl_dir, mailboxes):
  job.result['batch'] = {'dir': batch_dir, 'acl_dir': acl_dir, 'mailboxes': mailboxes}
  job.result['handover'] = int(time.time())
  job.save()
  query("UPDATE backup_job SET step = 'mails' WHERE id = %s AND state = 'running'", (job.id,))
  job.log('mails, IMAP ACLs and Sieve scripts of %d mailboxes are restored by the dovecot container' % len(mailboxes))
  started = time.monotonic()
  while True:
    sleep(5)
    tick()
    if stopping:
      # the dovecot runner finishes the batch, the job fails after the restart
      raise JobDetached()
    row = query("SELECT state, step, result FROM backup_job WHERE id = %s", (job.id,)).fetchone()
    if not row or row['state'] != 'running':
      raise JobGone()
    if row['step'] == 'mails_done':
      job.result = json.loads(row['result'] or '{}')
      break
    if row['step'] == 'mails' and time.monotonic() - started > pickup_timeout and query("""UPDATE backup_job SET step = 'files'
        WHERE id = %s AND step = 'mails'""", (job.id,)).rowcount == 1:
      raise JobError('the dovecot container did not take over the restore (ofelia job dovecot_restore_runner)')
    if row['step'] == 'mails_running' and time.time() - restore_seen() > dovecot_timeout:
      raise JobError('the restore of the mails in the dovecot container stopped answering')
  for key in ('batch', 'handover'):
    job.result.pop(key, None)
  job.warnings = job.result.get('warnings') or job.warnings
  job.step('files')

def count_errors(result):
  count = sum(len(result.get(key, {}).get('errors') or []) for key in ('domain', 'aliases') if isinstance(result.get(key), dict))
  for entry in (result.get('mailboxes') or {}).values():
    count += int(bool(entry.get('error')))
    count += sum(len(value.get('errors') or []) for value in entry.values() if isinstance(value, dict))
  return count

# What restic restores for a batch: the home only when mails are restored, else only the files of the other
# components; returns (include patterns, bytes of the homes)
def batch_includes(ready, homes, components):
  includes, need = [], 0
  for username in ready:
    local, _, domain = username.rpartition('@')
    home = '/var/vmail/%s/%s' % (domain, local)
    if home in homes:
      if 'mails' in components:
        includes.append(glob_escape(home))
        need += homes[home]
      else:
        if 'imap_acl' in components:
          includes.append(glob_escape(home) + '/**/dovecot-acl')
        if components & {'filters', 'sieve_activate'}:
          includes.append(glob_escape(home) + '/sieve')
    if components & {'filters', 'sieve_activate'}:
      includes.append('/var/vmail/sieve/%s.sieve' % glob_escape(username))
    if 'sogo' in components:
      includes.append('/sogo_backup/%s' % glob_escape(username))
  return includes, need

# A restore: deleted domains and mailboxes are created by backup.php first, then the mailboxes are restored in
# batches: restic restores only what the components need into /restore/<id>/b<n>, backup.php merges the settings,
# sogo_restore.py the SOGo data, the dovecot runner mails, IMAP ACLs and Sieve scripts; the staging of a batch is
# deleted before the next one
def job_restore(job, s, repo):
  params = job.params
  snapshot = str(params.get('snapshot') or '')
  if not re.fullmatch(r'[0-9a-f]{8,64}', snapshot):
    raise JobError('invalid snapshot id')
  mailboxes = params.get('mailboxes') if isinstance(params.get('mailboxes'), list) else []
  create_domain = bool(params.get('create_domain'))
  # a deleted domain without mailboxes: domain data and aliases only
  if (not mailboxes and not create_domain) or not all(isinstance(m, str) and mailbox_valid(m) for m in mailboxes):
    raise JobError('no or invalid mailboxes')
  components = {c for c in params.get('components') or [] if isinstance(c, str)}
  if not components:
    raise JobError('no components to restore')
  domains = sorted({m.rpartition('@')[2] for m in mailboxes} or {str(params.get('domain') or '')})
  if len(domains) != 1 or not domain_re.fullmatch(domains[0]):
    raise JobError('invalid domain')
  allowed = params.get('allowed_domains') if isinstance(params.get('allowed_domains'), list) else None
  if allowed is not None and not all(isinstance(d, str) and domain_re.fullmatch(d) for d in allowed):
    raise JobError('invalid allowed domains')
  open_repository(job, s, repo, create=False)
  job.step('files')
  found = restic_json(job, repo, ['snapshots', '--json', snapshot]) or []
  if not found:
    raise JobError('snapshot %s not found' % snapshot)
  snap = found[0]
  snap_id = snap['id']
  if 'mode:config' in (snap.get('tags') or []) and components & mail_components:
    raise JobError('the snapshot holds no mails (RESTIC_BACKUP_MODE=config): ' + ', '.join(sorted(components & mail_components)))
  job.result['snapshot'] = {'id': snap_id, 'short_id': snap.get('short_id') or snap_id[:8],
    'time': (local_time(snap.get('time')) or '')[:16]}
  target = '%s/%d' % (restore_dir, job.id)
  shutil.rmtree(target, ignore_errors=True)
  make_dir(target)
  results = job.result['mailboxes'] = {m: {} for m in mailboxes}
  create = set(params.get('create_mailboxes') or [])
  scope_domain = params.get('scope') == 'domain'
  # the export also tells which mailboxes the snapshot has
  exports = load_exports(job, repo, snap_id, target, domains)
  sogo_files = snapshot_names(job, repo, snap_id, '/sogo_backup') if 'sogo' in components else set()
  sections = {}
  for domain in domains:
    sections.update((exports.get(domain) or {}).get('mailboxes') or {})
  job.save()
  if create_domain:
    job.step('domain')
    restore_domain(job, domains[0], exports.get(domains[0]), mailboxes, results)
  if scope_domain and 'aliases' in components:
    job.step('aliases')
    restore_aliases(job, exports.get(domains[0]))
  exports = None
  acl_dir = None
  if 'imap_acl' in components and mailboxes:
    # the other side: rights other mailboxes granted to the restored ones (domain admins: only their domains)
    acl_dir = target + '/acl'
    make_dir(acl_dir)
    owners = ['/var/vmail/%s/**/dovecot-acl' % glob_escape(d) for d in allowed] if allowed is not None \
      else ['/var/vmail/**/dovecot-acl']
    restic_restore(job, repo, snap_id, acl_dir, owners, 0, 'the IMAP ACLs')
  homes_needed = bool(components & {'mails', 'imap_acl', 'filters', 'sieve_activate'})
  batches = [mailboxes[i:i + restore_batch] for i in range(0, len(mailboxes), restore_batch)]
  for number, batch in enumerate(batches, 1):
    check_cancel()
    job.step('files')
    homes = {}
    if homes_needed:
      homes = snapshot_sizes(job, repo, snap_id, ['/var/vmail/%s/%s' % (m.rpartition('@')[2], m.rpartition('@')[0])
        for m in batch])
    for username in batch:
      local, _, domain = username.rpartition('@')
      # a mailbox that never got mail has no home yet, the export still knows it
      if '/var/vmail/%s/%s' % (domain, local) not in homes and username not in sections and username not in sogo_files:
        results[username] = {'error': 'the mailbox is not in the snapshot'}
        job.log('%s: not in the snapshot' % username)
    job.step('settings')
    restore_settings(job, batch, sections, components & php_components, results)
    # mailboxes that exist now (created or not) get the rest
    live = {row['username'] for row in query("SELECT username FROM mailbox WHERE username IN (%s)" % ','.join(['%s'] * len(batch)),
      tuple(batch))}
    ready = []
    for username in batch:
      if results[username].get('error'):
        continue
      if username not in live:
        results[username]['error'] = 'the mailbox does not exist' + (' and could not be created' if username in create else '')
        continue
      ready.append(username)
    job.save()
    batch_dir = '%s/b%d' % (target, number)
    includes, need = batch_includes(ready, homes, components)
    if includes:
      make_dir(batch_dir)
      job.step('files')
      started = time.monotonic()
      restic_restore(job, repo, snap_id, batch_dir, includes, need, 'the files of the restore')
      job.log('files of %d mailboxes restored in %d s (batch %d of %d)' % (len(ready), time.monotonic() - started, number,
        len(batches)))
    detached = False
    try:
      if 'sogo' in components and ready:
        job.step('sogo')
        sogo_dir = batch_dir + '/sogo_backup'
        if os.path.isdir(sogo_dir) and not os.path.islink(sogo_dir):
          protect_sogo_files(sogo_dir)
        restore_sogo(job, sogo_dir, ready, results)
        shutil.rmtree(sogo_dir, ignore_errors=True)
      if components & dovecot_components and ready:
        job.result['errors'] = count_errors(job.result)
        handover(job, batch_dir, acl_dir, ready)
        results = job.result['mailboxes']
    except JobDetached:
      detached = True
      raise
    finally:
      # the dovecot runner may still read the batch of a detached job
      if not detached:
        shutil.rmtree(batch_dir, ignore_errors=True)
    job.result['errors'] = count_errors(job.result)
    job.save()
  shutil.rmtree(target, ignore_errors=True)
  job.result['errors'] = count_errors(job.result)
  if results and not any(not entry.get('error') for entry in results.values()):
    raise JobError('none of the mailboxes could be restored')

handlers = {'backup': job_backup, 'init': job_init, 'check': job_check, 'prune': job_prune, 'restore': job_restore}

# Mail to notify_email through postfix (internal network, port 588)
def append_log(job_id, text):
  print('backup job %d: %s' % (job_id, text), flush=True)
  query("UPDATE backup_job SET log = RIGHT(CONCAT(IFNULL(log, ''), %s), %s) WHERE id = %s",
    ('%s %s\n' % (time.strftime('%Y-%m-%d %H:%M:%S'), text), max_log, job_id))

# Mail through postfix (internal network, port 588) to notify_email or the given addresses; refused recipients and
# errors go to the container log and the log of the job
def notify(s, subject, text, job_id=None, addresses=None):
  if addresses is None:
    addresses = s['notify_email'].split(',')
  rcpts = [r.strip() for r in addresses if re.fullmatch(r'[^@\s<>,]+@[^@\s<>,]+', str(r).strip())]
  if not rcpts:
    return
  msg = EmailMessage()
  msg['From'] = 'backup@' + hostname
  msg['To'] = ', '.join(rcpts)
  msg['Subject'] = 'mailcow backup on %s: %s' % (hostname, subject)
  msg['Date'] = formatdate(localtime=True)
  msg['Message-ID'] = make_msgid(domain=hostname)
  msg.set_content(text + ('\n\nThe job log is in the backup section of the mailcow UI.\n' if job_id else '\n'))
  problem = None
  try:
    with smtplib.SMTP('postfix', 588, hostname, timeout=60) as smtp:
      refused = smtp.send_message(msg)
    if refused:
      problem = 'the notification was refused for %s' % ', '.join('%s (%s %s)' % (r, code, detail.decode('utf-8', 'replace')
        if isinstance(detail, bytes) else detail) for r, (code, detail) in refused.items())
  except smtplib.SMTPRecipientsRefused as e:
    problem = 'the notification was refused for %s' % ', '.join(e.recipients)
  except (OSError, smtplib.SMTPException) as e:
    problem = 'the notification could not be sent: %s' % e
  if problem:
    if job_id:
      try:
        append_log(job_id, problem)
      except MySQLdb.Error:
        pass
    else:
      print('backup runner: %s: %s' % (subject, problem), file=sys.stderr, flush=True)

def notify_failed(s, job_id, job_type, error):
  notify(s, '%s job %d failed' % (job_type, job_id), 'The %s job %d failed:\n\n%s' % (job_type, job_id, error), job_id)

def queue(job_type, params=None):
  query("INSERT INTO backup_job (type, params, requested_by) VALUES (%s, %s, 'schedule')",
    (job_type, json.dumps(params or {})))

def finish(job, state, error):
  try:
    job.save()
  except MySQLdb.Error as e:
    # the state matters more than the result
    print('backup job %d: the result could not be saved: %s' % (job.id, e), file=sys.stderr)
  query("""UPDATE backup_job SET state = IF(state = 'cancelled', state, %s), error = %s, finished = NOW(),
    step = IF(%s = 'done', NULL, step) WHERE id = %s""", (state, error, state, job.id))
  job.log('%s job %s%s' % (job.type, state, ': ' + error if error else ''))

def run_job(row):
  job = Job(row)
  current.update(job=job, interrupt=None)
  s = settings()
  state, error = 'done', None
  try:
    job.log('%s job started (requested by %s)' % (job.type, job.requested_by or 'unknown'))
    repo = Repository(s)
    repo.prepare(s)
    handlers[job.type](job, s, repo)
    repo.remember_host(s)
  except JobGone:
    state = None
  except JobDetached:
    # the dovecot runner still imports the batch: its staging stays, the job fails after the restart (fail_orphans)
    current['job'] = None
    return
  except JobError as e:
    state, error = 'failed', str(e)[:2000]
    if isinstance(e, RepoError) and job.restricted:
      print('backup job %d: %s' % (job.id, error), file=sys.stderr, flush=True)
      error = repo_error_generic
  except MySQLdb.Error as e:
    state, error = 'failed', 'database error: %s' % e
  except Exception as e:
    traceback.print_exc()
    state, error = 'failed', 'internal error: %s' % e
  finally:
    current['job'] = None
  if job.type == 'restore':
    shutil.rmtree('%s/%d' % (restore_dir, job.id), ignore_errors=True)
  if state is None:
    # failed by the dovecot runner, which also sent the notification
    return
  if current['interrupt'] == 'cancelled':
    state, error = 'cancelled', None
  finish(job, state, error)
  if state == 'failed':
    notify_failed(s, job.id, job.type, error)
  elif state == 'done' and job.warnings and job.type == 'backup':
    notify(s, 'backup job %d finished with warnings' % job.id, 'The backup job %d finished with warnings:\n\n%s' % (
      job.id, '\n'.join('- ' + w for w in job.warnings)), job.id)
  if state == 'done' and job.type == 'backup' and job.requested_by == 'schedule':
    after_scheduled_backup(s)

# After the first scheduled backup of a day: prune once a week from the prune weekday on (0 = Sunday, '' = never), then
# check the next part of the data
def after_scheduled_backup(s):
  today = datetime.datetime.now().replace(hour=0, minute=0, second=0, microsecond=0)
  weekday = s['prune_weekday'].strip()
  if weekday.isdigit() and not scheduled_since('prune', today - datetime.timedelta(days=(weekday_of(today) - int(weekday)) % 7)):
    queue('prune')
  if num(s['check_subset']) > 0 and not scheduled_since('check', today):
    queue('check')

def scheduled_since(job_type, since):
  return query("""SELECT 1 FROM backup_job WHERE type = %s AND requested_by = 'schedule' AND created >= %s LIMIT 1""",
    (job_type, since.strftime('%Y-%m-%d %H:%M:%S'))).fetchone() is not None

# 0 = Sunday, as prune_weekday and the days of the schedule
def weekday_of(day):
  return (day.weekday() + 1) % 7

# The schedule (JSON list of {days: [0 = Sunday ...], time: "HH:MM"}) as sorted (weekday, hour, minute) slots
def schedule_slots(s):
  try:
    entries = json.loads(s['schedule'])
  except ValueError:
    return []
  slots = set()
  for entry in entries if isinstance(entries, list) else []:
    m = re.fullmatch(r'(\d{1,2}):(\d{2})', str(entry.get('time', '')) if isinstance(entry, dict) else '')
    if not m or int(m.group(1)) > 23 or int(m.group(2)) > 59 or not isinstance(entry.get('days'), list):
      continue
    slots |= {(day, int(m.group(1)), int(m.group(2))) for day in entry['days'] if isinstance(day, int) and 0 <= day <= 6}
  return sorted(slots)

# The newest schedule time up to now, None without slots
def last_slot(slots, now):
  for back in range(8):
    day = now - datetime.timedelta(days=back)
    for weekday, hour, minute in reversed(slots):
      if weekday == weekday_of(day):
        slot = day.replace(hour=hour, minute=minute, second=0, microsecond=0)
        if slot <= now:
          return slot
  return None

# The longest time between two schedule times of a week (seconds), None without slots
def schedule_gap(slots):
  times = [day * 86400 + hour * 3600 + minute * 60 for day, hour, minute in slots]
  if not times:
    return None
  return max([b - a for a, b in zip(times, times[1:])] + [times[0] + 7 * 86400 - times[-1]])

# Scheduled backup: enabled, password confirmed, target complete, a schedule time passed since the schedule was
# switched on (or changed) and no scheduled backup since that time
def schedule_due(s):
  if s['enabled'] != '1' or s['password_confirmed'] != '1':
    if s.get('state_schedule_since'):
      set_setting('state_schedule_since', '')
    return False
  try:
    Repository(s)
  except JobError:
    return False
  slots = schedule_slots(s)
  if not slots:
    return False
  now = datetime.datetime.now()
  if not s.get('state_schedule_since') or s.get('state_schedule') != s['schedule']:
    set_setting('state_schedule_since', int(now.timestamp()))
    set_setting('state_schedule', s['schedule'])
    return False
  due = last_slot(slots, now)
  if due is None or num(s['state_schedule_since']) > due.timestamp():
    return False
  return not scheduled_since('backup', due)

# A mail when backups are on but the last success is older than the longest gap of the schedule plus stale_margin
# (36 hours for one backup a day), once a day
def check_last_success(s):
  if s['enabled'] != '1':
    return
  limit = (schedule_gap(schedule_slots(s)) or 86400) + stale_margin
  last = num(s.get('state_last_success')) or num(s.get('state_schedule_since'))
  today = time.strftime('%Y-%m-%d')
  if not last or time.time() - last < limit or s.get('state_stale_notified') == today:
    return
  set_setting('state_stale_notified', today)
  notify(s, 'no successful backup for %d hours' % ((time.time() - last) // 3600),
    'Backups are enabled, but the last successful backup is older than %d hours.' % (limit // 3600))

# Target and connection values of settings, without secrets
def target_summary(values):
  fields = {'s3': ('s3_endpoint', 's3_bucket', 's3_path', 's3_region'), 'sftp': ('sftp_host', 'sftp_port', 'sftp_user', 'sftp_path'),
    'rest': ('rest_url', 'rest_user'), 'local': ('local_path',)}
  target = str(values.get('target') or '')
  return target + ''.join(', %s=%s' % (key, values.get(key)) for key in fields.get(target, ()) if values.get(key) not in (None, ''))

# Mail to the notify addresses that were set before the target or its connection changed (backup.php stores them
# with the change); then the marker is removed, unless it changed meanwhile
def target_changed(s):
  marker = s['state_target_changed']
  try:
    change = json.loads(marker)
    if not isinstance(change, dict):
      raise ValueError()
  except ValueError:
    query("UPDATE backup_setting SET value = '' WHERE name = 'state_target_changed' AND value = %s", (marker,))
    return
  addresses = change.get('old_addresses') or []
  if isinstance(addresses, str):
    addresses = addresses.split(',')
  when = change.get('time')
  when = time.strftime('%Y-%m-%d %H:%M', time.localtime(int(when))) if str(when).isdigit() else str(when or '')
  changed = change.get('changed') if isinstance(change.get('changed'), list) else []
  old = change.get('old_target')
  notify(s, 'backup settings changed', 'The backup settings of mailcow on %s were changed%s%s%s.\n\nTarget before: %s\n'
    'Target now: %s\nNotification addresses now: %s\n\nIf you did not make this change, check the backup settings and '
    'the API keys of mailcow.' % (hostname, ' by %s' % change['changed_by'] if change.get('changed_by') else '',
    ' at %s' % when if when else '', ' (%s)' % ', '.join(str(c) for c in changed) if changed else '',
    target_summary(old) if isinstance(old, dict) else str(old or 'unknown'), target_summary(s), s['notify_email'] or 'none'),
    addresses=addresses)
  query("UPDATE backup_setting SET value = '' WHERE name = 'state_target_changed' AND value = %s", (marker,))

# Restore staging of jobs that do not run anymore (finished, failed, crashed or deleted)
def clean_restore():
  job = current['job']
  for name in os.listdir(restore_dir):
    if not name.isdigit() or (job and job.id == int(name)):
      continue
    row = query("SELECT state FROM backup_job WHERE id = %s", (int(name),)).fetchone()
    if row and row['state'] == 'running':
      continue
    shutil.rmtree(os.path.join(restore_dir, name), ignore_errors=True)

# Restores waiting for a runner that no longer waits (restart): a batch the dovecot runner finished, did not take or
# stopped working on
def fail_orphans(s):
  seen = num(s.get('state_restore_seen'))
  for row in query("""SELECT id, step FROM backup_job WHERE type = 'restore' AND state = 'running'
      AND step IN ('mails', 'mails_running', 'mails_done')""").fetchall():
    if current['job'] and current['job'].id == row['id']:
      continue
    if row['step'] == 'mails_running' and time.time() - seen < dovecot_timeout:
      continue
    error = 'interrupted: the backup runner was restarted'
    if query("UPDATE backup_job SET state = 'failed', error = %s, finished = NOW() WHERE id = %s AND state = 'running'",
        (error, row['id'])).rowcount == 1:
      notify_failed(s, row['id'], 'restore', error)

# After a restart: jobs this runner was running are lost; a scheduled backup is queued again once
def recover():
  stale = query("""SELECT id, type, params, requested_by FROM backup_job WHERE state = 'running'
    AND (type <> 'restore' OR step IS NULL OR step <> 'mails_running')""").fetchall()
  s = settings()
  for row in stale:
    error = 'interrupted: the backup runner was restarted'
    query("UPDATE backup_job SET state = 'failed', error = %s, finished = NOW() WHERE id = %s AND state = 'running'",
      (error, row['id']))
    notify_failed(s, row['id'], row['type'], error)
    try:
      retry = bool(json.loads(row['params'] or '{}').get('retry_of'))
    except (ValueError, AttributeError):
      retry = True
    if row['type'] == 'backup' and row['requested_by'] == 'schedule' and not retry:
      queue('backup', {'retry_of': row['id']})
  clean_restore()
  clean_staging()

def ssh_setup():
  os.makedirs(ssh_dir, mode=0o700, exist_ok=True)
  if not os.path.exists(ssh_key):
    subprocess.run(['ssh-keygen', '-q', '-t', 'ed25519', '-N', '', '-C', 'mailcow-backup@' + hostname, '-f', ssh_key],
      check=True, stdin=subprocess.DEVNULL)
  if not os.path.exists(ssh_key + '.pub'):
    public = subprocess.run(['ssh-keygen', '-y', '-f', ssh_key], check=True, capture_output=True, text=True).stdout
    with open(ssh_key + '.pub', 'w') as f:
      f.write(public)
  with open(ssh_key + '.pub') as f:
    return f.read().strip()

def stop(signum, frame):
  global stopping
  stopping = True
  proc = current['proc']
  if proc and proc.poll() is None:
    current['interrupt'] = current['interrupt'] or 'stopped'
    try:
      proc.send_signal(signal.SIGINT)
    except OSError:
      pass

def sleep(seconds):
  end = time.monotonic() + seconds
  while not stopping and time.monotonic() < end:
    time.sleep(min(1, end - time.monotonic()))

def main():
  global conn
  # dumps, exports and keys are for root only
  os.umask(0o077)
  lock_fd = os.open(lock_file, os.O_RDWR | os.O_CREAT, 0o600)
  try:
    fcntl.flock(lock_fd, fcntl.LOCK_EX | fcntl.LOCK_NB)
  except OSError:
    sys.exit("backup runner: can't lock " + lock_file)
  for sig in (signal.SIGINT, signal.SIGTERM, signal.SIGQUIT):
    signal.signal(sig, stop)
  if os.environ.get('MASTER', 'y') != 'y':
    print('backup runner: MASTER is not y, idle', flush=True)
    while not stopping:
      sleep(3600)
    return
  os.makedirs(cache_dir, mode=0o700, exist_ok=True)
  # /restore and /restore/<id>: only passable for vmail and sogo, the subtrees decide who reads them
  os.chown(restore_dir, 0, 0)
  os.chmod(restore_dir, 0o711)
  # marks the folder of the local repository, also when it lies inside the mailcow directory
  try:
    marker = repository_root + '/' + repo_marker
    content = (marker_header() + '\n').encode()
    if not os.path.exists(marker) or read_private(marker) != content:
      write_private(marker, content)
  except OSError as e:
    print('backup runner: %s: %s' % (repository_root, e), file=sys.stderr)
  public_key = ssh_setup()
  secrets_key = secrets_setup()
  secrets_public = base64.b64encode(bytes(secrets_key.public_key)).decode()
  print('backup runner: started (restic %s, %s)' % (restic_version(), instance_tag()), flush=True)
  Heartbeat().start()
  recovered, last_housekeeping = False, 0
  while not stopping:
    try:
      if not recovered:
        if settings().get('state_secrets_pubkey') != secrets_public:
          set_setting('state_secrets_pubkey', secrets_public)
        recover()
        recovered = True
      tick()
      s = settings()
      if s['sftp_public_key'] != public_key:
        set_setting('sftp_public_key', public_key)
      if s.get('_secrets_error', '') != s.get('state_secrets_error', ''):
        set_setting('state_secrets_error', s.get('_secrets_error', ''))
      if s.get('state_backup_mode', '') != configured_mode():
        set_setting('state_backup_mode', configured_mode())
      if s.get('state_target_changed'):
        target_changed(s)
      fail_orphans(s)
      if time.monotonic() - last_housekeeping >= 3600:
        last_housekeeping = time.monotonic()
        query("""DELETE FROM backup_job WHERE state IN ('done', 'failed', 'cancelled')
          AND IFNULL(finished, created) < NOW() - INTERVAL %s DAY""", (keep_jobs_days,))
        check_last_success(s)
      if schedule_due(s):
        queue('backup')
      row = query("""SELECT id, type, params, requested_by FROM backup_job WHERE state = 'queued'
        ORDER BY id LIMIT 1""").fetchone()
      if row and query("UPDATE backup_job SET state = 'running', started = NOW() WHERE id = %s AND state = 'queued'",
          (row['id'],)).rowcount == 1:
        run_job(row)
        continue
    except MySQLdb.Error as e:
      print('backup runner: database error: %s' % e, file=sys.stderr, flush=True)
      conn = None
    except Exception:
      traceback.print_exc()
    sleep(poll_interval)

# Command line for update.sh: --configured (exit 0 when backups are set up, 2 when they are not, 1 on errors such as
# credentials this container cannot open), --backup (queue a backup, wait for it and print its log; exit 0 when it is
# done)
def cli(argv):
  global conn
  conn = connect()
  s = settings()
  reason = None
  if s.get('_secrets_error'):
    print(s['_secrets_error'], file=sys.stderr)
    return 1
  try:
    backup_mode()
  except JobError as e:
    print(e, file=sys.stderr)
    return 1
  try:
    repo = Repository(s)
    if s['password_confirmed'] != '1':
      reason = 'the repository password is not confirmed'
    elif s.get('state_initialized') != '1' or s.get('state_repository') != repo.name:
      reason = 'the repository is not initialized'
  except JobError as e:
    reason = str(e)
  if argv[1] == '--configured':
    print(reason or 'backups are set up')
    return 0 if reason is None else 2
  if argv[1] != '--backup':
    print('usage: %s --configured | --backup' % argv[0], file=sys.stderr)
    return 2
  if reason:
    print('no backup: %s' % reason, file=sys.stderr)
    return 2
  if time.time() - num(s.get('state_runner_seen')) > 120:
    print('no backup: the backup runner does not run', file=sys.stderr)
    return 2
  job_id = query("INSERT INTO backup_job (type, params, requested_by) VALUES ('backup', '{}', 'update')").lastrowid
  print('backup job %d queued' % job_id, flush=True)
  printed, waiting = 0, None
  try:
    while True:
      row = query("SELECT state, error, log, result FROM backup_job WHERE id = %s", (job_id,)).fetchone()
      if not row:
        print('the backup job was deleted', file=sys.stderr)
        return 1
      if row['state'] == 'queued':
        other = query("SELECT id, type FROM backup_job WHERE state = 'running' ORDER BY id LIMIT 1").fetchone()
        if other and other['id'] != waiting:
          waiting = other['id']
          print('waiting for the running %s job %d' % (other['type'], other['id']), flush=True)
      log = row['log'] or ''
      if len(log) > printed:
        print(log[printed:], end='', flush=True)
        printed = len(log)
      if row['state'] in ('done', 'failed', 'cancelled'):
        break
      time.sleep(2)
  except KeyboardInterrupt:
    print('\nthe backup job %d goes on in the background' % job_id, file=sys.stderr)
    return 130
  if row['state'] == 'done':
    result = json.loads(row['result'] or '{}')
    print('backup done: snapshot %s%s' % ((result.get('snapshot') or '')[:8],
      ', %d warnings' % len(result['warnings']) if result.get('warnings') else ''))
    return 0
  print('backup %s%s' % (row['state'], ': ' + row['error'] if row['error'] else ''), file=sys.stderr)
  return 1

if __name__ == '__main__':
  sys.exit(cli(sys.argv)) if len(sys.argv) > 1 else main()
