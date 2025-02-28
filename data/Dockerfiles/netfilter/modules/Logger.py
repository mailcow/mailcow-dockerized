import time
import json

class Logger:
  def __init__(self):
    self.valkey = None

  def set_valkey(self, valkey):
    self.valkey = valkey

  def log(self, priority, message):
    tolog = {}
    tolog['time'] = int(round(time.time()))
    tolog['priority'] = priority
    tolog['message'] = message
    print(message)
    if self.valkey is not None:
      try:
        self.valkey.lpush('NETFILTER_LOG', json.dumps(tolog, ensure_ascii=False))
      except Exception as ex:
        print('Failed logging to valkey: %s'  % (ex))

  def logWarn(self, message):
    self.log('warn', message)

  def logCrit(self, message):
    self.log('crit', message)

  def logInfo(self, message):
    self.log('info', message)
