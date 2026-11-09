<?php

declare(strict_types=1);

namespace Mailcow\Tests\Archive\Model;

use Mailcow\Archive\Model\Mailbox;
use Mailcow\Archive\Service\MailboxListService;
use Mailcow\Archive\Repository\SettingsRepository;
use Mailcow\Archive\Model\Settings;
use Mailcow\Core\Context;
use Mailcow\Core\UserError;
use Mailcow\Tests\Support\FakeArchiveLookup;
use Mailcow\Tests\Support\FakeContext;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MailboxTest extends TestCase
{
    public static function settings(): array
    {
        return [
            'inherit, domain on' => ['inherit', 1, true],
            'inherit, domain off' => ['inherit', 0, false],
            'missing attribute' => [null, 1, true],
            'unknown value' => ['yes', 0, false],
            'on' => ['1', 0, true],
            'on as number' => [1, 0, true],
            'off' => ['0', 1, false],
            'off as number' => [0, 1, false],
            'on as boolean' => [true, 0, true],
            'off as boolean' => [false, 1, false],
        ];
    }

    #[DataProvider('settings')]
    public function testEffective(mixed $archive, int $domainArchive, bool $archived): void
    {
        $this->assertSame($archived, Mailbox::effective($archive, $domainArchive));
    }

    public function testListOfArchivedMailboxes(): void
    {
        $lookup = new FakeArchiveLookup();
        $lookup->mailboxes = ['b@firma.de' => ['inherit', 1], 'a@other.de' => ['1', 0], 'c@firma.de' => ['0', 1]];
        $settings = new Settings();
        $repo = $this->createMock(SettingsRepository::class);
        $repo->method('load')->willReturnCallback(fn(): Settings => $settings);
        $readOnly = new Context('admin', 'API', [], false, 'ro');

        $this->assertSame(['enabled' => 0, 'mailboxes' => []], (new MailboxListService($lookup, $repo, $readOnly))->archived());

        $settings->enabled = true;
        $this->assertSame(['enabled' => 1, 'mailboxes' => [
            ['username' => 'b@firma.de', 'domain' => 'firma.de', 'name' => '', 'active' => 1, 'source' => 'domain'],
            ['username' => 'a@other.de', 'domain' => 'other.de', 'name' => '', 'active' => 1, 'source' => 'mailbox'],
        ]], (new MailboxListService($lookup, $repo, $readOnly))->archived());

        $this->expectException(UserError::class);
        (new MailboxListService($lookup, $repo, FakeContext::domainAdmin()))->archived();
    }
}
