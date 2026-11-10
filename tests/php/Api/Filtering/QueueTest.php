<?php

declare(strict_types=1);

namespace Mailcow\Tests\Api\Filtering;

use Mailcow\Tests\Api\ApiTestCase;

/** Postfix mail queue */
class QueueTest extends ApiTestCase
{
    public function testGetMailQueuePassesTheJsonOfTheBackendThrough(): void
    {
        $queue = '[{"queue_name":"deferred","queue_id":"B98C6260CA1","sender":"sender@example.org","recipients":["rcpt@example.com"]}]';

        $result = $this->get('get/mailq/all', ['script' => [self::answer('mailq', ['get'], $queue)]]);

        $this->assertSame([['get']], $result->callsTo('mailq'));
        $this->assertSame($queue, $result->body);
    }

    public function testEmptyMailQueue(): void
    {
        $this->assertSame('[]', $this->get('get/mailq/all', ['script' => [self::answer('mailq', ['get'], '[]')]])->body);
        $this->assertSame('[]', $this->get('get/mailq/all', ['script' => [self::answer('mailq', ['get'], false)]])->body);
    }

    public function testPostcatAnswersThePlainText(): void
    {
        $text = "*** ENVELOPE RECORDS deferred/B/B98C6260CA1 ***\nsender: sender@example.org\n*** MESSAGE CONTENTS deferred/B/B98C6260CA1 ***\nSubject: Test\n";

        $result = $this->get('get/postcat/B98C6260CA1', ['script' => [self::answer('mailq', ['cat'], $text)]]);

        $this->assertSame([['cat', ['qid' => 'B98C6260CA1']]], $result->callsTo('mailq'));
        $this->assertSame($text, $result->body);
        $this->assertNull($result->json());
    }

    public function testPostcatWithoutAccessAnswersNothing(): void
    {
        $result = $this->get('get/postcat/B98C6260CA1', ['script' => [self::answer('mailq', ['cat'], false)]]);

        $this->assertSame([200, ''], [$result->status, $result->body]);
    }

    public function testEditMailQueue(): void
    {
        $hold = $this->post('edit/mailq', ['items' => ['B98C6260CA1', '4C1E8260D02'], 'attr' => ['action' => 'hold']]);
        $this->assertCalled($hold, 'mailq', ['edit', ['qid' => ['B98C6260CA1', '4C1E8260D02'], 'action' => 'hold']]);

        $deliver = $this->post('edit/mailq', ['items' => 'B98C6260CA1', 'attr' => ['action' => 'deliver']]);
        $this->assertCalled($deliver, 'mailq', ['edit', ['qid' => ['B98C6260CA1'], 'action' => 'deliver']]);

        $flush = $this->post('edit/mailq', ['items' => [], 'attr' => ['action' => 'flush']]);
        $this->assertCalled($flush, 'mailq', ['edit', ['qid' => [], 'action' => 'flush']]);
    }

    public function testAnActionOutsideOfAttrIsNotSeen(): void
    {
        $result = $this->post('edit/mailq', ['action' => 'flush']);

        $this->assertCalled($result, 'mailq', ['edit', ['qid' => []]]);
        $this->assertSame(['type' => 'success', 'msg' => 'Task completed'], $result->json());
    }

    public function testDeleteQueuedMessages(): void
    {
        $this->assertCalled($this->post('delete/mailq', ['B98C6260CA1', '4C1E8260D02']), 'mailq', ['delete', ['qid' => ['B98C6260CA1', '4C1E8260D02']]]);
        $this->assertCalled($this->post('delete/mailq', '"B98C6260CA1"'), 'mailq', ['delete', ['qid' => ['B98C6260CA1']]]);
    }

    public function testDeleteTakesNoAction(): void
    {
        $result = $this->post('delete/mailq', ['action' => 'super_delete']);

        $this->assertSame([['delete', ['qid' => ['action' => 'super_delete']]]], $result->callsTo('mailq'));
    }
}
