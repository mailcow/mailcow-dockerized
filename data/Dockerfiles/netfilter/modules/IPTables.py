import iptc
import time
import ipaddress
import contextlib

class IPTables:
  def __init__(self, chain_name, logger):
    self.chain_name = chain_name
    self.logger = logger

  def initChainIPv4(self):
    self.init_chain(iptc.Table(iptc.Table.FILTER))

  def initChainIPv6(self):
    self.init_chain(iptc.Table6(iptc.Table6.FILTER))

  def clearIPv4Table(self):
    self.clearTable(iptc.Table(iptc.Table.FILTER))

  def clearIPv6Table(self):
    self.clearTable(iptc.Table6(iptc.Table6.FILTER))

  @contextlib.contextmanager
  def transaction(self, table):
    # One commit for all changes, autocommit rewrites the whole table for every change
    table.autocommit = False
    table.refresh()
    try:
      yield
      table.commit()
    finally:
      table.refresh()
      table.autocommit = True

  def new_rule(self, table):
    return iptc.Rule6() if isinstance(table, iptc.Table6) else iptc.Rule()

  def family(self, table):
    return 'IPv6' if isinstance(table, iptc.Table6) else 'IPv4'

  def init_chain(self, filter_table):
    # Empty MAILCOW chain with one jump at the top of the input and forward chains
    filter_table.refresh()
    if filter_table.is_chain(self.chain_name):
      filter_table.flush_entries(self.chain_name)
    else:
      filter_table.create_chain(self.chain_name)
    self.ensure_jumps(filter_table)

  def ensure_chains(self):
    # Recreates missing MAILCOW chains and moves the jumps back to the top, True if a chain was recreated
    recreated = False
    for filter_table in [iptc.Table(iptc.Table.FILTER), iptc.Table6(iptc.Table6.FILTER)]:
      filter_table.refresh()
      if not filter_table.is_chain(self.chain_name):
        filter_table.create_chain(self.chain_name)
        self.logger.logWarn(f'MAILCOW {self.family(filter_table)} chain was missing and has been recreated')
        recreated = True
      self.ensure_jumps(filter_table)
    return recreated

  def ensure_jumps(self, filter_table):
    # Exactly one jump to MAILCOW as first rule: docker inserts its jump (DOCKER-USER) at the top
    for name in ['INPUT', 'FORWARD']:
      chain = iptc.Chain(filter_table, name)
      positions = [position for position, rule in enumerate(chain.rules) if rule.target.name == self.chain_name]
      if positions == [0]: continue

      with self.transaction(filter_table):
        for rule in [rule for rule in chain.rules if rule.target.name == self.chain_name]:
          chain.delete_rule(rule)
        rule = self.new_rule(filter_table)
        rule.target = iptc.Target(rule, self.chain_name)
        chain.insert_rule(rule)
      if positions:
        self.logger.logInfo(f'Moved MAILCOW jump to the top of the {self.family(filter_table)} {name} chain, it was at position {positions[0] + 1}')
      else:
        self.logger.logInfo(f'Added MAILCOW jump to the {self.family(filter_table)} {name} chain')

  def clearTable(self, filter_table):
    try:
      with self.transaction(filter_table):
        if not filter_table.is_chain(self.chain_name):
          return
        for chain in filter_table.chains:
          for rule in chain.rules:
            if rule.target.name == self.chain_name:
              chain.delete_rule(rule)
        filter_table.flush_entries(self.chain_name)
        filter_table.delete_chain(self.chain_name)
      self.logger.logInfo(f'Clear completed: {self.family(filter_table)}')
    except Exception as ex:
      self.logger.logWarn(f'Error clearing the {self.family(filter_table)} MAILCOW chain: {ex}')

  def ban_rule_network(self, rule):
    # Source network of a ban rule, None for every other rule
    if rule.target.name not in ('REJECT', 'DROP') or rule.matches or rule.in_interface or rule.out_interface:
      return None
    try:
      return ipaddress.ip_network(rule.src, strict=False)
    except ValueError:
      return None

  def split_tables(self, sources):
    networks = {4: [], 6: []}
    for source in sources:
      network = ipaddress.ip_network(source, strict=False)
      networks[network.version].append(network)
    tables = [(iptc.Table(iptc.Table.FILTER), networks[4]), (iptc.Table6(iptc.Table6.FILTER), networks[6])]
    return [(table, table_networks) for table, table_networks in tables if table_networks]

  def ban(self, sources):
    # One REJECT rule per network, existing rules are kept. Returns the networks that could not be banned
    failed = []
    for filter_table, networks in self.split_tables(sources):
      try:
        with self.transaction(filter_table):
          chain = iptc.Chain(filter_table, self.chain_name)
          existing = {self.ban_rule_network(rule) for rule in chain.rules}
          for network in dict.fromkeys(networks):
            if network in existing: continue
            rule = self.new_rule(filter_table)
            rule.src = str(network)
            rule.target = iptc.Target(rule, "REJECT")
            chain.insert_rule(rule)
      except Exception as ex:
        self.logger.logWarn(f'iptables: {ex}')
        failed += networks
    return [str(network) for network in failed]

  def unban(self, sources):
    # Removes every rule of the networks, also duplicates of older versions. Returns the unbanned networks
    removed = []
    for filter_table, networks in self.split_tables(sources):
      targets = set(networks)
      try:
        with self.transaction(filter_table):
          chain = iptc.Chain(filter_table, self.chain_name)
          found = set()
          for rule in chain.rules:
            network = self.ban_rule_network(rule)
            if network in targets:
              chain.delete_rule(rule)
              found.add(network)
      except Exception as ex:
        self.logger.logWarn(f'iptables: {ex}')
        continue
      removed += found
    return [str(network) for network in removed]

  def snat4(self, snat_target, source):
    try:
      table = iptc.Table('nat')
      table.refresh()
      chain = iptc.Chain(table, 'POSTROUTING')
      table.autocommit = False
      new_rule = self.getSnat4Rule(snat_target, source)

      if not chain.rules:
        # if there are no rules in the chain, insert the new rule directly
        self.logger.logInfo(f'Added POSTROUTING rule for source network {new_rule.src} to SNAT target {snat_target}')
        chain.insert_rule(new_rule)
      else:
        for position, rule in enumerate(chain.rules):
          if not hasattr(rule.target, 'parameter'):
              continue
          match = all((
            new_rule.get_src() == rule.get_src(),
            new_rule.get_dst() == rule.get_dst(),
            new_rule.target.parameters == rule.target.parameters,
            new_rule.target.name == rule.target.name
          ))
          if position == 0:
            if not match:
              self.logger.logInfo(f'Added POSTROUTING rule for source network {new_rule.src} to SNAT target {snat_target}')
              chain.insert_rule(new_rule)
          else:
            if match:
              self.logger.logInfo(f'Remove rule for source network {new_rule.src} to SNAT target {snat_target} from POSTROUTING chain at position {position}')
              chain.delete_rule(rule)

      table.commit()
      table.autocommit = True
      return True
    except:
      self.logger.logCrit('Error running SNAT4, retrying...')
      return False

  def snat6(self, snat_target, source):
    try:
      table = iptc.Table6('nat')
      table.refresh()
      chain = iptc.Chain(table, 'POSTROUTING')
      table.autocommit = False
      new_rule = self.getSnat6Rule(snat_target, source)

      if new_rule not in chain.rules:
        self.logger.logInfo('Added POSTROUTING rule for source network %s to SNAT target %s' % (new_rule.src, snat_target))
        chain.insert_rule(new_rule)
      else:
        for position, item in enumerate(chain.rules):
          if item == new_rule:
            if position != 0:
              chain.delete_rule(new_rule)

      table.commit()
      table.autocommit = True
    except:
      self.logger.logCrit('Error running SNAT6, retrying...')


  def getSnat4Rule(self, snat_target, source):
    rule = iptc.Rule()
    rule.src = source
    rule.dst = '!' + rule.src
    target = rule.create_target("SNAT")
    target.to_source = snat_target
    match = rule.create_match("comment")
    match.comment = f'{int(round(time.time()))}'
    return rule

  def getSnat6Rule(self, snat_target, source):
    rule = iptc.Rule6()
    rule.src = source
    rule.dst = '!' + rule.src
    target = rule.create_target("SNAT")
    target.to_source = snat_target
    return rule

  def create_mailcow_isolation_rule(self, _interface:str, _dports:list, _allow:str = ""):
    try:
      table = iptc.Table(iptc.Table.FILTER)
      table.refresh()
      chain = iptc.Chain(table, self.chain_name)

      # insert mailcow isolation rule
      rule = iptc.Rule()
      rule.in_interface = f'!{_interface}'
      rule.out_interface = _interface
      rule.protocol = 'tcp'
      rule.create_target("DROP")
      match = rule.create_match("multiport")
      match.dports = ','.join(map(str, _dports))

      if rule in chain.rules:
        chain.delete_rule(rule)
      chain.insert_rule(rule, position=0)

      # insert mailcow isolation exception rule
      if _allow:
        rule = iptc.Rule()
        rule.src = _allow
        rule.in_interface = f'!{_interface}'
        rule.out_interface = _interface
        rule.protocol = 'tcp'
        rule.create_target("ACCEPT")
        match = rule.create_match("multiport")
        match.dports = ','.join(map(str, _dports))

        if rule in chain.rules:
          chain.delete_rule(rule)
        chain.insert_rule(rule, position=0)


      return True
    except Exception as e:
      self.logger.logCrit(f"Error adding {self.chain_name} isolation: {e}")
      return False
