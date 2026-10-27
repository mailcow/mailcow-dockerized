import ipaddress
import json
import unittest

from modules.Regexes import Regexes
from support import FakeLogger, FakeRedis


class RegexesTest(unittest.TestCase):
  LINES = {
    '1': ('mailcow UI: Invalid password for admin by 203.0.113.5', '203.0.113.5'),
    '2': ('Rspamd UI: Invalid password by 2001:db8::5', '2001:db8::5'),
    '3': ('warning: unknown[203.0.113.6]: SASL LOGIN authentication failed: UGFzc3dvcmQ6', '203.0.113.6'),
    '4': ('warning: non-SMTP command from unknown[203.0.113.7]: GET / HTTP/1.1', '203.0.113.7'),
    '5': ('NOQUEUE: reject: RCPT from [203.0.113.8]: 503 5.5.0 <a@example.org>: Protocol error; proto=ESMTP', '203.0.113.8'),
    '6': ('auth: lua(user@example.org,203.0.113.9,<abcdef>): Password mismatch (SHA1 of given password: 0123ab)', '203.0.113.9'),
    '7': ('auth: lua(nobody@example.org,203.0.113.10,<abcdef>): unknown user (SHA1 of given password: 0123ab)', '203.0.113.10'),
    '8': ("SOGo[123]: Login from '203.0.113.11' for user nobody might not have worked", '203.0.113.11'),
    '9': ('203.0.113.12 "GET /SOGo/so/ HTTP/1.1" 403 123 "-"', '203.0.113.12'),
  }

  def setUp(self):
    self.r = FakeRedis()
    self.logger = FakeLogger()
    self.regexes = Regexes(self.logger)

  def test_defaults_are_stored_and_match(self):
    self.regexes.refresh(self.r)
    self.assertEqual(set(json.loads(self.r.get('F2B_REGEX'))), {str(rule_id) for rule_id in Regexes.DEFAULTS})
    for rule_id, (line, address) in self.LINES.items():
      self.assertEqual(self.regexes.match(line), (rule_id, address), line)

  def test_lost_connection_to_auth_server_is_no_attempt(self):
    self.regexes.refresh(self.r)
    line = 'warning: unknown[203.0.113.6]: SASL LOGIN authentication failed: Connection lost to authentication server'
    self.assertIsNone(self.regexes.match(line))

  def test_only_the_first_matching_rule_counts(self):
    self.r.set('F2B_REGEX', json.dumps({'2': r'by ([0-9a-f\.:]+)', '10': r'from ([0-9a-f\.:]+)', '1': r'login from ([0-9a-f\.:]+)'}))
    self.regexes.refresh(self.r)
    self.assertEqual(self.regexes.match('login from 203.0.113.1 by 203.0.113.2'), ('1', '203.0.113.1'))
    self.assertEqual([rule_id for rule_id, _ in self.regexes.rules], ['1', '2', '10'])

  def test_invalid_regex_is_skipped(self):
    self.r.set('F2B_REGEX', json.dumps({'1': r'([0-9', '2': r'by ([0-9a-f\.:]+)'}))
    self.regexes.refresh(self.r)
    self.assertEqual(self.regexes.match('by 203.0.113.2'), ('2', '203.0.113.2'))
    self.assertTrue(self.logger.messages('warn'))

  def test_invalid_json_keeps_current_rules(self):
    self.regexes.refresh(self.r)
    self.r.set('F2B_REGEX', '{broken')
    self.regexes.refresh(self.r)
    self.assertEqual(len(self.regexes.rules), len(Regexes.DEFAULTS))

  def test_rules_without_group_do_not_match(self):
    self.r.set('F2B_REGEX', json.dumps({'1': r'failed', '2': r'by ([0-9a-f\.:]+)?'}))
    self.regexes.refresh(self.r)
    self.assertIsNone(self.regexes.match('failed by nobody'))

  def test_temporary_failure(self):
    worker = 'auth-worker(3933): Error: conn unix:auth-worker (pid=3932,uid=401): auth-worker<4>: '
    self.assertEqual(Regexes.temporary_failure(worker + 'lua(a@example.org,203.0.113.1,<GGexLdBezoasFgEB>): passdb-lua: Upstream unavailable'), '203.0.113.1')
    self.assertEqual(Regexes.temporary_failure(worker + 'lua(a@example.org,2001:db8::1): passdb-lua: Upstream unreachable'), '2001:db8::1')
    self.assertIsNone(Regexes.temporary_failure('auth: lua(a@example.org,203.0.113.1,<GGexLdBezoasFgEB>): unknown user (SHA1 of given password: 0123ab)'))
    self.assertIsNone(Regexes.temporary_failure('warning: unknown[203.0.113.1]: SASL LOGIN authentication failed: (reason unavailable)'))

  def test_parse_ip(self):
    self.assertEqual(Regexes.parse_ip('203.0.113.1'), ipaddress.ip_address('203.0.113.1'))
    self.assertEqual(Regexes.parse_ip('203.0.113.1:'), ipaddress.ip_address('203.0.113.1'))
    self.assertEqual(Regexes.parse_ip('2001:db8::1'), ipaddress.ip_address('2001:db8::1'))
    self.assertEqual(Regexes.parse_ip('::ffff:203.0.113.1'), ipaddress.ip_address('203.0.113.1'))
    self.assertIsNone(Regexes.parse_ip('203.0.113'))
    self.assertIsNone(Regexes.parse_ip('...'))
    self.assertIsNone(Regexes.parse_ip(''))


if __name__ == '__main__':
  unittest.main()
