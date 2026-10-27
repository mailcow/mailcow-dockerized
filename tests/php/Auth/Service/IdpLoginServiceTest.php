<?php

declare(strict_types=1);

namespace Mailcow\Tests\Auth\Service;

use Mailcow\Auth\Service\IdpLoginService;
use Mailcow\Identity\Model\Settings;
use Mailcow\Identity\Service\LinkService;
use Mailcow\MailboxGroup\Service\TemplateService;
use PDOException;
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
}
