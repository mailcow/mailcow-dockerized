import ipaddress


class FakeLogger:
  def __init__(self):
    self.lines = []

  def log(self, priority, message):
    self.lines.append((priority, message))

  def logWarn(self, message):
    self.log('warn', message)

  def logCrit(self, message):
    self.log('crit', message)

  def logInfo(self, message):
    self.log('info', message)

  def messages(self, priority=None):
    return [message for level, message in self.lines if priority is None or level == priority]


class FakePipeline:
  def __init__(self, r):
    self.r = r

  def get(self, key):
    return self.r.get(key)

  def multi(self):
    pass

  def set(self, key, value):
    return self.r.set(key, value)


class FakePubSub:
  """Hands out the queued messages; an Exception in the queue is raised, an empty queue sets the stop event"""

  def __init__(self, queue, stop):
    self.queue = queue
    self.stop = stop
    self.channels = []
    self.closed = False

  def subscribe(self, channel):
    self.channels.append(channel)

  def get_message(self, timeout=0.0):
    if not self.queue:
      self.stop.set()
      return None
    item = self.queue.pop(0)
    if isinstance(item, Exception):
      raise item
    return {'type': 'message', 'channel': 'F2B_CHANNEL', 'data': item}

  def close(self):
    self.closed = True


class FakeRedis:
  def __init__(self):
    self.data = {}
    self.messages = []
    self.stop = None
    self.pubsubs = []

  def get(self, key):
    return self.data.get(key)

  def set(self, key, value, nx=False):
    if nx and key in self.data:
      return None
    self.data[key] = str(value)
    return True

  def exists(self, key):
    return int(key in self.data)

  def rename(self, src, dst):
    self.data[dst] = self.data.pop(src)

  def delete(self, *keys):
    for key in keys:
      self.data.pop(key, None)

  def hset(self, key, field, value):
    self.data.setdefault(key, {})[field] = str(value)

  def hdel(self, key, *fields):
    values = self.data.get(key, {})
    for field in fields:
      values.pop(field, None)
    if not values:
      self.data.pop(key, None)

  def hkeys(self, key):
    return list(self.data.get(key, {}))

  def hgetall(self, key):
    return dict(self.data.get(key, {}))

  def lpush(self, key, value):
    self.data.setdefault(key, []).insert(0, value)

  def transaction(self, func, *watches):
    func(FakePipeline(self))

  def pubsub(self, **kwargs):
    pubsub = FakePubSub(self.messages, self.stop)
    self.pubsubs.append(pubsub)
    return pubsub


class FakeTables:
  """Backend with one set of banned networks"""

  def __init__(self):
    self.rules = set()
    self.failing = set()
    self.calls = []
    self.recreate = False

  def ban(self, sources):
    failed = []
    for source in sources:
      network = str(ipaddress.ip_network(source, strict=False))
      if network in self.failing:
        failed.append(network)
      else:
        self.rules.add(network)
    self.calls.append(('ban', list(sources)))
    return failed

  def unban(self, sources):
    removed = []
    for source in sources:
      network = str(ipaddress.ip_network(source, strict=False))
      if network in self.rules:
        self.rules.discard(network)
        removed.append(network)
    self.calls.append(('unban', list(sources)))
    return removed

  def clearIPv4Table(self):
    self.rules = {rule for rule in self.rules if ipaddress.ip_network(rule).version != 4}
    self.calls.append(('clear', 4))

  def clearIPv6Table(self):
    self.rules = {rule for rule in self.rules if ipaddress.ip_network(rule).version != 6}
    self.calls.append(('clear', 6))

  def initChainIPv4(self):
    self.calls.append(('init', 4))

  def initChainIPv6(self):
    self.calls.append(('init', 6))

  def ensure_chains(self):
    self.calls.append(('ensure_chains',))
    recreate, self.recreate = self.recreate, False
    return recreate

  def create_mailcow_isolation_rule(self, interface, ports, allow=''):
    self.calls.append(('isolation', interface, tuple(ports), allow))
    return True

  def snat4(self, target, source):
    self.calls.append(('snat4', target, source))

  def snat6(self, target, source):
    self.calls.append(('snat6', target, source))

  def names(self):
    return [call[0] for call in self.calls]


class Clock:
  def __init__(self, now=1000000.0):
    self.now = now

  def __call__(self):
    return self.now
