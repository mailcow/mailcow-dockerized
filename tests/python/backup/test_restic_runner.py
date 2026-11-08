# Pure functions of the backup runner (data/Dockerfiles/restic/restic_runner.py)
import datetime
import gzip
import json
import os
import tempfile
import time
import types
import unittest
import urllib.parse

import nacl.public
import restic_runner as rr
import support

GiB = 1 << 30


class Patch(unittest.TestCase):
  # replaces module attributes of the runner for one test
  def patch(self, **values):
    for name, value in values.items():
      old = getattr(rr, name)
      self.addCleanup(setattr, rr, name, old)
      setattr(rr, name, value)


class Globs(unittest.TestCase):
  def test_glob_escape(self):
    self.assertEqual(rr.glob_escape('a*b?c[d]e\\f'), 'a\\*b\\?c\\[d\\]e\\\\f')
    self.assertEqual(rr.glob_escape('user.name+tag'), 'user.name+tag')

  def test_includes_per_component(self):
    homes = {'/var/vmail/x.de/a': 100, '/var/vmail/x.de/b*': 50}
    ready = ['a@x.de', 'b*@x.de', 'new@x.de']
    includes, need = rr.batch_includes(ready, homes, {'mails'})
    self.assertEqual((includes, need), (['/var/vmail/x.de/a', '/var/vmail/x.de/b\\*'], 150))
    includes, need = rr.batch_includes(['a@x.de'], homes, {'imap_acl', 'filters'})
    self.assertEqual(includes, ['/var/vmail/x.de/a/**/dovecot-acl', '/var/vmail/x.de/a/sieve', '/var/vmail/sieve/a@x.de.sieve'])
    self.assertEqual(need, 0)
    self.assertEqual(rr.batch_includes(['a@x.de'], homes, {'sogo', 'aliases'})[0], ['/sogo_backup/a@x.de'])
    # no home in the snapshot (never got mail): only the active Sieve link
    self.assertEqual(rr.batch_includes(['new@x.de'], homes, {'mails', 'sieve_activate'})[0], ['/var/vmail/sieve/new@x.de.sieve'])
    self.assertEqual(rr.batch_includes(['a@x.de'], homes, {'aliases', 'settings'}), ([], 0))


class Modes(Patch):
  def test_paths(self):
    s = dict(rr.defaults)
    paths, excludes = rr.backup_paths(s, 'all')
    self.assertIn('/mailcow', paths)
    self.assertIn('/var/vmail_index', paths)
    self.assertEqual(excludes, [])
    # data: of the mailcow directory only mailcow.conf
    paths, _ = rr.backup_paths(s, 'data')
    self.assertEqual([p for p in paths if p == '/mailcow' or p.startswith('/mailcow/install')], ['/mailcow/install/mailcow.conf'])
    self.assertTrue({'/var/vmail', '/mailcow/mysql', '/mailcow/export', '/mailcow/manifest.json'} <= set(paths))
    # config: no mails
    paths, _ = rr.backup_paths(s, 'config')
    self.assertEqual(sorted(paths), sorted(['/mail_crypt', '/redis', '/mailcow/mysql', '/mailcow/export',
      '/mailcow/manifest.json', '/mailcow/install/mailcow.conf', '/mailcow/install/data/conf']))
    s.update(include_index='1', include_fts='0')
    self.assertEqual(rr.backup_paths(s, 'data')[1], ['--exclude', '/var/vmail_index/**/fts-flatcurve'])

  def test_mode(self):
    self.addCleanup(os.environ.pop, 'RESTIC_BACKUP_MODE', None)
    for value, mode in (('', 'all'), (' Data ', 'data'), ('config', 'config')):
      os.environ['RESTIC_BACKUP_MODE'] = value
      self.assertEqual(rr.backup_mode(), mode)
    os.environ['RESTIC_BACKUP_MODE'] = 'everything'
    self.assertRaises(rr.JobError, rr.backup_mode)
    del os.environ['RESTIC_BACKUP_MODE']
    self.assertEqual(rr.backup_mode(), 'all')


class Retention(unittest.TestCase):
  def test_keep_args(self):
    s = dict(rr.defaults, keep_hourly='24', keep_daily='7', keep_weekly='0', keep_monthly='x', keep_yearly='2')
    self.assertEqual(rr.keep_args(s), ['--keep-hourly', '24', '--keep-daily', '7', '--keep-yearly', '2'])
    self.assertEqual(rr.keep_args(dict(rr.defaults, keep_daily='0', keep_weekly='0', keep_monthly='0')), [])

  def test_max_unused(self):
    for value, expected in (('10%', '10%'), ('2.5%', '2.5%'), ('500M', '500M'), ('unlimited', 'unlimited'),
        ('1; rm', '10%'), ('', '10%'), ('--x', '10%')):
      self.assertEqual(rr.max_unused(dict(rr.defaults, max_unused=value)), expected)


class Space(Patch):
  def test_needed(self):
    self.assertEqual(rr.local_space_needed(100 << 20, 0, 0, 0), GiB)
    self.assertEqual(rr.local_space_needed(3 * GiB, 0, 0, 0), 6 * GiB)
    self.assertEqual(rr.local_space_needed(None, 5 * GiB, GiB, GiB), 8 * GiB)

  def test_reserve(self):
    self.assertEqual(rr.disk_reserve(10 * GiB), 2 * GiB)
    self.assertEqual(rr.disk_reserve(200 * GiB), 10 * GiB)

  def check(self, free, total, added=None, mails=0, db=0):
    rows = {'backup_snapshot': [{'summary': json.dumps({'data_added': added})}] if added is not None else [],
      'quota2': [{'bytes': mails}], 'information_schema': [{'bytes': db}], 'backup_job': []}
    query = lambda sql, args=None: support.Cursor(next(v for k, v in rows.items() if k in sql))
    self.patch(query=query, instance_id=lambda: '0' * 16)
    stat = types.SimpleNamespace(f_bavail=free // 4096, f_frsize=4096, f_blocks=total // 4096)
    statvfs = os.statvfs
    os.statvfs = lambda path: stat
    self.addCleanup(setattr, os, 'statvfs', statvfs)
    logs = []
    rr.check_local_space(types.SimpleNamespace(log=logs.append), dict(rr.defaults, target='local'))
    return logs

  def test_check(self):
    self.assertIn('50.0 GiB free, about 1.0 GiB needed plus 5.0 GiB reserve', self.check(50 * GiB, 100 * GiB, added=1)[0])
    with self.assertRaisesRegex(rr.JobError, 'not enough free space for the local repository: 10.0 GiB free'):
      self.check(10 * GiB, 100 * GiB, added=3 * GiB)
    with self.assertRaises(rr.JobError):
      self.check(7 * GiB, 40 * GiB, mails=5 * GiB, db=GiB // 2)
    self.assertEqual(rr.check_local_space(None, dict(rr.defaults, target='s3')), None)


# The schedule setting: entries of (weekdays, time)
def plan(*entries):
  return json.dumps([{'days': days, 'time': time} for days, time in entries])

EVERY_DAY = [0, 1, 2, 3, 4, 5, 6]


class Schedule(Patch):
  def setUp(self):
    support.use_timezone(self, 'Europe/Berlin')
    self.written, self.jobs, self.queued = {}, [], []
    # jobs: (type, created) of scheduled jobs
    query = lambda sql, args=None: support.Cursor([{'1': 1}] if any(job_type == args[0] and created >= args[1]
      for job_type, created in self.jobs) else [])
    self.patch(datetime=support.Clock, query=query, set_setting=lambda k, v: self.written.__setitem__(k, str(v)),
      queue=lambda job_type, params=None: self.queued.append(job_type))
    self.s = dict(rr.defaults, enabled='1', password_confirmed='1', target='local', local_path='mailcow',
      password='x' * 8, schedule=plan((EVERY_DAY, '02:30')))

  def at(self, *when):
    support.FakeDatetime.current = support.FakeDatetime(*when)
    return support.FakeDatetime.current

  def test_arming(self):
    self.at(2026, 11, 8, 15, 0)
    self.assertFalse(rr.schedule_due(self.s))
    self.assertIn('state_schedule_since', self.written)
    # armed after today's time: the next day
    self.s.update(state_schedule_since=self.written['state_schedule_since'], state_schedule=self.s['schedule'])
    self.assertFalse(rr.schedule_due(self.s))
    self.at(2026, 11, 9, 2, 31)
    self.assertTrue(rr.schedule_due(self.s))
    self.jobs.append(('backup', '2026-11-09 02:31:00'))
    self.assertFalse(rr.schedule_due(self.s))

  def test_catch_up_and_time_change(self):
    armed = self.at(2026, 11, 1, 12, 0)
    self.s.update(state_schedule_since=str(int(armed.timestamp())), state_schedule=self.s['schedule'])
    self.at(2026, 11, 8, 1, 0)
    self.assertTrue(rr.schedule_due(self.s))
    self.written.clear()
    self.assertFalse(rr.schedule_due(dict(self.s, schedule=plan((EVERY_DAY, '01:00')))))
    self.assertEqual(self.written.get('state_schedule'), plan((EVERY_DAY, '01:00')))

  def test_dst(self):
    # 02:30 does not exist on 28 March 2027 in Berlin, the backup still runs that night
    armed = self.at(2027, 3, 27, 12, 0)
    self.s.update(state_schedule_since=str(int(armed.timestamp())), state_schedule=self.s['schedule'])
    self.at(2027, 3, 28, 1, 59)
    self.jobs.append(('backup', '2027-03-27 02:30:00'))
    self.assertFalse(rr.schedule_due(self.s))
    self.at(2027, 3, 28, 3, 1)
    self.assertTrue(rr.schedule_due(self.s))
    # 31 October 2027: 02:30 happens twice, one backup
    self.jobs.append(('backup', '2027-10-31 02:31:00'))
    self.at(2027, 10, 31, 2, 45)
    self.assertFalse(rr.schedule_due(self.s))

  def test_disabled(self):
    self.at(2026, 11, 8, 15, 0)
    self.assertFalse(rr.schedule_due(dict(self.s, enabled='0', state_schedule_since='1')))
    self.assertEqual(self.written.get('state_schedule_since'), '')

  def test_entries(self):
    # Monday and Wednesday at 12:00, Friday to Sunday at 18:00, Friday at 04:00; armed on Sunday 8 November 2026 19:00
    armed = self.at(2026, 11, 8, 19, 0)
    self.s.update(schedule=plan(([1, 3], '12:00'), ([5, 6, 0], '18:00'), ([5], '04:00')), state_schedule_since=str(int(armed.timestamp())))
    self.s['state_schedule'] = self.s['schedule']
    self.at(2026, 11, 9, 11, 0)
    self.assertFalse(rr.schedule_due(self.s))
    self.at(2026, 11, 9, 12, 1)
    self.assertTrue(rr.schedule_due(self.s))
    self.jobs.append(('backup', '2026-11-09 12:01:00'))
    # Tuesday: no entry
    self.at(2026, 11, 10, 15, 0)
    self.assertFalse(rr.schedule_due(self.s))
    self.jobs.append(('backup', '2026-11-11 12:01:00'))
    self.at(2026, 11, 13, 4, 1)
    self.assertTrue(rr.schedule_due(self.s))
    self.jobs.append(('backup', '2026-11-13 04:01:00'))
    self.at(2026, 11, 13, 17, 0)
    self.assertFalse(rr.schedule_due(self.s))
    self.at(2026, 11, 13, 18, 1)
    self.assertTrue(rr.schedule_due(self.s))
    # no entry, broken or wrong values: never
    for schedule in ('[]', 'not json', plan(([7], '02:30')), plan((EVERY_DAY, '25:00')), '[{"days": "1", "time": "02:30"}]'):
      self.assertFalse(rr.schedule_due(dict(self.s, schedule=schedule, state_schedule=schedule)), schedule)

  def test_gap(self):
    self.assertEqual(rr.schedule_gap(rr.schedule_slots(self.s)), 86400)
    self.assertEqual(rr.schedule_gap(rr.schedule_slots(dict(self.s, schedule=plan((EVERY_DAY, '02:30'), (EVERY_DAY, '10:00'), (EVERY_DAY, '18:00'))))), 8.5 * 3600)
    self.assertEqual(rr.schedule_gap(rr.schedule_slots(dict(self.s, schedule=plan(([0], '02:30'))))), 7 * 86400)
    # Monday 12:00 to Wednesday 12:00
    self.assertEqual(rr.schedule_gap(rr.schedule_slots(dict(self.s, schedule=plan(([1, 3], '12:00'), ([5, 6, 0], '18:00'), ([5], '04:00'))))), 2 * 86400)
    # the same time twice is one slot
    self.assertEqual(rr.schedule_slots(dict(self.s, schedule=plan(([1], '12:00'), ([1, 2], '12:00')))), [(1, 12, 0), (2, 12, 0)])
    self.assertIsNone(rr.schedule_gap(rr.schedule_slots(dict(self.s, schedule='[]'))))

  def test_after_scheduled_backup(self):
    # Sunday prune, backups Monday, Wednesday, Friday: the first backup from Sunday on prunes, a check once a day
    self.s.update(prune_weekday='0', check_subset='30')
    self.at(2026, 11, 9, 2, 35)
    rr.after_scheduled_backup(self.s)
    self.assertEqual(self.queued, ['prune', 'check'])
    self.jobs += [('prune', '2026-11-09 02:35:00'), ('check', '2026-11-09 02:35:00')]
    self.queued.clear()
    self.at(2026, 11, 9, 14, 5)
    rr.after_scheduled_backup(self.s)
    self.assertEqual(self.queued, [])
    self.at(2026, 11, 11, 2, 35)
    rr.after_scheduled_backup(self.s)
    self.assertEqual(self.queued, ['check'])
    # never prune, no check
    self.queued.clear()
    self.at(2026, 11, 16, 2, 35)
    rr.after_scheduled_backup(dict(self.s, prune_weekday='', check_subset='0'))
    self.assertEqual(self.queued, [])


class Export(Patch):
  def test_pages_merged(self):
    pages = {
      None: {'version': 1, 'domain': 'a.de', 'domain_data': {'domain': {'domain': 'a.de'}}, 'aliases': [{'address': 'x@a.de'}],
          'mailboxes': {'u1@a.de': {'mailbox': {'name': 'Ü One'}}, 'u2@a.de': {'mailbox': {'name': 'Two'}}}, 'next_after': 'u2@a.de'},
      'u2@a.de': {'mailboxes': {'u3@a.de': {'mailbox': {'name': None}}}, 'next_after': None},
    }
    calls = []

    def retried(url, body=None):
      q = urllib.parse.parse_qs(urllib.parse.urlsplit(url).query)
      calls.append(q.get('after', [None])[0])
      return json.dumps(pages[calls[-1]]).encode()

    self.patch(retried=retried, check_cancel=lambda: None)
    with tempfile.TemporaryDirectory() as folder:
      names = rr.export_domain(folder + '/a.de.json', 'a.de')
      with open(folder + '/a.de.json') as f:
        data = json.load(f)
      mode = os.stat(folder + '/a.de.json').st_mode & 0o777
    self.assertEqual(calls, [None, 'u2@a.de'])
    self.assertEqual(set(data['mailboxes']), {'u1@a.de', 'u2@a.de', 'u3@a.de'})
    self.assertEqual((data['aliases'], data['domain_data']), (pages[None]['aliases'], pages[None]['domain_data']))
    self.assertNotIn('next_after', data)
    self.assertEqual(names, {'u1@a.de': 'Ü One', 'u2@a.de': 'Two', 'u3@a.de': ''})
    self.assertEqual(mode, 0o600)

  def test_target_summary(self):
    self.assertEqual(rr.target_summary({'target': 'sftp', 'sftp_host': 'h', 'sftp_port': 22, 'sftp_user': 'u', 'sftp_path': '/p',
      'password': 'secret'}), 'sftp, sftp_host=h, sftp_port=22, sftp_user=u, sftp_path=/p')
    self.assertEqual(rr.target_summary({'target': 's3', 's3_endpoint': 'https://e', 's3_bucket': 'b', 's3_secret_key': 'x'}),
      's3, s3_endpoint=https://e, s3_bucket=b')

  def test_content_format(self):
    rows = {'FROM domain': [{'domain': 'a.de', 'created': 1700000000}, {'domain': 'b.de', 'created': 1700000001}],
      'FROM mailbox': [{'username': 'v@b.de', 'name': None}]}
    self.patch(query=lambda sql, args=None: support.Cursor(next(v for k, v in rows.items() if k in sql)))
    content = rr.snapshot_content({'a.de': {'u@a.de': 'U'}})
    self.assertEqual(content, {'domains': {'a.de': {'created': 1700000000, 'mailboxes': {'u@a.de': 'U'}},
      'b.de': {'created': 1700000001, 'mailboxes': {'v@b.de': ''}}}})
    self.assertEqual(json.loads(gzip.decompress(gzip.compress(json.dumps(content).encode()))), content)

  def test_section_aliases(self):
    # the aliases naming mailboxes come with the first page and go into the sections when the export is read
    export = {'aliases': [], 'mailbox_aliases': {'u@a.de': [{'id': 2}], 'gone@a.de': [{'id': 3}]},
      'mailboxes': {'u@a.de': {'aliases': [{'id': 1}]}, 'v@a.de': {'aliases': []}}}
    rr.section_aliases(export)
    self.assertNotIn('mailbox_aliases', export)
    self.assertEqual(export['mailboxes'], {'u@a.de': {'aliases': [{'id': 1}, {'id': 2}]}, 'v@a.de': {'aliases': []}})

  def test_own_aliases_come_with_the_mailboxes(self):
    posted = []
    self.patch(post=lambda body: posted.append(body['aliases']) or {'aliases': {'added': len(body['aliases'])}})
    job = types.SimpleNamespace(id=1, result={}, save=lambda: None, log=lambda text: None)
    export = {'mailboxes': {'u@a.de': {}}, 'aliases': [{'address': 'U@a.de', 'goto': 'u@a.de,x@ext.example'},
      {'address': 'info@a.de', 'goto': 'u@a.de'}]}
    self.patch(check_cancel=lambda: None)
    rr.restore_aliases(job, export)
    self.assertEqual(posted, [[{'address': 'info@a.de', 'goto': 'u@a.de'}]])
    self.assertEqual(job.result['aliases']['added'], 1)

  def test_snapshot_domains(self):
    # the manifest's creation times go into the row
    manifest = {'domains': ['b.de', 'a.de'], 'domains_created': {'b.de': 20, 'a.de': 10}}
    self.patch(restic=lambda job, repo, args: (0, json.dumps(manifest).encode(), b''))
    self.assertEqual(rr.snapshot_domains(None, None, {'id': 'x', 'tags': ['mailcow']}), '{"a.de": 10, "b.de": 20}')
    self.assertIsNone(rr.snapshot_domains(None, None, {'id': 'x', 'tags': []}))
    self.patch(restic=lambda job, repo, args: (1, b'', b'no such file'))
    self.assertIsNone(rr.snapshot_domains(None, None, {'id': 'x', 'tags': ['mailcow']}))


class Secrets(Patch):
  def test_seal_roundtrip_and_errors(self):
    key = nacl.public.PrivateKey.generate()
    sealed = rr.seal(key.public_key, 'pässwörd')
    self.assertTrue(sealed.startswith(rr.sealed_prefix))
    self.assertEqual(rr.unseal(key, sealed), 'pässwörd')
    rows = [{'name': 'password', 'value': sealed}, {'name': 's3_secret_key', 'value': rr.seal(nacl.public.PrivateKey.generate().public_key, 'x')}]
    self.patch(query=lambda sql, args=None: support.Cursor(rows), secrets_private_key=lambda: key)
    s = rr.settings()
    self.assertEqual(s['password'], 'pässwörd')
    self.assertEqual(s['s3_secret_key'], '')
    self.assertEqual(s['_secrets_error'], rr.secrets_error)
    with self.assertRaisesRegex(rr.JobError, 'cannot be decrypted'):
      rr.Repository(s)


class Errors(unittest.TestCase):
  def test_error_text(self):
    err = ['Stat(<config/>) returned error, retrying after 1.2s: Head "http://user:***@h:8000/r/config": no such host',
      'Stat(<config/>) returned error, retrying after 2.4s: Head "http://user:***@h:8000/r/config": no such host',
      '{"message_type":"exit_error","code":1,"message":"Fatal: unable to open config file"}']
    text = rr.error_text(err)
    self.assertEqual(text.count('no such host'), 1)
    self.assertNotIn('user', text)
    self.assertIn('Fatal: unable to open config file', text)

  def test_local_time(self):
    support.use_timezone(self, 'Europe/Berlin')
    self.assertEqual(rr.local_time('2026-11-08T02:44:44.050341463+01:00'), '2026-11-08 02:44:44')
    self.assertEqual(rr.local_time('2026-07-01T00:00:00Z'), '2026-07-01 02:00:00')
    self.assertIsNone(rr.local_time('garbage'))


if __name__ == '__main__':
  unittest.main()
