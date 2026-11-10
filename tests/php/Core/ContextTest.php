<?php

declare(strict_types=1);

namespace Mailcow\Tests\Core;

use Mailcow\Core\AccessDenied;
use Mailcow\Core\Context;
use Mailcow\Tests\Support\LegacyAclCalls;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/legacy_acl_functions.php';

class ContextTest extends TestCase
{
    protected function setUp(): void
    {
        LegacyAclCalls::$calls = [];
    }

    public function testFromSessionMapsRoleUsernameAclAndAccessAll(): void
    {
        $context = Context::fromSession([
            'mailcow_cc_role' => 'domainadmin',
            'mailcow_cc_username' => 'da',
            'acl' => ['syncjobs' => '1', 'spam_score' => '0'],
            'access_all_exception' => '1',
        ]);

        $this->assertSame('domainadmin', $context->role);
        $this->assertSame('da', $context->username);
        $this->assertTrue($context->isDomainAdmin());
        $this->assertFalse($context->isAdmin());
        $this->assertFalse($context->isUser());
        $this->assertTrue($context->hasAcl('syncjobs'));
        $this->assertFalse($context->hasAcl('spam_score'));
        $this->assertFalse($context->hasAcl('missing'));
        $this->assertTrue($context->accessAll);
    }

    public function testEmptySessionHasNoRights(): void
    {
        $context = Context::fromSession([]);

        $this->assertSame('', $context->role);
        $this->assertNull($context->username);
        $this->assertFalse($context->isAdmin() || $context->isDomainAdmin() || $context->isUser());
        $this->assertFalse($context->hasAcl('syncjobs'));
        $this->assertFalse($context->accessAll);
    }

    public function testAclAndAccessAllAcceptIntegerOneLikeTheLegacyCode(): void
    {
        $context = Context::fromSession(['mailcow_cc_role' => 'user', 'acl' => ['syncjobs' => 1], 'access_all_exception' => 1]);

        $this->assertTrue($context->hasAcl('syncjobs'));
        $this->assertTrue($context->accessAll);
        $this->assertFalse(Context::fromSession(['access_all_exception' => '0'])->accessAll);
        $this->assertFalse(Context::fromSession(['acl' => 'not an array'])->hasAcl('syncjobs'));
    }

    public function testApiAccessComesFromTheApiKeySession(): void
    {
        $ro = Context::fromSession(['mailcow_cc_role' => 'admin', 'mailcow_cc_api' => true, 'mailcow_cc_api_access' => 'ro']);
        $rw = Context::fromSession(['mailcow_cc_role' => 'admin', 'mailcow_cc_api' => true, 'mailcow_cc_api_access' => 'rw']);
        $login = Context::fromSession(['mailcow_cc_role' => 'admin', 'mailcow_cc_api_access' => 'ro']);

        $this->assertSame('ro', $ro->apiAccess);
        $this->assertTrue($ro->isReadOnlyApi());
        $this->assertSame('rw', $rw->apiAccess);
        $this->assertFalse($rw->isReadOnlyApi());
        $this->assertNull($login->apiAccess);
        $this->assertFalse($login->isReadOnlyApi());
        // Unknown access values are read-only
        $this->assertTrue(Context::fromSession(['mailcow_cc_api' => true, 'mailcow_cc_api_access' => 'x'])->isReadOnlyApi());
    }

    public function testSystemContextIsNoAccount(): void
    {
        $context = Context::system();

        $this->assertSame('system', $context->role);
        $this->assertNull($context->username);
        $this->assertFalse($context->isAdmin());
        $this->assertFalse($context->hasAcl('syncjobs'));
    }

    public function testAdminRightsComeFromTheRoleOrAccessAll(): void
    {
        $this->assertTrue((new Context('admin', 'admin'))->hasAdminRights());
        $this->assertTrue((new Context('user', 'user@example.org', [], true))->hasAdminRights());
        $this->assertFalse((new Context('domainadmin', 'da'))->hasAdminRights());
        $this->assertFalse(Context::system()->hasAdminRights());
    }

    public function testReadOnlyApiKeysMayOnlyRead(): void
    {
        $readOnly = new Context('admin', 'admin', [], false, 'ro');
        $readOnly->requireAdmin(false);
        (new Context('admin', 'admin', [], false, 'rw'))->requireAdmin();

        $this->expectException(AccessDenied::class);
        $readOnly->requireAdmin();
    }

    public function testRequireAdminDeniesOtherRoles(): void
    {
        $this->expectException(AccessDenied::class);
        (new Context('domainadmin', 'da'))->requireAdmin(false);
    }

    public function testBridgePassesUsernameAndRoleToTheLegacyFunctions(): void
    {
        $context = new Context('user', 'user@example.org');

        $this->assertTrue($context->canAccessMailbox('allowed@example.org'));
        $this->assertFalse($context->canAccessMailbox('other@example.org'));
        $this->assertTrue($context->canAccessDomain('example.org'));
        $this->assertSame([
            ['mailbox', 'user@example.org', 'user', 'allowed@example.org'],
            ['mailbox', 'user@example.org', 'user', 'other@example.org'],
            ['domain', 'user@example.org', 'user', 'example.org'],
        ], LegacyAclCalls::$calls);
    }
}
