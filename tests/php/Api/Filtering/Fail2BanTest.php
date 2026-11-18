<?php

declare(strict_types=1);

namespace Mailcow\Tests\Api\Filtering;

use Mailcow\Tests\Api\ApiTestCase;

/** Fail2Ban settings, bans and the banlist URL */
class Fail2BanTest extends ApiTestCase
{
    private const BANLIST_ID = 'a2c11baf-5770-47de-9c5e-575bd7bc9218';

    public function testGetFail2BanWithAnyObject(): void
    {
        $settings = ['ban_time' => 1800, 'ban_time_increment' => true, 'banlist_id' => self::BANLIST_ID, 'whitelist' => '192.0.2.0/24'];

        foreach (['get/fail2ban', 'get/fail2ban/all'] as $route) {
            $result = $this->get($route, ['script' => [self::answer('fail2ban', ['get'], $settings)]]);
            $this->assertSame([['get']], $result->callsTo('fail2ban'), $route);
            $this->assertSame($settings, $result->json(), $route);
        }

        $this->assertSame('{}', $this->get('get/fail2ban', ['script' => [self::answer('fail2ban', ['get'], false)]])->body);
    }

    public function testEditFail2BanSettings(): void
    {
        $attr = ['ban_time' => '1800', 'max_attempts' => '10', 'whitelist' => "192.0.2.0/24\nmail.example.org"];

        $result = $this->post('edit/fail2ban', ['items' => [], 'attr' => $attr]);

        $this->assertSame([['edit', ['network' => []] + $attr]], $result->callsTo('fail2ban'));
    }

    public function testItemsAreTheNetworks(): void
    {
        $list = $this->post('edit/fail2ban', ['items' => ['203.0.113.7/32', '2001:db8::/64'], 'attr' => ['action' => 'unban']]);
        $this->assertCalled($list, 'fail2ban', ['edit', ['network' => ['203.0.113.7/32', '2001:db8::/64'], 'action' => 'unban']]);

        $single = $this->post('edit/fail2ban', ['items' => '198.51.100.66/32', 'attr' => ['action' => 'blacklist']]);
        $this->assertCalled($single, 'fail2ban', ['edit', ['network' => ['198.51.100.66/32'], 'action' => 'blacklist']]);
    }

    public function testFurtherSegmentsAreIgnored(): void
    {
        $result = $this->post('edit/fail2ban/self', ['items' => ['self'], 'attr' => ['action' => 'unban-all']]);

        $this->assertSame([['edit', ['network' => ['self'], 'action' => 'unban-all']]], $result->callsTo('fail2ban'));
    }

    public function testRefreshBanlist(): void
    {
        $messages = [self::message('success', 'f2b_banlist_refreshed')];

        $result = $this->post('edit/fail2ban/banlist', ['items' => [self::BANLIST_ID]], ['script' => [self::answer('fail2ban', ['banlist', 'refresh'], true, $messages)]]);
        $this->assertSame([['banlist', 'refresh', [self::BANLIST_ID]]], $result->callsTo('fail2ban'));
        $this->assertSame($messages, $result->json());

        $single = $this->post('edit/fail2ban/banlist', ['items' => self::BANLIST_ID, 'attr' => []]);
        $this->assertSame([['banlist', 'refresh', [self::BANLIST_ID]]], $single->callsTo('fail2ban'));
    }

    public function testRefreshBanlistWithAWrongId(): void
    {
        $result = $this->post('edit/fail2ban/banlist', ['items' => ['wrong']], ['script' => [self::answer('fail2ban', ['banlist'], false)]]);

        $this->assertSame(['type' => 'error', 'msg' => 'Cannot edit item'], $result->json());
    }
}
