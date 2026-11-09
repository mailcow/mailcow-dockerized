#!/usr/bin/python3
# Mail part of restores (ofelia, dovecot container, as vmail): the restic container restores a batch of mailboxes
# into /restore/<job id>/b<n> and sets the job to step 'mails'. Per mailbox this imports the mails folder by folder
# (into "Restored <snapshot time>" or only the missing ones into the original folders), adds IMAP ACLs and missing
# Sieve scripts, merges the results into the job and sets step 'mails_done'; the restic runner then removes the staging
# and goes on with the next batch. Nothing that exists is changed or deleted.

import base64
import fcntl
import json
import os
import re
import smtplib
import stat
import subprocess
import sys
import threading
import time
from email.message import EmailMessage
from email.utils import formatdate, make_msgid
import MySQLdb
import MySQLdb.cursors

lock_file = '/tmp/restore_runner.lock'
restore_dir = '/restore'
max_log = 1000000
# import, moves and the removal of the temporary folder ignore the quota (a restore may need more than is free),
# the quota is recalculated afterwards
no_quota = ['-o', 'plugin/quota=']
# uid ranges per import (one argument stays far below the 128 KiB limit), folders per fetch and status
uid_ranges = 2000
folder_batch = 200
# the restic runner waits for the batch while this heartbeat is fresh
heartbeat_interval = 30
# seconds to wait for the next batch of a restore
batch_wait = 120
rights_by_letter = {'l': 'lookup', 'r': 'read', 'w': 'write', 's': 'write-seen', 't': 'write-deleted', 'i': 'insert',
  'p': 'post', 'e': 'expunge', 'k': 'create', 'x': 'delete', 'a': 'admin'}
components_here = {'mails', 'imap_acl', 'filters', 'sieve_activate'}

conn = None


def connect():
  return MySQLdb.connect(unix_socket='/var/run/mysqld/mysqld.sock', user=os.environ['DBUSER'],
    passwd=os.environ['DBPASS'], db=os.environ['DBNAME'], charset='utf8mb4', autocommit=True)

# Reconnect when the server dropped the connection (imports can outlast wait_timeout)
def query(sql, args=None):
  global conn
  try:
    conn.ping()
  except (AttributeError, MySQLdb.Error):
    conn = connect()
  cur = conn.cursor(MySQLdb.cursors.DictCursor)
  cur.execute(sql, args)
  return cur

# state_restore_seen while a batch runs, in its own connection
class Heartbeat(threading.Thread):
  def __init__(self):
    super().__init__(daemon=True)
    self.done = threading.Event()

  def run(self):
    db = None
    while not self.done.is_set():
      try:
        if db is None:
          db = connect()
        db.cursor().execute("""INSERT INTO backup_setting (name, value) VALUES ('state_restore_seen', %s)
          ON DUPLICATE KEY UPDATE value = VALUES(value)""", (str(int(time.time())),))
      except MySQLdb.Error:
        db = None
      self.done.wait(heartbeat_interval)

def log(job_id, text):
  print('restore job %d: %s' % (job_id, text), flush=True)
  line = '%s %s\n' % (time.strftime('%Y-%m-%d %H:%M:%S'), text)
  query("UPDATE backup_job SET log = RIGHT(CONCAT(IFNULL(log, ''), %s), %s) WHERE id = %s", (line, max_log, job_id))

def save(job_id, result):
  query("UPDATE backup_job SET result = %s WHERE id = %s", (json.dumps(result), job_id))

def doveadm(*args, data=None, check=True, partial=False):
  result = subprocess.run(['doveadm', *args], input=data, stdin=None if data is not None else subprocess.DEVNULL,
    capture_output=True, timeout=86400)
  # mailbox status fails for folders that cannot be opened (implicit parents); the others are listed
  if check and result.returncode != 0 and not (partial and result.stdout.strip()):
    # mail_log writes an info line per message, the errors are the other lines
    lines = [l for l in result.stderr.decode('utf-8', 'replace').splitlines() if ': Info: ' not in l]
    raise RuntimeError('\n'.join(lines)[-500:] or 'doveadm exit code %d' % result.returncode)
  return result.stdout.decode('utf-8', 'replace')

def doveadm_json(*args, partial=False):
  out = doveadm('-f', 'json', *args, partial=partial)
  return json.loads(out) if out.strip() else []

# free disk space that must stay after the import: max(2 GiB, 5 % of the filesystem), a full disk stops mail
# (disk_reserve, mib and count_errors as in restic_runner.py of the backup image; tests/python/backup compares them)
def disk_reserve(total):
  return max(2 << 30, total // 20)

def import_space_ok(need, free, total):
  return need * 11 // 10 + disk_reserve(total) <= free

def mib(size):
  return '%.1f MiB' % (size / 1048576)

def component(errors=None):
  return {'added': 0, 'skipped': 0, 'errors': errors or []}

# Folder names on disk: modified UTF-7, listescape writes . / \ as \xx
def mutf7_decode(text):
  def utf16(m):
    if not m.group(1):
      return '&'
    data = m.group(1).replace(',', '/')
    return base64.b64decode(data + '=' * (-len(data) % 4)).decode('utf-16-be')
  return re.sub(r'&([A-Za-z0-9+,]*)-', utf16, text)

def unescape(part):
  return mutf7_decode(re.sub(r'\\([0-9a-fA-F]{2})', lambda m: chr(int(m.group(1), 16)), part))

# Maildir++: the maildir is INBOX, ".A.B" is A/B
def folder_name(entry):
  return '/'.join(unescape(part) for part in entry[1:].split('.')) if entry else 'INBOX'

def is_dir(path):
  try:
    return stat.S_ISDIR(os.lstat(path).st_mode)
  except OSError:
    return False

def read_file(path):
  fd = os.open(path, os.O_RDONLY | os.O_NOFOLLOW)
  with os.fdopen(fd, 'rb') as f:
    return f.read()

# Prefixes of the shared and public namespaces, their folders belong to others
def shared_prefixes():
  prefixes, namespace, depth = {'Shared/'}, None, 0
  # as vmail doveconf cannot read every included file (mail_crypt keys) and fails, but prints the namespaces
  for line in subprocess.run(['doveconf', '-n'], capture_output=True, text=True).stdout.splitlines():
    stripped = line.strip()
    if stripped.endswith('{'):
      depth += 1
      if depth == 1 and stripped.startswith('namespace'):
        namespace = {}
    elif stripped == '}':
      if depth == 1 and namespace is not None:
        if namespace.get('type') in ('shared', 'public') and namespace.get('prefix'):
          prefixes.add(namespace['prefix'].split('%')[0])
        namespace = None
      depth = max(depth - 1, 0)
    elif depth == 1 and namespace is not None and '=' in stripped:
      key, value = (part.strip() for part in stripped.split('=', 1))
      namespace[key] = value
  return tuple(prefix for prefix in prefixes if prefix)

# name => guid of the user's own folders (implicit parents have none); the names are looked up literally
def own_folders(user, prefixes):
  names = [n for n in doveadm('mailbox', 'list', '-u', user).splitlines() if n and not n.startswith(prefixes)]
  wanted, guids = set(names), {}
  for i in range(0, len(names), folder_batch):
    for row in doveadm_json('mailbox', 'status', '-u', user, 'guid', '--', *names[i:i + folder_batch], partial=True):
      if row.get('mailbox') in wanted and re.fullmatch(r'[0-9a-f]{32}', row.get('guid') or ''):
        guids[row['mailbox']] = row['guid']
  return guids

def masked(user):
  return '*' in user or '?' in user

def free_name(base, existing):
  name, count = base, 1
  while name in existing or any(folder.startswith(name + '/') for folder in existing):
    count += 1
    name = '%s (%d)' % (base, count)
  return name

# Path of the maildir now (/var/vmail/<domain>/<local>/Maildir), None for unknown or inactive mailboxes
def mail_location(user):
  result = subprocess.run(['doveadm', 'user', '-f', 'mail', '--', user], capture_output=True, text=True)
  m = re.match(r'maildir:([^:]+)', result.stdout.strip()) if result.returncode == 0 else None
  return m.group(1).rstrip('/') if m else None

# The restored maildir of a mailbox (may hold only ACL files), None if the snapshot has nothing of it
def staged_maildir(staging, home, location):
  for path in (staging + location, home + '/' + os.environ.get('MAILDIR_SUB', 'Maildir'), home):
    if is_dir(path):
      return path
  return None

def uid_chunks(uids):
  ranges, start, prev = [], None, None
  for uid in sorted(uids):
    if start is None:
      start = prev = uid
    elif uid == prev + 1:
      prev = uid
    else:
      ranges.append('%d:%d' % (start, prev) if prev != start else str(start))
      start = prev = uid
  if start is not None:
    ranges.append('%d:%d' % (start, prev) if prev != start else str(start))
  return [','.join(ranges[i:i + uid_ranges]) for i in range(0, len(ranges), uid_ranges)]

# Same message: same Message-ID, without one the same Date and size
def message_key(row):
  message_id = re.sub(r'\s+', '', row.get('hdr.message-id') or '').strip('<>')
  if message_id:
    return 'id:' + message_id
  return 'date:%s:%s' % (row.get('date.sent'), row.get('size.physical'))

def present_keys(user, guids):
  keys = set()
  guids = list(guids)
  for i in range(0, len(guids), folder_batch):
    search = []
    for guid in guids[i:i + folder_batch]:
      search += (['OR'] if search else []) + ['mailbox-guid', guid]
    keys.update(message_key(row) for row in doveadm_json('fetch', '-u', user, 'hdr.message-id date.sent size.physical',
      *search))
  return keys

def location(path):
  if ':' in path:
    raise RuntimeError('unsupported path')
  return 'maildir:%s:INDEX=MEMORY' % path

# The folders of a restored maildir as standalone maildirs (their messages are the INBOX there): the subfolders are
# moved out of the maildir, so every folder is imported without the name rules of the source (listescape)
def split_folders(maildir, home):
  folders, failed = [('INBOX', maildir)], []
  out = home + '/_restore_folders'
  for entry in sorted(os.listdir(maildir)):
    path = os.path.join(maildir, entry)
    if not entry.startswith('.') or entry in ('.', '..') or not is_dir(path) or not is_dir(path + '/cur'):
      continue
    try:
      name = folder_name(entry)
    except (ValueError, UnicodeDecodeError):
      failed.append(entry)
      continue
    if not os.path.isdir(out):
      os.mkdir(out, 0o700)
    target = '%s/f%d' % (out, len(folders))
    os.rename(path, target)
    folders.append((name, target))
  return folders, failed

def source_messages(user, root):
  return doveadm_json('-o', 'mail_location=' + location(root), 'fetch', '-u', user,
    'uid hdr.message-id date.sent size.physical', 'mailbox', 'INBOX')

def remove_tree(user, name):
  doveadm(*no_quota, 'mailbox', 'delete', '-u', user, '-r', '-s', '--', name, check=False)

def folder_guid(user, name):
  rows = doveadm_json('mailbox', 'status', '-u', user, 'guid', '--', name)
  for row in rows:
    if row.get('mailbox') == name:
      return row['guid']
  raise RuntimeError('folder not found after the import')

def quota_room(user):
  # free bytes of the storage quota, None without a limit
  for row in doveadm_json('quota', 'get', '-u', user):
    if row.get('type') == 'STORAGE' and str(row.get('limit', '-')).isdigit():
      return (int(row['limit']) - int(row.get('value') or 0)) * 1024
  return None

# Subscriptions of the backup (subscriptions file: "V\t2", then names with \t between the levels)
def backup_subscriptions(maildir):
  try:
    lines = read_file(maildir + '/subscriptions').decode('utf-8', 'replace').splitlines()
  except OSError:
    return []
  if lines and lines[0].startswith('V\t'):
    return ['/'.join(unescape(part) for part in line.split('\t')) for line in lines[1:] if line.strip()]
  # version 1: storage names with the Maildir++ separator
  return [folder_name('.' + line.strip()) for line in lines if line.strip()]

def restore_subscriptions(user, wanted, prefixes):
  if not wanted:
    return 0
  existing = set(own_folders(user, prefixes)) | {'INBOX'}
  subscribed = set(doveadm('mailbox', 'list', '-u', user, '-s').splitlines())
  missing = [name for name in dict.fromkeys(wanted) if name in existing and name not in subscribed]
  if missing:
    doveadm('mailbox', 'subscribe', '-u', user, '--', *missing)
  return len(missing)

# Mails folder by folder: original = only messages missing anywhere in the mailbox, into their original folders;
# folder = all messages below "Restored <snapshot time>"
def restore_mails(job_id, user, home, maildir, mode, snapshot_time, prefixes, check_quota):
  result = component()
  result.update(folders=0, folders_failed=0)
  if not maildir or not is_dir(maildir + '/cur'):
    # a key the UI translates
    result['note'] = 'no_mails'
    return result
  tmp = 'Restoring %d' % job_id
  # a leftover of an interrupted run goes first, it is never counted as present
  remove_tree(user, tmp)
  subscriptions = backup_subscriptions(maildir)
  folders, broken = split_folders(maildir, home)
  for entry in broken:
    result['errors'].append('folder %s: name not readable' % entry)
    result['folders_failed'] += 1
  live = own_folders(user, prefixes)
  present = present_keys(user, live.values()) if mode == 'original' else set()
  plan, need = [], 0
  for name, root in folders:
    try:
      rows = source_messages(user, root)
    except RuntimeError as e:
      result['errors'].append('%s: %s' % (name, e))
      result['folders_failed'] += 1
      continue
    wanted = [row for row in rows if mode != 'original' or message_key(row) not in present]
    result['skipped'] += len(rows) - len(wanted)
    if wanted:
      size = sum(int(row.get('size.physical') or 0) for row in wanted)
      plan.append((name, root, [int(row['uid']) for row in wanted], size))
      need += size
  if not plan:
    if mode == 'original':
      result['subscribed'] = restore_subscriptions(user, subscriptions, prefixes)
    return result
  vfs = os.statvfs('/var/vmail')
  free, total = vfs.f_bavail * vfs.f_frsize, vfs.f_blocks * vfs.f_frsize
  if not import_space_ok(need, free, total):
    raise RuntimeError('not enough free disk space for the mails: %s needed plus %s reserve, %s free' % (
      mib(need * 11 // 10), mib(disk_reserve(total)), mib(free)))
  if check_quota:
    room = quota_room(user)
    if room is not None and need > room:
      raise RuntimeError('the mails do not fit into the quota of the mailbox: %s needed, %s free' % (
        mib(need), mib(max(room, 0))))
  names = set(live) | {'INBOX'}
  dest = None
  if mode != 'original':
    dest = free_name('Restored ' + snapshot_time, names)
    doveadm('mailbox', 'create', '-u', user, '-s', '--', dest)
    names.add(dest)
    result['folder'] = dest
  created = []
  try:
    for number, (name, root, uids, size) in enumerate(plan):
      target = dest + '/' + name if dest else name
      stage = '%s/f%d' % (tmp, number)
      try:
        for chunk in uid_chunks(uids):
          doveadm(*no_quota, 'import', '-u', user, '-s', location(root), stage, 'mailbox', 'INBOX', 'uid', chunk)
        guid = folder_guid(user, stage + '/INBOX')
        if target not in names:
          doveadm('mailbox', 'create', '-u', user, '-s', '--', target)
          names.add(target)
          created.append(target)
        doveadm(*no_quota, 'move', '-u', user, '--', target, 'mailbox-guid', guid, 'all')
        result['added'] += len(uids)
        result['folders'] += 1
      except RuntimeError as e:
        result['errors'].append('%s: %s' % (name, e))
        result['folders_failed'] += 1
  finally:
    remove_tree(user, tmp)
  result['folders_created'] = len(created)
  if mode == 'original':
    result['subscribed'] = restore_subscriptions(user, subscriptions, prefixes)
  return result

def acl_entries(path):
  entries = []
  try:
    lines = read_file(path).decode('utf-8', 'replace').splitlines()
  except OSError:
    return entries
  for line in lines:
    parts = line.split()
    # negative rights would take rights away
    if len(parts) < 2 or parts[0].startswith('-'):
      continue
    rights = [rights_by_letter[c] for c in parts[1] if c in rights_by_letter] + [p for p in parts[2:] if p.startswith(':')]
    if rights:
      entries.append((parts[0], rights))
  return entries

# (folder, entries) of the dovecot-acl files of a restored maildir
def maildir_acls(maildir):
  acls = []
  if not maildir or not is_dir(maildir):
    return acls
  for entry in [''] + sorted(e for e in os.listdir(maildir) if e.startswith('.') and e not in ('.', '..')):
    path = os.path.join(maildir, entry, 'dovecot-acl') if entry else os.path.join(maildir, 'dovecot-acl')
    if entry and not is_dir(os.path.join(maildir, entry)):
      continue
    try:
      if stat.S_ISREG(os.lstat(path).st_mode):
        acls.append((folder_name(entry), acl_entries(path)))
    except (OSError, ValueError, UnicodeDecodeError):
      continue
  return acls

# owner => (folder, entries) of every mailbox in the ACL staging (domain admins: only their domains)
def staged_acls(acl_dir, allowed):
  acls = {}
  base = (acl_dir or '') + '/var/vmail'
  sub = os.environ.get('MAILDIR_SUB', '')
  if not acl_dir or not is_dir(base):
    return acls
  for domain in os.listdir(base):
    if '.' not in domain or not is_dir(os.path.join(base, domain)) or (allowed is not None and domain.lower() not in allowed):
      continue
    for local in os.listdir(os.path.join(base, domain)):
      home = os.path.join(base, domain, local)
      maildir = os.path.join(home, sub) if sub and is_dir(os.path.join(home, sub)) else home
      found = maildir_acls(maildir)
      if found:
        acls['%s@%s' % (local, domain)] = found
  return acls

class AclRestore:
  def __init__(self, prefixes, allowed):
    self.prefixes = prefixes
    self.allowed = allowed
    self.users, self.folders = {}, {}
    # anyone/authenticated only when the configuration allows them
    self.anyone = os.environ.get('ACL_ANYONE', 'disallow') == 'allow'

  def in_scope(self, address):
    return self.allowed is None or address.rpartition('@')[2].lower() in self.allowed

  def user_exists(self, user):
    if user not in self.users:
      self.users[user] = query("SELECT 1 FROM mailbox WHERE username = %s", (user,)).fetchone() is not None
    return self.users[user]

  def identifier_ok(self, identifier):
    if identifier.startswith('user='):
      return self.user_exists(identifier[5:].lower())
    # group=mcg_<id> belongs to the mailbox groups, the group grants cron keeps them
    if identifier.startswith('group=mcg_'):
      return False
    if identifier in ('anyone', 'authenticated'):
      return self.anyone
    return True

  def shown(self, identifier):
    # domain admins do not see addresses of other domains
    if identifier.startswith('user=') and not self.in_scope(identifier[5:]):
      return 'user=(other domain)'
    return identifier

  def add(self, owner, folder, identifier, rights, result):
    if not self.in_scope(owner) or masked(owner):
      result['skipped'] += 1
      return
    try:
      if owner not in self.folders:
        self.folders[owner] = set(own_folders(owner, self.prefixes)) | {'INBOX'}
      if folder not in self.folders[owner] or not self.identifier_ok(identifier):
        result['skipped'] += 1
        return
      have = set()
      for row in doveadm_json('acl', 'get', '-u', owner, '--', folder):
        if row.get('id') == identifier:
          have.update((row.get('rights') or '').split())
      missing = [right for right in rights if right not in have]
      if not missing:
        result['skipped'] += 1
        return
      doveadm('acl', 'add', '-u', owner, '--', folder, identifier, *missing)
      result['added'] += 1
    except RuntimeError as e:
      result['errors'].append('%s %s %s: %s' % (owner, folder, self.shown(identifier), e))

# Rights the mailbox granted on its folders and rights other mailboxes granted to it; added, never removed
def restore_acl(acl, user, own, granted):
  result = component()
  for folder, entries in own:
    for identifier, rights in entries:
      acl.add(user, folder, identifier, rights, result)
  if acl.user_exists(user):
    for owner, folders in granted.items():
      if owner == user or not acl.in_scope(owner) or not acl.user_exists(owner):
        continue
      for folder, entries in folders:
        for identifier, rights in entries:
          if identifier.lower() == 'user=' + user:
            acl.add(owner, folder, identifier, rights, result)
  return result

# Own Sieve scripts (incl. the SOGo vacation script): missing names are added; with activate the active script of the
# backup is activated if none is active now
def restore_sieve(user, home, active_link, activate):
  result = component()
  folder = home + '/sieve'
  scripts = []
  if is_dir(folder):
    scripts = sorted(f[:-6] for f in os.listdir(folder) if f.endswith('.sieve') and stat.S_ISREG(os.lstat(os.path.join(folder, f)).st_mode))
  current, active = set(), None
  for line in doveadm('sieve', 'list', '-u', user).splitlines():
    name, _, flag = line.rpartition(' ')
    if name:
      current.add(name)
      if flag == 'ACTIVE':
        active = name
  for name in scripts:
    if name in current:
      result['skipped'] += 1
      continue
    try:
      doveadm('sieve', 'put', '-u', user, '--', name, data=read_file(os.path.join(folder, name + '.sieve')))
      current.add(name)
      result['added'] += 1
    except (OSError, RuntimeError) as e:
      result['errors'].append('%s: %s' % (name, e))
  if not activate:
    return result
  target = os.readlink(active_link) if os.path.islink(active_link) else ''
  backup_active = os.path.basename(target)[:-6] if target.endswith('.sieve') else None
  if backup_active and not active and backup_active in current:
    try:
      doveadm('sieve', 'activate', '-u', user, '--', backup_active)
      result['activated'] = backup_active
    except RuntimeError as e:
      result['errors'].append('%s: %s' % (backup_active, e))
  return result

# filters: backup.php reported its part already, the counts are added up
def merge(results, key, part):
  old = results.get(key)
  if not isinstance(old, dict):
    results[key] = part
    return
  for count in ('added', 'skipped'):
    old[count] = (old.get(count) or 0) + part.get(count, 0)
  old['errors'] = (old.get('errors') or []) + part.get('errors', [])
  for name, value in part.items():
    if name not in ('added', 'skipped', 'errors'):
      old[name] = value

def count_errors(result):
  count = sum(len(result.get(key, {}).get('errors') or []) for key in ('domain', 'aliases') if isinstance(result.get(key), dict))
  for entry in (result.get('mailboxes') or {}).values():
    count += int(bool(entry.get('error')))
    count += sum(len(value.get('errors') or []) for value in entry.values() if isinstance(value, dict))
  return count

def restore_mailbox(job_id, user, components, mode, staging, snapshot_time, prefixes, acl, granted, check_quota, result):
  local, _, domain = user.rpartition('@')
  home = '%s/var/vmail/%s/%s' % (staging, domain, local)
  if masked(user):
    for key in components & {'mails', 'imap_acl', 'filters'}:
      merge(result, key, component(['username with * or ?, doveadm reads it as a mask']))
    return
  location = mail_location(user)
  if not location:
    for key in components & {'mails', 'imap_acl', 'filters'}:
      merge(result, key, component(['the mailbox does not exist now or is inactive']))
    return
  maildir = staged_maildir(staging, home, location)
  # the ACL files are read before the folders of the maildir are moved for the import
  own = maildir_acls(maildir) if 'imap_acl' in components else []
  if 'mails' in components:
    try:
      result['mails'] = restore_mails(job_id, user, home, maildir, mode, snapshot_time, prefixes, check_quota)
    except Exception as e:
      result['mails'] = component([str(e)[:1000]])
    try:
      doveadm('quota', 'recalc', '-u', user)
    except RuntimeError as e:
      result['mails']['errors'].append('quota recalc: %s' % e)
    # new folders
    acl.folders.pop(user, None)
  if 'imap_acl' in components:
    try:
      result['imap_acl'] = restore_acl(acl, user, own, granted)
    except Exception as e:
      result['imap_acl'] = component([str(e)[:1000]])
  if 'filters' in components:
    try:
      part = restore_sieve(user, home, '%s/var/vmail/sieve/%s.sieve' % (staging, user), 'sieve_activate' in components)
    except Exception as e:
      part = component([str(e)[:1000]])
    merge(result, 'filters', part)

def fail(job_id, error):
  query("""UPDATE backup_job SET state = IF(state = 'cancelled', state, 'failed'), error = %s, finished = NOW()
    WHERE id = %s""", (error, job_id))
  log(job_id, 'restore job failed: ' + error)
  notify(job_id, error)

def notify(job_id, error):
  hostname = os.environ.get('MAILCOW_HOSTNAME', '')
  row = query("SELECT value FROM backup_setting WHERE name = 'notify_email'").fetchone()
  rcpts = [r.strip() for r in (row['value'] if row else '').split(',') if re.fullmatch(r'[^@\s<>,]+@[^@\s<>,]+', r.strip())]
  if not rcpts:
    return
  msg = EmailMessage()
  msg['From'] = 'backup@' + hostname
  msg['To'] = ', '.join(rcpts)
  msg['Subject'] = 'mailcow backup on %s: restore job %d failed' % (hostname, job_id)
  msg['Date'] = formatdate(localtime=True)
  msg['Message-ID'] = make_msgid(domain=hostname or None)
  msg.set_content('The restore job %d failed:\n\n%s\n\nThe job log is in the backup section of the mailcow UI.\n'
    % (job_id, error))
  try:
    with smtplib.SMTP('postfix', 588, hostname or None, timeout=60) as smtp:
      smtp.send_message(msg)
  except (OSError, smtplib.SMTPException) as e:
    print('restore job %d: the notification could not be sent: %s' % (job_id, e), file=sys.stderr)

def staging_dir(job_id, path):
  # the batch folder of this job, never a symlink
  if not isinstance(path, str) or not re.fullmatch(r'/restore/%d/[A-Za-z0-9_-]+' % job_id, path) or \
      os.path.realpath(path) != path or not is_dir(path):
    return None
  return path

def run_batch(job):
  job_id = job['id']
  params = json.loads(job['params'] or '{}')
  result = json.loads(job['result'] or '{}')
  batch = result.get('batch') or {}
  staging = staging_dir(job_id, batch.get('dir'))
  acl_dir = staging_dir(job_id, batch.get('acl_dir')) if batch.get('acl_dir') else None
  mailboxes = [m for m in batch.get('mailboxes') or [] if isinstance(m, str)]
  components = {c for c in params.get('components') or [] if c in components_here}
  mode = 'original' if params.get('mail_target') == 'original' else 'folder'
  allowed = {d.lower() for d in params['allowed_domains']} if isinstance(params.get('allowed_domains'), list) else None
  check_quota = params.get('requested_role', 'admin') != 'admin'
  snapshot_time = (result.get('snapshot') or {}).get('time') or time.strftime('%Y-%m-%d %H:%M')
  results = result.setdefault('mailboxes', {})
  prefixes = shared_prefixes()
  acl = AclRestore(prefixes, allowed)
  granted = staged_acls(acl_dir, allowed) if 'imap_acl' in components else {}
  log(job_id, 'restoring %s for %d mailboxes%s' % (', '.join(sorted(components)), len(mailboxes),
    ' (mails: %s)' % mode if 'mails' in components else ''))
  for user in mailboxes:
    entry = results.setdefault(user, {})
    if entry.get('error'):
      continue
    started = time.monotonic()
    if not staging:
      for key in components & {'mails', 'imap_acl', 'filters'}:
        merge(entry, key, component(['the restored files are missing']))
    else:
      restore_mailbox(job_id, user, components, mode, staging, snapshot_time, prefixes, acl, granted, check_quota, entry)
    summary = ', '.join('%s +%d' % (key, entry[key].get('added', 0)) for key in sorted(components) if isinstance(entry.get(key), dict))
    errors = sum(len(entry[key].get('errors') or []) for key in components if isinstance(entry.get(key), dict))
    log(job_id, '%s: %s%s (%d s)' % (user, summary, ', %d errors' % errors if errors else '', time.monotonic() - started))
    result['errors'] = count_errors(result)
    save(job_id, result)
  result['errors'] = count_errors(result)
  save(job_id, result)
  # the restic runner removes the staging and goes on
  query("UPDATE backup_job SET step = 'mails_done' WHERE id = %s AND state = 'running' AND step = 'mails_running'", (job_id,))

def main():
  global conn
  if os.geteuid() == 0:
    # as vmail like the ofelia job: a run as root would leave a lock file the runs as vmail cannot open
    os.execv('/usr/local/bin/gosu', ['gosu', 'vmail', sys.executable, os.path.abspath(__file__)] + sys.argv[1:])
  os.umask(0o077)
  # released by the kernel when the process ends, a crashed run leaves no stale lock
  lock_fd = os.open(lock_file, os.O_RDWR | os.O_CREAT, 0o600)
  try:
    fcntl.flock(lock_fd, fcntl.LOCK_EX | fcntl.LOCK_NB)
  except OSError:
    sys.exit("restore: can't lock " + lock_file)
  conn = connect()
  heartbeat = Heartbeat()
  heartbeat.start()
  try:
    # under the lock: a batch in mails_running was left by a crashed or stopped run, its temporary folders go
    for row in query("""SELECT id, result FROM backup_job WHERE type = 'restore' AND state = 'running'
        AND step = 'mails_running'""").fetchall():
      try:
        for user in (json.loads(row['result'] or '{}').get('batch') or {}).get('mailboxes') or []:
          remove_tree(user, 'Restoring %d' % row['id'])
          doveadm('quota', 'recalc', '-u', user, check=False)
      except (ValueError, AttributeError, RuntimeError):
        pass
      fail(row['id'], 'interrupted: the restore of the mails was stopped')
    last, waited = None, 0
    while True:
      job = query("""SELECT id, params, result FROM backup_job WHERE type = 'restore' AND state = 'running'
        AND step = 'mails' ORDER BY id LIMIT 1""").fetchone()
      if not job:
        # the next batch of a restore follows within seconds: wait for it instead of the next ofelia run
        row = query("SELECT state FROM backup_job WHERE id = %s", (last,)).fetchone() if last else None
        if row and row['state'] == 'running' and waited < batch_wait:
          time.sleep(2)
          waited += 2
          continue
        break
      last, waited = job['id'], 0
      if query("UPDATE backup_job SET step = 'mails_running' WHERE id = %s AND state = 'running' AND step = 'mails'",
          (job['id'],)).rowcount != 1:
        continue
      try:
        run_batch(job)
      except Exception as e:
        fail(job['id'], str(e)[:1000])
  finally:
    heartbeat.done.set()

if __name__ == '__main__':
  main()
