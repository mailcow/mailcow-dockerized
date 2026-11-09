<?php

declare(strict_types=1);

namespace Mailcow\Tests\Archive\Service;

use Mailcow\Archive\Model\Settings;
use Mailcow\Archive\Redis\ArchiveState;
use Mailcow\Archive\Repository\SettingsRepository;
use Mailcow\Archive\Service\HeaderBuilder;
use Mailcow\Archive\Service\JournalService;
use Mailcow\Archive\Service\OwnerResolver;
use Mailcow\Archive\Smtp\SmtpException;
use Mailcow\Core\Context;
use Mailcow\Core\UserError;
use Mailcow\Tests\Support\FakeArchiveLookup;
use Mailcow\Tests\Support\FakeContext;
use Mailcow\Tests\Support\FakeRedis;
use Mailcow\Tests\Support\FakeSmtpClient;
use PHPUnit\Framework\TestCase;

class JournalServiceTest extends TestCase
{
    private Settings $stored;
    private FakeArchiveLookup $lookup;
    private FakeRedis $redis;
    private FakeSmtpClient $smtp;

    protected function setUp(): void
    {
        $this->stored = new Settings();
        $this->stored->address = 'journal@archive.example';
        $this->stored->signingKey = 'key';
        $this->lookup = new FakeArchiveLookup();
        $this->lookup->mailboxes = ['anna@firma.de' => ['inherit', 1], 'olga@other.de' => ['inherit', 0]];
        foreach (array_keys($this->lookup->mailboxes) as $username) {
            $this->lookup->aliases[$username] = $username;
        }
        $this->redis = new FakeRedis();
        $this->redis->strings['ARCHIVE_ENABLED'] = '1';
        $this->smtp = new FakeSmtpClient();
    }

    private function service(?Context $context = null): JournalService
    {
        $repo = $this->createStub(SettingsRepository::class);
        $repo->method('load')->willReturnCallback(fn(): Settings => clone $this->stored);
        return new JournalService($repo, new OwnerResolver($this->lookup), new HeaderBuilder(), $this->smtp,
            new ArchiveState($this->redis), $context ?? Context::system());
    }

    private function expectUserError(string|array $msg, callable $call): void
    {
        try {
            $call();
            $this->fail('UserError ' . json_encode($msg) . ' expected');
        } catch (UserError $e) {
            $this->assertSame($msg, $e->msg());
        }
    }

    /** Header block and mail of a submitted copy, split after X-Archive-Signature */
    private static function split(string $data): array
    {
        $end = strpos($data, "\r\n", (int)strpos($data, 'X-Archive-Signature: ')) + 2;
        return [substr($data, 0, $end), substr($data, $end)];
    }

    public function testPrepareForAnArchivedRecipient(): void
    {
        $sha256 = hash('sha256', 'mail');
        $result = $this->service()->prepare(['from' => 'x@extern.example', 'rcpt' => ['anna@firma.de', 'olga@other.de'], 'user' => '',
            'bcc' => true, 'qid' => '4XyZ1', 'received' => 1700000000.25, 'sha256' => $sha256]);

        $this->assertSame(['archive' => true, 'rcpt' => 'journal@archive.example', 'sender' => 'archive-bounce@localhost'],
            array_slice($result, 0, 3));
        $this->assertStringStartsWith("X-Archive-Owner: anna@firma.de\r\nX-Archive-Rcpt: anna@firma.de,olga@other.de\r\n"
            . "X-Archive-From: x@extern.example\r\nX-Archive-Queue-ID: 4XyZ1\r\nX-Archive-Received: 2023-11-14T22:13:20.250Z\r\n"
            . "X-Archive-Direction: in\r\nX-Archive-Source: smtp\r\nX-Archive-SHA256: $sha256\r\nX-Archive-Signature: ", $result['headers']);
        $this->assertSame('1', $this->redis->strings['ARCHIVE_ENABLED']);
    }

    public function testPrepareWithoutCopy(): void
    {
        $this->assertSame(['archive' => false], $this->service()->prepare(['from' => 'x@extern.example', 'rcpt' => ['olga@other.de']]));

        $this->stored->signingKey = '';
        $this->assertSame(['archive' => false], $this->service()->prepare(['from' => 'x@extern.example', 'rcpt' => ['anna@firma.de']]));

        $this->redis->strings['ARCHIVE_ENABLED'] = '0';
        $this->assertSame(['archive' => false], $this->service()->prepare(['from' => 'x@extern.example', 'rcpt' => ['anna@firma.de']]));
        $this->assertSame([], $this->smtp->submitted);
    }

    public function testPrepareCleansTheRequest(): void
    {
        $result = $this->service()->prepare(['from' => "x@extern.example\r\nBcc: y@extern.example", 'rcpt' => ['anna@firma.de', ['x'], 5],
            'qid' => str_repeat('Q', 100), 'received' => 'yesterday', 'sha256' => 'ABC']);

        $this->assertTrue($result['archive']);
        $this->assertStringContainsString("\r\nX-Archive-From: x@extern.example Bcc: y@extern.example\r\n", $result['headers']);
        $this->assertStringContainsString("\r\nX-Archive-Rcpt: anna@firma.de\r\n", $result['headers']);
        $this->assertStringContainsString("\r\nX-Archive-Queue-ID: " . str_repeat('Q', 64) . "\r\n", $result['headers']);
        $this->assertMatchesRegularExpression('/\r\nX-Archive-Received: \d{4}-\d\d-\d\dT[\d:.]+Z\r\n/', $result['headers']);
        $this->assertStringNotContainsString('X-Archive-SHA256', $result['headers']);
    }

    public function testCopyOfADeliveredMail(): void
    {
        $this->assertTrue($this->service()->submitDelivered("Subject: hi\n\n.body\n", 'x@extern.example', ['anna@firma.de'], 'quarantine'));

        $sent = $this->smtp->submitted[0];
        $this->assertSame(['postfix', 591, 'archive-bounce@localhost', 'journal@archive.example'],
            [$sent['host'], $sent['port'], $sent['from'], $sent['rcpt']]);
        [$headers, $message] = self::split($sent['data']);
        $this->assertSame("Subject: hi\r\n\r\n.body\r\n", $message);
        $this->assertStringStartsWith("X-Archive-Owner: anna@firma.de\r\nX-Archive-Rcpt: anna@firma.de\r\n", $headers);
        $this->assertStringContainsString("\r\nX-Archive-Source: quarantine\r\nX-Archive-SHA256: " . hash('sha256', $message) . "\r\n", $headers);
    }

    public function testCopyOfADeliveredMailNeverThrows(): void
    {
        $this->assertFalse($this->service()->submitDelivered("x\r\n", 'x@extern.example', ['olga@other.de'], 'massmail'));
        $this->assertSame([], $this->smtp->submitted);

        $this->smtp->error = new SmtpException('connect', 'Connection refused');
        $errorLog = ini_set('error_log', '/dev/null');
        try {
            $this->assertFalse($this->service()->submitDelivered("Message-ID: <mm1@firma.de>\r\nSubject: x\r\n\r\nx\r\n",
                'x@extern.example', ['anna@firma.de'], 'massmail'));
        } finally {
            ini_set('error_log', (string)$errorLog);
        }
        $this->assertSame('1', $this->redis->strings['ARCHIVE_DROPPED']);
        // the Message-ID finds the mail in the archive log
        $this->assertSame(['qid' => '', 'reason' => 'massmail: connect: Connection refused', 'message_id' => 'mm1@firma.de'],
            array_slice(json_decode($this->redis->lists['ARCHIVE_DROPPED_LOG'][0], true), 1));
    }

    public function testTestMail(): void
    {
        $this->redis->strings['ARCHIVE_ENABLED'] = '0';

        $this->assertSame('Q1', $this->service(FakeContext::admin())->testMail());

        [$headers, $message] = self::split($this->smtp->submitted[0]['data']);
        $this->assertStringStartsWith("X-Archive-Rcpt: journal@archive.example\r\nX-Archive-From: archive-bounce@localhost\r\n", $headers);
        $this->assertStringContainsString("\r\nX-Archive-Source: test\r\n", $headers);
        $this->assertStringContainsString("\r\nSubject: mailcow archive test\r\n", $message);
    }

    public function testTestMailErrors(): void
    {
        $this->expectUserError('access_denied', fn() => $this->service(new Context('admin', 'API', [], false, 'ro'))->testMail());
        $this->expectUserError('access_denied', fn() => $this->service(FakeContext::domainAdmin(acl: ['archive' => '1']))->testMail());

        $this->smtp->error = new SmtpException('recipient', '550 5.7.1 <journal@archive.example>: relay denied');
        $this->expectUserError(['archive_testmail_failed', 'recipient: 550 5.7.1 journal@archive.example: relay denied'],
            fn() => $this->service(FakeContext::admin())->testMail());

        $this->stored->address = '';
        $this->expectUserError('archive_incomplete', fn() => $this->service(FakeContext::admin())->testMail());
        $this->assertSame([], $this->smtp->submitted);
    }
}
