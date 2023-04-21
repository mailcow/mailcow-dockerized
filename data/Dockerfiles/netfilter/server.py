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
import json
import redis
import nftables
import iptc
import dns.resolver
import dns.exception

while True:
  try:
    redis_slaveof_ip = os.getenv('REDIS_SLAVEOF_IP', '')
    redis_slaveof_port = os.getenv('REDIS_SLAVEOF_PORT', '')
    if "".__eq__(redis_slaveof_ip):
      r = redis.StrictRedis(host=os.getenv('IPV4_NETWORK', '172.22.1') + '.249', decode_responses=True, port=6379, db=0)
    else:
      r = redis.StrictRedis(host=redis_slaveof_ip, decode_responses=True, port=redis_slaveof_port, db=0)
    r.ping()
  except Exception as ex:
    print('%s - trying again in 3 seconds'  % (ex))
    time.sleep(3)
  else:
    break

pubsub = r.pubsub()

WHITELIST = []
BLACKLIST= []

bans = {}

quit_now = False
exit_code = 0
lock = Lock()

backend = sys.argv[1]
nft = None
nft_chain_names = {}

def log(priority, message):
  tolog = {}
  tolog['time'] = int(round(time.time()))
  tolog['priority'] = priority
  tolog['message'] = message
  r.lpush('NETFILTER_LOG', json.dumps(tolog, ensure_ascii=False))
  print(message)

def logWarn(message):
  log('warn', message)

def logCrit(message):
  log('crit', message)

def logInfo(message):
  log('info', message)

#nftables
if backend == 'nftables':
  logInfo('Using Nftables backend')
  nft = nftables.Nftables()
  nft.set_json_output(True)
  nft.set_handle_output(True)
  nft_chain_names = {'ip': {'filter': {'input': '', 'forward': ''}, 'nat': {'postrouting': ''} },
                    'ip6': {'filter': {'input': '', 'forward': ''}, 'nat': {'postrouting': ''} } }
else:
  logInfo('Using Iptables backend')

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
      print('Error loading F2B options: F2B_OPTIONS is not json')
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
      print('Error loading F2B options: F2B_REGEX is not json')
      quit_now = True
      exit_code = 2

if r.exists('F2B_LOG'):
  r.rename('F2B_LOG', 'NETFILTER_LOG')

# Nftables functions
def nft_exec_dict(query: dict):
  global nft

  if not query: return False

  rc, output, error = nft.json_cmd(query)
  if rc != 0:
    #logCrit(f"Nftables Error: {error}")
    return False

  # Prevent returning False or empty string on commands that do not produce output
  if rc == 0 and len(output) == 0:
    return True
  
  return output

def get_base_dict():
  return {'nftables': [{ 'metainfo': { 'json_schema_version': 1} } ] }

def search_current_chains():
  global nft_chain_names
  nft_chain_priority = {'ip': {'filter': {'input': None, 'forward': None}, 'nat': {'postrouting': None} },
                    'ip6': {'filter': {'input': None, 'forward': None}, 'nat': {'postrouting': None} } }

  # Command: 'nft list chains'
  _list = {'list' : {'chains': 'null'} }
  command = get_base_dict()
  command['nftables'].append(_list)
  kernel_ruleset = nft_exec_dict(command)
  if kernel_ruleset:
    for _object in kernel_ruleset['nftables']:
      chain = _object.get("chain")
      if not chain: continue

      _family = chain['family']
      _table = chain['table']
      _hook = chain.get("hook")
      _priority = chain.get("prio")
      _name = chain['name']

      if _family not in nft_chain_names: continue
      if _table not in nft_chain_names[_family]: continue
      if _hook not in nft_chain_names[_family][_table]: continue
      if _priority is None: continue

      _saved_priority = nft_chain_priority[_family][_table][_hook]
      if _saved_priority is None or _priority < _saved_priority:
        # at this point, we know the chain has:
        # hook and priority set
        # and it has the lowest priority
        nft_chain_priority[_family][_table][_hook] = _priority
        nft_chain_names[_family][_table][_hook] = _name

def search_for_chain(kernel_ruleset: dict, chain_name: str):
  found = False
  for _object in kernel_ruleset["nftables"]:
      chain = _object.get("chain")
      if not chain:
          continue
      ch_name = chain.get("name")
      if ch_name == chain_name:
          found = True
          break
  return found

def get_chain_dict(_family: str, _name: str):
  # nft (add | create) chain [<family>] <table> <name> 
  _chain_opts = {'family': _family, 'table': 'filter', 'name': _name  }
  _add = {'add': {'chain': _chain_opts} }
  final_chain = get_base_dict()
  final_chain["nftables"].append(_add)
  return final_chain

def get_mailcow_jump_rule_dict(_family: str, _chain: str):
  _jump_rule = get_base_dict()
  _expr_opt=[]
  _expr_counter = {'family': _family, 'table': 'filter', 'packets': 0, 'bytes': 0}
  _counter_dict = {'counter': _expr_counter}
  _expr_opt.append(_counter_dict)

  _jump_opts = {'jump': {'target': 'MAILCOW'} }

  _expr_opt.append(_jump_opts)

  _rule_params = {'family': _family,
                  'table': 'filter',
                  'chain': _chain,
                  'expr': _expr_opt,
                  'comment': "mailcow" }

  _add_rule = {'insert': {'rule': _rule_params} }

  _jump_rule["nftables"].append(_add_rule)

  return _jump_rule

def insert_mailcow_chains(_family: str):
  nft_input_chain = nft_chain_names[_family]['filter']['input']
  nft_forward_chain = nft_chain_names[_family]['filter']['forward']
  # Command: 'nft list table <family> filter'
  _table_opts = {'family': _family, 'name': 'filter'}
  _list = {'list': {'table': _table_opts} }
  command = get_base_dict()
  command['nftables'].append(_list)
  kernel_ruleset = nft_exec_dict(command)
  if kernel_ruleset:
    # MAILCOW chain
    if not search_for_chain(kernel_ruleset, "MAILCOW"):
      cadena = get_chain_dict(_family, "MAILCOW")
      if nft_exec_dict(cadena):
        logInfo(f"MAILCOW {_family} chain created successfully.")

    input_jump_found, forward_jump_found = False, False

    for _object in kernel_ruleset["nftables"]:
      if not _object.get("rule"):
        continue

      rule = _object["rule"]
      if nft_input_chain and rule["chain"] == nft_input_chain:
        if rule.get("comment") and rule["comment"] == "mailcow":
          input_jump_found = True
      if nft_forward_chain and rule["chain"] == nft_forward_chain:
        if rule.get("comment") and rule["comment"] == "mailcow":
          forward_jump_found = True

    if not input_jump_found:
      command = get_mailcow_jump_rule_dict(_family, nft_input_chain)
      nft_exec_dict(command)

    if not forward_jump_found:
      command = get_mailcow_jump_rule_dict(_family, nft_forward_chain)
      nft_exec_dict(command)

def delete_nat_rule(_family:str, _chain: str, _handle:str):
  delete_command = get_base_dict()
  _rule_opts = {'family': _family,
                'table': 'nat',
                'chain': _chain,
                'handle': _handle  }
  _delete = {'delete': {'rule': _rule_opts} }
  delete_command["nftables"].append(_delete)

  return nft_exec_dict(delete_command)

def snat_rule(_family: str, snat_target: str):
  chain_name = nft_chain_names[_family]['nat']['postrouting']

  # no postrouting chain, may occur if docker has ipv6 disabled.
  if not chain_name: return

  # Command: nft list chain <family> nat <chain_name>
  _chain_opts = {'family': _family, 'table': 'nat', 'name': chain_name}
  _list = {'list':{'chain': _chain_opts} }
  command = get_base_dict()
  command['nftables'].append(_list)
  kernel_ruleset = nft_exec_dict(command)
  if not kernel_ruleset:
    return

  rule_position = 0
  rule_handle = None
  rule_found = False
  for _object in kernel_ruleset["nftables"]:
    if not _object.get("rule"):
      continue

    rule = _object["rule"]
    if not rule.get("comment") or not rule["comment"] == "mailcow":
      rule_position +=1
      continue

    rule_found = True
    rule_handle = rule["handle"]
    break

  if _family == "ip":
    source_address = os.getenv('IPV4_NETWORK', '172.22.1') + '.0/24'
  else:
    source_address = os.getenv('IPV6_NETWORK', 'fd4d:6169:6c63:6f77::/64')

  dest_net = ipaddress.ip_network(source_address)
  target_net = ipaddress.ip_network(snat_target)

  if rule_found:
    saddr_ip = rule["expr"][0]["match"]["right"]["prefix"]["addr"]
    saddr_len = int(rule["expr"][0]["match"]["right"]["prefix"]["len"])

    daddr_ip = rule["expr"][1]["match"]["right"]["prefix"]["addr"]
    daddr_len = int(rule["expr"][1]["match"]["right"]["prefix"]["len"])

    target_ip = rule["expr"][3]["snat"]["addr"]

    saddr_net = ipaddress.ip_network(saddr_ip + '/' + str(saddr_len))
    daddr_net = ipaddress.ip_network(daddr_ip + '/' + str(daddr_len))
    current_target_net = ipaddress.ip_network(target_ip)

    match = all((
              dest_net == saddr_net,
              dest_net == daddr_net,
              target_net == current_target_net
            ))
    try:
      if rule_position == 0:
        if not match:
          # Position 0 , it is a mailcow rule , but it does not have the same parameters
          if delete_nat_rule(_family, chain_name, rule_handle):
            logInfo(f'Remove rule for source network {saddr_net} to SNAT target {target_net} from {_family} nat {chain_name} chain, rule does not match configured parameters')
      else:
        # Position > 0 and is mailcow rule
        if delete_nat_rule(_family, chain_name, rule_handle):
          logInfo(f'Remove rule for source network {saddr_net} to SNAT target {target_net} from {_family} nat {chain_name} chain, rule is at position {rule_position}')
    except:
        logCrit(f"Error running SNAT on {_family}, retrying..." )
  else:
    # rule not found
    json_command = get_base_dict()
    try:
      snat_dict = {'snat': {'addr': str(target_net.network_address)} }

      expr_counter = {'family': _family, 'table': 'nat', 'packets': 0, 'bytes': 0}
      counter_dict = {'counter': expr_counter}

      prefix_dict = {'prefix': {'addr': str(dest_net.network_address), 'len': int(dest_net.prefixlen)} }
      payload_dict = {'payload': {'protocol': _family, 'field': "saddr"} }
      match_dict1 = {'match': {'op': '==', 'left': payload_dict, 'right': prefix_dict} }

      payload_dict2 = {'payload': {'protocol': _family, 'field': "daddr"} }
      match_dict2 = {'match': {'op': '!=', 'left': payload_dict2, 'right': prefix_dict } }
      expr_list = [
                  match_dict1,
                  match_dict2,
                  counter_dict,
                  snat_dict
                  ]
      rule_fields = {'family': _family,
                      'table': 'nat',
                      'chain': chain_name,
                      'comment': "mailcow",
                      'expr': expr_list }

      insert_dict = {'insert': {'rule': rule_fields} }
      json_command["nftables"].append(insert_dict)
      if nft_exec_dict(json_command):
        logInfo(f'Added {_family} nat {chain_name} rule for source network {dest_net} to {target_net}')
    except:
      logCrit(f"Error running SNAT on {_family}, retrying...")

def get_chain_handle(_family: str, _table: str, chain_name: str):
  chain_handle = None
  # Command: 'nft list chains {family}'
  _list = {'list': {'chains': {'family': _family} } }
  command = get_base_dict()
  command['nftables'].append(_list)
  kernel_ruleset = nft_exec_dict(command)
  if kernel_ruleset:
    for _object in kernel_ruleset["nftables"]:
      if not _object.get("chain"):
        continue
      chain = _object["chain"]
      if chain["family"] == _family and chain["table"] == _table and chain["name"] == chain_name:
        chain_handle = chain["handle"]
        break
  return chain_handle

def get_rules_handle(_family: str, _table: str, chain_name: str):
  rule_handle = []
  # Command: 'nft list chain {family} {table} {chain_name}'
  _chain_opts = {'family': _family, 'table': _table, 'name': chain_name}
  _list = {'list': {'chain': _chain_opts} }
  command = get_base_dict()
  command['nftables'].append(_list)

  kernel_ruleset = nft_exec_dict(command)
  if kernel_ruleset:
    for _object in kernel_ruleset["nftables"]:
      if not _object.get("rule"):
        continue

      rule = _object["rule"]
      if rule["family"] == _family and rule["table"] == _table and rule["chain"] == chain_name:
        if rule.get("comment") and rule["comment"] == "mailcow":
          rule_handle.append(rule["handle"])
  return rule_handle

def get_ban_ip_dict(ipaddr: str, _family: str):
  json_command = get_base_dict()

  expr_opt = []
  ipaddr_net = ipaddress.ip_network(ipaddr)
  right_dict = {'prefix': {'addr': str(ipaddr_net.network_address), 'len': int(ipaddr_net.prefixlen) } }

  left_dict = {'payload': {'protocol': _family, 'field': 'saddr'} }
  match_dict = {'op': '==', 'left': left_dict, 'right': right_dict }
  expr_opt.append({'match': match_dict})

  counter_dict = {'counter': {'family': _family, 'table': "filter", 'packets': 0, 'bytes': 0} }
  expr_opt.append(counter_dict)

  expr_opt.append({'drop': "null"})

  rule_dict = {'family': _family, 'table': "filter", 'chain': "MAILCOW", 'expr': expr_opt}

  base_dict = {'insert': {'rule': rule_dict} }
  json_command["nftables"].append(base_dict)

  return json_command

def get_unban_ip_dict(ipaddr:str, _family: str):
  json_command = get_base_dict()
  # Command: 'nft list chain {s_family} filter  MAILCOW'
  _chain_opts = {'family': _family, 'table': 'filter', 'name': 'MAILCOW'}
  _list = {'list': {'chain': _chain_opts} }
  command = get_base_dict()
  command['nftables'].append(_list)
  kernel_ruleset = nft_exec_dict(command)
  rule_handle = None
  if kernel_ruleset:
    for _object in kernel_ruleset["nftables"]:
      if not _object.get("rule"):
        continue

      rule = _object["rule"]["expr"][0]["match"]
      left_opt = rule["left"]["payload"]
      if not left_opt["protocol"] == _family:
        continue
      if not left_opt["field"] =="saddr":
        continue

      # ip currently banned
      rule_right = rule["right"]
      if isinstance(rule_right, dict):
        current_rule_ip = rule_right["prefix"]["addr"] + '/' + str(rule_right["prefix"]["len"])
      else:
        current_rule_ip = rule_right
      current_rule_net = ipaddress.ip_network(current_rule_ip)

      # ip to ban
      candidate_net = ipaddress.ip_network(ipaddr)

      if current_rule_net == candidate_net:
        rule_handle = _object["rule"]["handle"]
        break

    if rule_handle is not None:
      mailcow_rule = {'family': _family, 'table': 'filter', 'chain': 'MAILCOW', 'handle': rule_handle}
      delete_rule = {'delete': {'rule': mailcow_rule} }
      json_command["nftables"].append(delete_rule)
    else:
        return False

  return json_command

def check_mailcow_chains(family: str, chain: str):
  position = 0
  rule_found = False
  chain_name = nft_chain_names[family]['filter'][chain]

  if not chain_name: return None

  _chain_opts = {'family': family, 'table': 'filter', 'name': chain_name}
  _list = {'list': {'chain': _chain_opts}}
  command = get_base_dict()
  command['nftables'].append(_list)
  kernel_ruleset = nft_exec_dict(command)
  if kernel_ruleset:
    for _object in kernel_ruleset["nftables"]:
      if not _object.get("rule"):
        continue
      rule = _object["rule"]
      if rule.get("comment") and rule["comment"] == "mailcow":
        rule_found = True
        break

      position+=1

  return position if rule_found else False

# Mailcow
def mailcowChainOrder():
  global lock
  global quit_now
  global exit_code

  while not quit_now:
    time.sleep(10)
    with lock:
      if backend == 'iptables':
        filter4_table = iptc.Table(iptc.Table.FILTER)
        filter6_table = iptc.Table6(iptc.Table6.FILTER)
        filter4_table.refresh()
        filter6_table.refresh()
        for f in [filter4_table, filter6_table]:
          forward_chain = iptc.Chain(f, 'FORWARD')
          input_chain = iptc.Chain(f, 'INPUT')
          for chain in [forward_chain, input_chain]:
            target_found = False
            for position, item in enumerate(chain.rules):
              if item.target.name == 'MAILCOW':
                target_found = True
                if position > 2:
                  logCrit(f'MAILCOW target is in position {position} in the {chain.name} chain, restarting container to fix it...')
                  quit_now = True
                  exit_code = 2
            if not target_found:
              logCrit(f'MAILCOW target not found in {chain.name} chain, restarting container to fix it...')
              quit_now = True
              exit_code = 2
      else:
        for family in ["ip", "ip6"]:
          for chain in ['input', 'forward']:
            chain_position = check_mailcow_chains(family, chain)
            if chain_position is None: continue

            if chain_position is False:
              logCrit(f'MAILCOW target not found in {family} {chain} table, restarting container to fix it...')
              quit_now = True
              exit_code = 2

            if chain_position > 0:
              logCrit(f'MAILCOW target is in position {chain_position} in the {family} {chain} table, restarting container to fix it...')
              quit_now = True
              exit_code = 2

def ban(address):
  global lock
  refreshF2boptions()
  BAN_TIME = int(f2boptions['ban_time'])
  BAN_TIME_INCREMENT = bool(f2boptions['ban_time_increment'])
  MAX_ATTEMPTS = int(f2boptions['max_attempts'])
  RETRY_WINDOW = int(f2boptions['retry_window'])
  NETBAN_IPV4 = '/' + str(f2boptions['netban_ipv4'])
  NETBAN_IPV6 = '/' + str(f2boptions['netban_ipv6'])

  ip = ipaddress.ip_address(address)
  if type(ip) is ipaddress.IPv6Address and ip.ipv4_mapped:
    ip = ip.ipv4_mapped
    address = str(ip)
  if ip.is_private or ip.is_loopback:
    return

  self_network = ipaddress.ip_network(address)

  with lock:
    temp_whitelist = set(WHITELIST)

  if temp_whitelist:
    for wl_key in temp_whitelist:
      wl_net = ipaddress.ip_network(wl_key, False)
      if wl_net.overlaps(self_network):
        logInfo('Address %s is whitelisted by rule %s' % (self_network, wl_net))
        return

  net = ipaddress.ip_network((address + (NETBAN_IPV4 if type(ip) is ipaddress.IPv4Address else NETBAN_IPV6)), strict=False)
  net = str(net)

  if net not in bans or time.time() - bans[net]['last_attempt'] > RETRY_WINDOW:
    bans[net] = { 'attempts': 0 }
    active_window = RETRY_WINDOW
  else:
    active_window = time.time() - bans[net]['last_attempt']

  bans[net]['attempts'] += 1
  bans[net]['last_attempt'] = time.time()

  if bans[net]['attempts'] >= MAX_ATTEMPTS:
    cur_time = int(round(time.time()))
    NET_BAN_TIME = BAN_TIME if not BAN_TIME_INCREMENT else BAN_TIME * 2 ** bans[net]['ban_counter']
    logCrit('Banning %s for %d minutes' % (net, NET_BAN_TIME / 60 ))
    if type(ip) is ipaddress.IPv4Address:
      with lock:
        if backend == 'iptables':
          chain = iptc.Chain(iptc.Table(iptc.Table.FILTER), 'MAILCOW')
          rule = iptc.Rule()
          rule.src = net
          target = iptc.Target(rule, "REJECT")
          rule.target = target
          if rule not in chain.rules:
            chain.insert_rule(rule)
        else:
          ban_dict = get_ban_ip_dict(net, "ip")
          nft_exec_dict(ban_dict)
    else:
      with lock:
        if backend == 'iptables':
          chain = iptc.Chain(iptc.Table6(iptc.Table6.FILTER), 'MAILCOW')
          rule = iptc.Rule6()
          rule.src = net
          target = iptc.Target(rule, "REJECT")
          rule.target = target
          if rule not in chain.rules:
            chain.insert_rule(rule)
        else:
          ban_dict = get_ban_ip_dict(net, "ip6")
          nft_exec_dict(ban_dict)

    r.hset('F2B_ACTIVE_BANS', '%s' % net, cur_time + NET_BAN_TIME)
  else:
    logWarn('%d more attempts in the next %d seconds until %s is banned' % (MAX_ATTEMPTS - bans[net]['attempts'], RETRY_WINDOW, net))

def unban(net):
  global lock
  if not net in bans:
   logInfo('%s is not banned, skipping unban and deleting from queue (if any)' % net)
   r.hdel('F2B_QUEUE_UNBAN', '%s' % net)
   return
  logInfo('Unbanning %s' % net)
  if type(ipaddress.ip_network(net)) is ipaddress.IPv4Network:
    with lock:
      if backend == 'iptables':
        chain = iptc.Chain(iptc.Table(iptc.Table.FILTER), 'MAILCOW')
        rule = iptc.Rule()
        rule.src = net
        target = iptc.Target(rule, "REJECT")
        rule.target = target
        if rule in chain.rules:
          chain.delete_rule(rule)
      else:
        dict_unban = get_unban_ip_dict(net, "ip")
        if dict_unban:
          if nft_exec_dict(dict_unban):
            logInfo(f"Unbanned ip: {net}")
  else:
    with lock:
      if backend == 'iptables':
        chain = iptc.Chain(iptc.Table6(iptc.Table6.FILTER), 'MAILCOW')
        rule = iptc.Rule6()
        rule.src = net
        target = iptc.Target(rule, "REJECT")
        rule.target = target
        if rule in chain.rules:
          chain.delete_rule(rule)
      else:
        dict_unban = get_unban_ip_dict(net, "ip6")
        if dict_unban:
          if nft_exec_dict(dict_unban):
            logInfo(f"Unbanned ip6: {net}")

  r.hdel('F2B_ACTIVE_BANS', '%s' % net)
  r.hdel('F2B_QUEUE_UNBAN', '%s' % net)
  if net in bans:
    bans[net]['attempts'] = 0
    bans[net]['ban_counter'] += 1

def permBan(net, unban=False):
  global lock
  if type(ipaddress.ip_network(net, strict=False)) is ipaddress.IPv4Network:
    with lock:
      if backend == 'iptables':
        chain = iptc.Chain(iptc.Table(iptc.Table.FILTER), 'MAILCOW')
        rule = iptc.Rule()
        rule.src = net
        target = iptc.Target(rule, "REJECT")
        rule.target = target
        if rule not in chain.rules and not unban:
          logCrit('Add host/network %s to blacklist' % net)
          chain.insert_rule(rule)
          r.hset('F2B_PERM_BANS', '%s' % net, int(round(time.time())))
        elif rule in chain.rules and unban:
          logCrit('Remove host/network %s from blacklist' % net)
          chain.delete_rule(rule)
          r.hdel('F2B_PERM_BANS', '%s' % net)
      else:
        if not unban:
          ban_dict = get_ban_ip_dict(net, "ip")
          if nft_exec_dict(ban_dict):
            logCrit('Add host/network %s to blacklist' % net)
          r.hset('F2B_PERM_BANS', '%s' % net, int(round(time.time())))
        elif unban:
          dict_unban = get_unban_ip_dict(net, "ip")
          if dict_unban:
            if nft_exec_dict(dict_unban):
              logCrit('Remove host/network %s from blacklist' % net)
          r.hdel('F2B_PERM_BANS', '%s' % net)
  else:
    with lock:
      if backend == 'iptables':
        chain = iptc.Chain(iptc.Table6(iptc.Table6.FILTER), 'MAILCOW')
        rule = iptc.Rule6()
        rule.src = net
        target = iptc.Target(rule, "REJECT")
        rule.target = target
        if rule not in chain.rules and not unban:
          logCrit('Add host/network %s to blacklist' % net)
          chain.insert_rule(rule)
          r.hset('F2B_PERM_BANS', '%s' % net, int(round(time.time())))
        elif rule in chain.rules and unban:
          logCrit('Remove host/network %s from blacklist' % net)
          chain.delete_rule(rule)
          r.hdel('F2B_PERM_BANS', '%s' % net)
      else:
        if not unban:
          ban_dict = get_ban_ip_dict(net, "ip6")
          if nft_exec_dict(ban_dict):
            logCrit('Add host/network %s to blacklist' % net)
          r.hset('F2B_PERM_BANS', '%s' % net, int(round(time.time())))
        elif unban:
          dict_unban = get_unban_ip_dict(net, "ip6")
          if dict_unban:
            if nft_exec_dict(dict_unban):
              logCrit('Remove host/network %s from blacklist' % net)
          r.hdel('F2B_PERM_BANS', '%s' % net)

def quit(signum, frame):
  global quit_now
  quit_now = True

def clear():
  global lock
  logInfo('Clearing all bans')
  for net in bans.copy():
    unban(net)
  with lock:
    if backend == 'iptables':
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
    else:
      for _family in ["ip", "ip6"]:
        is_empty_dict = True
        json_command = get_base_dict()
        chain_handle = get_chain_handle(_family, "filter", "MAILCOW")
        # if no handle, the chain doesn't exists
        if chain_handle is not None:
          is_empty_dict = False
          # flush chain MAILCOW
          mailcow_chain = {'family': _family, 'table': 'filter', 'name': 'MAILCOW'}
          flush_chain = {'flush': {'chain': mailcow_chain}}
          json_command["nftables"].append(flush_chain)

        # remove rule in forward chain
        # remove rule in input chain
        chains_family = [nft_chain_names[_family]['filter']['input'],
                        nft_chain_names[_family]['filter']['forward'] ]

        for chain_base in chains_family:
          if not chain_base: continue

          rules_handle = get_rules_handle(_family, "filter", chain_base)
          if rules_handle is not None:
            for r_handle in rules_handle:
              is_empty_dict = False
              mailcow_rule = {'family':_family,
                              'table': 'filter',
                              'chain': chain_base,
                              'handle': r_handle }
              delete_rules = {'delete': {'rule': mailcow_rule} }
              json_command["nftables"].append(delete_rules)

        # remove chain MAILCOW
        # after delete all rules referencing this chain
        if chain_handle is not None:
          mc_chain_handle = {'family':_family,
                            'table': 'filter',
                            'name': 'MAILCOW',
                            'handle': chain_handle }
          delete_chain = {'delete': {'chain': mc_chain_handle} }
          json_command["nftables"].append(delete_chain)

        if is_empty_dict == False:
          if nft_exec_dict(json_command):
            logInfo(f"Clear completed: {_family}")

    r.delete('F2B_ACTIVE_BANS')
    r.delete('F2B_PERM_BANS')
    pubsub.unsubscribe()

def watch():
  logInfo('Watching Redis channel F2B_CHANNEL')
  pubsub.subscribe('F2B_CHANNEL')

  global quit_now
  global exit_code

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
              logWarn('%s matched rule id %s (%s)' % (addr, rule_id, item['data']))
              ban(addr)
    except Exception as ex:
      logWarn('Error reading log line from pubsub: %s' % ex)
      quit_now = True
      exit_code = 2

def snat4(snat_target):
  global lock
  global quit_now

  def get_snat4_rule():
    rule = iptc.Rule()
    rule.src = os.getenv('IPV4_NETWORK', '172.22.1') + '.0/24'
    rule.dst = '!' + rule.src
    target = rule.create_target("SNAT")
    target.to_source = snat_target
    match = rule.create_match("comment")
    match.comment = f'{int(round(time.time()))}'
    return rule

  while not quit_now:
    time.sleep(10)
    with lock:
      try:
        if backend == 'iptables':
          table = iptc.Table('nat')
          table.refresh()
          chain = iptc.Chain(table, 'POSTROUTING')
          table.autocommit = False
          new_rule = get_snat4_rule()
  
          if not chain.rules:
            # if there are no rules in the chain, insert the new rule directly
            logInfo(f'Added POSTROUTING rule for source network {new_rule.src} to SNAT target {snat_target}')
            chain.insert_rule(new_rule)
          else:
            for position, rule in enumerate(chain.rules):
                match = all((
                  new_rule.get_src() == rule.get_src(),
                  new_rule.get_dst() == rule.get_dst(),
                  new_rule.target.parameters == rule.target.parameters,
                  new_rule.target.name == rule.target.name
                ))
                if position == 0:
                  if not match:
                    logInfo(f'Added POSTROUTING rule for source network {new_rule.src} to SNAT target {snat_target}')
                    chain.insert_rule(new_rule)
                else:
                  if match:
                    logInfo(f'Remove rule for source network {new_rule.src} to SNAT target {snat_target} from POSTROUTING chain at position {position}')
                    chain.delete_rule(rule)
  
          table.commit()
          table.autocommit = True
        else:
          snat_rule("ip", snat_target)
      except:
        print('Error running SNAT4, retrying...')

def snat6(snat_target):
  global lock
  global quit_now

  def get_snat6_rule():
    rule = iptc.Rule6()
    rule.src = os.getenv('IPV6_NETWORK', 'fd4d:6169:6c63:6f77::/64')
    rule.dst = '!' + rule.src
    target = rule.create_target("SNAT")
    target.to_source = snat_target
    return rule

  while not quit_now:
    time.sleep(10)
    with lock:
      try:
        if backend == 'iptables':
          table = iptc.Table6('nat')
          table.refresh()
          chain = iptc.Chain(table, 'POSTROUTING')
          table.autocommit = False
          new_rule = get_snat6_rule()
          for position, rule in enumerate(chain.rules):
            match = all((
              new_rule.get_src() == rule.get_src(),
              new_rule.get_dst() == rule.get_dst(),
              new_rule.target.parameters == rule.target.parameters,
              new_rule.target.name == rule.target.name
            ))
            if position == 0:
              if not match:
                logInfo(f'Added POSTROUTING rule for source network {new_rule.src} to SNAT target {snat_target}')
                chain.insert_rule(new_rule)
            else:
              if match:
                logInfo(f'Remove rule for source network {new_rule.src} to SNAT target {snat_target} from POSTROUTING chain at position {position}')
                chain.delete_rule(rule)
          table.commit()
          table.autocommit = True
        else:
          snat_rule("ip6", snat_target)
      except:
        print('Error running SNAT6, retrying...')

def autopurge():
  while not quit_now:
    time.sleep(10)
    refreshF2boptions()
    BAN_TIME = int(f2boptions['ban_time'])
    MAX_BAN_TIME = int(f2boptions['max_ban_time'])
    BAN_TIME_INCREMENT = bool(f2boptions['ban_time_increment'])
    MAX_ATTEMPTS = int(f2boptions['max_attempts'])
    QUEUE_UNBAN = r.hgetall('F2B_QUEUE_UNBAN')
    if QUEUE_UNBAN:
      for net in QUEUE_UNBAN:
        unban(str(net))
    for net in bans.copy():
      if bans[net]['attempts'] >= MAX_ATTEMPTS:
        NET_BAN_TIME = BAN_TIME if not BAN_TIME_INCREMENT else BAN_TIME * 2 ** bans[net]['ban_counter']
        TIME_SINCE_LAST_ATTEMPT = time.time() - bans[net]['last_attempt']
        if TIME_SINCE_LAST_ATTEMPT > NET_BAN_TIME or TIME_SINCE_LAST_ATTEMPT > MAX_BAN_TIME:
          unban(net)

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
        logInfo('Hostname %s timedout on resolve' % hostname)
        break
      except (dns.resolver.NXDOMAIN, dns.resolver.NoAnswer):
        continue
      except dns.exception.DNSException as dnsexception:
        logInfo('%s' % dnsexception)
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
        logInfo('Whitelist was changed, it has %s entries' % len(WHITELIST))
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
      logInfo('Blacklist was changed, it has %s entries' % len(BLACKLIST))
      if addban:
        for net in addban:
          permBan(net=net)
      if delban:
        for net in delban:
          permBan(net=net, unban=True)
    time.sleep(60.0 - ((time.time() - start_time) % 60.0))

def initChain():
  # Is called before threads start, no locking
  print("Initializing mailcow netfilter chain")
  if backend == 'iptables':
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
  else:
    for family in ["ip", "ip6"]:
      insert_mailcow_chains(family)


if __name__ == '__main__':

  if backend == 'nftables':
    search_current_chains()

  # In case a previous session was killed without cleanup
  clear()
  # Reinit MAILCOW chain
  initChain()

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

  signal.signal(signal.SIGTERM, quit)
  atexit.register(clear)

  while not quit_now:
    time.sleep(0.5)

  sys.exit(exit_code)
