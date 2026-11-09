# Shared helpers of the backup runner tests: fake database rows and a settable clock
import datetime
import os
import time


class Cursor:
  def __init__(self, rows):
    self.rows = rows
    self.rowcount = len(rows)
    self.lastrowid = 1

  def fetchone(self):
    return self.rows[0] if self.rows else None

  def fetchall(self):
    return self.rows

  def __iter__(self):
    return iter(self.rows)


class FakeDatetime(datetime.datetime):
  current = None

  @classmethod
  def now(cls, tz=None):
    return cls.current


class Clock:
  # datetime module for a runner: datetime.now() returns the set time
  timedelta = datetime.timedelta
  date = datetime.date
  datetime = FakeDatetime


# Local time zone of the process for one test (cleaned up with the test case); the fake clock is reset as well
def use_timezone(test, name):
  old = os.environ.get('TZ')

  def restore():
    if old is None:
      os.environ.pop('TZ', None)
    else:
      os.environ['TZ'] = old
    time.tzset()
    FakeDatetime.current = None
  test.addCleanup(restore)
  os.environ['TZ'] = name
  time.tzset()
