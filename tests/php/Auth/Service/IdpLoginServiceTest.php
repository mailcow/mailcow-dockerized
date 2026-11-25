<?php

declare(strict_types=1);

namespace Mailcow\Tests\Auth\Service;

use Exception;
use LdapRecord\Auth\BindException;
use LdapRecord\Auth\Guard;
use LdapRecord\Connection;
use LdapRecord\ConnectionException;
use LdapRecord\DetailedError;
use Mailcow\Auth\IdpUnavailable;
use Mailcow\Auth\Service\IdpLoginService;
use Mailcow\Identity\Model\Settings;
use Mailcow\Identity\Service\LinkService;
use Mailcow\MailboxGroup\Service\TemplateService;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class IdpLoginServiceTest extends TestCase
{
    public function testLinkErrorsDenyTheLogin(): void
    {
        $links = $this->createMock(LinkService::class);
        $links->method('verify')->willThrowException(new PDOException('gone'));
        $links->method('mayCreate')->willThrowException(new PDOException('gone'));
        $links->method('link')->willThrowException(new PDOException('gone'));
        $login = new IdpLoginService(Settings::fromArray([]), $links, $this->createMock(TemplateService::class));

        $this->assertFalse($login->verifyLink('keycloak', 'a@example.org', 'u1'));
        $this->assertFalse($login->mayCreate('keycloak', 'a@example.org', 'u1'));
        $login->storeLink('keycloak', 'a@example.org', 'u1');
    }

    public function testLinkChecksArePassedOn(): void
    {
        $links = $this->createMock(LinkService::class);
        $links->method('verify')->with('ldap', 'a@example.org', 'guid-1')->willReturn(true);
        $links->method('mayCreate')->with('ldap', 'b@example.org', 'guid-2')->willReturn(false);
        $links->expects($this->once())->method('link')->with('ldap', 'c@example.org', 'guid-3');
        $login = new IdpLoginService(Settings::fromArray([]), $links, $this->createMock(TemplateService::class));

        $this->assertTrue($login->verifyLink('ldap', 'a@example.org', 'guid-1'));
        $this->assertFalse($login->mayCreate('ldap', 'b@example.org', 'guid-2'));
        $login->storeLink('ldap', 'c@example.org', 'guid-3');
    }

    public function testGroupTemplateWinsAndErrorsFallBackToTheMapping(): void
    {
        $templates = $this->createMock(TemplateService::class);
        $templates->method('templateForLogin')->willReturnCallback(function (string $username, ?string $mapped): ?string {
            if ($username === 'broken@example.org') {
                throw new PDOException('gone');
            }
            return 'Group';
        });
        $login = new IdpLoginService(Settings::fromArray([]), $this->createMock(LinkService::class), $templates);

        $this->assertSame('Group', $login->templateFor('a@example.org', 'Staff'));
        $this->assertSame('Staff', $login->templateFor('broken@example.org', 'Staff'));
    }

    public function testMappingTakesSingleValuesAndLists(): void
    {
        $settings = Settings::fromArray(['mappers' => ['default', 'sales'], 'templates' => ['Staff', 'Sales']]);
        $login = new IdpLoginService($settings, $this->createMock(LinkService::class), $this->createMock(TemplateService::class));

        $this->assertSame('Staff', $login->mappedTemplate('default'));
        $this->assertSame('Sales', $login->mappedTemplate(['other', 'sales']));
        $this->assertNull($login->mappedTemplate(null));
        $this->assertNull($login->mappedTemplate(['unknown']));
    }

    public function testLdapPasswordIsCheckedWithABindAsTheUser(): void
    {
        $guard = $this->createMock(Guard::class);
        $guard->expects($this->once())->method('bind')->with('uid=a,dc=example,dc=org', 'secret');
        $guard->expects($this->once())->method('bindAsConfiguredUser');

        $this->assertTrue($this->login()->verifyLdapPassword($this->ldap($guard), 'uid=a,dc=example,dc=org', 'secret'));
    }

    public function testWrongLdapPassword(): void
    {
        $guard = $this->createMock(Guard::class);
        $guard->method('bind')->willThrowException($this->bindError(49, 'Invalid credentials'));
        $guard->expects($this->once())->method('bindAsConfiguredUser');

        $this->assertFalse($this->login()->verifyLdapPassword($this->ldap($guard), 'uid=a,dc=example,dc=org', 'wrong'));
    }

    public function testEmptyLdapPasswordNeverBinds(): void
    {
        $guard = $this->createMock(Guard::class);
        $guard->expects($this->never())->method('bind');

        $this->assertFalse($this->login()->verifyLdapPassword($this->ldap($guard), 'uid=a,dc=example,dc=org', ''));
    }

    public static function ldapOutages(): array
    {
        return [
            'server down' => [-1, "Can't contact LDAP server"],
            'timeout' => [-5, 'Timed out'],
            'busy' => [51, 'Busy'],
            'unavailable' => [52, 'Unavailable'],
        ];
    }

    #[DataProvider('ldapOutages')]
    public function testLdapOutageIsNoWrongPassword(int $code, string $message): void
    {
        $guard = $this->createMock(Guard::class);
        $guard->method('bind')->willThrowException($this->bindError($code, $message));

        $this->expectException(IdpUnavailable::class);
        $this->login()->verifyLdapPassword($this->ldap($guard), 'uid=a,dc=example,dc=org', 'secret');
    }

    public function testFailedStartTlsIsAnOutage(): void
    {
        $guard = $this->createMock(Guard::class);
        $guard->method('bind')->willThrowException(new ConnectionException('Unable to connect'));

        $this->expectException(IdpUnavailable::class);
        $this->login()->verifyLdapPassword($this->ldap($guard), 'uid=a,dc=example,dc=org', 'secret');
    }

    private function login(): IdpLoginService
    {
        return new IdpLoginService(Settings::fromArray([]), $this->createMock(LinkService::class), $this->createMock(TemplateService::class));
    }

    private function ldap(Guard $guard): Connection
    {
        $ldap = $this->createMock(Connection::class);
        $ldap->method('auth')->willReturn($guard);
        return $ldap;
    }

    private function bindError(int $code, string $message): BindException
    {
        return BindException::withDetailedError(new Exception($message, $code), new DetailedError($code, $message, null));
    }
}
