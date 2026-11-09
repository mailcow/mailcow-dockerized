<?php

declare(strict_types=1);

namespace Mailcow\Tests\Archive\Service;

use Mailcow\Archive\Redis\ArchiveState;
use Mailcow\Archive\Service\MailboxListService;
use Mailcow\Core\AccessDenied;
use Mailcow\Core\Context;
use Mailcow\Tests\Support\FakeArchiveLookup;
use Mailcow\Tests\Support\FakeContext;
use Mailcow\Tests\Support\FakeRedis;
use PHPUnit\Framework\TestCase;

class MailboxListServiceTest extends TestCase
{
    private FakeArchiveLookup $lookup;
    private FakeRedis $redis;

    protected function setUp(): void
    {
        $this->redis = new FakeRedis();
        $this->lookup = new FakeArchiveLookup();
        // username => [mailbox setting, domain default]
        $this->lookup->mailboxes = [
            'own@firma.de' => ['1', 0],
            'inherit@firma.de' => ['inherit', 1],
            'excluded@firma.de' => ['0', 1],
            'plain@other.example' => ['inherit', 0],
        ];
    }

    private function service(bool $enabled, ?Context $context = null): MailboxListService
    {
        $this->redis->strings['ARCHIVE_ENABLED'] = $enabled ? '1' : '0';
        return new MailboxListService($this->lookup, new ArchiveState($this->redis), $context ?? FakeContext::admin());
    }

    public function testArchivedMailboxesWithTheirSource(): void
    {
        $expected = ['enabled' => 1, 'mailboxes' => [
            ['username' => 'own@firma.de', 'domain' => 'firma.de', 'name' => '', 'active' => 1, 'source' => 'mailbox'],
            ['username' => 'inherit@firma.de', 'domain' => 'firma.de', 'name' => '', 'active' => 1, 'source' => 'domain'],
        ]];

        $this->assertSame($expected, $this->service(true)->archived());
        // read-only API keys too: archivecow keeps its mailboxes in line with this list
        $this->assertSame($expected, $this->service(true, new Context('admin', 'API', [], false, 'ro'))->archived());
    }

    public function testEmptyWhileArchivingIsOff(): void
    {
        $this->assertSame(['enabled' => 0, 'mailboxes' => []], $this->service(false)->archived());
    }

    public function testOnlyAdmins(): void
    {
        foreach ([FakeContext::domainAdmin(['own@firma.de'], ['firma.de'], ['archive' => '1']), FakeContext::user('own@firma.de')] as $context) {
            try {
                $this->service(true, $context)->archived();
                $this->fail('AccessDenied expected');
            } catch (AccessDenied) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
