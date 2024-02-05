import time
import json

class Logger:
  def __init__(self):
    self.r = None

  def set_redis(self, redis):
    self.r = redis

  def log(self, priority, message):
    tolog = {}
    tolog['time'] = int(round(time.time()))
    tolog['priority'] = priority
    tolog['message'] = message
    if self.r is not None:
      self.r.lpush('NETFILTER_LOG', json.dumps(tolog, ensure_ascii=False))
    print(message)

  def logWarn(self, message):
    self.log('warn', message)

  def logCrit(self, message):
    self.log('crit', message)

  def logInfo(self, message):
    self.log('info', message)
