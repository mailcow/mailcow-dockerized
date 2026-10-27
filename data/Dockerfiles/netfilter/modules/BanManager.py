import ipaddress
import time


class BanManager:
  """Failed attempts and automatic bans (Redis F2B_ACTIVE_BANS: network => expiry), the denylist (F2B_PERM_BANS)
  and the allowlist. Every firewall change runs under lock, which also guards the other firewall work of netfilter
  (chains, SNAT), because the backends must not run in parallel."""

  # Seconds failed logins of an address are not counted after the auth backend was unavailable for it
  EXCUSE_TIME = 60

  def __init__(self, r, tables, options, logger, lock, clock=time.time):
    self.r = r
    self.tables = tables
    self.options = options
    self.logger = logger
    self.lock = lock
    self.clock = clock
    self.counters = {}
    self.banned = set()
    self.denied = {}
    self.allowlist = []
    self.excused = {}

  def excuse(self, ip):
    """The auth backend could not check a login of ip: its failed logins are not counted for EXCUSE_TIME"""
    with self.lock:
      now = self.clock()
      if self.excused.get(ip, 0) <= now:
        self.logger.logWarn('Auth backend unavailable for %s, its failed logins of the next %d seconds are not counted' % (
          ip, self.EXCUSE_TIME))
      self.excused[ip] = now + self.EXCUSE_TIME

  def attempt(self, ip):
    """Counts a failed attempt of ip and bans its network after max_attempts within retry_window"""
    options = self.options.values
    prefix = options['netban_ipv4'] if ip.version == 4 else options['netban_ipv6']
    net = str(ipaddress.ip_network('%s/%d' % (ip, prefix), strict=False))
    with self.lock:
      now = self.clock()
      allowed = self.allowed_by(ip)
      if allowed is not None:
        self.logger.logInfo('Address %s is allowlisted by rule %s' % (ip, allowed))
        return
      if self.excused.get(ip, 0) > now:
        self.logger.logInfo('Not counting the failed login of %s, the auth backend was unavailable' % ip)
        return
      if net in self.banned:
        # log lines of connections from before the ban
        return
      counter = self.counter(net)
      if now - counter['last_attempt'] > options['retry_window']:
        counter['attempts'] = 0
      counter['attempts'] += 1
      counter['last_attempt'] = now
      if counter['attempts'] < options['max_attempts']:
        self.logger.logWarn('%d more attempts in the next %d seconds until %s is banned' % (
          options['max_attempts'] - counter['attempts'], options['retry_window'], net))
        return

      ban_time = self.options.ban_time(counter['ban_counter'])
      self.logger.logCrit('Banning %s for %d minutes' % (net, ban_time / 60))
      # Redis first: a firewall rule without expiry would never be lifted
      self.r.hset('F2B_ACTIVE_BANS', net, int(round(now + ban_time)))
      self.banned.add(net)
      if options['manage_external'] != 1:
        self.apply([net])

  def unban(self, net):
    """Lifts the automatic ban of a network; the rule stays while the denylist bans the same network"""
    with self.lock:
      normalized = self.normalize(net)
      if normalized is None:
        self.logger.logWarn('Removing invalid network %s from the active bans' % net)
      else:
        self.logger.logInfo('Unbanning %s' % net)
        if normalized not in self.denied:
          self.remove([normalized])
      self.r.hdel('F2B_ACTIVE_BANS', net)
      self.r.hdel('F2B_QUEUE_UNBAN', net)
      if net in self.banned:
        self.banned.discard(net)
        counter = self.counter(net)
        counter['attempts'] = 0
        counter['ban_counter'] += 1

  def unban_queued(self, entry):
    """Unban request of the UI: a banned network, or a network that contains banned ones (allowlisting)"""
    network = self.network(entry)
    if network is None:
      self.logger.logWarn('Removing invalid network %s from the unban queue' % entry)
      self.r.hdel('F2B_QUEUE_UNBAN', entry)
      return
    with self.lock:
      banned = set(self.r.hkeys('F2B_ACTIVE_BANS')) | self.banned
      targets = [net for net in banned if self.overlaps(net, network)]
      for net in targets:
        self.unban(net)
      if not targets and str(network) not in self.denied:
        # a rule without known ban, e.g. left by an older netfilter
        if self.remove([str(network)]):
          self.logger.logInfo('Unbanning %s' % network)
        else:
          self.logger.logInfo('%s is not banned, removing it from the unban queue' % network)
      self.r.hdel('F2B_QUEUE_UNBAN', entry)

  def purge(self):
    """Processes the unban queue of the UI and lifts expired bans"""
    for entry in self.r.hkeys('F2B_QUEUE_UNBAN'):
      self.unban_queued(entry)
    now = self.clock()
    for net, expires in self.r.hgetall('F2B_ACTIVE_BANS').items():
      try:
        expired = self.network(net) is None or float(expires) <= now
      except (TypeError, ValueError):
        expired = True
      if expired:
        self.unban(net)
    with self.lock:
      # bans whose Redis entry is gone, e.g. deleted by hand
      for net in self.banned - set(self.r.hkeys('F2B_ACTIVE_BANS')):
        self.unban(net)
    self.forget_idle(now)

  def restore(self):
    """Applies the unexpired automatic bans of Redis again after a start of netfilter and drops the expired ones"""
    now = self.clock()
    restored = []
    with self.lock:
      for net, expires in self.r.hgetall('F2B_ACTIVE_BANS').items():
        try:
          valid = self.network(net) is not None and float(expires) > now
        except (TypeError, ValueError):
          valid = False
        if not valid:
          self.r.hdel('F2B_ACTIVE_BANS', net)
          continue
        self.banned.add(net)
        self.counter(net)
        restored.append(net)
      if restored and self.options.values['manage_external'] != 1:
        self.apply(restored)
    if restored:
      self.logger.logInfo('Restored %d active bans' % len(restored))

  def reapply(self):
    """Applies all bans again, e.g. after the MAILCOW chain had to be recreated"""
    with self.lock:
      if self.options.values['manage_external'] != 1:
        self.apply(list(self.banned) + list(self.denied))

  def set_denylist(self, entries):
    """Bans the networks of the denylist permanently and lifts the bans of removed entries"""
    networks = {}
    for entry in entries:
      net = self.normalize(entry)
      if net is None:
        self.logger.logWarn('Skipping invalid denylist entry %s' % entry)
        continue
      networks[net] = entry
    with self.lock:
      if networks.keys() == self.denied.keys():
        return
      self.logger.logInfo('Denylist was changed, it has %d entries' % len(networks))
      added = [net for net in networks if net not in self.denied]
      removed = [net for net in self.denied if net not in networks]
      if added and self.options.values['manage_external'] != 1:
        self.apply(added)
      for net in added:
        self.r.hset('F2B_PERM_BANS', networks[net], int(round(self.clock())))
        self.logger.logCrit('Added host/network %s to denylist' % networks[net])
      # an automatic ban of the same network keeps its rule
      auto_banned = {self.normalize(net) for net in self.banned}
      self.remove([net for net in removed if net not in auto_banned])
      for net in removed:
        self.r.hdel('F2B_PERM_BANS', self.denied[net])
        self.logger.logCrit('Removed host/network %s from denylist' % self.denied[net])
      self.denied = networks

  def set_allowlist(self, entries):
    networks = []
    for entry in entries:
      network = self.network(entry)
      if network is None:
        self.logger.logWarn('Skipping invalid allowlist entry %s' % entry)
        continue
      networks.append(network)
    with self.lock:
      if set(networks) != set(self.allowlist):
        self.allowlist = networks
        self.logger.logInfo('Allowlist was changed, it has %d entries' % len(networks))

  def allowed_by(self, ip):
    address = ipaddress.ip_network(ip)
    for network in self.allowlist:
      if network.version == address.version and network.overlaps(address):
        return network
    return None

  def counter(self, net):
    return self.counters.setdefault(net, {'attempts': 0, 'last_attempt': 0.0, 'ban_counter': 0})

  def forget_idle(self, now):
    """Drops counters of networks that were never banned and had no attempt within the retry window"""
    window = self.options.values['retry_window']
    with self.lock:
      for net in [net for net, counter in self.counters.items()
                  if net not in self.banned and counter['ban_counter'] == 0 and now - counter['last_attempt'] > window]:
        del self.counters[net]
      for ip in [ip for ip, until in self.excused.items() if until <= now]:
        del self.excused[ip]

  def apply(self, nets):
    for net in self.tables.ban(nets):
      self.logger.logWarn('Could not add the firewall rule for %s' % net)

  def remove(self, nets):
    return self.tables.unban(nets) if nets else []

  @staticmethod
  def network(entry):
    try:
      return ipaddress.ip_network(entry, strict=False)
    except (TypeError, ValueError):
      return None

  @classmethod
  def normalize(cls, entry):
    network = cls.network(entry)
    return str(network) if network is not None else None

  @classmethod
  def overlaps(cls, net, network):
    other = cls.network(net)
    return other is not None and other.version == network.version and other.overlaps(network)
