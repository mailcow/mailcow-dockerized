import ipaddress
import json
import re


class Regexes:
  """Rules for log lines (Redis F2B_REGEX): rule id => regex whose first group captures the client address"""

  # Anchored to the line start: text clients control in other lines (login names, SMTP commands) must not match.
  # Failed IMAP, POP3, Sieve and SMTP logins are reported by mailcowauth.php (rule 3), not by Dovecot or Postfix.
  # Only the master passdb is checked by Dovecot itself (rule 8); the last address is the one Dovecot added.
  DEFAULTS = {
    1: r'^mailcow UI: Invalid password for .+ by ([0-9a-f\.:]+)$',
    2: r'^Rspamd UI: Invalid password by ([0-9a-f\.:]+)$',
    3: r'^MAILCOWAUTH: Login failed for user .+ with service \w* from IP ([0-9a-f\.:]+)$',
    4: r'^warning: non-SMTP command from [^\[\]\s]*\[([0-9a-f\.:]+)\]:.+',
    5: r'^NOQUEUE: reject: RCPT from \[([0-9a-f\.:]+)].+Protocol error.+',
    6: r'^(?:\[\d+\]: )?SOGo\w* Login from \'(?:[0-9a-f\.:]+, )*([0-9a-f\.:]+)\' for user .+ might not have worked',
    7: r'([0-9a-f\.:]+) \"GET \/SOGo\/.* HTTP.+\" 403 .+',
    8: r'^auth: passwd-file\(.*,([0-9a-f\.:]+),master(?:,<[^<>]*>)?\): (?:Password mismatch|unknown user)',
  }
  # Former default rules (with any rule id) => rule id of their replacement, None: the rule is removed.
  # Rules the admin changed are kept.
  FORMER_DEFAULTS = {
    r'mailcow UI: Invalid password for .+ by ([0-9a-f\.:]+)': 1,
    r'Rspamd UI: Invalid password by ([0-9a-f\.:]+)': 2,
    r'warning: .*\[([0-9a-f\.:]+)\]: SASL .+ authentication failed': 3,
    r'warning: .*\[([0-9a-f\.:]+)\]: SASL .+ authentication failed: (?!.*Connection lost to authentication server).+': 3,
    r'warning: non-SMTP command from .*\[([0-9a-f\.:]+)]:.+': 4,
    r'NOQUEUE: reject: RCPT from \[([0-9a-f\.:]+)].+Protocol error.+': 5,
    r"SOGo.+ Login from '([0-9a-f\.:]+)' for user .+ might not have worked": 6,
    r'SOGo.+ Login from \'([0-9a-f\.:]+)\' for user .+ might not have worked': 6,
    r'-login: Disconnected \(auth failed, .+\): user=.*, method=.+, rip=([0-9a-f\.:]+),': None,
    r'-login: Disconnected.+ \(auth failed, .+\): user=.*, method=.+, rip=([0-9a-f\.:]+),': None,
    r'-login: Aborted login \(auth failed .+\): user=.+, rip=([0-9a-f\.:]+), lip.+': None,
    r'-login: Aborted login.+ \(auth failed .+\): user=.+, rip=([0-9a-f\.:]+), lip.+': None,
    r'-login: Aborted login \(tried to use disallowed .+\): user=.+, rip=([0-9a-f\.:]+), lip.+': None,
    r'-login: Aborted login.+ \(tried to use disallowed .+\): user=.+, rip=([0-9a-f\.:]+), lip.+': None,
    r'auth: static\([^,]+,([0-9a-f\.:]+),<[^>]+>\): Password mismatch \(SHA1 of given password: [a-f0-9]+\)': 8,
    r'auth: \w+\([^,]+,([0-9a-f\.:]+),<[^>]+>\): Password mismatch \(SHA1 of given password: [a-f0-9]+\)': 8,
    r'\w+\([^,]+,([0-9a-f\.:]+),<[^>]+>\): Password mismatch \(SHA1 of given password: [a-f0-9]+\)': 8,
    r'\w+\([^,]+,([0-9a-f\.:]+),<[^>]+>\): unknown user \(SHA1 of given password: [a-f0-9]+\)': None,
  }
  # Lines of the web UI are not sanitized like syslog lines: a newline in a login name must not hide the address
  CONTROL_CHARS = re.compile(r'[\x00-\x1f\x7f]')

  def __init__(self, logger):
    self.logger = logger
    self.rules = []
    self.source = None

  def refresh(self, r):
    """Compiles the rules of the UI again when they changed; invalid rules are skipped"""
    raw = r.get('F2B_REGEX')
    if not raw:
      raw = json.dumps(self.DEFAULTS, ensure_ascii=False)
      r.set('F2B_REGEX', raw, nx=True)
    if raw == self.source:
      return
    try:
      stored = json.loads(raw)
    except ValueError as ex:
      self.logger.logWarn('F2B_REGEX is no valid JSON, keeping the current rules: %s' % ex)
      return
    if not isinstance(stored, dict):
      self.logger.logWarn('F2B_REGEX is no JSON object, keeping the current rules')
      return
    migrated = self.migrate(stored)
    if migrated != stored:
      self.store_migrated(r)
      stored = migrated

    rules = []
    for rule_id, regex in sorted(stored.items(), key=lambda item: self.sort_key(item[0])):
      try:
        rules.append((str(rule_id), re.compile(regex)))
      except (re.error, TypeError) as ex:
        self.logger.logWarn('Skipping regex of rule id %s: %s' % (rule_id, ex))
    self.rules = rules
    self.source = raw

  def match(self, line):
    """(rule id, captured address) of the first rule that matches the line, None if no rule matches"""
    line = self.CONTROL_CHARS.sub('_', line)
    for rule_id, regex in self.rules:
      result = regex.search(line)
      if result and result.lastindex and result.group(1):
        return rule_id, result.group(1)
    return None

  @classmethod
  def migrate(cls, stored):
    """The stored rules with former default rules replaced by the current ones (or removed)"""
    migrated = {}
    for rule_id, regex in stored.items():
      if isinstance(regex, str) and regex in cls.FORMER_DEFAULTS:
        replacement = cls.FORMER_DEFAULTS[regex]
        if replacement is None:
          continue
        regex = cls.DEFAULTS[replacement]
      migrated[rule_id] = regex
    return migrated

  def store_migrated(self, r):
    # WATCH keeps a save of the UI between reading and writing
    def replace(pipe):
      current = json.loads(pipe.get('F2B_REGEX') or '{}')
      if not isinstance(current, dict):
        return
      migrated = self.migrate(current)
      if migrated == current:
        return
      pipe.multi()
      pipe.set('F2B_REGEX', json.dumps(migrated, ensure_ascii=False))

    try:
      r.transaction(replace, 'F2B_REGEX')
      self.logger.logInfo('Replaced former default rules in F2B_REGEX')
    except Exception as ex:
      self.logger.logWarn('Could not replace former default rules in F2B_REGEX: %s' % ex)

  @staticmethod
  def parse_ip(text):
    """IP address of a captured address, IPv4-mapped IPv6 as IPv4; a trailing colon (e.g. "1.2.3.4:") is
    tolerated. None if the text is no address."""
    for candidate in (text, text.rstrip(':')):
      try:
        ip = ipaddress.ip_address(candidate)
      except ValueError:
        continue
      if ip.version == 6 and ip.ipv4_mapped:
        return ip.ipv4_mapped
      return ip
    return None

  @staticmethod
  def sort_key(rule_id):
    try:
      return (0, int(rule_id))
    except (TypeError, ValueError):
      return (1, str(rule_id))
