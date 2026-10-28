import json
import unittest

from modules.Options import Options
from support import FakeLogger, FakeRedis


class OptionsTest(unittest.TestCase):
  def setUp(self):
    self.r = FakeRedis()
    self.logger = FakeLogger()
    self.options = Options(self.logger)

  def stored(self):
    return json.loads(self.r.get('F2B_OPTIONS'))

  def test_first_start_stores_defaults(self):
    self.options.refresh(self.r)
    stored = self.stored()
    for key, value in Options.DEFAULTS.items():
      self.assertEqual(stored[key], value)
    self.assertTrue(stored['banlist_id'])

  def test_legacy_keys_are_migrated(self):
    self.r.set('F2B_BAN_TIME', '900')
    self.r.set('F2B_MAX_ATTEMPTS', '3')
    self.options.refresh(self.r)
    self.assertEqual(self.options.values['ban_time'], 900)
    self.assertEqual(self.stored()['ban_time'], 900)
    self.assertEqual(self.stored()['max_attempts'], 3)

  def test_saved_options_are_never_overwritten(self):
    self.r.set('F2B_OPTIONS', json.dumps({'ban_time': 120, 'max_attempts': 'x', 'banlist_id': 'abc'}))
    self.options.refresh(self.r)
    stored = self.stored()
    self.assertEqual(stored['ban_time'], 120)
    # invalid values are only replaced in memory
    self.assertEqual(stored['max_attempts'], 'x')
    self.assertEqual(self.options.values['max_attempts'], 10)
    self.assertEqual(stored['banlist_id'], 'abc')
    # missing keys are added
    self.assertEqual(stored['retry_window'], 600)

  def test_null_values_are_filled(self):
    self.r.set('F2B_OPTIONS', json.dumps({'ban_time': 120, 'banlist_id': None}))
    self.options.refresh(self.r)
    self.assertTrue(self.stored()['banlist_id'])
    self.assertEqual(self.stored()['banlist_id'], self.options.values['banlist_id'])

  def test_save_of_the_ui_between_read_and_write_wins(self):
    self.r.set('F2B_OPTIONS', json.dumps({'ban_time': 120}))
    original = self.r.transaction

    def transaction(func, *watches):
      # the UI saves while netfilter adds the missing keys
      self.r.set('F2B_OPTIONS', json.dumps({'ban_time': 300, 'retry_window': 60}))
      original(func, *watches)

    self.r.transaction = transaction
    self.options.refresh(self.r)
    stored = self.stored()
    self.assertEqual(stored['ban_time'], 300)
    self.assertEqual(stored['retry_window'], 60)
    self.assertEqual(stored['max_attempts'], 10)

  def test_invalid_json_keeps_current_values(self):
    self.r.set('F2B_OPTIONS', json.dumps({'ban_time': 120}))
    self.options.refresh(self.r)
    self.r.set('F2B_OPTIONS', '{broken')
    self.options.refresh(self.r)
    self.assertEqual(self.options.values['ban_time'], 120)
    self.assertTrue(self.logger.messages('warn'))
    self.assertEqual(self.r.get('F2B_OPTIONS'), '{broken')

  def test_values_are_clamped(self):
    values = Options.normalize({'netban_ipv4': 0, 'netban_ipv6': 200, 'ban_time': -5, 'max_attempts': 0,
                                'manage_external': 5, 'ban_time_increment': '0'})
    self.assertEqual(values['netban_ipv4'], 8)
    self.assertEqual(values['netban_ipv6'], 128)
    self.assertEqual(values['ban_time'], 1)
    self.assertEqual(values['max_attempts'], 1)
    self.assertEqual(values['manage_external'], 0)
    self.assertFalse(values['ban_time_increment'])

  def test_ban_time_grows_up_to_max_ban_time(self):
    self.r.set('F2B_OPTIONS', json.dumps({'ban_time': 60, 'max_ban_time': 600, 'ban_time_increment': True}))
    self.options.refresh(self.r)
    self.assertEqual([self.options.ban_time(counter) for counter in range(5)], [60, 120, 240, 480, 600])
    self.assertEqual(self.options.ban_time(10 ** 6), 600)

  def test_ban_time_without_increment(self):
    self.r.set('F2B_OPTIONS', json.dumps({'ban_time': 60, 'max_ban_time': 600, 'ban_time_increment': False}))
    self.options.refresh(self.r)
    self.assertEqual(self.options.ban_time(3), 60)

  def test_ban_time_is_never_below_ban_time(self):
    self.r.set('F2B_OPTIONS', json.dumps({'ban_time': 600, 'max_ban_time': 60}))
    self.options.refresh(self.r)
    self.assertEqual(self.options.ban_time(0), 600)


if __name__ == '__main__':
  unittest.main()
