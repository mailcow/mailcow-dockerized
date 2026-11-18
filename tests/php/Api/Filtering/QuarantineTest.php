<?php

declare(strict_types=1);

namespace Mailcow\Tests\Api\Filtering;

use Mailcow\Tests\Api\ApiTestCase;

/** Quarantine items and settings */
class QuarantineTest extends ApiTestCase
{
    public function testGetQuarantine(): void
    {
        $items = [['id' => 33, 'qid' => '8224615004C1', 'subject' => 'You have won', 'virus_flag' => 0, 'score' => 15.48, 'rcpt' => 'john.doe@example.org']];

        $result = $this->get('get/quarantine/all', ['script' => [self::answer('quarantine', ['get'], $items)]]);
        $this->assertSame([['get']], $result->callsTo('quarantine'));
        $this->assertSame($items, $result->json());

        $this->assertSame('[]', $this->get('get/quarantine/all')->body);
    }

    public function testGetQuarantineItem(): void
    {
        $item = ['id' => 33, 'qid' => '8224615004C1', 'msg' => "Subject: You have won\r\n\r\nClick here\r\n", 'qhash' => str_repeat('a', 64)];

        $result = $this->get('get/quarantine/33', ['script' => [self::answer('quarantine', ['details', '33'], $item)]]);
        $this->assertSame([['details', '33']], $result->callsTo('quarantine'));
        $this->assertSame($item, $result->json());

        $this->assertSame('[]', $this->get('get/quarantine/34', ['script' => [self::answer('quarantine', ['details'], false)]])->body);
    }

    public function testEditQuarantineItems(): void
    {
        $release = $this->post('edit/qitem', ['items' => ['33', '34'], 'attr' => ['action' => 'release']]);
        $this->assertCalled($release, 'quarantine', ['edit', ['id' => ['33', '34'], 'action' => 'release']]);

        $spam = $this->post('edit/qitem', ['items' => 35, 'attr' => ['action' => 'learnspam']]);
        $this->assertCalled($spam, 'quarantine', ['edit', ['id' => [35], 'action' => 'learnspam']]);
    }

    public function testDeleteQuarantineItems(): void
    {
        $this->assertCalled($this->post('delete/qitem', ['33', '34']), 'quarantine', ['delete', ['id' => ['33', '34']]]);
        $this->assertCalled($this->post('delete/qitem', '"33"'), 'quarantine', ['delete', ['id' => ['33']]]);
    }

    public function testEditQuarantineSettingsIgnoresItems(): void
    {
        $attr = ['action' => 'settings', 'retention_size' => '20', 'max_size' => '15', 'exclude_domains' => ['example.com']];
        $messages = [self::message('success', 'saved_settings')];

        $result = $this->post('edit/quarantine', ['items' => ['self'], 'attr' => $attr], ['script' => [self::answer('quarantine', ['edit'], true, $messages)]]);

        $this->assertSame([['edit', $attr]], $result->callsTo('quarantine'));
        $this->assertSame($messages, $result->json());
    }
}
