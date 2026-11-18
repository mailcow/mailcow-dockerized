<?php

declare(strict_types=1);

namespace Mailcow\Tests\Api\Filtering;

use Mailcow\Tests\Api\ApiTestCase;

/** Rspamd settings rules, global regex maps, action statistics and presets */
class RspamdTest extends ApiTestCase
{
    private const RULE = ['desc' => 'Disable Rspamd for example.org', 'content' => 'priority = 10;', 'active' => '1'];

    public function testAddRsetting(): void
    {
        $messages = [self::message('success', 'settings_map_added', ['rsettings', 'add', self::RULE])];

        $result = $this->post('add/rsetting', self::RULE, ['script' => [self::answer('rsettings', ['add'], null, $messages)]]);

        $this->assertCalled($result, 'rsettings', ['add', self::RULE]);
        $this->assertSame($messages, $result->json());
    }

    public function testGetAllRsettingsReadsTheDetailsOfEveryRule(): void
    {
        $first = ['id' => 1, 'desc' => 'first', 'content' => 'priority = 10;', 'active' => 1];
        $third = ['id' => 3, 'desc' => 'third', 'content' => 'priority = 5;', 'active' => 0];

        $result = $this->get('get/rsetting/all', ['script' => [
            self::answer('rsettings', ['get'], [['id' => 1, 'desc' => 'first', 'active' => 1], ['id' => 2, 'desc' => 'gone', 'active' => 1], ['id' => 3, 'desc' => 'third', 'active' => 0]]),
            self::answer('rsettings', ['details', 1], $first),
            self::answer('rsettings', ['details', 2], false),
            self::answer('rsettings', ['details', 3], $third),
        ]]);

        $this->assertSame([['get'], ['details', 1], ['details', 2], ['details', 3]], $result->callsTo('rsettings'));
        $this->assertSame([$first, $third], $result->json());
    }

    public function testGetAllRsettingsWithoutRules(): void
    {
        $result = $this->get('get/rsetting/all', ['script' => [self::answer('rsettings', ['get'], false)]]);

        $this->assertSame('{}', $result->body);
        $this->assertSame([['get']], $result->callsTo('rsettings'));
    }

    public function testGetOneRsetting(): void
    {
        $rule = ['id' => 7, 'desc' => 'seven', 'content' => 'priority = 10;', 'active' => 1];

        $found = $this->get('get/rsetting/7', ['script' => [self::answer('rsettings', ['details', '7'], $rule)]]);
        $this->assertSame([['details', '7']], $found->callsTo('rsettings'));
        $this->assertSame($rule, $found->json());

        $this->assertSame('{}', $this->get('get/rsetting/8', ['script' => [self::answer('rsettings', ['details'], false)]])->body);
    }

    public function testEditRsetting(): void
    {
        $result = $this->post('edit/rsetting', ['items' => [1, 2], 'attr' => ['desc' => 'renamed', 'active' => '0']]);
        $this->assertCalled($result, 'rsettings', ['edit', ['id' => [1, 2], 'desc' => 'renamed', 'active' => '0']]);

        $single = $this->post('edit/rsetting', ['items' => '3', 'attr' => ['content' => 'priority = 1;']]);
        $this->assertCalled($single, 'rsettings', ['edit', ['id' => ['3'], 'content' => 'priority = 1;']]);
    }

    public function testDeleteRsetting(): void
    {
        $this->assertCalled($this->post('delete/rsetting', [1, 2]), 'rsettings', ['delete', ['id' => [1, 2]]]);
        $this->assertCalled($this->post('delete/rsetting', 3), 'rsettings', ['delete', ['id' => [3]]]);
    }

    public function testEditRspamdMap(): void
    {
        $data = "/^spammer@example\\.com$/i\n/@spam\\.example\\.net$/i";

        $result = $this->post('edit/rspamd-map', ['items' => ['global_smtp_from_blacklist.map', 'global_rcpt_blacklist.map'], 'attr' => ['rspamd_map_data' => $data]]);

        $this->assertCalled($result, 'rspamd_maps', ['edit', ['map' => ['global_smtp_from_blacklist.map', 'global_rcpt_blacklist.map'], 'rspamd_map_data' => $data]]);
    }

    public function testRspamdActions(): void
    {
        $actions = [['reject', 38], ['soft reject', 1], ['add header', 6], ['no action', 255]];

        $result = $this->get('get/rspamd/actions', ['script' => [self::answer('rspamd_actions', [], $actions)]]);
        $this->assertSame([[]], $result->callsTo('rspamd_actions'));
        $this->assertSame($actions, $result->json());

        $this->assertSame('{}', $this->get('get/rspamd/actions', ['script' => [self::answer('rspamd_actions', [], false)]])->body);
    }

    public function testOtherRspamdObjectsAnswerNothing(): void
    {
        $result = $this->get('get/rspamd/stat');

        $this->assertSame([200, ''], [$result->status, $result->body]);
        $this->assertNotCalled($result, 'rspamd_actions');
    }

    public function testPresets(): void
    {
        $preset = [['name' => 'preset_1', 'headline' => 'Disable all but DKIM', 'content' => 'priority = 10;']];

        $rspamd = $this->get('get/presets/rspamd', ['script' => [self::answer('presets', ['get', 'rspamd'], $preset)]]);
        $this->assertSame([['get', 'rspamd']], $rspamd->callsTo('presets'));
        $this->assertSame($preset, $rspamd->json());

        $sieve = $this->get('get/presets/sieve', ['session' => self::DOMAIN_ADMIN]);
        $this->assertSame([['get', 'sieve']], $sieve->callsTo('presets'));
        $this->assertSame('{}', $sieve->body);

        $other = $this->get('get/presets/other');
        $this->assertSame([200, ''], [$other->status, $other->body]);
        $this->assertNotCalled($other, 'presets');
    }
}
