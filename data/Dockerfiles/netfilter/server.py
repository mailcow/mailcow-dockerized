#!/usr/bin/env python2

import re
import os
import time
import atexit
import signal
import ipaddress
import subprocess
from threading import Thread
import redis
import time
import json
import iptc

r = redis.StrictRedis(host=os.getenv('IPV4_NETWORK', '172.22.1') + '.249', decode_responses=True, port=6379, db=0)
pubsub = r.pubsub()

RULES = {}
RULES[1] = 'warning: .*\[([0-9a-f\.:]+)\]: SASL .+ authentication failed'
RULES[2] = '-login: Disconnected \(auth failed, .+\): user=.*, method=.+, rip=([0-9a-f\.:]+),'
RULES[3] = '-login: Aborted login \(no auth .+\): user=.+, rip=([0-9a-f\.:]+), lip.+'
RULES[4] = '-login: Aborted login \(tried to use disallowed .+\): user=.+, rip=([0-9a-f\.:]+), lip.+'
RULES[5] = 'SOGo.+ Login from \'([0-9a-f\.:]+)\' for user .+ might not have worked'
RULES[6] = 'mailcow UI: Invalid password for .+ by ([0-9a-f\.:]+)'

def refresh_f2boptions():
  global f2boptions
  if not r.get('F2B_OPTIONS'):
    f2boptions = {}
    f2boptions['ban_time'] = int
    f2boptions['max_attempts'] = int
    f2boptions['retry_window'] = int
    f2boptions['netban_ipv4'] = int
    f2boptions['netban_ipv6'] = int
    f2boptions['ban_time'] = r.get('F2B_BAN_TIME') or 1800
    f2boptions['max_attempts'] = r.get('F2B_MAX_ATTEMPTS') or 10
    f2boptions['retry_window'] = r.get('F2B_RETRY_WINDOW') or 600
    f2boptions['netban_ipv4'] = r.get('F2B_NETBAN_IPV4') or 24
    f2boptions['netban_ipv6'] = r.get('F2B_NETBAN_IPV6') or 64
    r.set('F2B_OPTIONS', json.dumps(f2boptions, ensure_ascii=False))
  else:
    try:
      f2boptions = {}
      f2boptions = json.loads(r.get('F2B_OPTIONS'))
    except ValueError, e:
      print 'Error loading F2B options: F2B_OPTIONS is not json'
      global quit_now
      quit_now = True

if r.exists('F2B_LOG'):
  r.rename('F2B_LOG', 'NETFILTER_LOG')

bans = {}
log = {}
quit_now = False

def checkChainOrder():
  filter4_table = iptc.Table(iptc.Table.FILTER)
  filter6_table = iptc.Table6(iptc.Table6.FILTER)
  for f in [filter4_table, filter6_table]:
    forward_chain = iptc.Chain(f, 'FORWARD')
    for position, item in enumerate(forward_chain.rules):
      if item.target.name == 'MAILCOW':
        mc_position = position
      if item.target.name == 'DOCKER':
        docker_position = position
    if 'mc_position' in locals() and 'docker_position' in locals():
      if int(mc_position) > int(docker_position):
        log['time'] = int(round(time.time()))
        log['priority'] = 'crit'
        log['message'] = 'Error in chain order, restarting container'
        r.lpush('NETFILTER_LOG', json.dumps(log, ensure_ascii=False))
        print 'Error in chain order, restarting container...'
        global quit_now
        quit_now = True

def ban(address):
  refresh_f2boptions()
  BAN_TIME = int(f2boptions['ban_time'])
  MAX_ATTEMPTS = int(f2boptions['max_attempts'])
  RETRY_WINDOW = int(f2boptions['retry_window'])
  NETBAN_IPV4 = '/' + str(f2boptions['netban_ipv4'])
  NETBAN_IPV6 = '/' + str(f2boptions['netban_ipv6'])
  WHITELIST = r.hgetall('F2B_WHITELIST')

  ip = ipaddress.ip_address(address.decode('ascii'))
  if type(ip) is ipaddress.IPv6Address and ip.ipv4_mapped:
    ip = ip.ipv4_mapped
    address = str(ip)
  if ip.is_private or ip.is_loopback:
    return

  self_network = ipaddress.ip_network(address.decode('ascii'))
  if WHITELIST:
    for wl_key in WHITELIST:
      wl_net = ipaddress.ip_network(wl_key.decode('ascii'), False)
      if wl_net.overlaps(self_network):
        log['time'] = int(round(time.time()))
        log['priority'] = 'info'
        log['message'] = 'Address %s is whitelisted by rule %s' % (self_network, wl_net)
        r.lpush('NETFILTER_LOG', json.dumps(log, ensure_ascii=False))
        print 'Address %s is whitelisted by rule %s' % (self_network, wl_net)
        return

  net = ipaddress.ip_network((address + (NETBAN_IPV4 if type(ip) is ipaddress.IPv4Address else NETBAN_IPV6)).decode('ascii'), strict=False)
  net = str(net)

  if not net in bans or time.time() - bans[net]['last_attempt'] > RETRY_WINDOW:
    bans[net] = { 'attempts': 0 }
    active_window = RETRY_WINDOW
  else:
    active_window = time.time() - bans[net]['last_attempt']

  bans[net]['attempts'] += 1
  bans[net]['last_attempt'] = time.time()

  active_window = time.time() - bans[net]['last_attempt']

  if bans[net]['attempts'] >= MAX_ATTEMPTS:
    log['time'] = int(round(time.time()))
    log['priority'] = 'crit'
    log['message'] = 'Banning %s' % net
    r.lpush('NETFILTER_LOG', json.dumps(log, ensure_ascii=False))
    print 'Banning %s for %d minutes' % (net, BAN_TIME / 60)
    if type(ip) is ipaddress.IPv4Address:
      chain = iptc.Chain(iptc.Table(iptc.Table.FILTER), 'MAILCOW')
      rule = iptc.Rule()
      rule.src = net
      target = iptc.Target(rule, "REJECT")
      rule.target = target
      if rule not in chain.rules:
        chain.insert_rule(rule)
    else:
      chain = iptc.Chain(iptc.Table6(iptc.Table6.FILTER), 'MAILCOW')
      rule = iptc.Rule6()
      rule.src = net
      target = iptc.Target(rule, "REJECT")
      rule.target = target
      if rule not in chain.rules:
        chain.insert_rule(rule)
    r.hset('F2B_ACTIVE_BANS', '%s' % net, log['time'] + BAN_TIME)
  else:
    log['time'] = int(round(time.time()))
    log['priority'] = 'warn'
    log['message'] = '%d more attempts in the next %d seconds until %s is banned' % (MAX_ATTEMPTS - bans[net]['attempts'], RETRY_WINDOW, net)
    r.lpush('NETFILTER_LOG', json.dumps(log, ensure_ascii=False))
    print '%d more attempts in the next %d seconds until %s is banned' % (MAX_ATTEMPTS - bans[net]['attempts'], RETRY_WINDOW, net)

def unban(net):
  log['time'] = int(round(time.time()))
  log['priority'] = 'info'
  r.lpush('NETFILTER_LOG', json.dumps(log, ensure_ascii=False))
  #if not net in bans:
  #  log['message'] = '%s is not banned, skipping unban and deleting from queue (if any)' % net
  #  r.lpush('NETFILTER_LOG', json.dumps(log, ensure_ascii=False))
  #  print '%s is not banned, skipping unban and deleting from queue (if any)' % net
  #  r.hdel('F2B_QUEUE_UNBAN', '%s' % net)
  #  return
  log['message'] = 'Unbanning %s' % net
  r.lpush('NETFILTER_LOG', json.dumps(log, ensure_ascii=False))
  print 'Unbanning %s' % net
  if type(ipaddress.ip_network(net.decode('ascii'))) is ipaddress.IPv4Network:
    chain = iptc.Chain(iptc.Table(iptc.Table.FILTER), 'MAILCOW')
    rule = iptc.Rule()
    rule.src = net
    target = iptc.Target(rule, "REJECT")
    rule.target = target
    if rule in chain.rules:
      chain.delete_rule(rule)
  else:
    chain = iptc.Chain(iptc.Table6(iptc.Table6.FILTER), 'MAILCOW')
    rule = iptc.Rule6()
    rule.src = net
    target = iptc.Target(rule, "REJECT")
    rule.target = target
    if rule in chain.rules:
      chain.delete_rule(rule)
  r.hdel('F2B_ACTIVE_BANS', '%s' % net)
  r.hdel('F2B_QUEUE_UNBAN', '%s' % net)
  if net in bans:
    del bans[net]

def quit(signum, frame):
  global quit_now
  quit_now = True

def clear():
  log['time'] = int(round(time.time()))
  log['priority'] = 'info'
  log['message'] = 'Clearing all bans'
  r.lpush('NETFILTER_LOG', json.dumps(log, ensure_ascii=False))
  print 'Clearing all bans'
  for net in bans.copy():
    unban(net)
  filter4_table = iptc.Table(iptc.Table.FILTER)
  filter6_table = iptc.Table6(iptc.Table6.FILTER)
  for filter_table in [filter4_table, filter6_table]:
    filter_table.autocommit = False
    forward_chain = iptc.Chain(filter_table, "FORWARD")
    input_chain = iptc.Chain(filter_table, "INPUT")
    mailcow_chain = iptc.Chain(filter_table, "MAILCOW")
    if mailcow_chain in filter_table.chains:
      for rule in mailcow_chain.rules:
        mailcow_chain.delete_rule(rule)
      for rule in forward_chain.rules:
        if rule.target.name == 'MAILCOW':
          forward_chain.delete_rule(rule)
      for rule in input_chain.rules:
        if rule.target.name == 'MAILCOW':
          input_chain.delete_rule(rule)
      filter_table.delete_chain("MAILCOW")
    filter_table.commit()
    filter_table.refresh()
    filter_table.autocommit = True
  r.delete('F2B_ACTIVE_BANS')
  pubsub.unsubscribe()

def watch():
  log['time'] = int(round(time.time()))
  log['priority'] = 'info'
  log['message'] = 'Watching Redis channel F2B_CHANNEL'
  r.lpush('NETFILTER_LOG', json.dumps(log, ensure_ascii=False))
  pubsub.subscribe('F2B_CHANNEL')
  print 'Subscribing to Redis channel F2B_CHANNEL'
  while True:
    for item in pubsub.listen():
      for rule_id, rule_regex in RULES.iteritems():
        if item['data'] and item['type'] == 'message':
          result = re.search(rule_regex, item['data'])
          if result:
            addr = result.group(1)
            ip = ipaddress.ip_address(addr.decode('ascii'))
            if ip.is_private or ip.is_loopback:
              continue
            print '%s matched rule id %d' % (addr, rule_id)
            log['time'] = int(round(time.time()))
            log['priority'] = 'warn'
            log['message'] = '%s matched rule id %d' % (addr, rule_id)
            r.lpush('NETFILTER_LOG', json.dumps(log, ensure_ascii=False))
            ban(addr)

def snat(snat_target):
  def get_snat_rule():
    rule = iptc.Rule()
    rule.position = 1
    rule.src = os.getenv('IPV4_NETWORK', '172.22.1') + '.0/24'
    rule.dst = '!' + rule.src
    target = rule.create_target("SNAT")
    target.to_source = snat_target
    return rule

  while True:
    table = iptc.Table('nat')
    table.autocommit = False
    chain = iptc.Chain(table, 'POSTROUTING')
    if get_snat_rule() not in chain.rules:
      log['time'] = int(round(time.time()))
      log['priority'] = 'info'
      log['message'] = 'Added POSTROUTING rule for source network ' + get_snat_rule().src + ' to SNAT target ' + snat_target
      r.lpush('NETFILTER_LOG', json.dumps(log, ensure_ascii=False))
      print log['message']
      chain.insert_rule(get_snat_rule())
      table.commit()
      table.refresh()
    time.sleep(10)

def autopurge():
  while not quit_now:
    checkChainOrder()
    refresh_f2boptions()
    BAN_TIME = f2boptions['ban_time']
    MAX_ATTEMPTS = f2boptions['max_attempts']
    QUEUE_UNBAN = r.hgetall('F2B_QUEUE_UNBAN')
    if QUEUE_UNBAN:
      for net in QUEUE_UNBAN:
        unban(str(net))
    for net in bans.copy():
      if bans[net]['attempts'] >= MAX_ATTEMPTS:
        if time.time() - bans[net]['last_attempt'] > BAN_TIME:
          unban(net)
    time.sleep(10)

def initChain():
  print "Initializing mailcow netfilter chain"
  # IPv4
  if not iptc.Chain(iptc.Table(iptc.Table.FILTER), "MAILCOW") in iptc.Table(iptc.Table.FILTER).chains:
    iptc.Table(iptc.Table.FILTER).create_chain("MAILCOW")
  for c in ['FORWARD', 'INPUT']:
    chain = iptc.Chain(iptc.Table(iptc.Table.FILTER), c)
    rule = iptc.Rule()
    rule.src = '0.0.0.0/0'
    rule.dst = '0.0.0.0/0'
    target = iptc.Target(rule, "MAILCOW")
    rule.target = target
    if rule not in chain.rules:
      chain.insert_rule(rule)
  # IPv6
  if not iptc.Chain(iptc.Table6(iptc.Table6.FILTER), "MAILCOW") in iptc.Table6(iptc.Table6.FILTER).chains:
    iptc.Table6(iptc.Table6.FILTER).create_chain("MAILCOW")
  for c in ['FORWARD', 'INPUT']:
    chain = iptc.Chain(iptc.Table6(iptc.Table6.FILTER), c)
    rule = iptc.Rule6()
    rule.src = '::/0'
    rule.dst = '::/0'
    target = iptc.Target(rule, "MAILCOW")
    rule.target = target
    if rule not in chain.rules:
      chain.insert_rule(rule)
  # Apply blacklist
  BLACKLIST = r.hgetall('F2B_BLACKLIST')
  if BLACKLIST:
    for bl_key in BLACKLIST:
      if type(ipaddress.ip_network(bl_key.decode('ascii'), strict=False)) is ipaddress.IPv4Network:
        chain = iptc.Chain(iptc.Table(iptc.Table.FILTER), 'MAILCOW')
        rule = iptc.Rule()
        rule.src = bl_key
        target = iptc.Target(rule, "REJECT")
        rule.target = target
        if rule not in chain.rules:
          chain.insert_rule(rule)
      else:
        chain = iptc.Chain(iptc.Table6(iptc.Table6.FILTER), 'MAILCOW')
        rule = iptc.Rule6()
        rule.src = bl_key
        target = iptc.Target(rule, "REJECT")
        rule.target = target
        if rule not in chain.rules:
          chain.insert_rule(rule)

if __name__ == '__main__':

  # In case a previous session was killed without cleanup
  clear()
  # Reinit MAILCOW chain
  initChain()

  watch_thread = Thread(target=watch)
  watch_thread.daemon = True
  watch_thread.start()

  if os.getenv('SNAT_TO_SOURCE') and os.getenv('SNAT_TO_SOURCE') is not 'n':
    try:
      snat_ip = os.getenv('SNAT_TO_SOURCE').decode('ascii')
      snat_ipo = ipaddress.ip_address(snat_ip)
      if type(snat_ipo) is ipaddress.IPv4Address:
        snat_thread = Thread(target=snat,args=(snat_ip,))
        snat_thread.daemon = True
        snat_thread.start()
    except ValueError:
      print os.getenv('SNAT_TO_SOURCE') + ' is not a valid IPv4 address'

  autopurge_thread = Thread(target=autopurge)
  autopurge_thread.daemon = True
  autopurge_thread.start()

  signal.signal(signal.SIGTERM, quit)
  atexit.register(clear)

  while not quit_now:
    time.sleep(0.5)
