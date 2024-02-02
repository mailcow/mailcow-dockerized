import iptc
import time
import os

class IPTables:
  def __init__(self, chain_name, logger):
    self.chain_name = chain_name
    self.logger = logger

  def initChainIPv4(self):
    if not iptc.Chain(iptc.Table(iptc.Table.FILTER), self.chain_name) in iptc.Table(iptc.Table.FILTER).chains:
      iptc.Table(iptc.Table.FILTER).create_chain(self.chain_name)
    for c in ['FORWARD', 'INPUT']:
      chain = iptc.Chain(iptc.Table(iptc.Table.FILTER), c)
      rule = iptc.Rule()
      rule.src = '0.0.0.0/0'
      rule.dst = '0.0.0.0/0'
      target = iptc.Target(rule, self.chain_name)
      rule.target = target
      if rule not in chain.rules:
        chain.insert_rule(rule)

  def initChainIPv6(self):
    if not iptc.Chain(iptc.Table6(iptc.Table6.FILTER), self.chain_name) in iptc.Table6(iptc.Table6.FILTER).chains:
      iptc.Table6(iptc.Table6.FILTER).create_chain(self.chain_name)
    for c in ['FORWARD', 'INPUT']:
      chain = iptc.Chain(iptc.Table6(iptc.Table6.FILTER), c)
      rule = iptc.Rule6()
      rule.src = '::/0'
      rule.dst = '::/0'
      target = iptc.Target(rule, self.chain_name)
      rule.target = target
      if rule not in chain.rules:
        chain.insert_rule(rule)

  def checkIPv4ChainOrder(self):
    filter_table = iptc.Table(iptc.Table.FILTER)
    filter_table.refresh()
    return self.checkChainOrder(filter_table)

  def checkIPv6ChainOrder(self):
    filter_table = iptc.Table6(iptc.Table6.FILTER)
    filter_table.refresh()
    return self.checkChainOrder(filter_table)

  def checkChainOrder(self, filter_table):
    err = False
    exit_code = None

    forward_chain = iptc.Chain(filter_table, 'FORWARD')
    input_chain = iptc.Chain(filter_table, 'INPUT')
    for chain in [forward_chain, input_chain]:
      target_found = False
      for position, item in enumerate(chain.rules):
        if item.target.name == self.chain_name:
          target_found = True
          if position > 2:
            self.logger.logCrit('Error in %s chain: %s target not found, restarting container' % (chain.name, self.chain_name))
            err = True
            exit_code = 2
      if not target_found:
        self.logger.logCrit('Error in %s chain: %s target not found, restarting container' % (chain.name, self.chain_name))
        err = True
        exit_code = 2

    return err, exit_code

  def clearIPv4Table(self):
    self.clearTable(iptc.Table(iptc.Table.FILTER))

  def clearIPv6Table(self):
    self.clearTable(iptc.Table6(iptc.Table6.FILTER))

  def clearTable(self, filter_table):
    filter_table.autocommit = False
    forward_chain = iptc.Chain(filter_table, "FORWARD")
    input_chain = iptc.Chain(filter_table, "INPUT")
    mailcow_chain = iptc.Chain(filter_table, self.chain_name)
    if mailcow_chain in filter_table.chains:
      for rule in mailcow_chain.rules:
        mailcow_chain.delete_rule(rule)
      for rule in forward_chain.rules:
        if rule.target.name == self.chain_name:
          forward_chain.delete_rule(rule)
      for rule in input_chain.rules:
        if rule.target.name == self.chain_name:
          input_chain.delete_rule(rule)
      filter_table.delete_chain(self.chain_name)
    filter_table.commit()
    filter_table.refresh()
    filter_table.autocommit = True

  def banIPv4(self, source):
    chain = iptc.Chain(iptc.Table(iptc.Table.FILTER), self.chain_name)
    rule = iptc.Rule()
    rule.src = source
    target = iptc.Target(rule, "REJECT")
    rule.target = target
    if rule in chain.rules:
      return False
    chain.insert_rule(rule)
    return True

  def banIPv6(self, source):
    chain = iptc.Chain(iptc.Table6(iptc.Table6.FILTER), self.chain_name)
    rule = iptc.Rule6()
    rule.src = source
    target = iptc.Target(rule, "REJECT")
    rule.target = target
    if rule in chain.rules:
      return False
    chain.insert_rule(rule)
    return True

  def unbanIPv4(self, source):
    chain = iptc.Chain(iptc.Table(iptc.Table.FILTER), self.chain_name)
    rule = iptc.Rule()
    rule.src = source
    target = iptc.Target(rule, "REJECT")
    rule.target = target
    if rule not in chain.rules: 
      return False
    chain.delete_rule(rule)
    return True

  def unbanIPv6(self, source):
    chain = iptc.Chain(iptc.Table6(iptc.Table6.FILTER), self.chain_name)
    rule = iptc.Rule6()
    rule.src = source
    target = iptc.Target(rule, "REJECT")
    rule.target = target
    if rule not in chain.rules:
      return False
    chain.delete_rule(rule)
    return True

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
      chain = iptc.Chain(iptc.Table(iptc.Table.FILTER), self.chain_name)

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
      if _allow != "":
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