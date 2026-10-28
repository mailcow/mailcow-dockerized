import ipaddress
import threading
import dns.exception
import dns.resolver
from modules.BanManager import BanManager
from modules.Options import Options
from modules.Regexes import Regexes


class Netfilter:
  """Runs the workers of netfilter. Every worker is a loop that logs errors and goes on, so neither a broken log
  line nor a Redis restart can stop the expiry of bans."""

  ISOLATION_INTERFACE = 'br-mailcow'
  ISOLATION_PORTS = [3306, 6379, 8983, 12345]

  def __init__(self, r, tables, logger, env):
    self.r = r
    self.tables = tables
    self.logger = logger
    self.env = env
    # reentrant, bans.reapply() runs within the chain check
    self.lock = threading.RLock()
    self.stop = threading.Event()
    self.options = Options(logger)
    self.regexes = Regexes(logger)
    self.bans = BanManager(r, tables, self.options, logger, self.lock)
    self.snat4_target = self.snat_target('SNAT_TO_SOURCE', 4)
    self.snat6_target = self.snat_target('SNAT6_TO_SOURCE', 6)
    self.resolved = {}
    self.workers = {
      'watch': (self.watch, 3),
      'purge': (self.purge, 10),
      'chains': (self.check_chains, 10),
      'lists': (self.update_lists, 60),
    }
    if self.snat4_target or self.snat6_target:
      self.workers['snat'] = (self.snat, 10)
    self.threads = {}

  def setup(self):
    if self.r.exists('F2B_LOG'):
      self.r.rename('F2B_LOG', 'NETFILTER_LOG')
    # filled again by the lists worker
    self.r.delete('F2B_PERM_BANS')
    self.options.refresh(self.r)
    self.regexes.refresh(self.r)
    with self.lock:
      self.tables.clearIPv4Table()
      self.tables.clearIPv6Table()
      self.logger.logInfo('Initializing mailcow netfilter chain')
      self.tables.initChainIPv4()
      self.tables.initChainIPv6()
      self.isolate()
    self.bans.restore()

  def run(self):
    for name in self.workers:
      self.start(name)
    while not self.stop.wait(5):
      for name, thread in list(self.threads.items()):
        if not thread.is_alive():
          self.logger.logCrit('Worker %s stopped unexpectedly, restarting it' % name)
          self.start(name)

  def shutdown(self):
    """Removes the firewall rules, the active bans stay in Redis and are restored on the next start"""
    self.stop.set()
    for thread in self.threads.values():
      thread.join(timeout=5)
    with self.lock:
      self.logger.logInfo('Clearing all bans')
      self.tables.clearIPv4Table()
      self.tables.clearIPv6Table()
    try:
      self.r.delete('F2B_PERM_BANS')
    except Exception as ex:
      self.logger.logWarn('Error clearing redis key F2B_PERM_BANS: %s' % ex)

  def start(self, name):
    task, interval = self.workers[name]
    thread = threading.Thread(target=self.loop, args=(name, task, interval), name=name, daemon=True)
    self.threads[name] = thread
    thread.start()

  def loop(self, name, task, interval):
    while not self.stop.is_set():
      try:
        task()
      except Exception as ex:
        self.logger.logWarn('Error in worker %s: %s' % (name, ex))
      self.stop.wait(interval)

  def watch(self):
    pubsub = self.r.pubsub(ignore_subscribe_messages=True)
    try:
      pubsub.subscribe('F2B_CHANNEL')
      self.logger.logInfo('Watching Redis channel F2B_CHANNEL')
      while not self.stop.is_set():
        message = pubsub.get_message(timeout=1.0)
        if message and message['type'] == 'message' and message['data']:
          self.handle_line(message['data'])
    finally:
      try:
        pubsub.close()
      except Exception:
        pass

  def handle_line(self, line):
    # a broken line must not end the watcher
    try:
      excused = Regexes.temporary_failure(line)
      if excused is not None:
        ip = Regexes.parse_ip(excused)
        if self.external(ip):
          self.bans.excuse(ip)
        return
      result = self.regexes.match(line)
      if result is None:
        return
      rule_id, address = result
      ip = Regexes.parse_ip(address)
      if ip is None:
        self.logger.logWarn('Rule id %s matched an invalid address %s (%s)' % (rule_id, address, line))
        return
      if not self.external(ip):
        return
      self.logger.logWarn('%s matched rule id %s (%s)' % (ip, rule_id, line))
      self.bans.attempt(ip)
    except Exception as ex:
      self.logger.logWarn('Error processing log line %r: %s' % (line, ex))

  @staticmethod
  def external(ip):
    # private and loopback addresses are never banned
    return ip is not None and not (ip.is_private or ip.is_loopback)

  def purge(self):
    self.options.refresh(self.r)
    self.regexes.refresh(self.r)
    self.bans.purge()

  def check_chains(self):
    with self.lock:
      if self.tables.ensure_chains():
        self.isolate()
        self.bans.reapply()

  def snat(self):
    with self.lock:
      if self.snat4_target:
        self.tables.snat4(self.snat4_target, self.env.get('IPV4_NETWORK', '172.22.1') + '.0/24')
      if self.snat6_target:
        self.tables.snat6(self.snat6_target, self.env.get('IPV6_NETWORK', 'fd4d:6169:6c63:6f77::/64'))

  def update_lists(self):
    self.bans.set_allowlist(self.resolve(self.r.hkeys('F2B_WHITELIST')))
    self.bans.set_denylist(self.resolve(self.r.hkeys('F2B_BLACKLIST')))

  def isolate(self):
    if self.env.get('DISABLE_NETFILTER_ISOLATION_RULE', '').lower() in ('y', 'yes'):
      self.logger.logInfo('Skipping MAILCOW isolation')
      return
    self.logger.logInfo('Setting MAILCOW isolation')
    self.tables.create_mailcow_isolation_rule(self.ISOLATION_INTERFACE, self.ISOLATION_PORTS, self.env.get('MAILCOW_REPLICA_IP', ''))

  def resolve(self, entries):
    """Networks of the entries; hostnames are resolved, on DNS errors to their last known addresses"""
    resolver = dns.resolver.Resolver()
    networks = set()
    for entry in entries:
      if BanManager.network(entry) is not None:
        networks.add(entry)
        continue
      addresses = set()
      for rdtype in ['A', 'AAAA']:
        try:
          answer = resolver.resolve(qname=entry, rdtype=rdtype, lifetime=3)
        except (dns.resolver.NXDOMAIN, dns.resolver.NoAnswer):
          continue
        except dns.exception.DNSException as ex:
          self.logger.logInfo('Could not resolve %s: %s' % (entry, ex))
          addresses = self.resolved.get(entry, set())
          break
        addresses.update(rdata.to_text() for rdata in answer)
      self.resolved[entry] = addresses
      networks |= addresses
    return networks

  def snat_target(self, name, version):
    value = self.env.get(name, '')
    if not value or value == 'n':
      return None
    try:
      if ipaddress.ip_address(value).version == version:
        return value
    except ValueError:
      pass
    self.logger.logWarn('%s is not a valid IPv%d address' % (value, version))
    return None
