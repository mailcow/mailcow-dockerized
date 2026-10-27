import ipaddress
import json
import re


class Regexes:
  """Rules for log lines (Redis F2B_REGEX): rule id => regex whose first group captures the client address"""

  DEFAULTS = {
    1: r'mailcow UI: Invalid password for .+ by ([0-9a-f\.:]+)',
    2: r'Rspamd UI: Invalid password by ([0-9a-f\.:]+)',
    3: r'warning: .*\[([0-9a-f\.:]+)\]: SASL .+ authentication failed: (?!.*Connection lost to authentication server).+',
    4: r'warning: non-SMTP command from .*\[([0-9a-f\.:]+)]:.+',
    5: r'NOQUEUE: reject: RCPT from \[([0-9a-f\.:]+)].+Protocol error.+',
    6: r'\w+\([^,]+,([0-9a-f\.:]+),<[^>]+>\): Password mismatch \(SHA1 of given password: [a-f0-9]+\)',
    7: r'\w+\([^,]+,([0-9a-f\.:]+),<[^>]+>\): unknown user \(SHA1 of given password: [a-f0-9]+\)',
    8: r'SOGo.+ Login from \'([0-9a-f\.:]+)\' for user .+ might not have worked',
    9: r'([0-9a-f\.:]+) \"GET \/SOGo\/.* HTTP.+\" 403 .+',
  }
  # Dovecot could not ask the auth backend (identity provider down, mailcowauth.php unreachable). Postfix logs
  # such a temporary failure like a wrong password, so failed logins of the address are not counted for a while.
  TEMPORARY_FAILURE = re.compile(r'lua\([^,]+,([0-9a-f\.:]+)(?:,<[^>]+>)?\): passdb-lua: Upstream (?:unavailable|unreachable)')

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
    for rule_id, regex in self.rules:
      result = regex.search(line)
      if result and result.lastindex and result.group(1):
        return rule_id, result.group(1)
    return None

  @classmethod
  def temporary_failure(cls, line):
    """Address of a login that failed because the auth backend was unavailable, None for other lines"""
    result = cls.TEMPORARY_FAILURE.search(line)
    return result.group(1) if result else None

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
