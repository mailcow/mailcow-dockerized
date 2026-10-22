#!/usr/bin/python3

import fcntl
import json
import os
import re
import signal
import subprocess
import sys
import tempfile
import time
import MySQLdb
import MySQLdb.cursors

run_dir = '/tmp'
# Max size of imapsync output stored in returned_text (the tail holds the summary)
max_output = 16 * 1024 * 1024
lock_file = run_dir + '/imapsync_busy.lock'
# An OAuth run needs a token valid this long at start (half the lifetime for short-lived tokens)
token_min_valid = 900
# Refreshed tokens are written to the token files of running jobs this often, imapsync re-reads them on reconnect
token_sync_interval = 60

# Next due job: higher prio first, never-run jobs first, then oldest run
next_sql = """SELECT i.id, i.user1, i.user2, i.password1, i.exclude, i.delete2duplicates, i.maxage,
  i.subfolder2, i.delete1, i.delete2, i.automap, i.skipcrossduplicates, i.maxbytespersecond, i.custom_params,
  i.subscribeall, i.timeout1, i.timeout2, i.dry, i.source_id,
  s.host1, s.port1, s.enc1, s.auth_type, s.oauth_flow
    FROM imapsync i JOIN imapsync_source s ON i.source_id = s.id
      WHERE i.active = 1
        AND s.active = 1
        AND i.is_running = 0
        AND (UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(i.last_run) > i.mins_interval * 60 OR i.last_run IS NULL)
        {skip}
  ORDER BY i.prio DESC, i.last_run IS NOT NULL, i.last_run ASC, i.id ASC
  LIMIT 1"""
# Atomic claim, a job is only started when this flips is_running 0 -> 1
claim_sql = """UPDATE imapsync SET is_running = 1, success = NULL, exit_status = NULL
  WHERE id = %s AND is_running = 0 AND active = 1"""
# Access token: client_credentials share the source token, authorization_code use the token of
# source + local mailbox (user2) + remote user (user1). issued/error describe the last refresh.
source_token_sql = """SELECT oauth_access_token AS access_token, oauth_token_expires AS token_expires,
  UNIX_TIMESTAMP(oauth_last_refresh_attempt) AS issued, oauth_last_refresh_error AS error
  FROM imapsync_source WHERE id = %s"""
user_token_sql = """SELECT access_token, token_expires, UNIX_TIMESTAMP(oauth_last_refresh_attempt) AS issued,
  last_refresh_error AS error FROM imapsync_source_oauth_token
  WHERE source_id = %s AND owner = %s AND username = %s"""

conn = None
# Popen => job of running workers
running = {}
# Jobs released without a run (token not ready), skipped for the rest of this runner round
deferred = set()
stopping = False

def num(value):
  try:
    return int(value)
  except (TypeError, ValueError):
    return 0

def unlink(files):
  for path in files:
    try:
      os.unlink(path)
    except OSError:
      pass

def imapsync_running():
  for pid in filter(str.isdigit, os.listdir('/proc')):
    try:
      with open('/proc/' + pid + '/cmdline', 'rb') as f:
        cmdline = f.read().replace(b'\0', b' ')
    except OSError:
      continue
    if re.search(rb'imapsync\s', cmdline, re.I):
      return True
  return False

def connect():
  return MySQLdb.connect(unix_socket='/var/run/mysqld/mysqld.sock', user=os.environ['DBUSER'],
    passwd=os.environ['DBPASS'], db=os.environ['DBNAME'], charset='utf8mb4', autocommit=True)

# Reconnect when the server dropped the connection (syncs can outlast wait_timeout)
def query(sql, args=None):
  global conn
  try:
    conn.ping()
  except MySQLdb.Error:
    conn = connect()
  cur = conn.cursor(MySQLdb.cursors.DictCursor)
  cur.execute(sql, args)
  return cur

# Store a result and release the job
def finish_job(job_id, text, success, exit_status):
  try:
    query("UPDATE imapsync SET returned_text = %s, success = %s, exit_status = %s, last_run = NOW(), is_running = 0 WHERE id = %s",
      (text, success, exit_status, job_id))
  except MySQLdb.Error:
    query("UPDATE imapsync SET success = 0, last_run = NOW(), is_running = 0 WHERE id = %s", (job_id,))

# Release a job without a run; last_run stays, so the next runner round retries it
def defer_job(job_id, text, exit_status):
  query("UPDATE imapsync SET returned_text = %s, success = 0, exit_status = %s, is_running = 0 WHERE id = %s",
    (text, exit_status, job_id))
  deferred.add(job_id)

def oauth_token(j):
  if j['oauth_flow'] == 'authorization_code':
    return query(user_token_sql, (j['source_id'], j['user2'], j['user1'])).fetchone() or {}
  return query(source_token_sql, (j['source_id'],)).fetchone() or {}

# Valid for token_min_valid, or half the lifetime of a short-lived token (known when its last refresh succeeded)
def token_ready(row):
  token, expires, issued = row.get('access_token'), row.get('token_expires'), row.get('issued')
  if not token or expires is None:
    return False
  need = token_min_valid
  if issued and not row.get('error') and expires > int(issued):
    need = min(need, (expires - int(issued)) // 2)
  return expires - time.time() >= need

# Write a refreshed token over the token file of a running job (atomic, same path)
def sync_token_file(oauth):
  token = oauth_token(oauth['job']).get('access_token')
  if not token or token == oauth['token']:
    return
  fd, tmp = tempfile.mkstemp(prefix='imapsync.', dir=run_dir)
  try:
    with os.fdopen(fd, 'w', encoding='utf-8') as f:
      f.write(token + '\n')
    os.replace(tmp, oauth['file'])
    oauth['token'] = token
  except OSError:
    unlink([tmp])

# Max concurrent imapsync processes and global per-process bandwidth cap in bytes/s (0 = off),
# re-read before every claim round so changes apply to long runs
def read_settings():
  settings = {r['name']: r['value'] for r in query("SELECT name, value FROM imapsync_settings")}
  return max(num(settings.get('max_parallel')), 1), max(num(settings.get('max_bytes_per_second')), 0)

# Build temp files and argv for a claimed job. Returns None when the job was finished without running imapsync.
def prepare_job(j, global_bw, files):
  def tmpfile(content):
    fd, path = tempfile.mkstemp(prefix='imapsync.', dir=run_dir)
    files.append(path)
    with os.fdopen(fd, 'w', encoding='utf-8') as f:
      f.write(content)
    return path

  auth_type = j['auth_type'] or 'PLAIN'
  oauth = None
  if auth_type == 'XOAUTH2':
    row = oauth_token(j)
    if not token_ready(row):
      defer_job(j['id'], "OAuth access token for the configured source is missing or expires too soon; refresh cron will retry.",
        'OAUTH_TOKEN_NOT_READY')
      return None
    oauth = {'job': j, 'token': row['access_token'], 'file': tmpfile(row['access_token'] + '\n')}
    auth_args = ['--authmech1', 'XOAUTH2', '--oauthaccesstoken1', oauth['file']]
  else:
    auth_args = ['--passfile1', tmpfile(j['password1'] + '\n')]
    # imapsync auto-picks PLAIN, only pass --authmech1 for LOGIN/CRAM-MD5
    if auth_type != 'PLAIN':
      auth_args += ['--authmech1', auth_type]

  # Custom params: JSON array of {o,v}, validated by the backend on write. One argv token per pair,
  # "--opt" or "--opt=value" (value glued to its option, no shell)
  custom_params = []
  try:
    pairs = json.loads(j['custom_params'] or '[]')
  except ValueError:
    pairs = []
  for p in pairs if isinstance(pairs, list) else []:
    if not isinstance(p, dict) or not isinstance(p.get('o'), str) or not re.fullmatch(r'[a-z0-9]+', p['o']):
      continue
    value = '' if p.get('v') is None else str(p['v'])
    custom_params.append('--' + p['o'] if value == '' else '--' + p['o'] + '=' + value)

  # Effective per-process bandwidth cap (bytes/s): per-job value, capped by the global limit
  eff_bw = num(j['maxbytespersecond'])
  if global_bw > 0 and (eff_bw <= 0 or eff_bw > global_bw):
    eff_bw = global_bw

  # --nolog: the output is stored in returned_text, imapsync's own log dir would be the (read-only) cwd
  cmd = ['/usr/local/bin/imapsync', '--tmpdir', '/tmp', '--nolog', '--nofoldersizes', '--addheader']
  if num(j['timeout1']) > 0:
    cmd += ['--timeout1', str(j['timeout1'])]
  if num(j['timeout2']) > 0:
    cmd += ['--timeout2', str(j['timeout2'])]
  if j['exclude']:
    cmd += ['--exclude', j['exclude']]
  if j['subfolder2']:
    cmd += ['--subfolder2', j['subfolder2']]
  if num(j['maxage']) != 0:
    cmd += ['--maxage', str(j['maxage'])]
  if eff_bw > 0:
    cmd += ['--maxbytespersecond', str(eff_bw)]
  for col, opt in (('delete2duplicates', '--delete2duplicates'), ('subscribeall', '--subscribeall'),
      ('delete1', '--delete1'), ('delete2', '--delete2'), ('automap', '--automap'),
      ('skipcrossduplicates', '--skipcrossduplicates')):
    if j[col] == 1:
      cmd.append(opt)
  enc1 = {'TLS': '--tls1', 'SSL': '--ssl1'}.get(j['enc1'])
  if enc1:
    cmd.append(enc1)
  cmd += ['--host1', j['host1'], '--user1', j['user1']] + auth_args + ['--port1', str(j['port1'])]
  cmd += ['--host2', 'localhost', '--user2', j['user2'] + '*' + master_user, '--passfile2', tmpfile(master_pass + '\n')]
  if j['dry'] == 1:
    cmd.append('--dry')
  cmd += ['--no-modulesversion', '--noreleasecheck'] + custom_params

  return {'id': j['id'], 'files': files, 'outfile': tmpfile(''), 'cmd': cmd, 'oauth': oauth}

# Worker: imapsync with output to a file, no inherited fds (DB socket, lock)
def start_job(job):
  with open(job['outfile'], 'wb') as out:
    return subprocess.Popen(job['cmd'], stdin=subprocess.DEVNULL, stdout=out, stderr=subprocess.STDOUT)

# Store the result of a finished worker and remove its temp files
def reap_job(job):
  stdout = b''
  try:
    with open(job['outfile'], 'rb') as f:
      size = os.fstat(f.fileno()).st_size
      if size > max_output:
        f.seek(size - max_output)
        stdout = b'[output truncated, last %d bytes]\n' % max_output
      stdout += f.read()
  except OSError:
    pass
  unlink(job['files'])
  text = stdout.decode('utf-8', 'replace')
  # Last match: the echoed command line at the top may contain user controlled values
  m = re.findall(r'Exiting\swith\sreturn\svalue\s(\d+)\s\(([^:)]+)', text)
  exit_code, exit_status = m[-1] if m else (None, None)
  if exit_code is None:
    text = 'Could not start or finish imapsync\n' + text
  finish_job(job['id'], text, 1 if exit_code is not None and int(exit_code) == 0 else 0, exit_status)

# Stop claiming new jobs, pass the signal on to the running imapsync processes
def stop(signum, frame):
  global stopping
  stopping = True
  for proc in list(running):
    proc.send_signal(signal.SIGTERM)

# Released by the kernel when the process ends, a crashed run leaves no stale lock
lock_fd = os.open(lock_file, os.O_RDWR | os.O_CREAT, 0o600)
try:
  fcntl.flock(lock_fd, fcntl.LOCK_EX | fcntl.LOCK_NB)
except OSError:
  sys.exit("can't lock " + lock_file)

if imapsync_running():
  print('imapsync is active, exiting...')
  sys.exit(0)

for sig in (signal.SIGINT, signal.SIGTERM, signal.SIGQUIT):
  signal.signal(sig, stop)

conn = connect()
# Stale flags from crashed runs, safe only here: under the lock and with no imapsync running
query("UPDATE imapsync SET is_running = 0")

with open('/etc/sogo/sieve.creds') as f:
  master_user, master_pass = [s.strip() for s in f.readline().split(':', 1)]

# Worker pool: keep up to max_parallel workers busy, claim the next due job whenever a slot is free
# (after every worker exit and at least every 10 seconds while workers run)
last_token_sync = time.monotonic()
try:
  while True:
    for proc, job in list(running.items()):
      if proc.poll() is not None:
        del running[proc]
        reap_job(job)

    if time.monotonic() - last_token_sync >= token_sync_interval:
      last_token_sync = time.monotonic()
      for job in running.values():
        if job['oauth']:
          try:
            sync_token_file(job['oauth'])
          except MySQLdb.Error as e:
            print('imapsync job %d: %s' % (job['id'], e), file=sys.stderr)

    max_parallel, global_bw = read_settings()
    # Deferred and finished jobs drop out of next_sql, only failed claims are capped
    failed_claims = 0
    while not stopping and len(running) < max_parallel and failed_claims < 100:
      skip = ('AND i.id NOT IN (%s)' % ', '.join(['%s'] * len(deferred))) if deferred else ''
      j = query(next_sql.format(skip=skip), tuple(deferred) or None).fetchone()
      if not j:
        break
      if query(claim_sql, (j['id'],)).rowcount != 1:
        failed_claims += 1
        continue

      files = []
      try:
        job = prepare_job(j, global_bw, files)
      except Exception as e:
        print('imapsync job %d: %s' % (j['id'], e), file=sys.stderr)
        finish_job(j['id'], 'Could not start or finish imapsync', 0, None)
        job = None
      if not job:
        unlink(files)
        continue
      try:
        running[start_job(job)] = job
      except OSError as e:
        # Release the claim, the job is picked up again later
        print('imapsync job %d: %s' % (j['id'], e), file=sys.stderr)
        unlink(files)
        query("UPDATE imapsync SET is_running = 0 WHERE id = %s", (j['id'],))
        break

    if not running:
      break
    # A worker exit ends the wait early
    for _ in range(50):
      if any(proc.poll() is not None for proc in running):
        break
      time.sleep(0.2)
finally:
  # Never leave password/token files behind
  for job in running.values():
    unlink(job['files'])
