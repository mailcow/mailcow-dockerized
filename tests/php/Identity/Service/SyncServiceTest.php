<?php

declare(strict_types=1);

namespace Mailcow\Tests\Identity\Service;

use Mailcow\Identity\Directory\Directory;
use Mailcow\Identity\Directory\DirectoryException;
use Mailcow\Identity\Legacy\LegacyAccounts;
use Mailcow\Identity\Model\Account;
use Mailcow\Identity\Model\DirectoryGroup;
use Mailcow\Identity\Model\DirectoryUser;
use Mailcow\Identity\Model\Listing;
use Mailcow\Identity\Model\Settings;
use Mailcow\Identity\Repository\IdentityRepository;
use Mailcow\Identity\Repository\RunRepository;
use Mailcow\Identity\Service\SyncPlanner;
use Mailcow\Identity\Service\SyncService;
use Mailcow\MailboxGroup\Model\Group;
use Mailcow\MailboxGroup\Service\IdpSyncService;
use Mailcow\MailboxGroup\Service\TemplateService;
use Mailcow\Sogo\Service\SogoService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SyncServiceTest extends TestCase
{
    private IdentityRepository&MockObject $repo;
    private RunRepository&MockObject $runs;
    private IdpSyncService&MockObject $groups;
    private TemplateService&MockObject $templates;
    private LegacyAccounts&MockObject $legacy;
    private SogoService&MockObject $sogo;
    private array $finished = [];

    protected function setUp(): void
    {
        $this->repo = $this->createMock(IdentityRepository::class);
        $this->repo->method('accounts')->willReturn(['a@example.org' => new Account('a@example.org', 'A', 1, 'u1'),
            'b@example.org' => new Account('b@example.org', 'B', 1)]);
        $this->repo->method('mailboxes')->willReturn(['a@example.org' => 'keycloak', 'b@example.org' => 'keycloak']);
        $this->repo->method('domains')->willReturn(['example.org']);
        $this->runs = $this->createMock(RunRepository::class);
        $this->runs->method('finish')->willReturnCallback(function (int $id, string $status, array $summary): void {
            $this->finished = [$id, $status, $summary];
        });
        $this->groups = $this->createMock(IdpSyncService::class);
        $this->groups->method('groups')->willReturn([['id' => 4, 'domain' => 'example.org', 'name' => 'Sales', 'idp_ref' => 'g1',
            'template' => null, 'priority' => 0, 'active' => 1, 'members' => []]]);
        $this->templates = $this->createMock(TemplateService::class);
        $this->templates->method('effective')->willReturn([]);
        $this->legacy = $this->createMock(LegacyAccounts::class);
        $this->sogo = $this->createMock(SogoService::class);
    }

    public function testRunAppliesThePlan(): void
    {
        $this->repo->expects($this->exactly(2))->method('link')->willReturnCallback(fn(string $u, string $p, string $id) =>
            $this->assertContains([$u, $p, $id], [['b@example.org', 'keycloak', 'u2'], ['new@example.org', 'keycloak', 'u3']]));
        $this->repo->expects($this->once())->method('setActive')->with('a@example.org', 'keycloak', 2, true);
        $this->legacy->expects($this->once())->method('createMailbox')->with('new@example.org', 'New', 'keycloak', 'Default')->willReturn(null);
        $this->sogo->expects($this->once())->method('refreshStaticView')->with(['a@example.org']);
        $group = Group::fromRow(['id' => 4, 'name' => 'Sales']);
        $this->groups->method('findByIdpRef')->with('g1')->willReturn($group);
        $this->groups->expects($this->once())->method('syncGroupMembers')->with(4, ['b@example.org', 'new@example.org'])
            ->willReturn(['added' => [], 'removed' => [], 'skipped' => [], 'warnings' => []]);

        $run = $this->service($this->listing())->execute(9, 'run');

        $this->assertSame('ok', $run['status']);
        $this->assertSame([], $run['summary']['errors']);
        $this->assertSame([9, 'ok'], array_slice($this->finished, 0, 2));
    }

    public function testLeavingATemplateGroupAppliesTheMappingInTheSameRun(): void
    {
        $this->repo = $this->createMock(IdentityRepository::class);
        $this->repo->method('accounts')->willReturn(['a@example.org' => new Account('a@example.org', 'A', 1, 'u1', false, null, 'h-default')]);
        $this->repo->method('templateHashes')->willReturn(['Default' => 'h-default', 'Staff' => 'h-staff']);
        $this->groups = $this->createMock(IdpSyncService::class);
        $this->groups->method('groups')->willReturn([['id' => 4, 'domain' => 'example.org', 'name' => 'Sales', 'idp_ref' => 'g1',
            'template' => 'Default', 'priority' => 0, 'active' => 1, 'members' => ['a@example.org' => 'idp']]]);
        $this->groups->method('findByIdpRef')->willReturn(Group::fromRow(['id' => 4, 'name' => 'Sales']));
        $this->groups->method('syncGroupMembers')->with(4, [])
            ->willReturn(['added' => [], 'removed' => ['a@example.org'], 'skipped' => [], 'warnings' => []]);
        $this->templates = $this->createMock(TemplateService::class);
        $this->templates->method('effective')->willReturnOnConsecutiveCalls(['a@example.org' => 'Default'], ['a@example.org' => null]);
        $this->legacy->expects($this->once())->method('applyTemplate')->with('a@example.org', 'A', 'Staff')->willReturn(null);
        $directory = $this->createMock(Directory::class);
        $directory->method('listAll')->willReturn(new Listing(['u1' => new DirectoryUser('u1', 'a@example.org', 'A', true, ['staff'])],
            ['g1' => new DirectoryGroup('g1', 'Sales')]));

        $run = $this->service($directory, ['periodic_sync' => 1, 'mappers' => ['staff'], 'templates' => ['Staff']])->execute(9, 'run');

        $this->assertSame(1, $run['summary']['counts']['updates']);
    }

    public function testPreviewWritesNothing(): void
    {
        $this->repo->expects($this->never())->method('link');
        $this->repo->expects($this->never())->method('setActive');
        $this->legacy->expects($this->never())->method('createMailbox');
        $this->groups->expects($this->never())->method('syncGroupMembers');

        $run = $this->service($this->listing())->execute(9, 'preview');

        $this->assertSame(['ok', 'preview', 1, 1], [$run['status'], $run['summary']['mode'], $run['summary']['counts']['deactivate'],
            $run['summary']['counts']['creates']]);
    }

    public function testListingErrorsWriteNothing(): void
    {
        $this->repo->expects($this->never())->method('setMissing');
        $directory = $this->createMock(Directory::class);
        $directory->method('listAll')->willThrowException(new DirectoryException('LDAP search incomplete (code 4): Sizelimit exceeded'));

        $run = $this->service($directory)->execute(9, 'run');

        $this->assertSame('error', $run['status']);
        $this->assertSame('LDAP search incomplete (code 4): Sizelimit exceeded', $this->finished[2]['error']);
    }

    public function testBlockedRunsWriteNothing(): void
    {
        $this->repo->expects($this->never())->method('link');
        $this->repo->expects($this->never())->method('setActive');

        $run = $this->service($this->listing(), ['offboarding_limit' => 0, 'group_sync' => 0], 6)->execute(9, 'run');

        $this->assertSame('blocked', $run['status']);
    }

    public function testScheduledRunsWaitForTheInterval(): void
    {
        $this->runs->method('nextPending')->willReturn(null);
        $this->runs->method('secondsSinceScheduled')->willReturn(600);
        $this->runs->expects($this->never())->method('create');

        $this->assertNull($this->service($this->listing())->runDue());
    }

    public function testRequestedRunsComeFirst(): void
    {
        $this->runs->method('nextPending')->willReturn(['id' => 3, 'mode' => 'preview']);
        $this->runs->expects($this->once())->method('start')->with(3);

        $this->assertSame('preview', $this->service($this->listing())->runDue()['mode']);
    }

    public function testNothingIsDueWithoutSync(): void
    {
        $this->runs->method('nextPending')->willReturn(null);
        $this->runs->expects($this->never())->method('create');

        $this->assertNull($this->service($this->listing(), ['offboarding' => 0, 'group_sync' => 0, 'import_users' => 0])->runDue());
    }

    /** a@ (linked) is disabled, b@ is adopted, new@ is created, b@ and new@ are in Sales */
    private function listing(): Directory
    {
        $directory = $this->createMock(Directory::class);
        $directory->method('listAll')->willReturn(new Listing([
            'u1' => new DirectoryUser('u1', 'a@example.org', 'A', false),
            'u2' => new DirectoryUser('u2', 'b@example.org', 'B', true),
            'u3' => new DirectoryUser('u3', 'new@example.org', 'New', true),
        ], ['g1' => new DirectoryGroup('g1', 'Sales', null, ['u2', 'u3'])]));
        return $directory;
    }

    private function service(Directory $directory, array $settings = [], int $extraAccounts = 0): SyncService
    {
        if ($extraAccounts > 0) {
            $accounts = [];
            for ($i = 0; $i < $extraAccounts; $i++) {
                $accounts["x$i@example.org"] = new Account("x$i@example.org", '', 1, "x$i", false, '2026-10-25 10:00:00');
            }
            $this->repo = $this->createMock(IdentityRepository::class);
            $this->repo->method('accounts')->willReturn($accounts);
            $this->repo->expects($this->never())->method('setActive');
        }
        return new SyncService(Settings::fromArray($settings + ['authsource' => 'keycloak', 'offboarding' => 1, 'import_users' => 1,
            'group_sync' => 1, 'default_template' => 'Default']), fn(): Directory => $directory, $this->repo, $this->runs, new SyncPlanner(),
            $this->groups, $this->templates, $this->legacy, $this->sogo);
    }
}
