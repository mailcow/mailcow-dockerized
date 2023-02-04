#!/usr/bin/env python3

import re
import os
import sys
import time
import atexit
import signal
import nftables
import ipaddress
from collections import Counter
from random import randint
from threading import Thread
from threading import Lock
import redis
import json
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

#nftables
nft = nftables.Nftables()
nft.set_json_output(True)
nft.set_handle_output(True)

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

def search_for_chain(rules: dict, chain_name: str):
  found = False
  for object in rules["nftables"]:
      chain = object.get("chain")
      if not chain:
          continue
      ch_name = chain.get("name")
      if ch_name == chain_name:
          found = True
          break
  return found

def search_lower_priority_chain(data_structure: dict, hook_base: str):
  # hook_base posible values for ip and ip6 are:
  # prerouting, input, forward, output, postrouting
  lowest_prio = None
  return_chain = None
  for object in data_structure["nftables"]:
    chain = object.get("chain")
    if not chain:
      continue

    hook = chain.get("hook")
    if not hook or not hook == hook_base:
      continue

    priority = chain.get("prio")
    if priority is None:
      continue

    if lowest_prio is None:
      lowest_prio = priority
    else:
      if priority < lowest_prio:
        lowest_prio = priority
      else:
        continue

    # at this point, we know the chain has:
    #  hook and priority set
    # and is has the lowest priority
    return_chain = dict(
            family = chain["family"],
            table = chain["table"],
            name = chain["name"],
            handle = chain["handle"],
            prio = chain["prio"],
            )

  return return_chain

def get_base_dict():
  dict_rules = dict(nftables=[])
  dict_rules["nftables"] = []
  dict_rules["nftables"].append(dict(metainfo=dict(json_schema_version=1)))
  return dict_rules

def create_base_chain_dict(
      c_family: str,
      c_table: str,
      c_name: str,
      c_type: str = "filter",
      c_hook: str = "input",
      c_device: str = None,
      c_priority: int = 0,
      c_policy: str = "accept"
    ):
  # nft (add | create) chain [<family>] <table> <name> 
  # [ { type <type> hook <hook> [device <device>] priority <priority> \;
  #  [policy <policy> \;] } ]
  chain_params = dict(family = c_family,
                      table = c_table,
                      name = c_name,
                      type = c_type,
                      hook = c_hook,
                      prio = c_priority,
                      policy = c_policy
  )
  if c_device is not None:
    chain_params["device"] = c_device

  opts_chain = dict(chain = chain_params)
  add_chain=dict(add = opts_chain)
  final_chain = get_base_dict()
  final_chain["nftables"].append(add_chain)
  return final_chain

def create_chain_dict(c_family: str, c_table: str, c_name: str):
  # nft (add | create) chain [<family>] <table> <name> 
  chain_params = dict(family = c_family,
                        table = c_table,
                        name = c_name
  )

  opts_chain = dict(chain = chain_params)
  add_chain=dict(add = opts_chain)
  final_chain = get_base_dict()
  final_chain["nftables"].append(add_chain)
  return final_chain

def validate_json(json_data: dict):
  try:
    nft.json_validate(json_data)
  except Exception as e:
    logCrit(f"ERROR: failed validating JSON schema: {e}")
    return False
  return True

def nft_exec_dict(query: dict):
  global nft

  if not validate_json(query):
    return False
  rc, _, error = nft.json_cmd(query)
  if rc != 0:
    # do proper error handling here, exceptions etc
    logCrit(f"ERROR: running cmd: {query}")
    logCrit(error)
    return False

  return True

def nft_exec(query: str):
  global nft
  rc, output, error = nft.cmd(query)
  if rc != 0:
    # do proper error handling here, exceptions etc
    logCrit(f"ERROR: running cmd: {query}")
    logCrit(error)
    return False

  if len(output) == 0:
    # more error control
    logWarn("ERROR: no output from libnftables")
    return False

  data_structure = json.loads(output)

  if not validate_json(data_structure):
    return False

  return data_structure

def search_nat_chains(family: str):
  chain_postrouting_name = ""

  kernel_ruleset = nft_exec(f"list table {family} nat")
  if kernel_ruleset:
    first_pr_chain = search_lower_priority_chain(kernel_ruleset, "postrouting")

    if first_pr_chain is not None:
      chain_postrouting_name = first_pr_chain["name"]
    else:
      result = create_base_chain_dict(family, "nat", "HOST_POSTROUTING", c_hook="postrouting", c_priority=100)
      if(nft_exec_dict(result)):
        print(f"Postrouting {family} chain created successfully.")
        chain_postrouting_name = "HOST_POSTROUTING"

  return chain_postrouting_name

def search_filter_chains(family: str):
  chain_forward_name = ""
  chain_input_name = ""

  kernel_ruleset = nft_exec(f"list table {family} filter")
  if kernel_ruleset:
    first_fwd_chain = search_lower_priority_chain(kernel_ruleset, "forward")
    first_input_chain = search_lower_priority_chain(kernel_ruleset, "input")

    if first_fwd_chain is not None:
      chain_forward_name = first_fwd_chain["name"]
    else:
      result = create_base_chain_dict(family, "filter", "HOST_FORWARD", c_hook="forward")
      if(nft_exec_dict(result)):
        logInfo(f"Forward {family} chain created successfully.")
        chain_forward_name = "HOST_FORWARD"

    if first_input_chain is not None:
      chain_input_name = first_input_chain["name"]
    else:
      result = create_base_chain_dict(family, "filter", "HOST_INPUT", c_hook= "input")
      if(nft_exec_dict(result)):
        logInfo(f"Input {family} chain created successfully.")
        chain_input_name = "HOST_INPUT"

  return (chain_input_name, chain_forward_name)

def search_tables_needed():
  kernel_ruleset = nft_exec(f"list tables")
  tables_needed = {'ip' : {'filter', 'nat'}, 'ip6': {'filter', 'nat'}}
  if kernel_ruleset:
    for object in kernel_ruleset["nftables"]:
      g_table = object.get("table")
      if not g_table:
        continue
      try:
        family = g_table["family"]
        tables_needed[family].remove(g_table["name"])
        if len(tables_needed[family]) == 0:
          del tables_needed[family]
      except:
        pass

    if len(tables_needed) > 0:
      json_schema = get_base_dict()
      for v_family, table_names in tables_needed.items():
        for v_name in table_names:
          logInfo(f"Adding table {v_family} {v_name}")
          elements_dict = dict(family = v_family,
                              name = v_name
                          )
          table_dict = dict(table = elements_dict)
          add_dict = dict(add = table_dict)
          json_schema["nftables"].append(add_dict)

      if(nft_exec_dict(json_schema)):
        logInfo(f"Missing tables created successfully.")

search_tables_needed()

ip_filter_input, ip_filter_forward = search_filter_chains("ip")
ip6_filter_input, ip6_filter_forward = search_filter_chains("ip6")
ip_nat_postrouting = search_nat_chains("ip")
ip6_nat_postrouting = search_nat_chains("ip6")

def create_mailcow_jump_rule(c_family: str,
                            c_table: str,
                            c_chain: str,
                            dest_chain_name:str):

  expr_opt=[]
  expr_counter = dict(family = c_family,
                        table = c_table,
                        packets = 0,
                        bytes = 0)
  counter_dict = dict(counter = expr_counter)
  expr_opt.append(counter_dict)

  expr_jump = dict(target = dest_chain_name)
  jump_opts = dict(jump = expr_jump)

  expr_opt.append(jump_opts)

  rule_params = dict(family = c_family,
                        table = c_table,
                        chain = c_chain,
                        expr = expr_opt,
                        comment = "mailcow"
  )
  opts_rule = dict(rule = rule_params)
  add_rule = dict(insert = opts_rule)

  final_rule = get_base_dict()
  final_rule["nftables"].append(add_rule)
  return final_rule

def check_mailcow_chains(family: str, input_chain: str, forward_chain: str):
  order = []
  for chain_name in [input_chain, forward_chain]:
    kernel_ruleset = nft_exec(f"list chain {family} filter {chain_name}")
    if kernel_ruleset:
      counter = 0
      for object in kernel_ruleset["nftables"]:
        g_rule = object.get("rule")
        if not g_rule:
          continue
        rule = object["rule"]
        if rule.get("comment"):
          if rule["comment"] == "mailcow":
            break

        counter+=1
      order.append(counter)
  return order

def insert_mailcow_chains(family: str, input_chain: str, forward_chain: str):
  kernel_ruleset = nft_exec(f"list table {family} filter")
  if kernel_ruleset:
    if not search_for_chain(kernel_ruleset, "MAILCOW"):
      cadena = create_chain_dict(family, "filter", "MAILCOW")
      if(nft_exec_dict(cadena)):
        logInfo(f"MAILCOW {family} chain created successfully.")

    inpunt_jump_found = False
    forward_jump_found = False

    for object in kernel_ruleset["nftables"]:
      g_rule = object.get("rule")
      if not g_rule:
        continue

      rule = object["rule"]
      if rule["chain"] == input_chain:
        if rule.get("comment") and rule["comment"] == "mailcow":
          inpunt_jump_found = True
      if rule["chain"] == forward_chain:
          if rule.get("comment") and rule["comment"] == "mailcow":
            forward_jump_found = True

    if not inpunt_jump_found:
      mc_rule = create_mailcow_jump_rule(family, "filter", input_chain, "MAILCOW")
      nft_exec_dict(mc_rule)

    if not forward_jump_found:
      mc_rule = create_mailcow_jump_rule(family, "filter", forward_chain, "MAILCOW")
      nft_exec_dict(mc_rule)

def get_chain_handle(family: str, table: str, chain_name: str):
  chain_handle = None
  kernel_ruleset = nft_exec(f"list chains {family}")
  if kernel_ruleset:
    for object in kernel_ruleset["nftables"]:
      g_chain = object.get("chain")
      if not g_chain:
        continue
      chain = object["chain"]
      if chain["family"] == family and chain["table"] == table and chain["name"] == chain_name:
        chain_handle = chain["handle"]
        break
  return chain_handle

def get_rules_handle(family: str, table: str, chain_name: str):
  rule_handle = []
  kernel_ruleset = nft_exec(f"list chain {family} {table} {chain_name}")
  if kernel_ruleset:
    for object in kernel_ruleset["nftables"]:
      g_chain = object.get("rule")
      if not g_chain:
        continue

      rule = object["rule"]
      if rule["family"] == family and rule["table"] == table and rule["chain"] == chain_name:
        if rule.get("comment"):
          if rule["comment"] == "mailcow":
            rule_handle.append(rule["handle"])
  return rule_handle

def ban_ip(ipaddr:str, v_family: str):
  json_command = get_base_dict()

  expr_opt = []
  if re.search(r'/', ipaddr):
    divided = re.split(r'/', ipaddr)
    prefix_dict=dict(addr = divided[0],
                    len = int(divided[1])
                    )
    right_dict = dict(prefix = prefix_dict)
  else:
    right_dict = ipaddr

  payload_dict = dict(protocol = v_family,
                   field="saddr"
               )
  left_dict = dict(payload = payload_dict)
  match_dict = dict(op = "==",
                  left = left_dict,
                  right = right_dict
                )
  match_base = dict(match = match_dict)
  expr_opt.append(match_base)

  expr_counter = dict(family = v_family,
                      table = "filter",
                      packets = 0,
                      bytes = 0
                    )
  counter_dict = dict(counter = expr_counter)
  expr_opt.append(counter_dict)

  drop_dict = dict(drop = "null")
  expr_opt.append(drop_dict)

  rule_dict = dict(family = v_family,
                  table = "filter",
                  chain = "MAILCOW",
                  expr = expr_opt
    )

  base_rule = dict(rule = rule_dict)
  base_dict = dict(insert = base_rule)
  json_command["nftables"].append(base_dict)
  if(nft_exec_dict(json_command)):
    logInfo(f"Banned {v_family} {ipaddr}")

def unban_ip(ipaddr:str, v_family: str):
  json_command = get_base_dict()
  kernel_ruleset = nft_exec(f"list chain {v_family} filter  MAILCOW")
  rule_handle = None
  if kernel_ruleset:
    for object in kernel_ruleset["nftables"]:
      g_chain = object.get("rule")
      if not g_chain:
        continue

      rule = object["rule"]["expr"][0]["match"]
      left_opt = rule["left"]["payload"]
      if not left_opt["protocol"] == v_family:
        continue
      if not left_opt["field"] =="saddr":
        continue

      if v_family == "ip":
        rule_r_len = 32
        searched_len = 32
      else:
        rule_r_len = 128
        searched_len = 128

      rule_right = rule["right"]
      if isinstance(rule_right, dict):
        rule_r_ip = rule_right["prefix"]["addr"]
        rule_r_len = int(rule_right["prefix"]["len"])
      else:
        rule_r_ip = rule_right

      if re.search(r'/', ipaddr):
        divided = re.split(r'/', ipaddr)
        searched_ip = divided[0]
        searched_len = int(divided[1])
      else:
        searched_ip = ipaddr

      if rule_r_ip == searched_ip and rule_r_len == searched_len:
        rule_handle = object["rule"]["handle"]
        break


    if rule_handle is not None:
      mailcow_rule = dict(family = v_family,
                          table = "filter",
                          chain = "MAILCOW",
                          handle = rule_handle
                          )
      del_rule = dict(rule = mailcow_rule)
      delete_rule=dict(delete = del_rule)
      json_command["nftables"].append(delete_rule)
      if(nft_exec_dict(json_command)):
        logInfo(f"Unbanned {v_family}: {ipaddr}")
    else:
        logInfo(f"Can't unban {ipaddr}: rule not found")


def delete_rule(v_family:str, v_table: str, v_chain: str, v_handle:str):
  delete_command = get_base_dict()
  mailcow_rule = dict(family = v_family,
                      table = v_table,
                      chain = v_chain,
                      handle = v_handle
                    )
  del_rule = dict(rule = mailcow_rule)
  delete_rule = dict(delete = del_rule)
  delete_command["nftables"].append(delete_rule)
  if(nft_exec_dict(delete_command)):
    logInfo(f"Successfully removed: {v_family} {v_table} {v_chain} {v_handle}")
    return True

  return False

def split_ip_subnet(ip_subnet: str):
  if re.search(r'/', ip_subnet):
    src_ip_address = re.split(r'/', ip_subnet)
  else:
    src_ip_address = [ip_subnet, None]

  return src_ip_address

def snat_rule(v_family: str, snat_target: str):
  global ip_nat_postrouting, ip6_nat_postrouting

  chain_name = ip_nat_postrouting
  if v_family == "ip6":
    chain_name = ip6_nat_postrouting

  kernel_ruleset = nft_exec(f"list chain {v_family} nat {chain_name}")
  if not kernel_ruleset:
    return

  rule_position = 0
  rule_handle = None
  rule_found = False
  for object in kernel_ruleset["nftables"]:
    g_chain = object.get("rule")
    if not g_chain:
      continue

    rule = object["rule"]
    if not rule.get("comment"):
      rule_position +=1
      continue
    if not rule["comment"] == "mailcow":
      rule_position +=1
      continue
    else:
      rule_found = True
      rule_handle = rule["handle"]
      break

  if v_family == "ip":
    source_address = os.getenv('IPV4_NETWORK', '172.22.1') + '.0/24'
  else:
    source_address = os.getenv('IPV6_NETWORK', 'fd4d:6169:6c63:6f77::/64')

  dest_ip, dest_len = split_ip_subnet(source_address)

  if rule_found:
    saddr_ip = rule["expr"][0]["match"]["right"]["prefix"]["addr"]
    saddr_len = rule["expr"][0]["match"]["right"]["prefix"]["len"]

    daddr_ip = rule["expr"][1]["match"]["right"]["prefix"]["addr"]
    daddr_len = rule["expr"][1]["match"]["right"]["prefix"]["len"]
    match = all((
              saddr_ip == dest_ip,
              int(saddr_len) == int(dest_len),
              daddr_ip == dest_ip,
              int(daddr_len) == int(dest_len)
            ))
    try:
      if rule_position == 0:
        if not match:
          # Position 0 , it is a mailcow rule , but it does not have the same parameters
          delete_rule(v_family, "nat", chain_name, rule_handle)
      else:
        # Position > 0 and is mailcow rule
        delete_rule(v_family, "nat", chain_name, rule_handle)
    except:
        logCrit(f"Error running SNAT on {v_family}, retrying... rule = 0 ; deleting" )
  else:
    # rule not found
    json_command = get_base_dict()
    try:
      payload_fields = dict(protocol = v_family,
                            field = "saddr")
      payload_dict = dict(payload = payload_fields)
      payload_fields2 = dict(protocol = v_family,
                            field = "daddr")
      payload_dict2 = dict(payload = payload_fields2)
      prefix_fields=dict(addr = dest_ip,
                        len = int(dest_len))
      prefix_dict=dict(prefix = prefix_fields)

      snat_addr = dict(addr = snat_target)
      snat_dict = dict(snat = snat_addr)

      expr_counter = dict(family = v_family,
                          table = "nat",
                          packets = 0,
                          bytes = 0
                        )
      counter_dict = dict(counter = expr_counter)

      match_fields1 = dict(op = "==",
                          left = payload_dict,
                          right = prefix_dict
                         )
      match_dict1 = dict(match = match_fields1)

      match_fields2 = dict(op = "!=",
                          left = payload_dict2,
                          right = prefix_dict
                         )
      match_dict2 = dict(match = match_fields2)
      expr_list = [
                  match_dict1,
                  match_dict2,
                  counter_dict,
                  snat_dict
                  ]
      rule_fields = dict(family = v_family,
                        table = "nat",
                        chain = chain_name,
                        comment = "mailcow",
                        expr = expr_list
                        )
      rule_dict = dict(rule = rule_fields)
      insert_dict = dict(insert = rule_dict)
      json_command["nftables"].append(insert_dict)
      if(nft_exec_dict(json_command)):
        logInfo(f"Added {v_family} POSTROUTING rule for source network {dest_ip} to {snat_target}")
    except:
      logCrit(f"Error running SNAT on {v_family}, retrying... rule not found: inserting")

def refreshF2boptions():
  global f2boptions
  global quit_now
  global exit_code
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
    f2boptions['netban_ipv4'] = r.get('F2B_NETBAN_IPV4') or 32
    f2boptions['netban_ipv6'] = r.get('F2B_NETBAN_IPV6') or 128
    r.set('F2B_OPTIONS', json.dumps(f2boptions, ensure_ascii=False))
  else:
    try:
      f2boptions = {}
      f2boptions = json.loads(r.get('F2B_OPTIONS'))
    except ValueError:
      print('Error loading F2B options: F2B_OPTIONS is not json')
      quit_now = True
      exit_code = 2

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

def mailcowChainOrder():
  global lock
  global quit_now
  global exit_code
  global ip6_filter_forward, ip6_filter_input
  global ip_filter_forward, ip_filter_input

  while not quit_now:
    time.sleep(10)
    with lock:
      for family in ["ip", "ip6"]:
        if family == "ip":
          ip_input_order, ip_forward_order = check_mailcow_chains(family, ip_filter_input, ip_filter_forward)
          if ip_input_order > 0 or ip_forward_order > 0:
            quit_now = True
            exit_code = 2
        else:
          ip6_input_order, ip6_forward_order = check_mailcow_chains(family, ip6_filter_input, ip6_filter_forward)
          if ip6_input_order > 0 or ip6_forward_order > 0:
            quit_now = True
            exit_code = 2

def ban(address):
  global lock
  refreshF2boptions()
  BAN_TIME = int(f2boptions['ban_time'])
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

  if not net in bans or time.time() - bans[net]['last_attempt'] > RETRY_WINDOW:
    bans[net] = { 'attempts': 0 }
    active_window = RETRY_WINDOW
  else:
    active_window = time.time() - bans[net]['last_attempt']

  bans[net]['attempts'] += 1
  bans[net]['last_attempt'] = time.time()

  active_window = time.time() - bans[net]['last_attempt']

  if bans[net]['attempts'] >= MAX_ATTEMPTS:
    cur_time = int(round(time.time()))
    logCrit('Banning %s for %d minutes' % (net, BAN_TIME / 60))
    if type(ip) is ipaddress.IPv4Address:
      with lock:
        ban_ip(net, "ip")
    else:
      with lock:
        ban_ip(net, "ip6")
    r.hset('F2B_ACTIVE_BANS', '%s' % net, cur_time + BAN_TIME)
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
      unban_ip(net, "ip")
  else:
    with lock:
      unban_ip(net, "ip6")
  r.hdel('F2B_ACTIVE_BANS', '%s' % net)
  r.hdel('F2B_QUEUE_UNBAN', '%s' % net)
  if net in bans:
    del bans[net]

def permBan(net, unban=False):
  global lock
  if type(ipaddress.ip_network(net, strict=False)) is ipaddress.IPv4Network:
    with lock:
      if not unban:
        ban_ip(net, "ip")
        logCrit('Add host/network %s to blacklist' % net)
        r.hset('F2B_PERM_BANS', '%s' % net, int(round(time.time())))
      elif unban:
        logCrit('Remove host/network %s from blacklist' % net)
        unban_ip(net, "ip")
        r.hdel('F2B_PERM_BANS', '%s' % net)
  else:
    with lock:
      if not unban:
        logCrit('Add host/network %s to blacklist' % net)
        ban_ip(net, "ip6")
        r.hset('F2B_PERM_BANS', '%s' % net, int(round(time.time())))
      elif unban:
        logCrit('Remove host/network %s from blacklist' % net)
        unban_ip(net, "ip6")
        r.hdel('F2B_PERM_BANS', '%s' % net)

def quit(signum, frame):
  global quit_now
  quit_now = True

def clear():
  global ip_filter_input, ip_filter_forward
  global ip6_filter_input, ip6_filter_forward
  global lock
  logInfo('Clearing all bans')
  for net in bans.copy():
    unban(net)
  with lock:
    for fam in ["ip", "ip6"]:
      is_empty_dict = True
      json_command = get_base_dict()
      chain_handle = get_chain_handle(fam, "filter", "MAILCOW")
      # if no handle, the chain doesn't exists
      if chain_handle is not None:
        is_empty_dict = False
        # flush chain MAILCOW
        mailcow_chain = dict(family=fam,
                          table="filter",
                          name="MAILCOW"
                        )
        mc_chain_base = dict(chain=mailcow_chain)
        flush_chain = dict(flush=mc_chain_base)
        json_command["nftables"].append(flush_chain)

      # remove rule in forward chain
      # remove rule in input chain
      if fam == "ip":
        chains_family = [ip_filter_input, ip_filter_forward]
      else:
        chains_family = [ip6_filter_input, ip6_filter_forward]

      for chain_base in chains_family:
        rules_handle = get_rules_handle(fam, "filter", chain_base)
        if rules_handle is not None:
          for rule in rules_handle:
            is_empty_dict = False
            mailcow_rule = dict(family=fam,
                                table="filter",
                                chain=chain_base,
                                handle=rule
                        )
            del_rule = dict(rule=mailcow_rule)
            delete_rules=dict(delete=del_rule)
            json_command["nftables"].append(delete_rules)

      # remove chain MAILCOW
      # after delete all rules referencing this chain
      if chain_handle:
        mc_chain_handle = dict(family=fam,
                          table="filter",
                          name="MAILCOW",
                          handle=chain_handle
                        )
        del_chain=dict(chain=mc_chain_handle)
        delete_chain = dict(delete=del_chain)
        json_command["nftables"].append(delete_chain)

      if is_empty_dict == False:
        if(nft_exec_dict(json_command)):
          logInfo(f"Clear completed: {fam}")

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
      logWarn('Error reading log line from pubsub')
      quit_now = True
      exit_code = 2

def snat4(snat_target):
  global lock
  global quit_now

  while not quit_now:
    time.sleep(10)
    with lock:
      try:
        snat_rule("ip", snat_target)
      except:
        print('Error running SNAT4, retrying...')

def snat6(snat_target):
  global lock
  global quit_now

  while not quit_now:
    time.sleep(10)
    with lock:
      try:
        snat_rule("ip6", snat_target)
      except:
        print('Error running SNAT6, retrying...')

def autopurge():
  while not quit_now:
    time.sleep(10)
    refreshF2boptions()
    BAN_TIME = int(f2boptions['ban_time'])
    MAX_ATTEMPTS = int(f2boptions['max_attempts'])
    QUEUE_UNBAN = r.hgetall('F2B_QUEUE_UNBAN')
    if QUEUE_UNBAN:
      for net in QUEUE_UNBAN:
        unban(str(net))
    for net in bans.copy():
      if bans[net]['attempts'] >= MAX_ATTEMPTS:
        if time.time() - bans[net]['last_attempt'] > BAN_TIME:
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
  global ip_filter_input, ip_filter_forward
  global ip6_filter_input, ip6_filter_forward
  # Is called before threads start, no locking
  print("Initializing mailcow netfilter chain")
  #"""
  # check if chain MAILCOW exists
  for family in ["ip", "ip6"]:
    if family == "ip":
      insert_mailcow_chains(family, ip_filter_input, ip_filter_forward)
    else:
      insert_mailcow_chains(family, ip6_filter_input, ip6_filter_forward)

if __name__ == '__main__':

  logInfo("Using Nftables backend")
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
