<?php

declare(strict_types=1);

namespace Mailcow\Tests\Api\Mailboxes;

use Mailcow\Tests\Api\ApiTestCase;

/** Rate limits of mailboxes and the reset of rate limit counters */
class RatelimitRoutesTest extends ApiTestCase
{
    public function testRatelimitsOfAllMailboxesAddTheMailbox(): void
    {
        $result = $this->get('get/rl-mbox/all', ['script' => [
            self::answer('mailbox', ['get', 'domains'], ['example.org']),
            self::answer('mailbox', ['get', 'mailboxes', 'example.org'], ['john@example.org', 'jane@example.org']),
            self::answer('ratelimit', ['get', 'mailbox', 'john@example.org'], ['value' => '100', 'frame' => 'h']),
            self::answer('ratelimit', ['get', 'mailbox', 'jane@example.org'], false),
        ]]);

        $this->assertSame([['value' => '100', 'frame' => 'h', 'mailbox' => 'john@example.org']], $result->json());
        $this->assertSame('{}', $this->get('get/rl-mbox/all')->body);
    }

    public function testRatelimitOfAMailbox(): void
    {
        $result = $this->get('get/rl-mbox/john@example.org', ['script' => [self::answer('ratelimit', [], ['value' => '100', 'frame' => 'h'])]]);

        $this->assertSame([['get', 'mailbox', 'john@example.org']], $result->callsTo('ratelimit'));
        $this->assertSame(['value' => '100', 'frame' => 'h'], $result->json());
        $this->assertSame('{}', $this->get('get/rl-mbox/jane@example.org')->body);
    }

    public function testEditRatelimit(): void
    {
        $result = $this->post('edit/rl-mbox', ['items' => ['john@example.org'], 'attr' => ['rl_value' => 100, 'rl_frame' => 'h']]);

        $this->assertCalled($result, 'ratelimit', ['edit', 'mailbox', ['object' => ['john@example.org'], 'rl_value' => 100, 'rl_frame' => 'h']]);
    }

    public function testDeleteRatelimitHashAnswersTheRawResult(): void
    {
        $deleted = $this->post('delete/rlhash', ['RLabc123'], ['script' => [self::answer('ratelimit', [], true, [self::message('success', 'hash_deleted')])]]);
        $this->assertSame([['delete', null, 'RLabc123']], $deleted->callsTo('ratelimit'));
        $this->assertSame('1', $deleted->body);

        $missing = $this->post('delete/rlhash', ['RLabc123'], ['script' => [self::answer('ratelimit', [], false, [self::message('warning', 'hash_not_found')])]]);
        $this->assertSame('', $missing->body);

        $this->assertSame([['delete', null, 'RLabcRLdef']], $this->post('delete/rlhash', ['RLabc', 'RLdef'])->callsTo('ratelimit'));
    }
}
