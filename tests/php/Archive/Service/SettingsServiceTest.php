<?php

declare(strict_types=1);

namespace Mailcow\Tests\Archive\Service;

use Mailcow\Archive\Model\Settings;
use Mailcow\Archive\Redis\ArchiveState;
use Mailcow\Archive\Repository\SettingsRepository;
use Mailcow\Archive\Service\SettingsService;
use Mailcow\Archive\Smtp\SmtpException;
use Mailcow\Core\Context;
use Mailcow\Core\UserError;
use Mailcow\Tests\Support\FakeArchiveLookup;
use Mailcow\Tests\Support\FakeContext;
use Mailcow\Tests\Support\FakeRedis;
use Mailcow\Tests\Support\FakeSmtpClient;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SettingsServiceTest extends TestCase
{
    private SettingsRepository&MockObject $repo;
    private Settings $stored;
    private FakeRedis $redis;
    private FakeSmtpClient $smtp;
    private FakeArchiveLookup $lookup;

    protected function setUp(): void
    {
        $this->stored = new Settings();
        $this->stored->address = 'journal@archive.example';
        $this->stored->password = 'old-password';
        $this->stored->signingKey = 'old-key';
        $this->repo = $this->createMock(SettingsRepository::class);
        $this->repo->method('load')->willReturnCallback(fn(): Settings => clone $this->stored);
        $this->redis = new FakeRedis();
        $this->smtp = new FakeSmtpClient();
        $this->lookup = new FakeArchiveLookup();
        $this->lookup->domains = ['firma.de', 'alias-firma.de'];
    }

    private function service(?Context $context = null): SettingsService
    {
        return new SettingsService($this->repo, $this->lookup, new ArchiveState($this->redis), $this->smtp, $context ?? FakeContext::admin());
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

    public function testSaveKeepsMissingValuesAndEmptySecrets(): void
    {
        $this->repo->expects($this->once())->method('save')->with($this->callback(fn(Settings $s): bool => $s->enabled
            && $s->address === 'journal@archive.example' && $s->password === 'old-password' && $s->signingKey === 'old-key'));

        $settings = $this->service()->save(['enabled' => ['0', '1'], 'password' => '', 'signing_secret' => '']);

        $this->assertSame(1, $settings->toArray()['enabled']);
        $this->assertSame('1', $this->redis->strings['ARCHIVE_ENABLED']);
    }

    public function testSaveRemovesThePassword(): void
    {
        $this->assertSame('old-password', $this->service()->save(['password' => '', 'password_remove' => '0'])->password);
        $this->assertSame('', $this->service()->save(['password' => '', 'password_remove' => '1'])->password);
    }

    public function testEnabledIsOffWithoutTheTable(): void
    {
        $repo = $this->createMock(SettingsRepository::class);
        $repo->method('load')->willThrowException(new PDOException('no such table'));

        $this->assertFalse((new SettingsService($repo, $this->lookup, new ArchiveState($this->redis), $this->smtp, FakeContext::admin()))->enabled());
    }

    public function testAddressOutsideTheMailcowDomains(): void
    {
        $this->expectUserError('archive_address_local', fn() => $this->service()->save(['address' => 'journal@firma.de']));
        $this->expectUserError('archive_address_local', fn() => $this->service()->save(['address' => 'Journal@Alias-Firma.de']));
        $this->assertSame('journal@archiv.firma.de', $this->service()->save(['address' => 'journal@archiv.firma.de'])->address);
    }

    public function testSaveAllFields(): void
    {
        $settings = $this->service()->save(['enabled' => '0', 'address' => ' Journal@Archive.Example ', 'host' => '[2001:DB8::25]',
            'port' => '2525', 'tls' => 'verify', 'username' => 'mailcow', 'password' => 'new:password', 'profile' => 'envelope',
            'signing_secret' => 'new-key', 'header_from' => 'X-Real-MailFrom', 'header_rcpt' => 'X-Real-RcptTo']);

        $this->assertSame(['enabled' => 0, 'address' => 'journal@archive.example', 'host' => '2001:db8::25', 'port' => 2525,
            'tls' => 'verify', 'username' => 'mailcow', 'has_password' => 1, 'profile' => 'envelope', 'has_signing_secret' => 1,
            'header_from' => 'X-Real-MailFrom', 'header_rcpt' => 'X-Real-RcptTo'], $settings->toArray());
        $this->assertSame(['new:password', 'new-key'], [$settings->password, $settings->signingKey]);
        $this->assertSame('0', $this->redis->strings['ARCHIVE_ENABLED']);
    }

    public static function invalid(): array
    {
        return [
            'address' => [['address' => 'journal'], 'archive_address_invalid'],
            'address array' => [['address' => ['x']], 'archive_address_invalid'],
            'host' => [['host' => 'archive example'], 'archive_host_invalid'],
            'port' => [['port' => '0'], 'archive_port_invalid'],
            'port too high' => [['port' => '65536'], 'archive_port_invalid'],
            'tls' => [['tls' => 'none'], 'archive_tls_invalid'],
            'username with colon' => [['username' => 'a:b'], 'archive_username_invalid'],
            'password with space' => [['password' => 'a b'], 'archive_password_invalid'],
            'password too long' => [['password' => str_repeat('a', 256)], 'archive_password_invalid'],
            'profile' => [['profile' => 'journal'], 'archive_profile_invalid'],
            'signing secret not ascii' => [['signing_secret' => 'schlüssel'], 'archive_signing_secret_invalid'],
            'header name with space' => [['header_from' => 'X Envelope'], 'archive_header_invalid'],
            'header name with line break' => [['header_rcpt' => "X-To\r\nBcc"], 'archive_header_invalid'],
            'switch on without address' => [['enabled' => '1', 'address' => ''], 'archive_incomplete'],
        ];
    }

    #[DataProvider('invalid')]
    public function testSaveValidates(array $input, string $error): void
    {
        $this->repo->expects($this->never())->method('save');
        $this->expectUserError($error, fn() => $this->service()->save($input));
        $this->assertArrayNotHasKey('ARCHIVE_ENABLED', $this->redis->strings);
    }

    public function testArchivecowNeedsASigningKey(): void
    {
        $this->stored->signingKey = '';
        $this->expectUserError('archive_incomplete', fn() => $this->service()->save(['enabled' => '1']));

        $this->assertTrue($this->service()->save(['enabled' => '1', 'profile' => 'none'])->enabled);
    }

    public function testPermissions(): void
    {
        $readOnly = new Context('admin', 'API', [], false, 'ro');
        $this->repo->expects($this->never())->method('save');

        $this->assertSame(1, $this->service($readOnly)->get()->toArray()['has_password']);
        $this->expectUserError('access_denied', fn() => $this->service($readOnly)->save(['enabled' => '0']));
        $this->expectUserError('access_denied', fn() => $this->service($readOnly)->test());
        $this->expectUserError('access_denied', fn() => $this->service(FakeContext::domainAdmin(acl: ['archive' => '1']))->get());
        $this->expectUserError('access_denied', fn() => $this->service(FakeContext::user())->save([]));
        $this->assertSame([], $this->smtp->probed);
    }

    public function testConnectionTestWithTheStoredTarget(): void
    {
        $this->stored->host = 'smtp.archive.example';
        $this->stored->port = 587;
        $this->stored->tls = 'verify';
        $this->stored->username = 'mailcow';

        $this->assertSame('archive_test_ok', $this->service()->test());
        $this->assertSame([['host' => 'smtp.archive.example', 'port' => 587, 'tls' => 'verify', 'username' => 'mailcow',
            'password' => 'old-password', 'from' => 'archive-bounce@localhost', 'rcpt' => 'journal@archive.example']], $this->smtp->probed);
    }

    public function testConnectionTestReportsMissingExtensions(): void
    {
        $this->smtp->mx['archive.example'] = 'mx1.archive.example';
        $this->smtp->extensions = ['STARTTLS', 'SIZE'];

        $this->assertSame(['archive_test_ok_missing', '8BITMIME'], $this->service()->test());
        $this->assertSame('mx1.archive.example', $this->smtp->probed[0]['host']);
    }

    public function testConnectionTestFailure(): void
    {
        $this->smtp->error = new SmtpException('recipient', "550 5.1.1 <journal@archive.example>:\n unknown");

        $this->expectUserError(['archive_test_failed', 'archive.example:25 recipient: 550 5.1.1 journal@archive.example: unknown'],
            fn() => $this->service()->test());
    }

    public function testConnectionTestNeedsAnAddress(): void
    {
        $this->stored->address = '';
        $this->expectUserError('archive_incomplete', fn() => $this->service()->test());
        $this->assertSame([], $this->smtp->probed);
    }
}
