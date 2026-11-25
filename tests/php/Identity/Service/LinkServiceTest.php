<?php

declare(strict_types=1);

namespace Mailcow\Tests\Identity\Service;

use Mailcow\Identity\Repository\IdentityRepository;
use Mailcow\Identity\Service\LinkService;
use PHPUnit\Framework\TestCase;

class LinkServiceTest extends TestCase
{
    public function testLinkedMailboxNeedsTheLinkedUser(): void
    {
        $repo = $this->createMock(IdentityRepository::class);
        $repo->method('findLink')->willReturn(['provider' => 'keycloak', 'external_id' => 'u1', 'idp_disabled' => 0, 'missing_since' => null]);
        $repo->expects($this->never())->method('link');
        $login = new LinkService($repo);

        $this->assertTrue($login->verify('keycloak', 'a@example.org', 'u1'));
        $this->assertFalse($login->verify('keycloak', 'a@example.org', 'u2'));
        $this->assertFalse($login->verify('keycloak', 'a@example.org', ''));
    }

    public function testUnlinkedMailboxIsLinkedOnLogin(): void
    {
        $repo = $this->createMock(IdentityRepository::class);
        $repo->method('findLink')->willReturn(['provider' => 'keycloak', 'external_id' => null, 'idp_disabled' => 1, 'missing_since' => null]);
        $repo->method('usernameOf')->willReturn(null);
        $repo->expects($this->once())->method('link')->with('a@example.org', 'keycloak', 'u1');

        $this->assertTrue((new LinkService($repo))->verify('keycloak', 'a@example.org', 'u1'));
    }

    public function testUserOfAnotherMailboxIsDenied(): void
    {
        $repo = $this->createMock(IdentityRepository::class);
        $repo->method('findLink')->willReturn(null);
        $repo->method('usernameOf')->with('ldap', 'guid-1')->willReturn('old@example.org');
        $repo->expects($this->never())->method('link');
        $login = new LinkService($repo);

        $this->assertFalse($login->verify('ldap', 'new@example.org', 'guid-1'));
        $this->assertFalse($login->mayCreate('ldap', 'new@example.org', 'guid-1'));
        $this->assertTrue($login->mayCreate('ldap', 'old@example.org', 'guid-1'));
    }

    public function testLinkOfAnotherProviderIsReplaced(): void
    {
        $repo = $this->createMock(IdentityRepository::class);
        $repo->method('findLink')->willReturn(['provider' => 'ldap', 'external_id' => 'guid-1', 'idp_disabled' => 0, 'missing_since' => null]);
        $repo->method('usernameOf')->willReturn(null);
        $repo->expects($this->once())->method('link')->with('a@example.org', 'keycloak', 'u1');

        $this->assertTrue((new LinkService($repo))->verify('keycloak', 'a@example.org', 'u1'));
    }
}
