import ipaddress
import shutil
import subprocess
import unittest

import nftables

from modules.NFTables import NFTables
from support import FakeLogger


def nft(command):
  rc, output, error = nftables.Nftables().cmd(command)
  if rc != 0:
    raise RuntimeError(error)
  return output


def kernel_available():
  # own network namespace with CAP_NET_ADMIN (docker run --network none --cap-add NET_ADMIN)
  try:
    nft('add table ip mailcow_test')
    nft('delete table ip mailcow_test')
    return True
  except RuntimeError:
    return False


class RuleParsingTest(unittest.TestCase):
  def setUp(self):
    self.tables = NFTables.__new__(NFTables)
    self.tables.chain_name = 'MAILCOW'

  def test_ban_rule_network(self):
    prefix = {'expr': [{'match': {'op': '==', 'left': {'payload': {'protocol': 'ip', 'field': 'saddr'}},
                                  'right': {'prefix': {'addr': '203.0.113.0', 'len': 24}}}},
                       {'counter': {'packets': 0, 'bytes': 0}}, {'drop': None}]}
    host = {'expr': [{'match': {'op': '==', 'left': {'payload': {'protocol': 'ip6', 'field': 'saddr'}},
                                'right': '2001:db8::1'}}, {'drop': None}]}
    accept = {'expr': [{'match': {'op': '==', 'left': {'payload': {'protocol': 'ip', 'field': 'saddr'}},
                                  'right': '203.0.113.1'}}, {'accept': None}]}
    isolation = dict(prefix, comment='mailcow isolation')
    self.assertEqual(self.tables.ban_rule_network(prefix, 'ip'), ipaddress.ip_network('203.0.113.0/24'))
    self.assertEqual(self.tables.ban_rule_network(host, 'ip6'), ipaddress.ip_network('2001:db8::1/128'))
    self.assertIsNone(self.tables.ban_rule_network(host, 'ip'))
    self.assertIsNone(self.tables.ban_rule_network(accept, 'ip'))
    self.assertIsNone(self.tables.ban_rule_network(isolation, 'ip'))
    self.assertIsNone(self.tables.ban_rule_network({'expr': [{'drop': None}]}, 'ip'))

  def test_is_mailcow_jump(self):
    self.assertTrue(self.tables.is_mailcow_jump({'expr': [{'counter': None}, {'jump': {'target': 'MAILCOW'}}]}))
    self.assertFalse(self.tables.is_mailcow_jump({'expr': [{'jump': {'target': 'DOCKER-USER'}}], 'comment': 'mailcow'}))


@unittest.skipUnless(kernel_available(), 'needs an own network namespace with CAP_NET_ADMIN')
class KernelTest(unittest.TestCase):
  def setUp(self):
    nft('flush ruleset')
    for family in ['ip', 'ip6']:
      nft('add table %s filter' % family)
      nft('add chain %s filter INPUT { type filter hook input priority 0; policy accept; }' % family)
      nft('add chain %s filter FORWARD { type filter hook forward priority 0; policy drop; }' % family)
      nft('add chain %s filter DOCKER-USER' % family)
      nft('add chain %s filter DOCKER-FORWARD' % family)
      nft('add rule %s filter FORWARD counter jump DOCKER-USER' % family)
      nft('add rule %s filter FORWARD counter jump DOCKER-FORWARD' % family)
    nft('add table ip nat')
    nft('add chain ip nat POSTROUTING { type nat hook postrouting priority 100; policy accept; }')
    self.logger = FakeLogger()
    self.tables = NFTables('MAILCOW', self.logger)
    self.tables.initChainIPv4()
    self.tables.initChainIPv6()

  def tearDown(self):
    nft('flush ruleset')

  def rules(self, family, chain, table='filter'):
    return self.tables.list_rules(family, table, chain)

  def jumps(self, family, chain):
    return [position for position, rule in enumerate(self.rules(family, chain)) if self.tables.is_mailcow_jump(rule)]

  def bans(self, family='ip'):
    return [str(self.tables.ban_rule_network(rule, family)) for rule in self.rules(family, 'MAILCOW')
            if self.tables.ban_rule_network(rule, family)]

  def test_init_puts_one_jump_on_top(self):
    for family in ['ip', 'ip6']:
      self.assertEqual(self.jumps(family, 'INPUT'), [0])
      self.assertEqual(self.jumps(family, 'FORWARD'), [0])

  def test_init_empties_an_existing_chain(self):
    self.tables.ban(['203.0.113.1'])
    self.tables.initChainIPv4()
    self.assertEqual(self.bans(), [])
    self.assertEqual(self.jumps('ip', 'FORWARD'), [0])

  def test_jump_is_moved_back_to_the_top(self):
    # docker restarts and inserts its jumps again
    nft('insert rule ip filter FORWARD counter jump DOCKER-FORWARD')
    nft('insert rule ip filter FORWARD counter jump DOCKER-USER')
    self.assertFalse(self.tables.ensure_chains())
    self.assertEqual(self.jumps('ip', 'FORWARD'), [0])
    self.assertEqual(len(self.rules('ip', 'FORWARD')), 5)

  def test_duplicate_jumps_without_comment_are_merged(self):
    # as left by iptables-restore
    nft('add rule ip filter FORWARD jump MAILCOW')
    nft('insert rule ip filter FORWARD jump MAILCOW')
    self.tables.ensure_chains()
    self.assertEqual(self.jumps('ip', 'FORWARD'), [0])
    self.assertEqual(self.rules('ip', 'FORWARD')[0].get('comment'), 'mailcow')

  def test_missing_chain_is_recreated(self):
    self.tables.clearIPv4Table()
    self.assertIsNone(self.rules('ip', 'MAILCOW'))
    self.assertTrue(self.tables.ensure_chains())
    self.assertEqual(self.rules('ip', 'MAILCOW'), [])
    self.assertEqual(self.jumps('ip', 'INPUT'), [0])
    self.assertFalse(self.tables.ensure_chains())

  def test_clear_removes_jumps_without_comment(self):
    nft('add rule ip filter INPUT jump MAILCOW')
    nft('add rule ip filter DOCKER-USER jump MAILCOW')
    self.tables.ban(['203.0.113.1'])
    self.tables.clearIPv4Table()
    self.assertIsNone(self.rules('ip', 'MAILCOW'))
    self.assertEqual(self.jumps('ip', 'INPUT'), [])
    self.assertEqual(self.jumps('ip', 'DOCKER-USER'), [])

  def test_ban_is_idempotent(self):
    self.assertEqual(self.tables.ban(['203.0.113.1/32', '198.51.100.0/24', '2001:db8::/64']), [])
    self.assertEqual(self.tables.ban(['203.0.113.1', '198.51.100.0/24']), [])
    self.assertEqual(sorted(self.bans()), ['198.51.100.0/24', '203.0.113.1/32'])
    self.assertEqual(self.bans('ip6'), ['2001:db8::/64'])

  def test_unban_removes_duplicates(self):
    nft('add rule ip filter MAILCOW ip saddr 203.0.113.1 counter drop')
    nft('add rule ip filter MAILCOW ip saddr 203.0.113.1 counter drop')
    nft('add rule ip filter MAILCOW ip saddr 203.0.113.2 counter drop')
    self.assertEqual(self.tables.unban(['203.0.113.1/32']), ['203.0.113.1/32'])
    self.assertEqual(self.bans(), ['203.0.113.2/32'])
    self.assertEqual(self.tables.unban(['203.0.113.1/32']), [])

  def test_ban_without_ip6_table(self):
    self.tables.clearIPv6Table()
    nft('delete table ip6 filter')
    self.tables.search_current_chains()
    self.assertEqual(self.tables.ban(['2001:db8::/64', '203.0.113.1']), ['2001:db8::/64'])
    self.assertFalse(self.tables.ensure_chains())

  def test_isolation_rules(self):
    self.tables.ban(['203.0.113.1'])
    for _ in range(2):
      self.assertTrue(self.tables.create_mailcow_isolation_rule('br-mailcow', [3306, 6379], '192.0.2.10'))
    rules = self.rules('ip', 'MAILCOW')
    self.assertEqual([rule.get('comment') for rule in rules],
                     ['mailcow isolation allow', 'mailcow isolation allow', 'mailcow isolation', 'mailcow isolation', None])
    self.assertEqual(self.bans(), ['203.0.113.1/32'])
    # the ban is not lifted by unbanning the network of the replica
    self.tables.unban(['192.0.2.10'])
    self.assertEqual(len(self.rules('ip', 'MAILCOW')), 5)

  def test_isolation_with_invalid_replica_keeps_the_drop_rules(self):
    self.assertFalse(self.tables.create_mailcow_isolation_rule('br-mailcow', [3306], '2001:db8::1'))
    self.assertEqual([rule.get('comment') for rule in self.rules('ip', 'MAILCOW')], ['mailcow isolation'])

  @unittest.skipUnless(shutil.which('iptables-nft-save') or shutil.which('iptables-save'), 'needs iptables-save')
  def test_isolation_rules_survive_iptables_save(self):
    self.tables.create_mailcow_isolation_rule('br-mailcow', [3306, 6379], '192.0.2.10')
    binary = shutil.which('iptables-nft-save') or shutil.which('iptables-save')
    saved = subprocess.run([binary, '-t', 'filter'], capture_output=True, text=True, check=True).stdout
    lines = [line for line in saved.splitlines() if line.startswith('-A MAILCOW')]
    self.assertEqual(len(lines), 4)
    for line in lines:
      self.assertRegex(line, r'--dport (3306|6379)')

  def test_snat(self):
    nft('add rule ip nat POSTROUTING counter masquerade')
    self.tables.snat4('192.0.2.1', '172.22.1.0/24')
    rules = self.rules('ip', 'POSTROUTING', 'nat')
    self.assertEqual(rules[0].get('comment'), 'mailcow')
    self.tables.snat4('192.0.2.1', '172.22.1.0/24')
    self.assertEqual(len(self.rules('ip', 'POSTROUTING', 'nat')), 2)


if __name__ == '__main__':
  unittest.main()
