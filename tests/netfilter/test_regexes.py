import ipaddress
import json
import unittest

from modules.Regexes import Regexes
from support import FakeLogger, FakeRedis


class RegexesTest(unittest.TestCase):
  LINES = {
    '1': ('mailcow UI: Invalid password for admin by 203.0.113.5', '203.0.113.5'),
    '2': ('Rspamd UI: Invalid password by 2001:db8::5', '2001:db8::5'),
    '3': ('MAILCOWAUTH: Login failed for user user@example.org with service smtp from IP 203.0.113.6', '203.0.113.6'),
    '4': ('warning: non-SMTP command from unknown[203.0.113.7]: GET _ HTTP_1.1', '203.0.113.7'),
    '5': ('NOQUEUE: reject: RCPT from [203.0.113.8]:52516: 550 5.5.1 Protocol error; from=<a@example.org> to=<b@example.org>',
          '203.0.113.8'),
    '6': ("[6714]: SOGoRootPage Login from '203.0.113.11' for user 'nobody@example.org' might not have worked - "
          "password policy: 65535  grace: -1  expire: -1  bound: 0", '203.0.113.11'),
    '7': ('203.0.113.12 "GET /SOGo/so/ HTTP/1.1" 403 123 "-"', '203.0.113.12'),
    '8': ('auth: passwd-file(master@mailcow.local,203.0.113.13,master,<qIIX9EZfYISsFgEB>): '
          'Password mismatch (SHA1 of given password: 05d839)', '203.0.113.13'),
  }
  # Defaults of 2025-02 to 2026-10 and of 2022-11 to 2025-02
  FORMER = {
    '1': r'mailcow UI: Invalid password for .+ by ([0-9a-f\.:]+)',
    '2': r'Rspamd UI: Invalid password by ([0-9a-f\.:]+)',
    '3': r'warning: .*\[([0-9a-f\.:]+)\]: SASL .+ authentication failed: (?!.*Connection lost to authentication server).+',
    '4': r'warning: non-SMTP command from .*\[([0-9a-f\.:]+)]:.+',
    '5': r'NOQUEUE: reject: RCPT from \[([0-9a-f\.:]+)].+Protocol error.+',
    '6': r'\w+\([^,]+,([0-9a-f\.:]+),<[^>]+>\): Password mismatch \(SHA1 of given password: [a-f0-9]+\)',
    '7': r'\w+\([^,]+,([0-9a-f\.:]+),<[^>]+>\): unknown user \(SHA1 of given password: [a-f0-9]+\)',
    '8': r'SOGo.+ Login from \'([0-9a-f\.:]+)\' for user .+ might not have worked',
    '9': r'([0-9a-f\.:]+) \"GET \/SOGo\/.* HTTP.+\" 403 .+',
  }
  OLDER = dict(FORMER, **{
    '6': r'-login: Disconnected.+ \(auth failed, .+\): user=.*, method=.+, rip=([0-9a-f\.:]+),',
    '7': r'-login: Aborted login.+ \(auth failed .+\): user=.+, rip=([0-9a-f\.:]+), lip.+',
    '8': r'-login: Aborted login.+ \(tried to use disallowed .+\): user=.+, rip=([0-9a-f\.:]+), lip.+',
    '9': r'SOGo.+ Login from \'([0-9a-f\.:]+)\' for user .+ might not have worked',
    '10': r'([0-9a-f\.:]+) \"GET \/SOGo\/.* HTTP.+\" 403 .+',
  })

  def setUp(self):
    self.r = FakeRedis()
    self.logger = FakeLogger()
    self.regexes = Regexes(self.logger)

  def test_defaults_are_stored_and_match(self):
    self.regexes.refresh(self.r)
    self.assertEqual(set(json.loads(self.r.get('F2B_REGEX'))), {str(rule_id) for rule_id in Regexes.DEFAULTS})
    for rule_id, (line, address) in self.LINES.items():
      self.assertEqual(self.regexes.match(line), (rule_id, address), line)

  def test_postfix_and_dovecot_lines_are_no_attempts(self):
    # mailcowauth.php reports the failed login once, also when Postfix asked Dovecot
    self.regexes.refresh(self.r)
    for line in ('warning: unknown[203.0.113.6]: SASL LOGIN authentication failed: (reason unavailable), sasl_username=a@example.org',
                 'auth-worker(37358): conn unix:auth-worker (pid=577,uid=401): auth-worker<25>: '
                 'lua(a@example.org,203.0.113.6,<j4rbQeReAqwqAPggBBcAAAAFAAEAdgES>): Password mismatch (SHA1 of given password: 6426a0)',
                 'imap-login: Disconnected: Connection closed (auth failed, 1 attempts in 2 secs): user=<a@example.org>, '
                 'method=PLAIN, rip=203.0.113.6, lip=172.22.1.250'):
      self.assertIsNone(self.regexes.match(line), line)

  def test_failed_master_logins(self):
    # Dovecot checks the master passdb itself, mailcowauth.php is not asked
    self.regexes.refresh(self.r)
    self.assertEqual(self.regexes.match('auth: passwd-file(x@mailcow.local,2001:db8::13,master,<dfbigUdfZJysFgEB>): '
                                        'unknown user (SHA1 of given password: 6d0653)'), ('8', '2001:db8::13'))
    # no session of Postfix
    self.assertEqual(self.regexes.match('auth: passwd-file(x@mailcow.local,203.0.113.13,master): '
                                        'Password mismatch (SHA1 of given password: 6d0653)'), ('8', '203.0.113.13'))
    # without the client address (doveadm) or for the user of a master login
    self.assertIsNone(self.regexes.match('auth: passwd-file(x@mailcow.local,master,<dfbigUdfZJysFgEB>): Password mismatch'))
    self.assertIsNone(self.regexes.match('auth: lua(a@example.org,203.0.113.13,master,<dfbigUdfZJysFgEB>): unknown user'))

  def test_text_of_clients_does_not_match(self):
    # Login names, SMTP commands and addresses of other lines must not ban a foreign address
    self.regexes.refresh(self.r)
    self.assertEqual(self.regexes.match('warning: non-SMTP command from unknown[198.51.100.7]: '
                                        'mailcow UI: Invalid password for x by 203.0.113.1'), ('4', '198.51.100.7'))
    self.assertEqual(self.regexes.match('warning: non-SMTP command from unknown[198.51.100.7]: x [203.0.113.1]: y'),
                     ('4', '198.51.100.7'))
    self.assertIsNone(self.regexes.match('NOQUEUE: reject: RCPT from unknown[198.51.100.7]: 554 5.7.1 Relay access denied; '
                                         'from=<NOQUEUE: reject: RCPT from [203.0.113.1] Protocol error x>'))
    self.assertIsNone(self.regexes.match('improper command pipelining after EHLO from unknown[198.51.100.7]: '
                                         'MAILCOWAUTH: Login failed for user x with service imap from IP 203.0.113.1'))
    self.assertEqual(self.regexes.match('MAILCOWAUTH: Login failed for user x with service imap from IP 203.0.113.1 '
                                        'with service smtp from IP 198.51.100.7'),
                     ('3', '198.51.100.7'))
    self.assertEqual(self.regexes.match("[1]: SOGoRootPage Login from '198.51.100.7' for user 'x' might not have worked "
                                        "SOGoRootPage Login from '203.0.113.1' for user y might not have worked"), ('6', '198.51.100.7'))
    # nginx appends the client to the X-Forwarded-For header a client sent
    self.assertEqual(self.regexes.match("[1]: SOGoRootPage Login from '203.0.113.1, 198.51.100.7' for user 'x' might not have worked"),
                     ('6', '198.51.100.7'))
    # the address Dovecot adds follows the login name
    self.assertEqual(self.regexes.match('auth: passwd-file(x,203.0.113.1,master,<a>): Password mismatch,198.51.100.7,master,<b>): '
                                        'Password mismatch (SHA1 of given password: 6d0653)'), ('8', '198.51.100.7'))
    # lines of the web UI are not sanitized like syslog lines
    self.assertEqual(self.regexes.match('mailcow UI: Invalid password for admin\n by 203.0.113.5'), ('1', '203.0.113.5'))

  def test_former_defaults_are_replaced(self):
    self.r.set('F2B_REGEX', json.dumps(self.FORMER))
    self.regexes.refresh(self.r)
    defaults = {str(rule_id): regex for rule_id, regex in Regexes.DEFAULTS.items()}
    # the rule for failed Dovecot logins counts failed master logins now
    expected = dict(defaults, **{'6': defaults['8'], '8': defaults['6'], '9': defaults['7']})
    del expected['7']
    self.assertEqual(json.loads(self.r.get('F2B_REGEX')), expected)
    self.assertEqual(self.regexes.match(self.LINES['3'][0]), ('3', '203.0.113.6'))
    self.assertEqual(self.regexes.match(self.LINES['6'][0]), ('8', '203.0.113.11'))
    self.assertEqual(self.regexes.match(self.LINES['8'][0]), ('6', '203.0.113.13'))

  def test_older_defaults_are_replaced(self):
    self.r.set('F2B_REGEX', json.dumps(self.OLDER))
    self.regexes.refresh(self.r)
    stored = json.loads(self.r.get('F2B_REGEX'))
    self.assertEqual(sorted(stored, key=int), ['1', '2', '3', '4', '5', '9', '10'])
    self.assertEqual(stored['9'], Regexes.DEFAULTS[6])
    self.assertEqual(stored['10'], self.OLDER['10'])

  def test_changed_rules_are_kept(self):
    custom = {'1': r'mailcow UI: Invalid password for (?:x|y) by ([0-9a-f\.:]+)', '2': self.FORMER['3'], '3': r'custom ([0-9\.]+)'}
    self.r.set('F2B_REGEX', json.dumps(custom))
    self.regexes.refresh(self.r)
    self.assertEqual(json.loads(self.r.get('F2B_REGEX')), dict(custom, **{'2': Regexes.DEFAULTS[3]}))
    # current rules are not written again
    raw = self.r.get('F2B_REGEX')
    self.regexes.refresh(self.r)
    self.assertEqual(self.r.get('F2B_REGEX'), raw)

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
