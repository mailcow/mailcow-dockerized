"""SOGo part of a mailbox restore (component sogo, overwrites), called by the restic runner for one mailbox.

Runs `sogo-tool restore -f ALL <dir> <user>` and `sogo-tool restore -p <dir> <user>` in the sogo container through
dockerapi on the user's file of the nightly sogo-tool backup: missing calendars, address books and entries come back,
the names and shares of existing folders and all SOGo preferences are replaced by the backup's, entries changed since
stay. Then the user's entries in SOGo's cache (memcached) are removed, so the change shows at once.
"""

import json
import os
import re
import socket
import ssl
import urllib.request

dockerapi_url = 'https://dockerapi:443'
memcached_addr = ('memcached-mailcow', 11211)
# dockerapi stops each of the two sogo-tool runs after 850 s
restore_timeout = 1800
# accepted by the dockerapi handler as well
user_re = re.compile(r'[A-Za-z0-9_][A-Za-z0-9._+-]*@[A-Za-z0-9-]+(\.[A-Za-z0-9-]+)+')


def _short(text, limit=300):
  # sogo-tool may print database URLs: no credentials, one line, the end has the error
  text = re.sub(r'([A-Za-z][A-Za-z0-9+.-]*://)[^\s/@]*@', r'\1***@', str(text))
  text = ' '.join(text.split())
  return text if len(text) <= limit else '...' + text[-limit:]


def _api(path, body=None, timeout=60):
  # dockerapi uses the mail certificate, its name does not match
  ctx = ssl.create_default_context()
  ctx.check_hostname = False
  ctx.verify_mode = ssl.CERT_NONE
  data = None if body is None else json.dumps(body).encode()
  req = urllib.request.Request(dockerapi_url + path, data=data, headers={'Content-Type': 'application/json'},
    method='GET' if body is None else 'POST')
  with urllib.request.urlopen(req, timeout=timeout, context=ctx) as response:
    return json.load(response)


def _sogo_container():
  project = os.environ.get('COMPOSE_PROJECT_NAME', '').lower()
  by_name = None
  for cid, info in _api('/containers/json').items():
    if not cid.isalnum() or not isinstance(info, dict):
      continue
    labels = (info.get('Config') or {}).get('Labels') or {}
    if labels.get('com.docker.compose.service') == 'sogo-mailcow':
      if not project or labels.get('com.docker.compose.project', '').lower() == project:
        return cid
    elif by_name is None and 'sogo-mailcow' in str(info.get('Name', '')):
      by_name = cid
  if by_name:
    return by_name
  raise RuntimeError('sogo container not found')


def _memcached_delete(keys):
  keys = [k for k in keys if 0 < len(k.encode()) <= 250 and not re.search(r'[\s\x00-\x1f\x7f]', k)]
  if not keys:
    return
  with socket.create_connection(memcached_addr, timeout=10) as s:
    for key in keys:
      s.sendall(b'delete ' + key.encode() + b'\r\n')
      reply = b''
      while not reply.endswith(b'\r\n'):
        chunk = s.recv(64)
        if not chunk:
          raise RuntimeError('memcached closed the connection')
        reply += chunk
      if reply.strip() not in (b'DELETED', b'NOT_FOUND'):
        raise RuntimeError('memcached: %s' % _short(reply.decode('ascii', 'replace')))


def _cache_keys(db, username):
  # preferences and the ACLs of the user's folders (<uid>/Calendar/personal+acl); never read the c_*location
  # columns of sogo_folder_info (database credentials)
  keys = ['%s+defaults' % username, '%s+settings' % username]
  cur = db.cursor()
  try:
    cur.execute('SELECT c_path FROM sogo_folder_info WHERE c_path2 = %s', (username,))
    keys += ['%s+acl' % row[0][len('/Users/'):] for row in cur.fetchall() if row[0].startswith('/Users/')]
  finally:
    cur.close()
  return keys


def restore(db, backup_dir, username, log):
  """Restores the SOGo data of one user from <backup_dir>/<username> (staged readable for the sogo user only).
  db: open MySQLdb connection; log: callable for short progress lines. Returns {"added": 0|1, "errors": [...]}."""
  result = {'added': 0, 'errors': []}
  if not user_re.fullmatch(username):
    result['errors'].append('username not supported by the SOGo restore')
    return result
  path = os.path.join(backup_dir, username)
  if not os.path.isfile(path) or os.path.islink(path):
    # sogo-tool backup ALL skips users without SOGo folders
    # a key the UI translates
    result['note'] = 'no_sogo_data'
    log('sogo: no SOGo data in the snapshot')
    return result
  try:
    res = _api('/containers/%s/exec' % _sogo_container(), {'cmd': 'sogo', 'task': 'restore', 'dir': backup_dir,
      'username': username}, timeout=restore_timeout)
    if not isinstance(res, dict) or res.get('type') != 'success':
      raise RuntimeError('sogo-tool restore failed: %s' % _short(res.get('msg', '') if isinstance(res, dict) else res))
    result['added'] = 1
    log('sogo: calendars, address books and preferences reset to the backup')
  except Exception as e:
    result['errors'].append(_short(e))
  try:
    # SOGo caches ACLs and preferences for 15 minutes
    _memcached_delete(_cache_keys(db, username))
  except Exception as e:
    result['errors'].append('SOGo cache not cleared, changes may show up after 15 minutes: %s' % _short(e))
  return result
