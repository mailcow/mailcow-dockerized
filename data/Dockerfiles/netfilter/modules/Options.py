import json
import uuid


class Options:
  """Fail2ban options of the mailcow UI (Redis F2B_OPTIONS). The values are replaced as a whole and never changed
  in place, so a thread that took them (options.values) keeps a consistent set."""

  DEFAULTS = {
    'ban_time': 1800,
    'max_ban_time': 10000,
    'ban_time_increment': True,
    'max_attempts': 10,
    'retry_window': 600,
    'netban_ipv4': 32,
    'netban_ipv6': 128,
    'manage_external': 0,
  }
  # Single keys of old installations, read while F2B_OPTIONS does not exist
  LEGACY_KEYS = {
    'ban_time': 'F2B_BAN_TIME',
    'max_ban_time': 'F2B_MAX_BAN_TIME',
    'ban_time_increment': 'F2B_BAN_TIME_INCREMENT',
    'max_attempts': 'F2B_MAX_ATTEMPTS',
    'retry_window': 'F2B_RETRY_WINDOW',
    'netban_ipv4': 'F2B_NETBAN_IPV4',
    'netban_ipv6': 'F2B_NETBAN_IPV6',
  }

  def __init__(self, logger):
    self.logger = logger
    self.values = self.normalize({})

  def refresh(self, r):
    """Reads the options of the UI; options it lacks are added to Redis once, existing ones are never overwritten"""
    raw = r.get('F2B_OPTIONS')
    if raw:
      try:
        stored = json.loads(raw)
      except ValueError as ex:
        self.logger.logWarn('F2B_OPTIONS is no valid JSON, keeping the current options: %s' % ex)
        return
      if not isinstance(stored, dict):
        self.logger.logWarn('F2B_OPTIONS is no JSON object, keeping the current options')
        return
    else:
      stored = {key: r.get(name) for key, name in self.LEGACY_KEYS.items()}
      stored = {key: value for key, value in stored.items() if value is not None}

    values = self.normalize(stored)
    self.values = values
    # First start or old installation: store the complete set, else only what the UI has not saved yet
    missing = {key: value for key, value in values.items() if not raw or stored.get(key) is None}
    if missing:
      self.add_missing(r, missing)

  def ban_time(self, ban_counter):
    """Seconds of the next ban of a network that was banned ban_counter times before"""
    values = self.values
    ban_time = values['ban_time']
    if values['ban_time_increment']:
      # the exponent is capped, max_ban_time limits the result anyway
      ban_time = ban_time * 2 ** min(ban_counter, 32)
    return max(values['ban_time'], min(ban_time, values['max_ban_time']))

  @classmethod
  def normalize(cls, stored):
    values = dict(stored)
    for key, default in cls.DEFAULTS.items():
      if isinstance(default, bool):
        values[key] = cls.to_bool(stored.get(key), default)
      else:
        values[key] = cls.to_int(stored.get(key), default)
    values['ban_time'] = max(1, values['ban_time'])
    values['max_ban_time'] = max(1, values['max_ban_time'])
    values['max_attempts'] = max(1, values['max_attempts'])
    values['retry_window'] = max(1, values['retry_window'])
    # A short prefix would ban whole regions or everything (/0)
    values['netban_ipv4'] = min(32, max(8, values['netban_ipv4']))
    values['netban_ipv6'] = min(128, max(8, values['netban_ipv6']))
    values['manage_external'] = 1 if values['manage_external'] == 1 else 0
    if not values.get('banlist_id'):
      values['banlist_id'] = str(uuid.uuid4())
    return values

  @staticmethod
  def to_int(value, default):
    try:
      return int(value)
    except (TypeError, ValueError):
      return default

  @staticmethod
  def to_bool(value, default):
    if isinstance(value, bool):
      return value
    if isinstance(value, (int, float)):
      return value != 0
    if isinstance(value, str):
      return value.strip().lower() in ('1', 'true', 'yes', 'on')
    return default

  def add_missing(self, r, missing):
    # WATCH keeps a save of the UI between reading and writing
    def fill(pipe):
      raw = pipe.get('F2B_OPTIONS')
      current = json.loads(raw) if raw else {}
      if not isinstance(current, dict):
        return
      added = {key: value for key, value in missing.items() if current.get(key) is None}
      if not added:
        return
      current.update(added)
      pipe.multi()
      pipe.set('F2B_OPTIONS', json.dumps(current, ensure_ascii=False))

    try:
      r.transaction(fill, 'F2B_OPTIONS')
    except Exception as ex:
      self.logger.logWarn('Could not add default options to F2B_OPTIONS: %s' % ex)
