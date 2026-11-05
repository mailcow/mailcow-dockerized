<?php

declare(strict_types=1);

namespace Mailcow\Tests\MailboxGroup\Service;

use Mailcow\Dovecot\Service\DovecotService;
use Mailcow\MailboxGroup\Model\Group;
use Mailcow\MailboxGroup\Repository\GroupRepository;
use Mailcow\MailboxGroup\Service\ChangeEffects;
use Mailcow\MailboxGroup\Service\SharingService;
use Mailcow\Sogo\Service\SogoService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SharingServiceTest extends TestCase
{
    public function testGrantStatesFollowDovecot(): void
    {
        $repo = $this->createMock(GroupRepository::class);
        $repo->method('imapGrantsToApply')->with(7, false)->willReturn([
            self::grant(1, 'a@example.org', 'write', 'pending'),
            self::grant(2, 'b@example.org', 'read', 'removing'),
            self::grant(3, 'c@example.org', 'read', 'pending'),
            self::grant(4, 'd@example.org', 'read', 'removing'),
        ]);
        $dovecot = $this->createMock(DovecotService::class);
        $dovecot->method('setAclAll')->willReturnCallback(fn(string $owner, string $identifier, array $rights): ?string =>
            in_array($owner, ['c@example.org', 'd@example.org'], true) ? 'command failed' : null);
        $states = [];
        $repo->method('setImapState')->willReturnCallback(function (int $id, string $state, ?string $error) use (&$states): void {
            $states[$id] = [$state, $error];
        });
        $repo->expects($this->once())->method('deleteImapGrant')->with(2);

        $warnings = (new SharingService($repo, $dovecot, $this->createMock(SogoService::class), $this->createMock(ChangeEffects::class)))->applyImap(7);

        $this->assertSame([1 => ['applied', null], 3 => ['failed', 'command failed'], 4 => ['removing', 'command failed']], $states);
        $this->assertSame([['group_imap_grant_failed', 'c@example.org'], ['group_imap_grant_failed', 'd@example.org']], $warnings);
    }

    public function testRightsPerLevel(): void
    {
        $repo = $this->createMock(GroupRepository::class);
        $repo->method('imapGrantsToApply')->willReturn([self::grant(1, 'a@example.org', 'read', 'pending')]);
        $dovecot = $this->createMock(DovecotService::class);
        $dovecot->expects($this->once())->method('setAclAll')->with('a@example.org', 'group=mcg_7', ['lookup', 'read', 'write-seen'])->willReturn(null);

        (new SharingService($repo, $dovecot, $this->createMock(SogoService::class), $this->createMock(ChangeEffects::class)))->applyImap(7);
    }

    public function testRemovingAGroupDropsItsAcls(): void
    {
        $group = Group::fromRow(['id' => 7]);
        $group->imapGrants = [['owner' => 'a@example.org', 'rights' => 'read']];
        $dovecot = $this->createMock(DovecotService::class);
        $dovecot->expects($this->once())->method('setAclAll')->with('a@example.org', 'group=mcg_7', []);

        (new SharingService($this->createMock(GroupRepository::class), $dovecot, $this->createMock(SogoService::class), $this->createMock(ChangeEffects::class)))->removeImap($group);
    }

    public function testReconcileAppliesEverythingAgain(): void
    {
        $repo = $this->createMock(GroupRepository::class);
        $repo->expects($this->once())->method('imapGrantsToApply')->with(null, true)->willReturn([]);
        $repo->method('groupIdsWithSogoGrants')->willReturn([3, 5]);
        $repo->method('rebuild')->willReturnCallback(fn(int $id): array => ($id === 3) ? ['a@example.org'] : ['a@example.org', 'b@example.org']);
        $repo->expects($this->once())->method('cleanupSogoAcl');
        $effects = $this->createMock(ChangeEffects::class);
        $effects->expects($this->once())->method('apply')->with(['a@example.org', 'b@example.org'], [])->willReturn([]);

        (new SharingService($repo, $this->createMock(DovecotService::class), $this->createMock(SogoService::class), $effects))->reconcile();
    }

    public function testMembersAreSubscribedOnce(): void
    {
        $repo = $this->reconcileRepo();
        $repo->method('staleSubscriptions')->willReturn([]);
        $repo->method('missingSubscriptions')->willReturnCallback(fn(string $kind): array => ($kind === 'imap')
            ? [['grant_id' => 2, 'owner' => 'team@example.org', 'folder' => '', 'usernames' => ['a@example.org']]]
            : [['grant_id' => 3, 'owner' => 'team@example.org', 'folder' => 'Calendar/personal', 'usernames' => ['a@example.org', 'b@example.org']],
               ['grant_id' => 4, 'owner' => 'team@example.org', 'folder' => 'Contacts/personal', 'usernames' => ['c@example.org']]]);
        $added = [];
        $repo->method('addSubscriptions')->willReturnCallback(function (string $kind, int $grant, string $owner, string $folder, array $users) use (&$added): void {
            $added[] = [$kind, $grant, $folder, $users];
        });
        $dovecot = $this->createMock(DovecotService::class);
        $dovecot->expects($this->once())->method('subscribeShared')->with('team@example.org', ['a@example.org'], false)->willReturn(null);
        $legacy = $this->createMock(SogoService::class);
        $legacy->method('subscribe')->willReturnCallback(fn(string $owner, string $folder): ?string =>
            ($folder === 'Contacts/personal') ? 'command failed' : null);
        $legacy->expects($this->once())->method('flushCache');

        $warnings = (new SharingService($repo, $dovecot, $legacy, $this->effects()))->reconcile();

        // the failed batch is not recorded, so the next run tries again
        $this->assertSame([['imap', 2, '', ['a@example.org']], ['sogo', 3, 'Calendar/personal', ['a@example.org', 'b@example.org']]], $added);
        $this->assertSame([['group_subscription_failed', 'c@example.org']], $warnings);
    }

    public function testSubscriptionsOfMembersWithoutAccessAreRemoved(): void
    {
        $repo = $this->reconcileRepo();
        $repo->method('missingSubscriptions')->willReturn([]);
        $repo->method('staleSubscriptions')->willReturnCallback(fn(string $kind): array => ($kind === 'imap')
            ? [['id' => 1, 'username' => 'a@example.org', 'owner' => 'team@example.org', 'folder' => '', 'member_exists' => 1, 'owner_exists' => 1, 'covered' => 0],
               ['id' => 2, 'username' => 'gone@example.org', 'owner' => 'team@example.org', 'folder' => '', 'member_exists' => 0, 'owner_exists' => 1, 'covered' => 0]]
            : [['id' => 3, 'username' => 'b@example.org', 'owner' => 'team@example.org', 'folder' => 'Calendar/personal', 'member_exists' => 1, 'owner_exists' => 1, 'covered' => 0],
               // another group still grants the calendar
               ['id' => 4, 'username' => 'c@example.org', 'owner' => 'team@example.org', 'folder' => 'Calendar/personal', 'member_exists' => 1, 'owner_exists' => 1, 'covered' => 1]]);
        $deleted = [];
        $repo->method('deleteSubscription')->willReturnCallback(function (int $id) use (&$deleted): void {
            $deleted[] = $id;
        });
        $dovecot = $this->createMock(DovecotService::class);
        $dovecot->expects($this->once())->method('subscribeShared')->with('team@example.org', ['a@example.org'], true)->willReturn(null);
        $legacy = $this->createMock(SogoService::class);
        $legacy->expects($this->once())->method('subscribe')->with('team@example.org', 'Calendar/personal', ['b@example.org'], true)->willReturn('failed');
        $legacy->expects($this->never())->method('flushCache');

        $warnings = (new SharingService($repo, $dovecot, $legacy, $this->effects()))->reconcile();

        $this->assertSame([1, 2, 4], $deleted);
        $this->assertSame([['group_subscription_failed', 'b@example.org']], $warnings);
    }

    public function testMissingSogoFoldersAreCreatedAfterAWrite(): void
    {
        $repo = $this->createMock(GroupRepository::class);
        $repo->method('pendingSogoOwners')->with(7)->willReturn(['team@example.org']);
        $repo->expects($this->once())->method('rebuild')->with(7)->willReturn([]);
        $legacy = $this->createMock(SogoService::class);
        $legacy->expects($this->once())->method('initUsers')->with(['team@example.org'])->willReturn(null);

        $this->assertSame([], (new SharingService($repo, $this->createMock(DovecotService::class), $legacy, $this->effects()))->applySogo(7));
    }

    public function testNothingHappensWithoutPendingFolders(): void
    {
        $repo = $this->createMock(GroupRepository::class);
        $repo->method('pendingSogoOwners')->willReturn([]);
        $repo->expects($this->never())->method('rebuild');
        $legacy = $this->createMock(SogoService::class);
        $legacy->expects($this->never())->method('initUsers');

        $this->assertSame([], (new SharingService($repo, $this->createMock(DovecotService::class), $legacy, $this->effects()))->applySogo(7));
    }

    public function testFailedFolderCreationIsAWarning(): void
    {
        $repo = $this->createMock(GroupRepository::class);
        $repo->method('pendingSogoOwners')->willReturn(['team@example.org']);
        $repo->expects($this->never())->method('rebuild');
        $legacy = $this->createMock(SogoService::class);
        $legacy->method('initUsers')->willReturn('command failed');

        $this->assertSame([['group_sogo_init_failed', 'team@example.org']],
            (new SharingService($repo, $this->createMock(DovecotService::class), $legacy, $this->effects()))->applySogo(7));
    }

    /** Repository mock for reconcile(): no IMAP grants, no SOGo grants, no pending folders */
    private function reconcileRepo(): GroupRepository&MockObject
    {
        $repo = $this->createMock(GroupRepository::class);
        $repo->method('imapGrantsToApply')->willReturn([]);
        $repo->method('groupIdsWithSogoGrants')->willReturn([]);
        $repo->method('pendingSogoOwners')->willReturn([]);
        return $repo;
    }

    private function effects(): ChangeEffects&MockObject
    {
        $effects = $this->createMock(ChangeEffects::class);
        $effects->method('apply')->willReturn([]);
        return $effects;
    }

    private static function grant(int $id, string $owner, string $rights, string $state): array
    {
        return ['id' => $id, 'group_id' => 7, 'owner' => $owner, 'rights' => $rights, 'state' => $state, 'last_error' => null];
    }
}
