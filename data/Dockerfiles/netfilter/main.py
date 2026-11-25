#!/usr/bin/env python3

import os
import sys
import time
import signal
import redis
from modules.Logger import Logger
from modules.Netfilter import Netfilter

chain_name = "MAILCOW"

def connect_redis(logger):
  if os.getenv('REDIS_SLAVEOF_IP', ''):
    host, port = os.getenv('REDIS_SLAVEOF_IP'), os.getenv('REDIS_SLAVEOF_PORT', '')
  else:
    host, port = os.getenv('IPV4_NETWORK', '172.22.1') + '.249', 6379
  while True:
    try:
      # log lines are not always valid UTF-8
      r = redis.StrictRedis(host=host, port=port, db=0, password=os.environ['REDISPASS'], decode_responses=True,
                            encoding_errors='replace', socket_connect_timeout=5, socket_timeout=30)
      r.ping()
      return r
    except Exception as ex:
      logger.logWarn('Redis connection failed: %s - trying again in 3 seconds' % ex)
      time.sleep(3)

if __name__ == '__main__':
  logger = Logger()

  backend = sys.argv[1]
  if backend == "nftables":
    from modules.NFTables import NFTables
    logger.logInfo('Using NFTables backend')
    tables = NFTables(chain_name, logger)
  else:
    from modules.IPTables import IPTables
    logger.logInfo('Using IPTables backend')
    logger.logWarn(
        "DEPRECATION: iptables-legacy is deprecated and will be removed in future releases. "
        "Please switch to nftables on your host to ensure complete compatibility."
    )
    time.sleep(5)
    tables = IPTables(chain_name, logger)

  r = connect_redis(logger)
  logger.set_redis(r)

  netfilter = Netfilter(r, tables, logger, os.environ)
  signal.signal(signal.SIGTERM, lambda signum, frame: netfilter.stop.set())
  signal.signal(signal.SIGINT, lambda signum, frame: netfilter.stop.set())

  netfilter.setup()
  netfilter.run()
  netfilter.shutdown()
  sys.exit(0)
