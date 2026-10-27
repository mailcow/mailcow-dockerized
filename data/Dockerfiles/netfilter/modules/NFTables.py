import nftables
import ipaddress

class NFTables:
  ISOLATION_COMMENTS = ('mailcow isolation', 'mailcow isolation allow')

  def __init__(self, chain_name, logger):
    self.chain_name = chain_name
    self.logger = logger

    self.nft = nftables.Nftables()
    self.nft.set_json_output(True)
    self.nft.set_handle_output(True)
    self.nft_chain_names = {'ip': {'filter': {'input': '', 'forward': ''}, 'nat': {'postrouting': ''} },
                            'ip6': {'filter': {'input': '', 'forward': ''}, 'nat': {'postrouting': ''} } }
    self.mailcow_chains = set()

    self.search_current_chains()

  def initChainIPv4(self):
    self.init_chain("ip")

  def initChainIPv6(self):
    self.init_chain("ip6")

  def clearIPv4Table(self):
    self.clearTable("ip")

  def clearIPv6Table(self):
    self.clearTable("ip6")

  def snat4(self, snat_target, source):
    self.snat_rule("ip", snat_target, source)

  def snat6(self, snat_target, source):
    self.snat_rule("ip6", snat_target, source)

  def nft_exec_dict(self, query: dict, quiet: bool = False):
    if not query: return False

    rc, output, error = self.nft.json_cmd(query)
    if rc != 0:
      if not quiet:
        self.logger.logWarn(f"nftables: {str(error).strip() or f'error {rc}'}")
      return False

    # Prevent returning False or empty string on commands that do not produce output
    if rc == 0 and len(output) == 0:
      return True

    return output

  def get_base_dict(self):
    return {'nftables': [{ 'metainfo': { 'json_schema_version': 1} } ] }

  def search_current_chains(self):
    nft_chain_names = {'ip': {'filter': {'input': '', 'forward': ''}, 'nat': {'postrouting': ''} },
                       'ip6': {'filter': {'input': '', 'forward': ''}, 'nat': {'postrouting': ''} } }
    nft_chain_priority = {'ip': {'filter': {'input': None, 'forward': None}, 'nat': {'postrouting': None} },
                          'ip6': {'filter': {'input': None, 'forward': None}, 'nat': {'postrouting': None} } }
    mailcow_chains = set()

    # Command: 'nft list chains'
    _list = {'list' : {'chains': 'null'} }
    command = self.get_base_dict()
    command['nftables'].append(_list)
    kernel_ruleset = self.nft_exec_dict(command)
    if not kernel_ruleset:
      return

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
      if _table == 'filter' and _name == self.chain_name:
        mailcow_chains.add(_family)
      if _hook not in nft_chain_names[_family][_table]: continue
      if _priority is None: continue

      _saved_priority = nft_chain_priority[_family][_table][_hook]
      if _saved_priority is None or _priority < _saved_priority:
        # at this point, we know the chain has:
        # hook and priority set
        # and it has the lowest priority
        nft_chain_priority[_family][_table][_hook] = _priority
        nft_chain_names[_family][_table][_hook] = _name

    self.nft_chain_names = nft_chain_names
    self.mailcow_chains = mailcow_chains

  def has_filter_chains(self, _family: str):
    # no base chains: the filter table does not exist, e.g. ip6 if docker has ipv6 disabled
    return any(self.nft_chain_names[_family]['filter'].values())

  def list_rules(self, _family: str, _table: str, _chain: str):
    # Command: 'nft list chain {family} {table} {chain}', None if the chain does not exist
    _list = {'list': {'chain': {'family': _family, 'table': _table, 'name': _chain}}}
    command = self.get_base_dict()
    command['nftables'].append(_list)
    kernel_ruleset = self.nft_exec_dict(command, quiet=True)
    if not kernel_ruleset or not isinstance(kernel_ruleset, dict):
      return None
    return [_object["rule"] for _object in kernel_ruleset["nftables"] if _object.get("rule")]

  def get_chain_dict(self, _family: str, _name: str):
    # nft (add | create) chain [<family>] <table> <name>
    _chain_opts = {'family': _family, 'table': 'filter', 'name': _name  }
    _add = {'add': {'chain': _chain_opts} }
    final_chain = self.get_base_dict()
    final_chain["nftables"].append(_add)
    return final_chain

  def get_mailcow_jump_rule(self, _family: str, _chain: str):
    _expr_opt = [{'counter': {'family': _family, 'table': 'filter', 'packets': 0, 'bytes': 0}},
                 {'jump': {'target': self.chain_name}}]
    _rule_params = {'family': _family,
                    'table': 'filter',
                    'chain': _chain,
                    'expr': _expr_opt,
                    'comment': "mailcow" }
    return {'insert': {'rule': _rule_params} }

  def is_mailcow_jump(self, rule: dict):
    # by target, jumps restored by iptables-restore carry no nft comment
    return any(isinstance(expr, dict) and isinstance(expr.get('jump'), dict) and expr['jump'].get('target') == self.chain_name
               for expr in rule.get('expr', []))

  def init_chain(self, _family: str):
    # Empty MAILCOW chain with one jump at the top of the input and forward chains
    if not self.has_filter_chains(_family): return

    if self.list_rules(_family, 'filter', self.chain_name) is None:
      if self.nft_exec_dict(self.get_chain_dict(_family, self.chain_name)):
        self.logger.logInfo(f"MAILCOW {_family} chain created successfully.")
    else:
      flush_chain = {'flush': {'chain': {'family': _family, 'table': 'filter', 'name': self.chain_name}}}
      command = self.get_base_dict()
      command['nftables'].append(flush_chain)
      self.nft_exec_dict(command)

    self.ensure_jumps(_family)

  def ensure_chains(self):
    # Recreates missing MAILCOW chains and moves the jumps back to the top, True if a chain was recreated
    self.search_current_chains()
    recreated = False
    for _family in ['ip', 'ip6']:
      if not self.has_filter_chains(_family): continue

      if _family not in self.mailcow_chains:
        if self.nft_exec_dict(self.get_chain_dict(_family, self.chain_name)):
          self.logger.logWarn(f"MAILCOW {_family} chain was missing and has been recreated")
          recreated = True
      self.ensure_jumps(_family)
    return recreated

  def ensure_jumps(self, _family: str):
    # Exactly one jump to MAILCOW as first rule: docker inserts its jumps (DOCKER-USER, DOCKER-FORWARD) at the top
    for hook in ['input', 'forward']:
      base_chain = self.nft_chain_names[_family]['filter'][hook]
      if not base_chain: continue

      rules = self.list_rules(_family, 'filter', base_chain)
      if rules is None: continue

      positions = [position for position, rule in enumerate(rules) if self.is_mailcow_jump(rule)]
      if positions == [0]: continue

      # delete and insert in one transaction, the chain is never without jump
      command = self.get_base_dict()
      for position in positions:
        _rule_opts = {'family': _family, 'table': 'filter', 'chain': base_chain, 'handle': rules[position]['handle']}
        command['nftables'].append({'delete': {'rule': _rule_opts}})
      command['nftables'].append(self.get_mailcow_jump_rule(_family, base_chain))
      if self.nft_exec_dict(command):
        if positions:
          self.logger.logInfo(f"Moved MAILCOW jump to the top of the {_family} {base_chain} chain, it was at position {positions[0] + 1}")
        else:
          self.logger.logInfo(f"Added MAILCOW jump to the {_family} {base_chain} chain")

  def clearTable(self, _family: str):
    # Jumps first, the chain can only be deleted without references. Separate steps, so one failing step
    # does not roll back the others
    _list = {'list': {'table': {'family': _family, 'name': 'filter'}}}
    command = self.get_base_dict()
    command['nftables'].append(_list)
    kernel_ruleset = self.nft_exec_dict(command, quiet=True)
    if not kernel_ruleset or not isinstance(kernel_ruleset, dict):
      return

    delete_jumps = self.get_base_dict()
    for _object in kernel_ruleset["nftables"]:
      rule = _object.get("rule")
      if rule and self.is_mailcow_jump(rule):
        _rule_opts = {'family': _family, 'table': 'filter', 'chain': rule['chain'], 'handle': rule['handle']}
        delete_jumps['nftables'].append({'delete': {'rule': _rule_opts}})
    if len(delete_jumps['nftables']) > 1:
      self.nft_exec_dict(delete_jumps)

    if self.list_rules(_family, 'filter', self.chain_name) is None:
      return
    mailcow_chain = {'family': _family, 'table': 'filter', 'name': self.chain_name}
    command = self.get_base_dict()
    command['nftables'].append({'flush': {'chain': mailcow_chain}})
    command['nftables'].append({'delete': {'chain': mailcow_chain}})
    if self.nft_exec_dict(command):
      self.logger.logInfo(f"Clear completed: {_family}")

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

    rules = self.list_rules(_family, 'nat', chain_name)
    if rules is None:
      return

    rule_position = 0
    rule_handle = None
    rule_found = False
    for rule in rules:
      if not rule.get("comment") or not rule["comment"] == "mailcow":
        rule_position +=1
        continue

      rule_found = True
      rule_handle = rule["handle"]
      break

    dest_net = ipaddress.ip_network(source_address, strict=False)
    target_net = ipaddress.ip_network(snat_target, strict=False)

    if rule_found:
      try:
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
      except (KeyError, IndexError, TypeError, ValueError):
        # rule of another format, replace it
        saddr_net = dest_net
        match = False
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

  def get_rules_handle(self, _family: str, _table: str, chain_name: str, _comment_filter = "mailcow"):
    rules = self.list_rules(_family, _table, chain_name) or []
    return [rule["handle"] for rule in rules if rule.get("comment") == _comment_filter]

  def get_ban_rule(self, network, _family: str):
    expr_opt = []
    right_dict = {'prefix': {'addr': str(network.network_address), 'len': int(network.prefixlen) } }

    left_dict = {'payload': {'protocol': _family, 'field': 'saddr'} }
    match_dict = {'op': '==', 'left': left_dict, 'right': right_dict }
    expr_opt.append({'match': match_dict})

    counter_dict = {'counter': {'family': _family, 'table': "filter", 'packets': 0, 'bytes': 0} }
    expr_opt.append(counter_dict)

    expr_opt.append({'drop': "null"})

    rule_dict = {'family': _family, 'table': "filter", 'chain': self.chain_name, 'expr': expr_opt}

    return {'insert': {'rule': rule_dict} }

  def ban_rule_network(self, rule: dict, _family: str):
    # Source network of a ban rule (saddr match and drop), None for every other rule
    if rule.get("comment") in self.ISOLATION_COMMENTS:
      return None
    exprs = [expr for expr in rule.get("expr", []) if isinstance(expr, dict)]
    if not any('drop' in expr or 'reject' in expr for expr in exprs):
      return None
    for expr in exprs:
      match = expr.get("match")
      if not isinstance(match, dict) or match.get("op") != "==":
        continue
      left = match.get("left")
      payload = left.get("payload") if isinstance(left, dict) else None
      if not isinstance(payload, dict) or payload.get("protocol") != _family or payload.get("field") != "saddr":
        continue
      right = match.get("right")
      try:
        if isinstance(right, dict) and isinstance(right.get("prefix"), dict):
          return ipaddress.ip_network(f'{right["prefix"]["addr"]}/{right["prefix"]["len"]}', strict=False)
        if isinstance(right, str):
          return ipaddress.ip_network(right, strict=False)
      except (KeyError, ValueError):
        return None
    return None

  def split_families(self, sources):
    families = {}
    for source in sources:
      network = ipaddress.ip_network(source, strict=False)
      families.setdefault('ip' if network.version == 4 else 'ip6', []).append(network)
    return families

  def ban(self, sources):
    # One drop rule per network, existing rules are kept. Returns the networks that could not be banned
    failed = []
    for _family, networks in self.split_families(sources).items():
      rules = self.list_rules(_family, 'filter', self.chain_name)
      if rules is None:
        failed += networks
        continue
      existing = {self.ban_rule_network(rule, _family) for rule in rules}
      missing = [network for network in dict.fromkeys(networks) if network not in existing]
      if not missing: continue

      command = self.get_base_dict()
      for network in missing:
        command['nftables'].append(self.get_ban_rule(network, _family))
      if not self.nft_exec_dict(command):
        failed += missing
    return [str(network) for network in failed]

  def unban(self, sources):
    # Removes every rule of the networks, also duplicates of older versions. Returns the unbanned networks
    removed = []
    for _family, networks in self.split_families(sources).items():
      rules = self.list_rules(_family, 'filter', self.chain_name)
      if not rules: continue

      targets = set(networks)
      command = self.get_base_dict()
      found = set()
      for rule in rules:
        network = self.ban_rule_network(rule, _family)
        if network in targets:
          _rule_opts = {'family': _family, 'table': 'filter', 'chain': self.chain_name, 'handle': rule['handle']}
          command['nftables'].append({'delete': {'rule': _rule_opts}})
          found.add(network)
      if found and self.nft_exec_dict(command):
        removed += found
    return [str(network) for network in removed]

  def create_mailcow_isolation_rule(self, _interface:str, _dports:list, _allow:str = ""):
    # One rule per port: iptables-nft cannot show an anonymous set, a host that saves and restores the ruleset
    # with iptables-save (e.g. netfilter-persistent) would get a drop for all ports
    for comment in self.ISOLATION_COMMENTS:
      for handle in self.get_rules_handle("ip", "filter", self.chain_name, comment):
        self.delete_filter_rule("ip", self.chain_name, handle)

    if not self.nft_exec_dict(self.get_isolation_dict(_interface, _dports, self.ISOLATION_COMMENTS[0], {"drop": None})):
      self.logger.logCrit(f"Error adding {self.chain_name} isolation")
      return False

    # separate, an invalid address must not prevent the drop rules. Inserted last, so they are above them
    if _allow and not self.nft_exec_dict(self.get_isolation_dict(_interface, _dports, self.ISOLATION_COMMENTS[1], {"accept": None}, _allow)):
      self.logger.logCrit(f"Error adding {self.chain_name} isolation exception for {_allow}")
      return False

    return True

  def get_isolation_dict(self, _interface:str, _dports:list, _comment:str, _verdict:dict, _saddr:str = ""):
    json_command = self.get_base_dict()
    for port in _dports:
      expr = []
      if _saddr:
        expr.append({"match": {"op": "==", "left": {"payload": {"protocol": "ip", "field": "saddr"}}, "right": _saddr}})
      expr += [
        {"match": {"op": "!=", "left": {"meta": {"key": "iifname"}}, "right": _interface}},
        {"match": {"op": "==", "left": {"meta": {"key": "oifname"}}, "right": _interface}},
        {"match": {"op": "==", "left": {"payload": {"protocol": "tcp", "field": "dport"}}, "right": int(port)}},
        {"counter": {"packets": 0, "bytes": 0}},
        _verdict
      ]
      json_command["nftables"].append({"insert": {"rule": {
        "family": "ip",
        "table": "filter",
        "chain": self.chain_name,
        "comment": _comment,
        "expr": expr
      }}})
    return json_command
