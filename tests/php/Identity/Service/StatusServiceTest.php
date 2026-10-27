<?php

declare(strict_types=1);

namespace Mailcow\Tests\Identity\Service;

use Mailcow\Core\AccessDenied;
use Mailcow\Core\Context;
use Mailcow\Core\UserError;
use Mailcow\Identity\Model\Settings;
use Mailcow\Identity\Repository\IdentityRepository;
use Mailcow\Identity\Repository\RunRepository;
use Mailcow\Identity\Service\StatusService;
use Mailcow\Tests\Support\FakeContext;
use PHPUnit\Framework\TestCase;

class StatusServiceTest extends TestCase
{
    public function testAdminRequestsRuns(): void
    {
        $runs = $this->createMock(RunRepository::class);
        $runs->expects($this->once())->method('request')->with('keycloak', 'force', 'admin')->willReturn(5);

        $this->assertSame(5, $this->service(FakeContext::admin(), $runs)->requestRun('force'));
    }

    public function testInvalidModeIsRejected(): void
    {
        $this->expectExceptionObject(new UserError('iam_sync_mode_invalid'));
        $this->service(FakeContext::admin())->requestRun(['run']);
    }

    public function testReadOnlyApiMayOnlyRead(): void
    {
        $service = $this->service(new Context('admin', 'api', [], false, 'ro'));

        $this->assertSame('keycloak', $service->status()['provider']);
        $this->expectException(AccessDenied::class);
        $service->requestRun('run');
    }

    public function testDomainAdminsSeeOnlyLinksOfTheirMailboxes(): void
    {
        $repo = $this->createMock(IdentityRepository::class);
        $repo->method('findLink')->willReturn(['provider' => 'keycloak', 'external_id' => 'u1', 'idp_disabled' => 0, 'missing_since' => null]);
        $service = $this->service(FakeContext::domainAdmin(['a@example.org']), null, $repo);

        $this->assertSame('u1', $service->link('A@example.org')['external_id']);
        $this->assertNull($service->link('b@example.org'));
        $this->expectException(AccessDenied::class);
        $service->status();
    }

    public function testDomainAdminsCannotUnlink(): void
    {
        $repo = $this->createMock(IdentityRepository::class);
        $repo->expects($this->never())->method('unlink');

        $this->expectException(AccessDenied::class);
        $this->service(FakeContext::domainAdmin(['a@example.org']), null, $repo)->unlink('a@example.org');
    }

    private function service(Context $context, ?RunRepository $runs = null, ?IdentityRepository $repo = null): StatusService
    {
        return new StatusService(Settings::fromArray(['authsource' => 'keycloak']), $repo ?? $this->createMock(IdentityRepository::class),
            $runs ?? $this->createMock(RunRepository::class), $context);
    }
}
