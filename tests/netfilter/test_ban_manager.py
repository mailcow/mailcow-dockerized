import ipaddress
import json
import threading
import unittest

from modules.BanManager import BanManager
from modules.Options import Options
from support import Clock, FakeLogger, FakeRedis, FakeTables


class BanManagerTest(unittest.TestCase):
  def setUp(self):
    self.r = FakeRedis()
    self.tables = FakeTables()
    self.logger = FakeLogger()
    self.clock = Clock()
    self.configure()

  def configure(self, **options):
    values = {'ban_time': 60, 'max_ban_time': 600, 'max_attempts': 3, 'retry_window': 100}
    values.update(options)
    self.r.set('F2B_OPTIONS', json.dumps(values))
    self.options = Options(self.logger)
    self.options.refresh(self.r)
    self.bans = BanManager(self.r, self.tables, self.options, self.logger, threading.RLock(), self.clock)

  def attempts(self, address, count):
    for _ in range(count):
      self.bans.attempt(ipaddress.ip_address(address))

  def active(self):
    return self.r.hgetall('F2B_ACTIVE_BANS')

  def test_bans_after_max_attempts(self):
    self.attempts('203.0.113.1', 2)
    self.assertEqual(self.active(), {})
    self.attempts('203.0.113.1', 1)
    self.assertEqual(self.active(), {'203.0.113.1/32': str(int(self.clock.now + 60))})
    self.assertEqual(self.tables.rules, {'203.0.113.1/32'})

  def test_attempts_outside_the_retry_window_start_again(self):
    self.attempts('203.0.113.1', 2)
    self.clock.now += 101
    self.attempts('203.0.113.1', 2)
    self.assertEqual(self.active(), {})

  def test_netban_prefix(self):
    self.configure(netban_ipv4=24, netban_ipv6=64)
    self.attempts('203.0.113.1', 1)
    self.attempts('203.0.113.2', 2)
    self.attempts('2001:db8::1', 3)
    self.assertEqual(set(self.active()), {'203.0.113.0/24', '2001:db8::/64'})

  def test_further_attempts_of_a_banned_network_are_ignored(self):
    self.attempts('203.0.113.1', 3)
    self.attempts('203.0.113.1', 5)
    self.assertEqual(self.tables.names().count('ban'), 1)

  def test_allowlisted_address_is_never_banned(self):
    self.bans.set_allowlist(['203.0.113.0/24', 'nonsense'])
    self.attempts('203.0.113.1', 5)
    self.assertEqual(self.active(), {})
    self.assertTrue(any('allowlisted' in message for message in self.logger.messages('info')))

  def test_manage_external_only_writes_redis(self):
    self.configure(manage_external=1)
    self.attempts('203.0.113.1', 3)
    self.assertIn('203.0.113.1/32', self.active())
    self.assertEqual(self.tables.rules, set())

  def test_failed_rule_is_logged_and_ban_kept(self):
    self.tables.failing.add('203.0.113.1/32')
    self.attempts('203.0.113.1', 3)
    self.assertIn('203.0.113.1/32', self.active())
    self.assertTrue(any('Could not add' in message for message in self.logger.messages('warn')))

  def test_expired_ban_is_lifted_and_next_ban_is_longer(self):
    self.attempts('203.0.113.1', 3)
    self.clock.now += 61
    self.bans.purge()
    self.assertEqual(self.active(), {})
    self.assertEqual(self.tables.rules, set())
    self.attempts('203.0.113.1', 3)
    self.assertEqual(self.active(), {'203.0.113.1/32': str(int(self.clock.now + 120))})

  def test_purge_keeps_unexpired_bans(self):
    self.attempts('203.0.113.1', 3)
    self.clock.now += 30
    self.bans.purge()
    self.assertIn('203.0.113.1/32', self.active())
    self.assertEqual(self.tables.rules, {'203.0.113.1/32'})

  def test_purge_lifts_bans_with_invalid_entries(self):
    self.r.hset('F2B_ACTIVE_BANS', '203.0.113.1/32', 'soon')
    self.r.hset('F2B_ACTIVE_BANS', 'nonsense', str(int(self.clock.now + 600)))
    self.tables.rules.add('203.0.113.1/32')
    self.bans.purge()
    self.assertEqual(self.active(), {})
    self.assertEqual(self.tables.rules, set())

  def test_purge_lifts_bans_whose_redis_entry_is_gone(self):
    self.attempts('203.0.113.1', 3)
    self.r.delete('F2B_ACTIVE_BANS')
    self.bans.purge()
    self.assertEqual(self.tables.rules, set())
    self.assertEqual(self.bans.banned, set())

  def test_unban_queue(self):
    self.attempts('203.0.113.1', 3)
    self.r.hset('F2B_QUEUE_UNBAN', '203.0.113.1/32', 1)
    self.bans.purge()
    self.assertEqual(self.active(), {})
    self.assertEqual(self.r.hgetall('F2B_QUEUE_UNBAN'), {})
    self.assertEqual(self.tables.rules, set())

  def test_unban_queue_with_containing_network(self):
    self.attempts('203.0.113.1', 3)
    self.attempts('203.0.113.2', 3)
    self.attempts('198.51.100.1', 3)
    self.r.hset('F2B_QUEUE_UNBAN', '203.0.113.0/24', 1)
    self.bans.purge()
    self.assertEqual(set(self.active()), {'198.51.100.1/32'})
    self.assertEqual(self.tables.rules, {'198.51.100.1/32'})

  def test_unban_queue_without_ban_removes_left_over_rule(self):
    self.tables.rules.add('203.0.113.1/32')
    self.r.hset('F2B_QUEUE_UNBAN', '203.0.113.1/32', 1)
    self.r.hset('F2B_QUEUE_UNBAN', 'nonsense', 1)
    self.bans.purge()
    self.assertEqual(self.tables.rules, set())
    self.assertEqual(self.r.hgetall('F2B_QUEUE_UNBAN'), {})

  def test_restore_applies_unexpired_bans(self):
    self.r.hset('F2B_ACTIVE_BANS', '203.0.113.1/32', str(int(self.clock.now + 60)))
    self.r.hset('F2B_ACTIVE_BANS', '203.0.113.2/32', str(int(self.clock.now - 1)))
    self.r.hset('F2B_ACTIVE_BANS', 'nonsense', str(int(self.clock.now + 60)))
    self.bans.restore()
    self.assertEqual(set(self.active()), {'203.0.113.1/32'})
    self.assertEqual(self.tables.rules, {'203.0.113.1/32'})
    self.assertEqual(self.bans.banned, {'203.0.113.1/32'})
    # one call for all bans
    self.assertEqual(self.tables.names(), ['ban'])

  def test_restore_with_manage_external(self):
    self.configure(manage_external=1)
    self.r.hset('F2B_ACTIVE_BANS', '203.0.113.1/32', str(int(self.clock.now + 60)))
    self.bans.restore()
    self.assertEqual(self.bans.banned, {'203.0.113.1/32'})
    self.assertEqual(self.tables.rules, set())

  def test_denylist(self):
    self.bans.set_denylist(['198.51.100.0/24', '203.0.113.5', 'nonsense'])
    self.assertEqual(self.tables.rules, {'198.51.100.0/24', '203.0.113.5/32'})
    self.assertEqual(set(self.r.hgetall('F2B_PERM_BANS')), {'198.51.100.0/24', '203.0.113.5'})
    self.bans.set_denylist(['203.0.113.5'])
    self.assertEqual(self.tables.rules, {'203.0.113.5/32'})
    self.assertEqual(set(self.r.hgetall('F2B_PERM_BANS')), {'203.0.113.5'})

  def test_unchanged_denylist_does_nothing(self):
    self.bans.set_denylist(['198.51.100.0/24'])
    self.bans.set_denylist(['198.51.100.0/24'])
    self.assertEqual(self.tables.names(), ['ban'])

  def test_denylist_and_automatic_ban_of_the_same_network(self):
    self.bans.set_denylist(['203.0.113.1/32'])
    self.r.hset('F2B_ACTIVE_BANS', '203.0.113.1/32', str(int(self.clock.now - 1)))
    self.bans.banned.add('203.0.113.1/32')
    self.bans.purge()
    # the denylist still bans it
    self.assertEqual(self.tables.rules, {'203.0.113.1/32'})

    self.attempts('198.51.100.1', 3)
    self.bans.set_denylist(['203.0.113.1/32', '198.51.100.1/32'])
    self.bans.set_denylist([])
    # the automatic ban is still active
    self.assertEqual(self.tables.rules, {'198.51.100.1/32'})

  def test_reapply(self):
    self.attempts('203.0.113.1', 3)
    self.bans.set_denylist(['198.51.100.0/24'])
    self.tables.rules = set()
    self.bans.reapply()
    self.assertEqual(self.tables.rules, {'203.0.113.1/32', '198.51.100.0/24'})

  def test_idle_counters_are_dropped(self):
    self.attempts('203.0.113.1', 1)
    self.clock.now += 101
    self.bans.purge()
    self.assertEqual(self.bans.counters, {})

  def test_excused_address_is_not_counted(self):
    ip = ipaddress.ip_address('203.0.113.1')
    self.bans.excuse(ip)
    self.bans.excuse(ip)
    self.attempts('203.0.113.1', 5)
    self.assertEqual(self.active(), {})
    self.assertEqual(len([message for message in self.logger.messages('warn') if 'Auth backend unavailable' in message]), 1)
    # other addresses are counted
    self.attempts('203.0.113.2', 3)
    self.assertIn('203.0.113.2/32', self.active())

  def test_excuse_ends(self):
    self.bans.excuse(ipaddress.ip_address('203.0.113.1'))
    self.clock.now += BanManager.EXCUSE_TIME + 1
    self.bans.purge()
    self.assertEqual(self.bans.excused, {})
    self.attempts('203.0.113.1', 3)
    self.assertIn('203.0.113.1/32', self.active())


if __name__ == '__main__':
  unittest.main()
