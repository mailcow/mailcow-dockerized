import json
import unittest
from unittest import mock

import dns.exception
import dns.resolver

from modules.Netfilter import Netfilter
from support import FakeLogger, FakeRedis, FakeTables


class NetfilterTest(unittest.TestCase):
  ENV = {'IPV4_NETWORK': '172.22.1', 'IPV6_NETWORK': 'fd4d:6169:6c63:6f77::/64', 'MAILCOW_REPLICA_IP': ''}

  def setUp(self):
    self.r = FakeRedis()
    self.r.set('F2B_OPTIONS', json.dumps({'max_attempts': 2}))
    self.tables = FakeTables()
    self.logger = FakeLogger()

  def create(self, **env):
    netfilter = Netfilter(self.r, self.tables, self.logger, dict(self.ENV, **env))
    self.r.stop = netfilter.stop
    netfilter.options.refresh(self.r)
    netfilter.regexes.refresh(self.r)
    return netfilter

  def sasl(self, address):
    return 'warning: unknown[%s]: SASL LOGIN authentication failed: UGFzc3dvcmQ6' % address

  def test_setup_restores_bans_and_resets_the_firewall(self):
    self.r.hset('F2B_ACTIVE_BANS', '1.2.3.4/32', '9999999999')
    self.r.hset('F2B_PERM_BANS', '198.51.100.1', '1')
    self.r.set('F2B_LOG', 'old')
    netfilter = self.create()
    netfilter.setup()
    self.assertEqual(self.tables.names(), ['clear', 'clear', 'init', 'init', 'isolation', 'ban'])
    self.assertEqual(self.tables.rules, {'1.2.3.4/32'})
    self.assertEqual(self.r.hgetall('F2B_PERM_BANS'), {})
    self.assertEqual(self.r.get('NETFILTER_LOG'), 'old')

  def test_isolation_can_be_disabled(self):
    self.create(DISABLE_NETFILTER_ISOLATION_RULE='y').setup()
    self.assertNotIn('isolation', self.tables.names())

  def test_isolation_allows_the_replica(self):
    self.create(MAILCOW_REPLICA_IP='192.0.2.10').setup()
    self.assertIn(('isolation', 'br-mailcow', (3306, 6379, 8983, 12345), '192.0.2.10'), self.tables.calls)

  def test_shutdown_keeps_the_active_bans(self):
    netfilter = self.create()
    netfilter.handle_line(self.sasl('1.2.3.4'))
    netfilter.handle_line(self.sasl('1.2.3.4'))
    self.r.hset('F2B_PERM_BANS', '198.51.100.1', '1')
    netfilter.shutdown()
    self.assertEqual(self.tables.rules, set())
    self.assertIn('1.2.3.4/32', self.r.hgetall('F2B_ACTIVE_BANS'))
    self.assertEqual(self.r.hgetall('F2B_PERM_BANS'), {})

  def test_handle_line_bans(self):
    netfilter = self.create()
    netfilter.handle_line(self.sasl('1.2.3.4'))
    netfilter.handle_line(self.sasl('1.2.3.4'))
    self.assertEqual(self.tables.rules, {'1.2.3.4/32'})
    self.assertIn('1.2.3.4 matched rule id 3 (%s)' % self.sasl('1.2.3.4'), self.logger.messages('warn'))

  def test_handle_line_with_trailing_colon(self):
    netfilter = self.create()
    netfilter.handle_line(self.sasl('1.2.3.4:'))
    netfilter.handle_line(self.sasl('1.2.3.4:'))
    self.assertEqual(self.tables.rules, {'1.2.3.4/32'})

  def test_handle_line_ignores_private_and_invalid_addresses(self):
    netfilter = self.create()
    for _ in range(3):
      netfilter.handle_line(self.sasl('192.168.1.1'))
      netfilter.handle_line(self.sasl('::1'))
      netfilter.handle_line(self.sasl('1.2.3'))
    self.assertEqual(self.tables.rules, set())
    self.assertTrue(any('invalid address 1.2.3' in message for message in self.logger.messages('warn')))

  def test_handle_line_with_replaced_characters(self):
    netfilter = self.create()
    line = b'warning: unknown[1.2.3.4]: SASL LOGIN authentication failed: \xff\xfe'.decode('utf-8', 'replace')
    netfilter.handle_line(line)
    netfilter.handle_line(line)
    self.assertEqual(self.tables.rules, {'1.2.3.4/32'})

  def test_unavailable_auth_backend_is_no_failed_login(self):
    # Dovecot logs the temporary failure, Postfix then the failed SASL login like for a wrong password
    netfilter = self.create()
    netfilter.handle_line('auth-worker(1): Error: conn unix:auth-worker (pid=2,uid=401): auth-worker<4>: '
                          'lua(a@example.org,1.2.3.4): passdb-lua: Upstream unavailable')
    for _ in range(3):
      netfilter.handle_line('warning: unknown[1.2.3.4]: SASL LOGIN authentication failed: (reason unavailable), sasl_username=a@example.org')
    self.assertEqual(self.tables.rules, set())
    self.assertNotIn('1.2.3.4/32', self.r.hgetall('F2B_ACTIVE_BANS'))

  def test_handle_line_survives_errors(self):
    netfilter = self.create()
    with mock.patch.object(netfilter.bans, 'attempt', side_effect=RuntimeError('boom')):
      netfilter.handle_line(self.sasl('1.2.3.4'))
    self.assertTrue(any('boom' in message for message in self.logger.messages('warn')))

  def test_watch_reconnects_after_errors(self):
    netfilter = self.create()
    self.r.messages.extend([self.sasl('1.2.3.4'), ConnectionError('gone'), self.sasl('1.2.3.4')])
    netfilter.loop('watch', netfilter.watch, 0)
    self.assertEqual(self.tables.rules, {'1.2.3.4/32'})
    self.assertEqual(len(self.r.pubsubs), 2)
    self.assertTrue(all(pubsub.closed for pubsub in self.r.pubsubs))
    self.assertTrue(any('gone' in message for message in self.logger.messages('warn')))

  def test_worker_survives_errors(self):
    netfilter = self.create()
    calls = []

    def task():
      calls.append(1)
      if len(calls) == 1:
        raise KeyError('ban_time')
      netfilter.stop.set()

    netfilter.loop('purge', task, 0)
    self.assertEqual(len(calls), 2)

  def test_recreated_chain_gets_isolation_and_bans_again(self):
    netfilter = self.create()
    netfilter.handle_line(self.sasl('1.2.3.4'))
    netfilter.handle_line(self.sasl('1.2.3.4'))
    self.tables.rules = set()
    self.tables.calls = []
    self.tables.recreate = True
    netfilter.check_chains()
    self.assertEqual(self.tables.names(), ['ensure_chains', 'isolation', 'ban'])
    self.assertEqual(self.tables.rules, {'1.2.3.4/32'})
    netfilter.check_chains()
    self.assertEqual(self.tables.names(), ['ensure_chains', 'isolation', 'ban', 'ensure_chains'])

  def test_snat(self):
    netfilter = self.create(SNAT_TO_SOURCE='192.0.2.1', SNAT6_TO_SOURCE='192.0.2.2')
    self.assertIn('snat', netfilter.workers)
    netfilter.snat()
    self.assertEqual(self.tables.calls, [('snat4', '192.0.2.1', '172.22.1.0/24')])
    self.assertTrue(any('192.0.2.2 is not a valid IPv6 address' in message for message in self.logger.messages('warn')))
    self.assertNotIn('snat', self.create(SNAT_TO_SOURCE='n', SNAT6_TO_SOURCE='n').workers)

  def test_lists(self):
    netfilter = self.create()
    self.r.hset('F2B_WHITELIST', '1.2.3.0/24', 1)
    self.r.hset('F2B_BLACKLIST', '198.51.100.1', 1)
    netfilter.update_lists()
    self.assertEqual(self.tables.rules, {'198.51.100.1/32'})
    netfilter.handle_line(self.sasl('1.2.3.4'))
    netfilter.handle_line(self.sasl('1.2.3.4'))
    self.assertNotIn('1.2.3.4/32', self.tables.rules)

  def test_resolve_keeps_addresses_on_dns_errors(self):
    netfilter = self.create()
    answers = {('relay.example.org', 'A'): ['192.0.2.1'], ('relay.example.org', 'AAAA'): ['2001:db8::1']}

    def resolve(qname, rdtype, lifetime):
      if qname == 'gone.example.org':
        raise dns.resolver.NXDOMAIN()
      if (qname, rdtype) not in answers:
        raise dns.resolver.NoAnswer()
      return [mock.Mock(to_text=mock.Mock(return_value=address)) for address in answers[(qname, rdtype)]]

    with mock.patch('dns.resolver.Resolver') as resolver:
      resolver.return_value.resolve.side_effect = resolve
      entries = ['relay.example.org', 'gone.example.org', '1.2.3.0/24']
      self.assertEqual(netfilter.resolve(entries), {'192.0.2.1', '2001:db8::1', '1.2.3.0/24'})
      resolver.return_value.resolve.side_effect = dns.exception.Timeout()
      self.assertEqual(netfilter.resolve(entries), {'192.0.2.1', '2001:db8::1', '1.2.3.0/24'})


if __name__ == '__main__':
  unittest.main()
