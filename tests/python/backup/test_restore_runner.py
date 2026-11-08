# Pure functions of the dovecot restore runner (data/Dockerfiles/dovecot/restore_runner.py)
import os
import tempfile
import unittest

import restic_runner
import restore_runner as rr


class FolderNames(unittest.TestCase):
  def test_maildir_plus_plus(self):
    self.assertEqual(rr.folder_name(''), 'INBOX')
    self.assertEqual(rr.folder_name('.Sent'), 'Sent')
    self.assertEqual(rr.folder_name('.A.B.C'), 'A/B/C')

  def test_listescape_dot(self):
    self.assertEqual(rr.folder_name('.a\\2eb'), 'a.b')
    self.assertEqual(rr.folder_name('.rt\\2ea\\2eb.Kind'), 'rt.a.b/Kind')

  def test_modified_utf7(self):
    self.assertEqual(rr.folder_name('.Entw&APw-rfe'), 'Entwürfe')
    self.assertEqual(rr.folder_name('.Tom &- Jerry'), 'Tom & Jerry')
    self.assertEqual(rr.mutf7_decode('&ZeVnLIqe-'), '日本語')

  def test_subscriptions_file(self):
    with tempfile.TemporaryDirectory() as folder:
      with open(os.path.join(folder, 'subscriptions'), 'w') as f:
        f.write('V\t2\n\nSent\nEntw&APw-rfe\tUnter\\2ePunkt\nA\tB\n')
      self.assertEqual(rr.backup_subscriptions(folder), ['Sent', 'Entwürfe/Unter.Punkt', 'A/B'])

  def test_subscriptions_version_1(self):
    with tempfile.TemporaryDirectory() as folder:
      with open(os.path.join(folder, 'subscriptions'), 'w') as f:
        f.write('Sent\nA.B\n')
      self.assertEqual(rr.backup_subscriptions(folder), ['Sent', 'A/B'])


class Acl(unittest.TestCase):
  def test_entries(self):
    with tempfile.NamedTemporaryFile('w', delete=False) as f:
      f.write('user=a@x.de lr\n-user=b@x.de l\ngroup=mcg_1 lrwstipekxa\nuser=c@y.de lr :custom\nbroken\n')
    try:
      self.assertEqual(rr.acl_entries(f.name), [
        ('user=a@x.de', ['lookup', 'read']),
        ('group=mcg_1', ['lookup', 'read', 'write', 'write-seen', 'write-deleted', 'insert', 'post', 'expunge', 'create',
          'delete', 'admin']),
        ('user=c@y.de', ['lookup', 'read', ':custom'])])
    finally:
      os.unlink(f.name)

  def test_scope(self):
    acl = rr.AclRestore(('Shared/',), {'x.de'})
    self.assertTrue(acl.in_scope('a@x.de'))
    self.assertFalse(acl.in_scope('a@y.de'))
    self.assertEqual(acl.shown('user=a@y.de'), 'user=(other domain)')
    self.assertEqual(acl.shown('user=a@x.de'), 'user=a@x.de')
    self.assertTrue(rr.AclRestore(('Shared/',), None).in_scope('a@y.de'))


class Messages(unittest.TestCase):
  def test_message_key(self):
    self.assertEqual(rr.message_key({'hdr.message-id': ' <abc@x>\n', 'date.sent': 'd', 'size.physical': '1'}), 'id:abc@x')
    self.assertEqual(rr.message_key({'hdr.message-id': '', 'date.sent': '2026-11-08 01:00:00 (+0100)', 'size.physical': '159'}),
      'date:2026-11-08 01:00:00 (+0100):159')

  def test_present_matching(self):
    present = {rr.message_key(r) for r in ({'hdr.message-id': '<a@x>'}, {'hdr.message-id': '', 'date.sent': 'd', 'size.physical': '5'})}
    staged = [{'uid': '1', 'hdr.message-id': '<a@x>'}, {'uid': '2', 'hdr.message-id': '<b@x>'},
      {'uid': '3', 'hdr.message-id': '', 'date.sent': 'd', 'size.physical': '5'},
      {'uid': '4', 'hdr.message-id': '', 'date.sent': 'd', 'size.physical': '6'}]
    self.assertEqual([r['uid'] for r in staged if rr.message_key(r) not in present], ['2', '4'])

  def test_uid_chunks(self):
    self.assertEqual(rr.uid_chunks([5, 1, 2, 3, 9]), ['1:3,5,9'])
    self.assertEqual(rr.uid_chunks([]), [])
    chunks = rr.uid_chunks(range(1, 2 * rr.uid_ranges * 2, 2))
    self.assertEqual(len(chunks), 2)
    self.assertLess(max(len(c) for c in chunks), 128 * 1024)


class Space(unittest.TestCase):
  GiB = 1 << 30

  def test_reserve(self):
    self.assertEqual(rr.disk_reserve(10 * self.GiB), 2 * self.GiB)
    self.assertEqual(rr.disk_reserve(100 * self.GiB), 5 * self.GiB)

  def test_import(self):
    self.assertTrue(rr.import_space_ok(1 * self.GiB, 10 * self.GiB, 100 * self.GiB))
    self.assertFalse(rr.import_space_ok(5 * self.GiB, 10 * self.GiB, 100 * self.GiB))
    self.assertFalse(rr.import_space_ok(0, 1 * self.GiB, 10 * self.GiB))



class SameAsBackupRunner(unittest.TestCase):
  # helpers both runners have (separate images): they must give the same results
  def test_helpers(self):
    for total in (0, 10 << 30, 100 << 30, 1 << 40):
      self.assertEqual(rr.disk_reserve(total), restic_runner.disk_reserve(total))
    for size in (0, 1, 1048576, 5 << 30):
      self.assertEqual(rr.mib(size), restic_runner.mib(size))
    result = {'domain': {'errors': ['a']}, 'aliases': {'errors': ['b', 'c']}, 'mailboxes': {
      'u@d': {'error': 'gone', 'mails': {'errors': ['x']}, 'filters': {'errors': []}}, 'v@d': {'mails': {'added': 1}}}}
    self.assertEqual(rr.count_errors(result), 5)
    self.assertEqual(rr.count_errors(result), restic_runner.count_errors(result))


if __name__ == '__main__':
  unittest.main()
