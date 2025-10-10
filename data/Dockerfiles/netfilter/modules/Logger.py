import time
import json
import datetime

class Logger:
  def __init__(self):
    self.valkey = None

  def set_valkey(self, valkey):
    self.valkey = valkey

  def _format_timestamp(self):
    # Local time with milliseconds
    return datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")

  def log(self, priority, message):
    # build valkey-friendly dict
    tolog = {
      'time': int(round(time.time())),  # keep raw timestamp for Valkey
      'priority': priority,
      'message': message
    }

    # print human-readable message with timestamp
    ts = self._format_timestamp()
    print(f"{ts} {priority.upper()}: {message}", flush=True)

    # also push JSON to Redis if connected
    if self.valkey is not None:
      try:
        self.valkey.lpush('NETFILTER_LOG', json.dumps(tolog, ensure_ascii=False))
      except Exception as ex:
        print(f'{ts} WARN: Failed logging to valkey: {ex}', flush=True)

  def logWarn(self, message):
    self.log('warn', message)

  def logCrit(self, message):
    self.log('crit', message)

  def logInfo(self, message):
    self.log('info', message)