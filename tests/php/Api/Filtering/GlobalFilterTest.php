<?php

declare(strict_types=1);

namespace Mailcow\Tests\Api\Filtering;

use Mailcow\Tests\Api\ApiTestCase;

/** Global Sieve prefilter and postfilter */
class GlobalFilterTest extends ApiTestCase
{
    private const FILTERS = ['prefilter' => "# before\n", 'postfilter' => "require \"fileinto\";\n"];

    public function testAddGlobalFilter(): void
    {
        $attr = ['filter_type' => 'postfilter', 'script_data' => "require \"fileinto\";\nfileinto \"Junk\";"];
        $messages = [
            self::message('success', 'dovecot_restart_success'),
            self::message('success', 'global_filter_written'),
        ];

        $result = $this->post('add/global-filter', $attr, ['script' => [self::answer('mailbox', ['add', 'global_filter'], true, $messages)]]);

        $this->assertCalled($result, 'mailbox', ['add', 'global_filter', $attr]);
        $this->assertSame($messages, $result->json());
    }

    public function testGetAllGlobalFilters(): void
    {
        $result = $this->get('get/global_filters/all', ['script' => [self::answer('mailbox', ['get', 'global_filter_details'], self::FILTERS)]]);

        $this->assertSame([['get', 'global_filter_details']], $result->callsTo('mailbox'));
        $this->assertSame(self::FILTERS, $result->json());

        $this->assertSame('{}', $this->get('get/global_filters/all', ['script' => [self::answer('mailbox', ['get', 'global_filter_details'], false)]])->body);
    }

    public function testOneGlobalFilterIsAJsonString(): void
    {
        $script = [self::answer('mailbox', ['get', 'global_filter_details'], self::FILTERS)];

        $this->assertSame("# before\n", $this->get('get/global_filters/prefilter', ['script' => $script])->json());
        $this->assertSame("require \"fileinto\";\n", $this->get('get/global_filters/postfilter', ['script' => $script])->json());

        $empty = [self::answer('mailbox', ['get', 'global_filter_details'], ['prefilter' => '', 'postfilter' => ''])];
        $this->assertSame('{}', $this->get('get/global_filters/prefilter', ['script' => $empty])->body);
        $this->assertSame('{}', $this->get('get/global_filters/postfilter', ['script' => $empty])->body);
    }

    public function testOtherObjectsAnswerNothing(): void
    {
        $result = $this->get('get/global_filters/before', ['script' => [self::answer('mailbox', ['get', 'global_filter_details'], self::FILTERS)]]);

        $this->assertSame([200, ''], [$result->status, $result->body]);
    }
}
