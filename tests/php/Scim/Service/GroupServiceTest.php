<?php

declare(strict_types=1);

namespace Mailcow\Tests\Scim\Service;

use Mailcow\Core\UserError;
use Mailcow\MailboxGroup\Model\Group as MailboxGroup;
use Mailcow\MailboxGroup\Service\GroupService as MailboxGroupService;
use Mailcow\MailboxGroup\Service\IdpSyncService;
use Mailcow\Scim\Model\Group;
use Mailcow\Scim\Model\Schema;
use Mailcow\Scim\Model\Token;
use Mailcow\Scim\Model\User;
use Mailcow\Scim\Repository\GroupRepository;
use Mailcow\Scim\Repository\UserRepository;
use Mailcow\Scim\ScimError;
use Mailcow\Scim\Service\GroupService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GroupServiceTest extends TestCase
{
    private GroupRepository&MockObject $groups;
    private UserRepository&MockObject $users;
    private MailboxGroupService&MockObject $mailboxGroups;
    private IdpSyncService&MockObject $idp;
    private GroupService $service;
    private Token $token;

    protected function setUp(): void
    {
        $this->groups = $this->createMock(GroupRepository::class);
        $this->users = $this->createMock(UserRepository::class);
        $this->mailboxGroups = $this->createMock(MailboxGroupService::class);
        $this->idp = $this->createMock(IdpSyncService::class);
        $this->service = new GroupService($this->groups, $this->users, $this->mailboxGroups, $this->idp);
        $this->token = Token::fromRow(['id' => 7, 'group_domain' => 'example.org']);
        // SCIM users u-1 .. u-3 = a@, b@, c@example.org
        $user = fn(string $id): ?User => in_array($id, ['u-1', 'u-2', 'u-3'], true)
            ? User::fromRow(['scim_id' => $id, 'username' => ['u-1' => 'a', 'u-2' => 'b', 'u-3' => 'c'][$id] . '@example.org',
                'domain' => 'example.org', 'active' => 1]) : null;
        $this->users->method('find')->willReturnCallback($user);
        $this->users->method('findMany')->willReturnCallback(fn(array $ids): array => array_filter(array_combine($ids, array_map($user, $ids))));
        // the advisory locks just run the work
        $this->groups->method('locked')->willReturnCallback(fn(int|string $group, callable $work): mixed => $work());
        $this->groups->method('lockedAll')->willReturnCallback(fn(array $groups, callable $work): mixed => $work());
    }

    private static function group(array $members = ['u-1' => 'a@example.org', 'u-2' => 'b@example.org']): Group
    {
        $group = Group::fromRow(['id' => 5, 'scim_id' => 'g-1', 'domain' => 'example.org', 'name' => 'Sales', 'external_id' => 'ext-g', 'token_id' => 7]);
        $group->members = $members;
        return $group;
    }

    private static function patch(array ...$operations): array
    {
        return ['schemas' => [Schema::PATCH], 'operations' => $operations];
    }

    private function expectScimError(int $status, ?string $scimType, callable $call): void
    {
        try {
            $call();
            $this->fail("ScimError $status expected");
        } catch (ScimError $e) {
            $this->assertSame([$status, $scimType], [$e->status, $e->scimType], $e->getMessage());
        }
    }

    public function testCreateInTheGroupDomain(): void
    {
        $created = MailboxGroup::fromRow(['id' => 5, 'domain' => 'example.org', 'name' => 'Sales']);
        $this->mailboxGroups->expects($this->once())->method('create')->with(['domain' => 'example.org', 'name' => 'Sales'])->willReturn($created);
        $this->groups->expects($this->once())->method('link')->with($this->anything(), 5, 'ext-g', 7);
        $this->idp->expects($this->once())->method('syncGroupMembers')->with(5, ['a@example.org']);
        $this->groups->method('find')->willReturn(self::group());

        // extra schema URN of Entra ID
        $this->service->create($this->token, ['schemas' => [Schema::GROUP, 'http://schemas.microsoft.com/2006/11/ResourceManagement/ADSCIM/2.0/Group'],
            'displayname' => ' Sales ', 'externalid' => 'ext-g', 'members' => [['value' => 'u-1']]]);
    }

    public function testCreateLinksAGroupDeletedThroughScimAgain(): void
    {
        // the same group (token, externalId) comes back: found by name here (no tombstone of its externalId first)
        $this->groups->method('deletedGroup')->with('example.org', 'sales')->willReturn(['id' => 5, 'external_id' => 'ext-g', 'token_id' => 7]);
        $this->mailboxGroups->expects($this->never())->method('create');
        $this->groups->expects($this->once())->method('relink')->with(5, $this->anything(), 7)->willReturn(true);
        $this->groups->method('find')->willReturn(self::group([]));
        $this->idp->expects($this->once())->method('renameFromIdp')->with(5, 'sales');
        // the tombstone kept the externalId: nothing to write
        $this->groups->expects($this->never())->method('setExternalId');
        $this->idp->expects($this->once())->method('syncGroupMembers')->with(5, ['a@example.org']);

        $this->service->create($this->token, ['schemas' => [Schema::GROUP], 'displayname' => 'sales', 'externalid' => 'ext-g',
            'members' => [['value' => 'u-1']]]);
    }

    public function testTheRestrictedDomainIsTheGroupDomain(): void
    {
        $this->token->domainRestriction = 'example.com';
        $this->groups->method('nameTaken')->with('example.com', 'Sales')->willReturn(true);
        $this->expectScimError(409, 'uniqueness', fn() => $this->service->create($this->token, ['schemas' => [Schema::GROUP], 'displayname' => 'Sales']));
    }

    public static function invalidGroups(): array
    {
        return [
            'no group domain' => [['displayname' => 'Sales'], null, 400, 'invalidValue'],
            'no displayName' => [['members' => []], 'example.org', 400, 'invalidValue'],
            'nested group' => [['displayname' => 'Sales', 'members' => [['value' => 'g-2', 'type' => 'Group']]], 'example.org', 400, 'invalidValue'],
            'unknown member' => [['displayname' => 'Sales', 'members' => [['value' => 'u-9']]], 'example.org', 400, 'invalidValue'],
        ];
    }

    #[DataProvider('invalidGroups')]
    public function testInvalidGroupsAreNotCreated(array $body, ?string $groupDomain, int $status, string $scimType): void
    {
        $this->token->groupDomain = $groupDomain;
        $this->mailboxGroups->expects($this->never())->method('create');
        $this->expectScimError($status, $scimType, fn() => $this->service->create($this->token, $body + ['schemas' => [Schema::GROUP]]));
    }

    public function testMailcowRejectionsAreMapped(): void
    {
        $this->mailboxGroups->method('create')->willThrowException(new UserError('object_exists', ['Sales']));
        $this->expectScimError(409, 'uniqueness', fn() => $this->service->create($this->token, ['schemas' => [Schema::GROUP], 'displayname' => 'Sales']));
    }

    /** The PATCH requests of Entra ID, Okta and Authentik */
    public static function memberPatches(): array
    {
        return [
            'add (Entra ID)' => [[['op' => 'Add', 'path' => 'members', 'value' => [['$ref' => null, 'value' => 'u-3']]]],
                ['a@example.org', 'b@example.org', 'c@example.org']],
            'add an existing member is a no-op' => [[['op' => 'add', 'path' => 'members', 'value' => [['value' => 'u-1']]]], null],
            'remove with value list (Entra ID default)' => [[['op' => 'Remove', 'path' => 'members', 'value' => [['value' => 'u-1']]]],
                ['b@example.org']],
            'remove by value filter (RFC, Okta, Authentik)' => [[['op' => 'remove', 'path' => 'members[value eq "u-2"]']], ['a@example.org']],
            'remove a non-member is a no-op' => [[['op' => 'remove', 'path' => 'members[value eq "u-9"]']], null],
            'remove all' => [[['op' => 'remove', 'path' => 'members']], []],
            'replace' => [[['op' => 'replace', 'path' => 'members', 'value' => [['value' => 'u-3']]]], ['c@example.org']],
            'replace by value filter' => [[['op' => 'replace', 'path' => 'members[value eq "u-1"]', 'value' => [['value' => 'u-3']]]],
                ['b@example.org', 'c@example.org']],
            'path-less add' => [[['op' => 'add', 'value' => ['members' => [['value' => 'u-3']]]]], ['a@example.org', 'b@example.org', 'c@example.org']],
            'remove and add in one request' => [[
                ['op' => 'remove', 'path' => 'members[value eq "u-1"]'],
                ['op' => 'add', 'path' => 'members', 'value' => [['value' => 'u-3']]],
            ], ['b@example.org', 'c@example.org']],
        ];
    }

    #[DataProvider('memberPatches')]
    public function testPatchMembers(array $operations, ?array $expected): void
    {
        // a member of another domain set by the IdP, invisible to a restricted token, stays
        $this->token->domainRestriction = 'example.org';
        $group = self::group();
        $group->assigned = ['a@example.org', 'b@example.org', 'x@example.net'];
        $this->groups->method('find')->willReturn($group);
        $wanted = fn(array $usernames): bool => self::sorted($usernames) === self::sorted(array_merge($expected ?? [], ['x@example.net']));
        if ($expected === null) {
            $this->idp->expects($this->never())->method('syncGroupMembers');
            $this->groups->expects($this->never())->method('setMembers');
        } else {
            $this->idp->expects($this->once())->method('syncGroupMembers')->with(5, $this->callback($wanted));
            $this->groups->expects($this->once())->method('setMembers')->with(5, $this->callback($wanted));
        }
        $this->service->patch($this->token, 'g-1', self::patch(...$operations));
    }

    private static function sorted(array $values): array
    {
        sort($values);
        return $values;
    }

    public function testPatchRename(): void
    {
        $this->groups->method('find')->willReturn(self::group());
        $this->idp->expects($this->once())->method('renameFromIdp')->with(5, 'Marketing');
        $this->idp->expects($this->never())->method('syncGroupMembers');
        // Okta/Authentik: path-less replace repeating id and schemas
        $this->service->patch($this->token, 'g-1', self::patch(['op' => 'replace', 'value' => ['id' => 'g-1', 'schemas' => [Schema::GROUP], 'displayname' => 'Marketing']]));
    }

    public function testPatchIsAtomic(): void
    {
        $this->groups->method('find')->willReturn(self::group());
        $this->idp->expects($this->never())->method($this->anything());
        $this->groups->expects($this->never())->method('setExternalId');
        $this->expectScimError(400, 'invalidValue', fn() => $this->service->patch($this->token, 'g-1', self::patch(
            ['op' => 'replace', 'path' => 'displayName', 'value' => 'Marketing'],
            ['op' => 'add', 'path' => 'members', 'value' => [['value' => 'u-9']]],
        )));
        // RFC 7644 3.5.2.2: removing a required attribute
        $this->expectScimError(400, 'mutability', fn() => $this->service->patch($this->token, 'g-1',
            self::patch(['op' => 'remove', 'path' => 'displayName'])));
        $this->expectScimError(400, 'mutability', fn() => $this->service->patch($this->token, 'g-1',
            self::patch(['op' => 'replace', 'path' => 'id', 'value' => 'g-2'])));
        $this->expectScimError(400, 'mutability', fn() => $this->service->patch($this->token, 'g-1',
            self::patch(['op' => 'replace', 'path' => 'schemas', 'value' => [Schema::GROUP]])));
        // RFC 7644 3.5.2.3: a replace whose value filter matches nothing
        $this->expectScimError(400, 'noTarget', fn() => $this->service->patch($this->token, 'g-1',
            self::patch(['op' => 'replace', 'path' => 'members[value eq "u-3"]', 'value' => [['value' => 'u-3']]])));
    }

    public function testChangesRunUnderTheGroupLock(): void
    {
        $locked = false;
        $this->groups = $this->createMock(GroupRepository::class);
        $this->groups->method('locked')->willReturnCallback(function (int|string $group, callable $work) use (&$locked): mixed {
            $this->assertSame('g-1', $group);
            $locked = true;
            return $work();
        });
        // the group is read under the lock, not before it
        $this->groups->method('find')->willReturnCallback(function () use (&$locked): Group {
            $this->assertTrue($locked);
            return self::group();
        });
        $service = new GroupService($this->groups, $this->users, $this->mailboxGroups, $this->idp);
        $service->patch($this->token, 'g-1', self::patch(['op' => 'add', 'path' => 'members', 'value' => [['value' => 'u-3']]]));
    }


    public function testAConcurrentCreateIs409(): void
    {
        // the unique key of another POST that won the race
        $duplicate = new \PDOException('Duplicate entry');
        $duplicate->errorInfo = ['23000', 1062, 'Duplicate entry'];
        $this->mailboxGroups->method('create')->willThrowException($duplicate);
        $this->expectScimError(409, 'uniqueness', fn() => $this->service->create($this->token, ['schemas' => [Schema::GROUP], 'displayname' => 'Sales']));
    }

    public function testAConcurrentRelinkIs409(): void
    {
        // both POSTs saw the tombstone, the other one took it over first
        $this->groups->method('deletedGroup')->willReturn(['id' => 5, 'external_id' => 'ext-g', 'token_id' => 7]);
        $this->groups->method('relink')->willReturn(false);
        $this->idp->expects($this->never())->method('syncGroupMembers');
        $this->expectScimError(409, 'uniqueness', fn() => $this->service->create($this->token, ['schemas' => [Schema::GROUP], 'displayname' => 'Sales', 'externalid' => 'ext-g']));
    }

    public function testADeletedGroupIsOnlyLinkedAgainToTheSameGroup(): void
    {
        // another IdP group with the name is a conflict
        $this->groups->method('deletedGroup')->willReturn(['id' => 5, 'external_id' => 'ext-g', 'token_id' => 7]);
        $this->groups->expects($this->never())->method('relink');
        $this->mailboxGroups->expects($this->never())->method('create');
        foreach ([['externalid' => 'ext-other'], []] as $extra) {
            $this->expectScimError(409, 'uniqueness', fn() => $this->service->create($this->token, ['schemas' => [Schema::GROUP], 'displayname' => 'Sales'] + $extra));
        }
        $other = Token::fromRow(['id' => 8, 'group_domain' => 'example.org']);
        $this->expectScimError(409, 'uniqueness', fn() => $this->service->create($other, ['schemas' => [Schema::GROUP], 'displayname' => 'Sales', 'externalid' => 'ext-g']));
    }

    public function testAFailedLinkRemovesTheNewGroup(): void
    {
        $this->mailboxGroups->method('create')->willReturn(MailboxGroup::fromRow(['id' => 5, 'domain' => 'example.org', 'name' => 'Sales']));
        $this->groups->method('link')->willThrowException(new \PDOException('gone'));
        $this->mailboxGroups->expects($this->once())->method('delete')->with(5);
        $this->expectException(\PDOException::class);
        $this->service->create($this->token, ['schemas' => [Schema::GROUP], 'displayname' => 'Sales']);
    }

    public function testDisplayNameSetToNull(): void
    {
        $this->groups->method('find')->willReturn(self::group());
        // RFC 7644 3.5.2.2: a required attribute that becomes unassigned
        $this->expectScimError(400, 'mutability', fn() => $this->service->patch($this->token, 'g-1',
            self::patch(['op' => 'replace', 'path' => 'displayName', 'value' => null])));
    }

    public function testAnotherTokensExternalIdIsNeitherShownNorAConflict(): void
    {
        $group = self::group();
        $group->tokenId = 8;
        $this->groups->method('find')->willReturn($group);
        $this->assertNull($this->service->get($this->token, 'g-1')->externalId);
        $this->groups->expects($this->once())->method('externalIdTaken')->with('ext-g', 7, 5)->willReturn(false);
        $this->service->patch($this->token, 'g-1', self::patch(['op' => 'replace', 'path' => 'externalId', 'value' => 'ext-g']));
    }

    public function testSkippedUsernamesAreNotKeptInTheIdpList(): void
    {
        // a@ was renamed meanwhile: syncGroupMembers skips it, the list must not keep the stale name
        $this->groups->method('find')->willReturn(self::group([]));
        $this->idp->method('syncGroupMembers')->willReturn(['added' => [], 'removed' => [], 'skipped' => ['a@example.org'], 'warnings' => []]);
        $this->groups->expects($this->once())->method('setMembers')->with(5, []);
        $this->service->replace($this->token, 'g-1', ['schemas' => [Schema::GROUP], 'displayname' => 'Sales', 'members' => [['value' => 'u-1']]]);
    }

    public function testAFailedMemberWriteRemovesTheNewGroup(): void
    {
        $this->mailboxGroups->method('create')->willReturn(MailboxGroup::fromRow(['id' => 5, 'domain' => 'example.org', 'name' => 'Sales']));
        $this->idp->method('syncGroupMembers')->willThrowException(new \PDOException('gone'));
        $this->mailboxGroups->expects($this->once())->method('delete')->with(5);
        $this->expectException(\PDOException::class);
        $this->service->create($this->token, ['schemas' => [Schema::GROUP], 'displayname' => 'Sales', 'members' => [['value' => 'u-1']]]);
    }

    public function testADeletedGroupComesBackUnderANewName(): void
    {
        // found by token and externalId, then renamed
        $this->groups->method('deletedGroupOf')->with(7, 'ext-g', 'example.org')->willReturn(5);
        $this->groups->expects($this->never())->method('deletedGroup');
        $this->groups->method('relink')->willReturn(true);
        $this->groups->method('find')->willReturn(self::group());
        $this->idp->expects($this->once())->method('renameFromIdp')->with(5, 'Marketing');
        $this->mailboxGroups->expects($this->never())->method('create');
        $this->service->create($this->token, ['schemas' => [Schema::GROUP], 'displayname' => 'Marketing', 'externalid' => 'ext-g']);
    }

    public function testRemovingAMemberLocksAllItsGroupsFirst(): void
    {
        $this->groups = $this->createMock(GroupRepository::class);
        $this->groups->method('groupsAssigning')->willReturn([6 => ['a@example.org'], 5 => ['a@example.org']]);
        $this->groups->expects($this->once())->method('lockedAll')->with([6, 5])->willThrowException(new ScimError(503, 'busy'));
        // a busy group fails the call before any group changed
        $this->idp->expects($this->never())->method('syncGroupMembers');
        $service = new GroupService($this->groups, $this->users, $this->mailboxGroups, $this->idp);
        $this->expectScimError(503, null, fn() => $service->removeMember('a@example.org'));
    }

    public function testALeavingUserGetsItsMappedTemplateBack(): void
    {
        $legacy = $this->createMock(\Mailcow\Scim\Legacy\LegacyMailboxes::class);
        $templates = $this->createMock(\Mailcow\MailboxGroup\Service\TemplateService::class);
        $service = new GroupService($this->groups, $this->users, $this->mailboxGroups, $this->idp, $legacy, $templates);
        $this->groups->method('find')->willReturn(self::group());
        $this->idp->method('syncGroupMembers')->willReturn(['added' => [], 'removed' => ['b@example.org'], 'skipped' => [], 'warnings' => []]);
        // b@ had the group template; no group template remains, the mapped one comes back (the identity sync converges so)
        $this->users->method('templateStates')->with(['b@example.org'])->willReturn([
            'b@example.org' => ['mapped' => 'Staff', 'applied' => 'Group template', 'name' => 'B'],
        ]);
        $this->users->method('templateExists')->willReturn(true);
        $templates->method('templateForLogin')->with('b@example.org', 'Staff')->willReturn('Staff');
        $legacy->expects($this->once())->method('applyTemplate')->with('b@example.org', 'B', 'Staff')->willReturn(null);
        $service->patch($this->token, 'g-1', self::patch(['op' => 'remove', 'path' => 'members[value eq "u-2"]']));
    }

    public function testATemplateAlreadyAppliedIsNotAppliedAgain(): void
    {
        $legacy = $this->createMock(\Mailcow\Scim\Legacy\LegacyMailboxes::class);
        $service = new GroupService($this->groups, $this->users, $this->mailboxGroups, $this->idp, $legacy);
        $this->groups->method('find')->willReturn(self::group());
        $this->idp->method('syncGroupMembers')->willReturn(['added' => [], 'removed' => ['b@example.org'], 'skipped' => [], 'warnings' => []]);
        $this->users->method('templateStates')->willReturn(['b@example.org' => ['mapped' => 'Staff', 'applied' => 'Staff', 'name' => 'B']]);
        $legacy->expects($this->never())->method('applyTemplate');
        $service->patch($this->token, 'g-1', self::patch(['op' => 'remove', 'path' => 'members[value eq "u-2"]']));
    }

    public function testAnEmptyExternalIdIsNone(): void
    {
        $this->mailboxGroups->method('create')->willReturn(MailboxGroup::fromRow(['id' => 5, 'domain' => 'example.org', 'name' => 'Sales']));
        $this->groups->method('find')->willReturn(self::group());
        $this->groups->expects($this->never())->method('externalIdTaken');
        $this->groups->expects($this->once())->method('link')->with($this->anything(), 5, null, 7);
        $this->service->create($this->token, ['schemas' => [Schema::GROUP], 'displayname' => 'Sales', 'externalid' => ' ']);
    }

    public function testDeleteKeepsTheMailboxGroup(): void
    {
        $this->groups->method('find')->willReturn(self::group());
        $this->idp->expects($this->once())->method('syncGroupMembers')->with(5, []);
        $this->groups->expects($this->once())->method('unlink')->with(5);
        $this->mailboxGroups->expects($this->never())->method('delete');
        $this->service->delete($this->token, 'g-1');
    }

    public function testMailboxRenameFollowsInTheMemberLists(): void
    {
        // the member rows already carry the new name; the list is read locked (current, not the rename's snapshot)
        $this->groups->expects($this->once())->method('groupsAssigning')->with('a@example.org', 'z@example.org', null, true)
            ->willReturn([5 => ['a@example.org', 'b@example.org']]);
        $this->groups->expects($this->once())->method('setMembers')->with(5, ['z@example.org', 'b@example.org']);
        $this->idp->expects($this->never())->method('syncGroupMembers');
        $this->service->onMailboxRenamed('a@example.org', 'z@example.org');
    }

    public function testRemoveMemberFromAllScimGroups(): void
    {
        // x@ is still in the IdP list of group 5, but was removed from the mailbox group in mailcow (mailbox deleted)
        $lists = [5 => ['a@example.org', 'b@example.org', 'x@example.org'], 6 => ['a@example.org']];
        // all groups once for the ids, then each group alone under its lock
        $this->groups->method('groupsAssigning')->willReturnCallback(fn(string $username, ?string $as = null, ?int $group = null): array
            => ($group === null) ? $lists : [$group => $lists[$group]]);
        $this->groups->method('memberUsernames')->willReturnMap([[5, ['a@example.org', 'b@example.org']], [6, ['a@example.org']]]);
        $calls = [];
        $this->idp->method('syncGroupMembers')->willReturnCallback(function (int $id, array $usernames) use (&$calls): array {
            $calls[$id] = $usernames;
            return [];
        });
        $this->service->removeMember('a@example.org');
        $this->assertSame([5 => ['b@example.org'], 6 => []], $calls);
    }
}
