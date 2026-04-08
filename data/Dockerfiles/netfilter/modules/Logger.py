import time
import json
import datetime

class Logger:
  def __init__(self):
    self.r = None

  def set_redis(self, redis):
    self.r = redis

  def _format_timestamp(self):
    # Local time with milliseconds
    return datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")

  def log(self, priority, message):
    # build redis-friendly dict
    tolog = {
      'time': int(round(time.time())),  # keep raw timestamp for Redis
      'priority': priority,
      'message': message
    }

    # print human-readable message with timestamp
    ts = self._format_timestamp()
    print(f"{ts} {priority.upper()}: {message}", flush=True)

    # also push JSON to Redis if connected
    if self.r is not None:
      try:
        self.r.lpush('NETFILTER_LOG', json.dumps(tolog, ensure_ascii=False))
      except Exception as ex:
        print(f'{ts} WARN: Failed logging to redis: {ex}', flush=True)

  def logWarn(self, message):
    self.log('warn', message)

  def logCrit(self, message):
    self.log('crit', message)

  def logInfo(self, message):
    self.log('info', message)