#!/usr/bin/env python3

import re
import os
import sys
import time
import atexit
import signal
import ipaddress
from collections import Counter
from random import randint
from threading import Thread
from threading import Lock
import redis
import json
import dns.resolver
import dns.exception
import uuid
from modules.Logger import Logger
from modules.IPTables import IPTables
from modules.NFTables import NFTables


# globals
WHITELIST = []
BLACKLIST= []
bans = {}
quit_now = False
exit_code = 0
lock = Lock()
chain_name = "MAILCOW"
r = None
pubsub = None
clear_before_quit = False


def refreshF2boptions():
  global f2boptions
  global quit_now
  global exit_code

  f2boptions = {}

  if not r.get('F2B_OPTIONS'):
    f2boptions['ban_time'] = r.get('F2B_BAN_TIME')
    f2boptions['max_ban_time'] = r.get('F2B_MAX_BAN_TIME')
    f2boptions['ban_time_increment'] = r.get('F2B_BAN_TIME_INCREMENT')
    f2boptions['max_attempts'] = r.get('F2B_MAX_ATTEMPTS')
    f2boptions['retry_window'] = r.get('F2B_RETRY_WINDOW')
    f2boptions['netban_ipv4'] = r.get('F2B_NETBAN_IPV4')
    f2boptions['netban_ipv6'] = r.get('F2B_NETBAN_IPV6')
  else:
    try:
      f2boptions = json.loads(r.get('F2B_OPTIONS'))
    except ValueError:
      logger.logCrit('Error loading F2B options: F2B_OPTIONS is not json')
      quit_now = True
      exit_code = 2

  verifyF2boptions(f2boptions)
  r.set('F2B_OPTIONS', json.dumps(f2boptions, ensure_ascii=False))

def verifyF2boptions(f2boptions):
  verifyF2boption(f2boptions,'ban_time', 1800)
  verifyF2boption(f2boptions,'max_ban_time', 10000)
  verifyF2boption(f2boptions,'ban_time_increment', True)
  verifyF2boption(f2boptions,'max_attempts', 10)
  verifyF2boption(f2boptions,'retry_window', 600)
  verifyF2boption(f2boptions,'netban_ipv4', 32)
  verifyF2boption(f2boptions,'netban_ipv6', 128)
  verifyF2boption(f2boptions,'banlist_id', str(uuid.uuid4()))
  verifyF2boption(f2boptions,'manage_external', 0)

def verifyF2boption(f2boptions, f2boption, f2bdefault):
  f2boptions[f2boption] = f2boptions[f2boption] if f2boption in f2boptions and f2boptions[f2boption] is not None else f2bdefault

def refreshF2bregex():
  global f2bregex
  global quit_now
  global exit_code
  if not r.get('F2B_REGEX'):
    f2bregex = {}
    f2bregex[1] = 'mailcow UI: Invalid password for .+ by ([0-9a-f\.:]+)'
    f2bregex[2] = 'Rspamd UI: Invalid password by ([0-9a-f\.:]+)'
    f2bregex[3] = 'warning: .*\[([0-9a-f\.:]+)\]: SASL .+ authentication failed: (?!.*Connection lost to authentication server).+'
    f2bregex[4] = 'warning: non-SMTP command from .*\[([0-9a-f\.:]+)]:.+'
    f2bregex[5] = 'NOQUEUE: reject: RCPT from \[([0-9a-f\.:]+)].+Protocol error.+'
    f2bregex[6] = '-login: Disconnected.+ \(auth failed, .+\): user=.*, method=.+, rip=([0-9a-f\.:]+),'
    f2bregex[7] = '-login: Aborted login.+ \(auth failed .+\): user=.+, rip=([0-9a-f\.:]+), lip.+'
    f2bregex[8] = '-login: Aborted login.+ \(tried to use disallowed .+\): user=.+, rip=([0-9a-f\.:]+), lip.+'
    f2bregex[9] = 'SOGo.+ Login from \'([0-9a-f\.:]+)\' for user .+ might not have worked'
    f2bregex[10] = '([0-9a-f\.:]+) \"GET \/SOGo\/.* HTTP.+\" 403 .+'
    r.set('F2B_REGEX', json.dumps(f2bregex, ensure_ascii=False))
  else:
    try:
      f2bregex = {}
      f2bregex = json.loads(r.get('F2B_REGEX'))
    except ValueError:
      logger.logCrit('Error loading F2B options: F2B_REGEX is not json')
      quit_now = True
      exit_code = 2

def get_ip(address):
  ip = ipaddress.ip_address(address)
  if type(ip) is ipaddress.IPv6Address and ip.ipv4_mapped:
    ip = ip.ipv4_mapped
  if ip.is_private or ip.is_loopback:
    return False
  
  return ip

def ban(address):
  global f2boptions
  global lock

  refreshF2boptions()
  MAX_ATTEMPTS = int(f2boptions['max_attempts'])
  RETRY_WINDOW = int(f2boptions['retry_window'])
  NETBAN_IPV4 = '/' + str(f2boptions['netban_ipv4'])
  NETBAN_IPV6 = '/' + str(f2boptions['netban_ipv6'])

  ip = get_ip(address)
  if not ip: return
  address = str(ip)
  self_network = ipaddress.ip_network(address)

  with lock:
    temp_whitelist = set(WHITELIST)
  if temp_whitelist:
    for wl_key in temp_whitelist:
      wl_net = ipaddress.ip_network(wl_key, False)
      if wl_net.overlaps(self_network):
        logger.logInfo('Address %s is whitelisted by rule %s' % (self_network, wl_net))
        return

  net = ipaddress.ip_network((address + (NETBAN_IPV4 if type(ip) is ipaddress.IPv4Address else NETBAN_IPV6)), strict=False)
  net = str(net)

  if not net in bans:
    bans[net] = {'attempts': 0, 'last_attempt': 0, 'ban_counter': 0}

  current_attempt = time.time()
  if current_attempt - bans[net]['last_attempt'] > RETRY_WINDOW:
    bans[net]['attempts'] = 0

  bans[net]['attempts'] += 1
  bans[net]['last_attempt'] = current_attempt

  if bans[net]['attempts'] >= MAX_ATTEMPTS:
    cur_time = int(round(time.time()))
    NET_BAN_TIME = calcNetBanTime(bans[net]['ban_counter'])
    logger.logCrit('Banning %s for %d minutes' % (net, NET_BAN_TIME / 60 ))
    if type(ip) is ipaddress.IPv4Address and int(f2boptions['manage_external']) != 1:
      with lock:
        tables.banIPv4(net)
    elif int(f2boptions['manage_external']) != 1:
      with lock:
        tables.banIPv6(net)

    r.hset('F2B_ACTIVE_BANS', '%s' % net, cur_time + NET_BAN_TIME)
  else:
    logger.logWarn('%d more attempts in the next %d seconds until %s is banned' % (MAX_ATTEMPTS - bans[net]['attempts'], RETRY_WINDOW, net))

def unban(net):
  global lock

  if not net in bans:
   logger.logInfo('%s is not banned, skipping unban and deleting from queue (if any)' % net)
   r.hdel('F2B_QUEUE_UNBAN', '%s' % net)
   return

  logger.logInfo('Unbanning %s' % net)
  if type(ipaddress.ip_network(net)) is ipaddress.IPv4Network:
    with lock:
      tables.unbanIPv4(net)
  else:
    with lock:
      tables.unbanIPv6(net)

  r.hdel('F2B_ACTIVE_BANS', '%s' % net)
  r.hdel('F2B_QUEUE_UNBAN', '%s' % net)
  if net in bans:
    bans[net]['attempts'] = 0
    bans[net]['ban_counter'] += 1

def permBan(net, unban=False):
  global f2boptions
  global lock

  is_unbanned = False
  is_banned = False
  if type(ipaddress.ip_network(net, strict=False)) is ipaddress.IPv4Network:
    with lock:
      if unban:
        is_unbanned = tables.unbanIPv4(net)
      elif int(f2boptions['manage_external']) != 1:
        is_banned = tables.banIPv4(net)
  else:
    with lock:
      if unban:
        is_unbanned = tables.unbanIPv6(net)
      elif int(f2boptions['manage_external']) != 1:
        is_banned = tables.banIPv6(net)


  if is_unbanned:
    r.hdel('F2B_PERM_BANS', '%s' % net)
    logger.logCrit('Removed host/network %s from blacklist' % net)
  elif is_banned:
    r.hset('F2B_PERM_BANS', '%s' % net, int(round(time.time())))
    logger.logCrit('Added host/network %s to blacklist' % net)

def clear():
  global lock
  logger.logInfo('Clearing all bans')
  for net in bans.copy():
    unban(net)
  with lock:
    tables.clearIPv4Table()
    tables.clearIPv6Table()
    try:
      if r is not None:
        r.delete('F2B_ACTIVE_BANS')
        r.delete('F2B_PERM_BANS')
    except Exception as ex:
      logger.logWarn('Error clearing redis keys F2B_ACTIVE_BANS and F2B_PERM_BANS: %s' % ex)

def watch():
  global pubsub
  global quit_now
  global exit_code

  logger.logInfo('Watching Redis channel F2B_CHANNEL')
  pubsub.subscribe('F2B_CHANNEL')

  while not quit_now:
    try:
      for item in pubsub.listen():
        refreshF2bregex()
        for rule_id, rule_regex in f2bregex.items():
          if item['data'] and item['type'] == 'message':
            try:
              result = re.search(rule_regex, item['data'])
            except re.error:
              result = False
            if result:
              addr = result.group(1)
              ip = ipaddress.ip_address(addr)
              if ip.is_private or ip.is_loopback:
                continue
              logger.logWarn('%s matched rule id %s (%s)' % (addr, rule_id, item['data']))
              ban(addr)
    except Exception as ex:
      logger.logWarn('Error reading log line from pubsub: %s' % ex)
      pubsub = None
      quit_now = True
      exit_code = 2

def snat4(snat_target):
  global lock
  global quit_now

  while not quit_now:
    time.sleep(10)
    with lock:
      tables.snat4(snat_target, os.getenv('IPV4_NETWORK', '172.22.1') + '.0/24')

def snat6(snat_target):
  global lock
  global quit_now

  while not quit_now:
    time.sleep(10)
    with lock:
      tables.snat6(snat_target, os.getenv('IPV6_NETWORK', 'fd4d:6169:6c63:6f77::/64'))

def autopurge():
  global f2boptions

  while not quit_now:
    time.sleep(10)
    refreshF2boptions()
    MAX_ATTEMPTS = int(f2boptions['max_attempts'])
    QUEUE_UNBAN = r.hgetall('F2B_QUEUE_UNBAN')
    if QUEUE_UNBAN:
      for net in QUEUE_UNBAN:
        unban(str(net))
    for net in bans.copy():
      if bans[net]['attempts'] >= MAX_ATTEMPTS:
        NET_BAN_TIME = calcNetBanTime(bans[net]['ban_counter'])
        TIME_SINCE_LAST_ATTEMPT = time.time() - bans[net]['last_attempt']
        if TIME_SINCE_LAST_ATTEMPT > NET_BAN_TIME:
          unban(net)

def mailcowChainOrder():
  global lock
  global quit_now
  global exit_code
  while not quit_now:
    time.sleep(10)
    with lock:
      quit_now, exit_code = tables.checkIPv4ChainOrder()
      if quit_now: return
      quit_now, exit_code = tables.checkIPv6ChainOrder()

def calcNetBanTime(ban_counter):
  global f2boptions

  BAN_TIME = int(f2boptions['ban_time'])
  MAX_BAN_TIME = int(f2boptions['max_ban_time'])
  BAN_TIME_INCREMENT = bool(f2boptions['ban_time_increment'])
  NET_BAN_TIME = BAN_TIME if not BAN_TIME_INCREMENT else BAN_TIME * 2 ** ban_counter
  NET_BAN_TIME = max([BAN_TIME, min([NET_BAN_TIME, MAX_BAN_TIME])])
  return NET_BAN_TIME

def isIpNetwork(address):
  try:
    ipaddress.ip_network(address, False)
  except ValueError:
    return False
  return True

def genNetworkList(list):
  resolver = dns.resolver.Resolver()
  hostnames = []
  networks = []
  for key in list:
    if isIpNetwork(key):
      networks.append(key)
    else:
      hostnames.append(key)
  for hostname in hostnames:
    hostname_ips = []
    for rdtype in ['A', 'AAAA']:
      try:
        answer = resolver.resolve(qname=hostname, rdtype=rdtype, lifetime=3)
      except dns.exception.Timeout:
        logger.logInfo('Hostname %s timedout on resolve' % hostname)
        break
      except (dns.resolver.NXDOMAIN, dns.resolver.NoAnswer):
        continue
      except dns.exception.DNSException as dnsexception:
        logger.logInfo('%s' % dnsexception)
        continue
      for rdata in answer:
        hostname_ips.append(rdata.to_text())
    networks.extend(hostname_ips)
  return set(networks)

def whitelistUpdate():
  global lock
  global quit_now
  global WHITELIST
  while not quit_now:
    start_time = time.time()
    list = r.hgetall('F2B_WHITELIST')
    new_whitelist = []
    if list:
      new_whitelist = genNetworkList(list)
    with lock:
      if Counter(new_whitelist) != Counter(WHITELIST):
        WHITELIST = new_whitelist
        logger.logInfo('Whitelist was changed, it has %s entries' % len(WHITELIST))
    time.sleep(60.0 - ((time.time() - start_time) % 60.0))

def blacklistUpdate():
  global quit_now
  global BLACKLIST
  while not quit_now:
    start_time = time.time()
    list = r.hgetall('F2B_BLACKLIST')
    new_blacklist = []
    if list:
      new_blacklist = genNetworkList(list)
    if Counter(new_blacklist) != Counter(BLACKLIST):
      addban = set(new_blacklist).difference(BLACKLIST)
      delban = set(BLACKLIST).difference(new_blacklist)
      BLACKLIST = new_blacklist
      logger.logInfo('Blacklist was changed, it has %s entries' % len(BLACKLIST))
      if addban:
        for net in addban:
          permBan(net=net)
      if delban:
        for net in delban:
          permBan(net=net, unban=True)
    time.sleep(60.0 - ((time.time() - start_time) % 60.0))

def sigterm_quit(signum, frame):
  global clear_before_quit
  clear_before_quit = True
  sys.exit(exit_code)

def berfore_quit():
  if clear_before_quit:
    clear()
  if pubsub is not None:
    pubsub.unsubscribe()


if __name__ == '__main__':
  atexit.register(berfore_quit)
  signal.signal(signal.SIGTERM, sigterm_quit)

  # init Logger
  logger = Logger()

  # init backend
  backend = sys.argv[1]
  if backend == "nftables":
    logger.logInfo('Using NFTables backend')
    tables = NFTables(chain_name, logger)
  else:
    logger.logInfo('Using IPTables backend')
    tables = IPTables(chain_name, logger)

  # In case a previous session was killed without cleanup
  clear()

  # Reinit MAILCOW chain
  # Is called before threads start, no locking
  logger.logInfo("Initializing mailcow netfilter chain")
  tables.initChainIPv4()
  tables.initChainIPv6()

  if os.getenv("DISABLE_NETFILTER_ISOLATION_RULE").lower() in ("y", "yes"):
    logger.logInfo(f"Skipping {chain_name} isolation")
  else:
    logger.logInfo(f"Setting {chain_name} isolation")
    tables.create_mailcow_isolation_rule("br-mailcow", [3306, 6379, 8983, 12345], os.getenv("MAILCOW_REPLICA_IP"))

  # connect to redis
  while True:
    try:
      redis_slaveof_ip = os.getenv('REDIS_SLAVEOF_IP', '')
      redis_slaveof_port = os.getenv('REDIS_SLAVEOF_PORT', '')
      if "".__eq__(redis_slaveof_ip):
        r = redis.StrictRedis(host=os.getenv('IPV4_NETWORK', '172.22.1') + '.249', decode_responses=True, port=6379, db=0)
      else:
        r = redis.StrictRedis(host=redis_slaveof_ip, decode_responses=True, port=redis_slaveof_port, db=0)
      r.ping()
      pubsub = r.pubsub()
    except Exception as ex:
      print('%s - trying again in 3 seconds'  % (ex))
      time.sleep(3)
    else:
      break
  logger.set_redis(r)

  # rename fail2ban to netfilter
  if r.exists('F2B_LOG'):
    r.rename('F2B_LOG', 'NETFILTER_LOG')
  # clear bans in redis
  r.delete('F2B_ACTIVE_BANS')
  r.delete('F2B_PERM_BANS')
  
  refreshF2boptions()

  watch_thread = Thread(target=watch)
  watch_thread.daemon = True
  watch_thread.start()

  if os.getenv('SNAT_TO_SOURCE') and os.getenv('SNAT_TO_SOURCE') != 'n':
    try:
      snat_ip = os.getenv('SNAT_TO_SOURCE')
      snat_ipo = ipaddress.ip_address(snat_ip)
      if type(snat_ipo) is ipaddress.IPv4Address:
        snat4_thread = Thread(target=snat4,args=(snat_ip,))
        snat4_thread.daemon = True
        snat4_thread.start()
    except ValueError:
      print(os.getenv('SNAT_TO_SOURCE') + ' is not a valid IPv4 address')

  if os.getenv('SNAT6_TO_SOURCE') and os.getenv('SNAT6_TO_SOURCE') != 'n':
    try:
      snat_ip = os.getenv('SNAT6_TO_SOURCE')
      snat_ipo = ipaddress.ip_address(snat_ip)
      if type(snat_ipo) is ipaddress.IPv6Address:
        snat6_thread = Thread(target=snat6,args=(snat_ip,))
        snat6_thread.daemon = True
        snat6_thread.start()
    except ValueError:
      print(os.getenv('SNAT6_TO_SOURCE') + ' is not a valid IPv6 address')

  autopurge_thread = Thread(target=autopurge)
  autopurge_thread.daemon = True
  autopurge_thread.start()

  mailcowchainwatch_thread = Thread(target=mailcowChainOrder)
  mailcowchainwatch_thread.daemon = True
  mailcowchainwatch_thread.start()

  blacklistupdate_thread = Thread(target=blacklistUpdate)
  blacklistupdate_thread.daemon = True
  blacklistupdate_thread.start()

  whitelistupdate_thread = Thread(target=whitelistUpdate)
  whitelistupdate_thread.daemon = True
  whitelistupdate_thread.start()

  while not quit_now:
    time.sleep(0.5)

  sys.exit(exit_code)
