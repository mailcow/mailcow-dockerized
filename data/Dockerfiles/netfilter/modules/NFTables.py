import nftables
import ipaddress
import os

class NFTables:
  def __init__(self, chain_name, logger):
    self.chain_name = chain_name
    self.logger = logger

    self.nft = nftables.Nftables()
    self.nft.set_json_output(True)
    self.nft.set_handle_output(True)
    self.nft_chain_names = {'ip': {'filter': {'input': '', 'forward': ''}, 'nat': {'postrouting': ''} },
                            'ip6': {'filter': {'input': '', 'forward': ''}, 'nat': {'postrouting': ''} } }

    self.search_current_chains()

  def initChainIPv4(self):
    self.insert_mailcow_chains("ip")

  def initChainIPv6(self):
    self.insert_mailcow_chains("ip6")

  def checkIPv4ChainOrder(self):
    return self.checkChainOrder("ip")

  def checkIPv6ChainOrder(self):
    return self.checkChainOrder("ip6")

  def checkChainOrder(self, filter_table):
    err = False
    exit_code = None

    for chain in ['input', 'forward']:
      chain_position = self.check_mailcow_chains(filter_table, chain)
      if chain_position is None: continue

      if chain_position is False:
        self.logger.logCrit(f'MAILCOW target not found in {filter_table} {chain} table, restarting container to fix it...')
        err = True
        exit_code = 2

      if chain_position > 0:
        chain_position += 1
        self.logger.logCrit(f'MAILCOW target is in position {chain_position} in the {filter_table} {chain} table, restarting container to fix it...')
        err = True
        exit_code = 2

    return err, exit_code

  def clearIPv4Table(self):
    self.clearTable("ip")

  def clearIPv6Table(self):
    self.clearTable("ip6")

  def clearTable(self, _family):
    is_empty_dict = True
    json_command = self.get_base_dict()
    chain_handle = self.get_chain_handle(_family, "filter", self.chain_name)
    # if no handle, the chain doesn't exists
    if chain_handle is not None:
      is_empty_dict = False
      # flush chain
      mailcow_chain = {'family': _family, 'table': 'filter', 'name': self.chain_name}
      flush_chain = {'flush': {'chain': mailcow_chain}}
      json_command["nftables"].append(flush_chain)

    # remove rule in forward chain
    # remove rule in input chain
    chains_family = [self.nft_chain_names[_family]['filter']['input'],
                    self.nft_chain_names[_family]['filter']['forward'] ]

    for chain_base in chains_family:
      if not chain_base: continue

      rules_handle = self.get_rules_handle(_family, "filter", chain_base)
      if rules_handle is not None:
        for r_handle in rules_handle:
          is_empty_dict = False
          mailcow_rule = {'family':_family,
                          'table': 'filter',
                          'chain': chain_base,
                          'handle': r_handle }
          delete_rules = {'delete': {'rule': mailcow_rule} }
          json_command["nftables"].append(delete_rules)

    # remove chain
    # after delete all rules referencing this chain
    if chain_handle is not None:
      mc_chain_handle = {'family':_family,
                        'table': 'filter',
                        'name': self.chain_name,
                        'handle': chain_handle }
      delete_chain = {'delete': {'chain': mc_chain_handle} }
      json_command["nftables"].append(delete_chain)

    if is_empty_dict == False:
      if self.nft_exec_dict(json_command):
        self.logger.logInfo(f"Clear completed: {_family}")

  def banIPv4(self, source):
    ban_dict = self.get_ban_ip_dict(source, "ip")
    return self.nft_exec_dict(ban_dict)

  def banIPv6(self, source):
    ban_dict = self.get_ban_ip_dict(source, "ip6")
    return self.nft_exec_dict(ban_dict)

  def unbanIPv4(self, source):
    unban_dict = self.get_unban_ip_dict(source, "ip")
    if not unban_dict:
      return False
    return self.nft_exec_dict(unban_dict)

  def unbanIPv6(self, source):
    unban_dict = self.get_unban_ip_dict(source, "ip6")
    if not unban_dict:
      return False
    return self.nft_exec_dict(unban_dict)

  def snat4(self, snat_target, source):
    self.snat_rule("ip", snat_target, source)

  def snat6(self, snat_target, source):
    self.snat_rule("ip6", snat_target, source)


  def nft_exec_dict(self, query: dict):
    if not query: return False

    rc, output, error = self.nft.json_cmd(query)
    if rc != 0:
      #self.logger.logCrit(f"Nftables Error: {error}")
      return False

    # Prevent returning False or empty string on commands that do not produce output
    if rc == 0 and len(output) == 0:
      return True

    return output

  def get_base_dict(self):
    return {'nftables': [{ 'metainfo': { 'json_schema_version': 1} } ] }

  def search_current_chains(self):
    nft_chain_priority = {'ip': {'filter': {'input': None, 'forward': None}, 'nat': {'postrouting': None} },
                      'ip6': {'filter': {'input': None, 'forward': None}, 'nat': {'postrouting': None} } }

    # Command: 'nft list chains'
    _list = {'list' : {'chains': 'null'} }
    command = self.get_base_dict()
    command['nftables'].append(_list)
    kernel_ruleset = self.nft_exec_dict(command)
    if kernel_ruleset:
      for _object in kernel_ruleset['nftables']:
        chain = _object.get("chain")
        if not chain: continue

        _family = chain['family']
        _table = chain['table']
        _hook = chain.get("hook")
        _priority = chain.get("prio")
        _name = chain['name']

        if _family not in self.nft_chain_names: continue
        if _table not in self.nft_chain_names[_family]: continue
        if _hook not in self.nft_chain_names[_family][_table]: continue
        if _priority is None: continue

        _saved_priority = nft_chain_priority[_family][_table][_hook]
        if _saved_priority is None or _priority < _saved_priority:
          # at this point, we know the chain has:
          # hook and priority set
          # and it has the lowest priority
          nft_chain_priority[_family][_table][_hook] = _priority
          self.nft_chain_names[_family][_table][_hook] = _name

  def search_for_chain(self, kernel_ruleset: dict, chain_name: str):
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

  def get_chain_dict(self, _family: str, _name: str):
    # nft (add | create) chain [<family>] <table> <name> 
    _chain_opts = {'family': _family, 'table': 'filter', 'name': _name  }
    _add = {'add': {'chain': _chain_opts} }
    final_chain = self.get_base_dict()
    final_chain["nftables"].append(_add)
    return final_chain

  def get_mailcow_jump_rule_dict(self, _family: str, _chain: str):
    _jump_rule = self.get_base_dict()
    _expr_opt=[]
    _expr_counter = {'family': _family, 'table': 'filter', 'packets': 0, 'bytes': 0}
    _counter_dict = {'counter': _expr_counter}
    _expr_opt.append(_counter_dict)

    _jump_opts = {'jump': {'target': self.chain_name} }

    _expr_opt.append(_jump_opts)

    _rule_params = {'family': _family,
                    'table': 'filter',
                    'chain': _chain,
                    'expr': _expr_opt,
                    'comment': "mailcow" }

    _add_rule = {'insert': {'rule': _rule_params} }

    _jump_rule["nftables"].append(_add_rule)

    return _jump_rule

  def insert_mailcow_chains(self, _family: str):
    nft_input_chain = self.nft_chain_names[_family]['filter']['input']
    nft_forward_chain = self.nft_chain_names[_family]['filter']['forward']
    # Command: 'nft list table <family> filter'
    _table_opts = {'family': _family, 'name': 'filter'}
    _list = {'list': {'table': _table_opts} }
    command = self.get_base_dict()
    command['nftables'].append(_list)
    kernel_ruleset = self.nft_exec_dict(command)
    if kernel_ruleset:
      # chain
      if not self.search_for_chain(kernel_ruleset, self.chain_name):
        cadena = self.get_chain_dict(_family, self.chain_name)
        if self.nft_exec_dict(cadena):
          self.logger.logInfo(f"MAILCOW {_family} chain created successfully.")

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
        command = self.get_mailcow_jump_rule_dict(_family, nft_input_chain)
        self.nft_exec_dict(command)

      if not forward_jump_found:
        command = self.get_mailcow_jump_rule_dict(_family, nft_forward_chain)
        self.nft_exec_dict(command)

  def delete_nat_rule(self, _family:str, _chain: str, _handle:str):
    delete_command = self.get_base_dict()
    _rule_opts = {'family': _family,
                  'table': 'nat',
                  'chain': _chain,
                  'handle': _handle  }
    _delete = {'delete': {'rule': _rule_opts} }
    delete_command["nftables"].append(_delete)

    return self.nft_exec_dict(delete_command)

  def delete_filter_rule(self, _family:str, _chain: str, _handle:str):
    delete_command = self.get_base_dict()
    _rule_opts = {'family': _family,
                  'table': 'filter',
                  'chain': _chain,
                  'handle': _handle  }
    _delete = {'delete': {'rule': _rule_opts} }
    delete_command["nftables"].append(_delete)

    return self.nft_exec_dict(delete_command)

  def snat_rule(self, _family: str, snat_target: str, source_address: str):
    chain_name = self.nft_chain_names[_family]['nat']['postrouting']

    # no postrouting chain, may occur if docker has ipv6 disabled.
    if not chain_name: return

    # Command: nft list chain <family> nat <chain_name>
    _chain_opts = {'family': _family, 'table': 'nat', 'name': chain_name}
    _list = {'list':{'chain': _chain_opts} }
    command = self.get_base_dict()
    command['nftables'].append(_list)
    kernel_ruleset = self.nft_exec_dict(command)
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

    dest_net = ipaddress.ip_network(source_address, strict=False)
    target_net = ipaddress.ip_network(snat_target, strict=False)

    if rule_found:
      saddr_ip = rule["expr"][0]["match"]["right"]["prefix"]["addr"]
      saddr_len = int(rule["expr"][0]["match"]["right"]["prefix"]["len"])

      daddr_ip = rule["expr"][1]["match"]["right"]["prefix"]["addr"]
      daddr_len = int(rule["expr"][1]["match"]["right"]["prefix"]["len"])

      target_ip = rule["expr"][3]["snat"]["addr"]

      saddr_net = ipaddress.ip_network(saddr_ip + '/' + str(saddr_len), strict=False)
      daddr_net = ipaddress.ip_network(daddr_ip + '/' + str(daddr_len), strict=False)
      current_target_net = ipaddress.ip_network(target_ip, strict=False)

      match = all((
                dest_net == saddr_net,
                dest_net == daddr_net,
                target_net == current_target_net
              ))
      try:
        if rule_position == 0:
          if not match:
            # Position 0 , it is a mailcow rule , but it does not have the same parameters
            if self.delete_nat_rule(_family, chain_name, rule_handle):
              self.logger.logInfo(f'Remove rule for source network {saddr_net} to SNAT target {target_net} from {_family} nat {chain_name} chain, rule does not match configured parameters')
        else:
          # Position > 0 and is mailcow rule
          if self.delete_nat_rule(_family, chain_name, rule_handle):
            self.logger.logInfo(f'Remove rule for source network {saddr_net} to SNAT target {target_net} from {_family} nat {chain_name} chain, rule is at position {rule_position}')
      except:
          self.logger.logCrit(f"Error running SNAT on {_family}, retrying..." )
    else:
      # rule not found
      json_command = self.get_base_dict()
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
        if self.nft_exec_dict(json_command):
          self.logger.logInfo(f'Added {_family} nat {chain_name} rule for source network {dest_net} to {target_net}')
      except:
        self.logger.logCrit(f"Error running SNAT on {_family}, retrying...")

  def get_chain_handle(self, _family: str, _table: str, chain_name: str):
    chain_handle = None
    # Command: 'nft list chains {family}'
    _list = {'list': {'chains': {'family': _family} } }
    command = self.get_base_dict()
    command['nftables'].append(_list)
    kernel_ruleset = self.nft_exec_dict(command)
    if kernel_ruleset:
      for _object in kernel_ruleset["nftables"]:
        if not _object.get("chain"):
          continue
        chain = _object["chain"]
        if chain["family"] == _family and chain["table"] == _table and chain["name"] == chain_name:
          chain_handle = chain["handle"]
          break
    return chain_handle

  def get_rules_handle(self, _family: str, _table: str, chain_name: str, _comment_filter = "mailcow"):
    rule_handle = []
    # Command: 'nft list chain {family} {table} {chain_name}'
    _chain_opts = {'family': _family, 'table': _table, 'name': chain_name}
    _list = {'list': {'chain': _chain_opts} }
    command = self.get_base_dict()
    command['nftables'].append(_list)

    kernel_ruleset = self.nft_exec_dict(command)
    if kernel_ruleset:
      for _object in kernel_ruleset["nftables"]:
        if not _object.get("rule"):
          continue

        rule = _object["rule"]
        if rule["family"] == _family and rule["table"] == _table and rule["chain"] == chain_name:
          if rule.get("comment") and rule["comment"] == _comment_filter:
            rule_handle.append(rule["handle"])
    return rule_handle

  def get_ban_ip_dict(self, ipaddr: str, _family: str):
    json_command = self.get_base_dict()

    expr_opt = []
    ipaddr_net = ipaddress.ip_network(ipaddr, strict=False)
    right_dict = {'prefix': {'addr': str(ipaddr_net.network_address), 'len': int(ipaddr_net.prefixlen) } }

    left_dict = {'payload': {'protocol': _family, 'field': 'saddr'} }
    match_dict = {'op': '==', 'left': left_dict, 'right': right_dict }
    expr_opt.append({'match': match_dict})

    counter_dict = {'counter': {'family': _family, 'table': "filter", 'packets': 0, 'bytes': 0} }
    expr_opt.append(counter_dict)

    expr_opt.append({'drop': "null"})

    rule_dict = {'family': _family, 'table': "filter", 'chain': self.chain_name, 'expr': expr_opt}

    base_dict = {'insert': {'rule': rule_dict} }
    json_command["nftables"].append(base_dict)

    return json_command

  def get_unban_ip_dict(self, ipaddr:str, _family: str):
    json_command = self.get_base_dict()
    # Command: 'nft list chain {s_family} filter  MAILCOW'
    _chain_opts = {'family': _family, 'table': 'filter', 'name': self.chain_name}
    _list = {'list': {'chain': _chain_opts} }
    command = self.get_base_dict()
    command['nftables'].append(_list)
    kernel_ruleset = self.nft_exec_dict(command)
    rule_handle = None
    if kernel_ruleset:
      for _object in kernel_ruleset["nftables"]:
        if not _object.get("rule"):
          continue

        rule = _object["rule"]["expr"][0]["match"]
        if not "payload" in rule["left"]:
          continue
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
        candidate_net = ipaddress.ip_network(ipaddr, strict=False)

        if current_rule_net == candidate_net:
          rule_handle = _object["rule"]["handle"]
          break

      if rule_handle is not None:
        mailcow_rule = {'family': _family, 'table': 'filter', 'chain': self.chain_name, 'handle': rule_handle}
        delete_rule = {'delete': {'rule': mailcow_rule} }
        json_command["nftables"].append(delete_rule)
      else:
        return False

    return json_command

  def check_mailcow_chains(self, family: str, chain: str):
    position = 0
    rule_found = False
    chain_name = self.nft_chain_names[family]['filter'][chain]

    if not chain_name: return None

    _chain_opts = {'family': family, 'table': 'filter', 'name': chain_name}
    _list = {'list': {'chain': _chain_opts}}
    command = self.get_base_dict()
    command['nftables'].append(_list)
    kernel_ruleset = self.nft_exec_dict(command)
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

  def create_mailcow_isolation_rule(self, _interface:str, _dports:list, _allow:str = ""):
    family = "ip"
    table = "filter"
    comment_filter_drop = "mailcow isolation"
    comment_filter_allow = "mailcow isolation allow"
    json_command = self.get_base_dict()

    # Delete old mailcow isolation rules
    handles = self.get_rules_handle(family, table, self.chain_name, comment_filter_drop)
    for handle in handles:
      self.delete_filter_rule(family, self.chain_name, handle)
    handles = self.get_rules_handle(family, table, self.chain_name, comment_filter_allow)
    for handle in handles:
      self.delete_filter_rule(family, self.chain_name, handle)

    # insert mailcow isolation rule
    _match_dict_drop = [
      {
        "match": {
          "op": "!=",
          "left": {
            "meta": {
              "key": "iifname"
            }
          },
          "right": _interface
        }
      },
      {
        "match": {
          "op": "==",
          "left": {
            "meta": {
              "key": "oifname"
            }
          },
          "right": _interface
        }
      },
      {
        "match": {
          "op": "==",
          "left": {
            "payload": {
              "protocol": "tcp",
              "field": "dport"
            }
          },
          "right": {
            "set": _dports
          }
        }
      },
      {
        "counter": {
          "packets": 0,
          "bytes": 0
        }
      },
      {
        "drop": None
      }
    ]
    rule_drop = { "insert": { "rule": {
      "family": family,
      "table": table,
      "chain": self.chain_name,
      "comment": comment_filter_drop,
      "expr": _match_dict_drop
    }}}
    json_command["nftables"].append(rule_drop)

    # insert mailcow isolation allow rule
    if _allow != "":
      _match_dict_allow = [
        {
          "match": {
            "op": "==",
            "left": {
              "payload": {
                "protocol": "ip",
                "field": "saddr"
              }
            },
            "right": _allow
          }
        },
        {
          "match": {
            "op": "!=",
            "left": {
              "meta": {
                "key": "iifname"
              }
            },
            "right": _interface
          }
        },
        {
          "match": {
            "op": "==",
            "left": {
              "meta": {
                "key": "oifname"
              }
            },
            "right": _interface
          }
        },
        {
          "match": {
            "op": "==",
            "left": {
              "payload": {
                "protocol": "tcp",
                "field": "dport"
              }
            },
            "right": {
              "set": _dports
            }
          }
        },
        {
          "counter": {
            "packets": 0,
            "bytes": 0
          }
        },
        {
          "accept": None
        }
      ]
      rule_allow = { "insert": { "rule": {
        "family": family,
        "table": table,
        "chain": self.chain_name,
        "comment": comment_filter_allow,
        "expr": _match_dict_allow
      }}}
      json_command["nftables"].append(rule_allow)

    success = self.nft_exec_dict(json_command)
    if success == False:
      self.logger.logCrit(f"Error adding {self.chain_name} isolation")
      return False

    return True