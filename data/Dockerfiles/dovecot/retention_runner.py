#!/usr/bin/python3
# Nightly run of the retention rules (ofelia, dovecot container): gets the plan page by page from the mailcow UI
# (nginx:8081/retention.php), gives every folder of a mailbox to the first entry of its plan that matches it, counts
# the mail saved in the folders before the period of the rule with doveadm, deletes it unless the rule only
# simulates, and reports the results back.

import fcntl
import hashlib
import hmac
import json
import os
import re
import subprocess
import sys
import time
import urllib.parse
import urllib.request

plan_url = 'http://nginx:8081/retention.php'
lock_file = '/tmp/retention_runner.lock'
# folder keys of the rules and the special-use flag of their folders in the Dovecot configuration
special_use = {'trash': '\\Trash', 'junk': '\\Junk', 'sent': '\\Sent', 'drafts': '\\Drafts', 'archive': '\\Archive'}
own_folder = 'folder:'
# mailboxes per page of the plan and results per report, a large instance has many mailboxes
plan_page = 200
report_batch = 200
# a request is tried this often, waiting longer each time
tries = 3

token = hmac.new(os.environ.get('DBPASS', '').encode(), b'retention', hashlib.sha256).hexdigest()

def request(url, body=None):
  data = None if body is None else json.dumps(body).encode()
  req = urllib.request.Request(url, data=data, headers={'Content-Type': 'application/json', 'X-Retention-Token': token},
    method='GET' if body is None else 'POST')
  with urllib.request.urlopen(req, timeout=300) as response:
    return json.load(response)

def retried(url, body=None):
  for attempt in range(1, tries + 1):
    try:
      return request(url, body)
    except Exception as e:
      if attempt == tries:
        raise
      print('retention: request failed, trying again: %s' % e, file=sys.stderr)
      time.sleep(30 * attempt)

def plan():
  # one page after the other, so memory stays small; rule changes apply to the pages fetched after them
  after = ''
  while after is not None:
    page = retried(plan_url + '?' + urllib.parse.urlencode({'after': after, 'limit': plan_page}))
    yield from page['mailboxes']
    after = page['next']

def post(body):
  # False if the report could not be sent
  try:
    retried(plan_url, body)
    return True
  except Exception as e:
    print('retention: report failed: %s' % e, file=sys.stderr)
    return False

class Reports:
  # results go back in parts; once a part cannot be sent, the rest waits for the end of the run
  def __init__(self):
    self.pending, self.failed = [], False

  def add(self, result):
    self.pending.append(result)
    if len(self.pending) >= report_batch and not self.failed:
      self.failed = not self.send()

  def send(self, summary=None, run=None):
    # full parts, at the end all results with the summary and the run in the last part
    final = summary is not None
    while len(self.pending) >= report_batch or final:
      body = {'results': self.pending[:report_batch]}
      if final and len(self.pending) <= report_batch:
        body.update(summary=summary, run=run)
      if not post(body):
        return False
      del self.pending[:report_batch]
      if 'summary' in body:
        break
    return True

def doveadm(*args, partial=False):
  result = subprocess.run(['doveadm', *args], capture_output=True, text=True, timeout=3600)
  # mailbox status also fails for single folders that cannot be opened; the others are still listed
  if result.returncode != 0 and not (partial and result.stdout):
    raise RuntimeError((result.stderr or result.stdout).strip()[:500] or 'doveadm exit %d' % result.returncode)
  return result.stdout

def configuration():
  # names of the special folders per key, and the prefixes of the shared and public namespaces (not the user's)
  names = {key: set() for key in special_use}
  prefixes = ['Shared/']
  namespace, mailbox = None, None
  # as vmail doveconf cannot read every included file (mail_crypt keys) and fails, but prints the namespaces
  for line in subprocess.run(['doveconf', '-n'], capture_output=True, text=True).stdout.splitlines():
    stripped = line.strip()
    if line.startswith('namespace ') and line.endswith('{'):
      namespace = {}
    elif line == '}':
      if namespace and namespace.get('type') in ('shared', 'public') and namespace.get('prefix'):
        prefixes.append(namespace['prefix'].split('%')[0])
      namespace = None
    elif namespace is None:
      continue
    elif re.match(r'mailbox .+ \{$', stripped):
      mailbox = stripped[len('mailbox '):-2].strip().strip('"')
    elif stripped == '}':
      mailbox = None
    elif '=' in stripped:
      key, value = (part.strip() for part in stripped.split('=', 1))
      if mailbox is not None and key == 'special_use':
        for folder, flag in special_use.items():
          if flag in value.split():
            names[folder].add(mailbox)
      elif mailbox is None and key in ('type', 'prefix'):
        namespace[key] = value
  if not names['trash']:
    raise RuntimeError('no special folders in the output of doveconf -n')
  return names, [prefix for prefix in prefixes if prefix]

def own_folders(user, prefixes):
  # folder name => guid of the user's own folders; listed first, so folders others share are not opened
  names = set(name for name in doveadm('-f', 'tab', 'mailbox', 'list', '-u', user).splitlines()[1:]
    if name and not any((name + '/').startswith(prefix) for prefix in prefixes))
  folders = {}
  if not names:
    return folders
  for line in doveadm('-f', 'tab', 'mailbox', 'status', '-u', user, 'guid', *sorted(names), partial=True).splitlines()[1:]:
    name, _, guid = line.rpartition('\t')
    if name in names and re.fullmatch(r'[0-9a-f]{32}', guid):
      folders[name] = guid
  return folders

def canonical(name):
  # Dovecot writes INBOX in capitals, also as the parent of folders
  return 'INBOX' + name[5:] if name[:5].upper() == 'INBOX' and name[5:6] in ('', '/') else name

def matches(action, name, names):
  # match: the folder itself, an own folder name with *, the subfolders of a special folder or the inbox, all folders
  key, match = action['folder'], action['match']
  if match == 'all':
    return True
  if key.startswith(own_folder):
    # * is the only wildcard of own folder names
    pattern = '.*'.join(re.escape(part) for part in canonical(key[len(own_folder):]).split('*'))
    return re.fullmatch(pattern, name) is not None
  folders = ['INBOX'] if key == 'inbox' else names.get(key, ())
  if match == 'subfolders':
    return any(name.startswith(folder + '/') for folder in folders)
  return name in folders

def decide(actions, folders, names):
  # rule id => (its action, the folders it decides about): every folder goes to the first action that matches it
  decided = {}
  for name in sorted(folders):
    for action in actions:
      if matches(action, canonical(name), names):
        decided.setdefault(action['rule_id'], (action, []))[1].append(name)
        break
  return decided

def found(user, query):
  return len(doveadm('search', '-u', user, *query).splitlines())

def clean(user, action, selected, folders, result):
  # name and guid together: doveadm then only opens these folders
  query = []
  for name in selected:
    query += (['OR'] if query else []) + ['(', 'mailbox', name, 'mailbox-guid', folders[name], ')']
  query = ['('] + query + [')', 'savedbefore', '%dd' % int(action['days'])]
  if action['keep_flagged']:
    query += ['NOT', 'FLAGGED']
  result['matched'] = found(user, query)
  if not result['matched'] or action['mode'] != 'delete':
    return
  try:
    doveadm('expunge', '-u', user, *query)
    result['deleted'] = result['matched']
  except Exception as e:
    # part of the mail may be gone: what is left is counted
    result['error'] = str(e)[:1000]
    try:
      result['deleted'] = max(0, result['matched'] - found(user, query))
    except Exception:
      pass

def main():
  # released by the kernel when the process ends, a crashed run leaves no stale lock
  lock_fd = os.open(lock_file, os.O_RDWR | os.O_CREAT, 0o600)
  try:
    fcntl.flock(lock_fd, fcntl.LOCK_EX | fcntl.LOCK_NB)
  except OSError:
    sys.exit("retention: can't lock " + lock_file)
  reports = Reports()
  run = {'started': int(time.time()), 'mailboxes': 0, 'matched': 0, 'deleted': 0, 'errors': 0, 'error': ''}
  summary = {}
  try:
    names, prefixes = configuration()
    for mailbox in plan():
      user = mailbox['username']
      run['mailboxes'] += 1
      try:
        folders, error = own_folders(user, prefixes), ''
        decided = decide(mailbox['actions'], folders, names)
      except Exception as e:
        # the folders are unknown: an error for every rule of the mailbox
        folders, error, decided = {}, str(e)[:1000], {}
        for action in mailbox['actions']:
          decided.setdefault(action['rule_id'], (action, []))
      for rule_id, (action, selected) in decided.items():
        rule = summary.setdefault(rule_id, {'rule_id': rule_id, 'mailboxes': 0, 'matched': 0, 'deleted': 0, 'errors': 0,
          'simulated': int(action['mode'] != 'delete')})
        result = {'username': user, 'rule_id': rule_id, 'folder': '\n'.join(selected), 'days': action['days'], 'matched': 0,
          'deleted': 0, 'simulated': rule['simulated'], 'error': error}
        if not error:
          try:
            clean(user, action, selected, folders, result)
          except Exception as e:
            result['error'] = str(e)[:1000]
        for counter in (rule, run):
          counter['matched'] += result['matched']
          counter['deleted'] += result['deleted']
          counter['errors'] += int(result['error'] != '')
        rule['mailboxes'] += 1
        # only mailboxes with found mail or an error are logged, every rule gets a summary
        if result['matched'] or result['error']:
          reports.add(result)
  except Exception as e:
    run['error'] = str(e)[:1000]
  reported = reports.send(list(summary.values()), run)
  print('retention: %d mailboxes, %d mails deleted, %d found by simulating rules, %d errors' % (run['mailboxes'],
    run['deleted'], sum(rule['matched'] for rule in summary.values() if rule['simulated']), run['errors']))
  if run['error']:
    print('retention: %s' % run['error'], file=sys.stderr)
  if not reported:
    print('retention: the results could not be reported', file=sys.stderr)
  if run['error'] or not reported:
    sys.exit(1)

if __name__ == '__main__':
  main()
