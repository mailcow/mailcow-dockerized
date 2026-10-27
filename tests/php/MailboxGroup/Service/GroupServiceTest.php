<?php

declare(strict_types=1);

namespace Mailcow\Tests\MailboxGroup\Service;

use Mailcow\Core\Context;
use Mailcow\Core\UserError;
use Mailcow\MailboxGroup\Model\Group;
use Mailcow\MailboxGroup\Repository\GroupRepository;
use Mailcow\MailboxGroup\Repository\LookupRepository;
use Mailcow\MailboxGroup\Service\ChangeEffects;
use Mailcow\MailboxGroup\Service\GroupService;
use Mailcow\MailboxGroup\Service\SharingService;
use Mailcow\MailboxGroup\Service\TemplateService;
use Mailcow\Tests\Support\FakeContext;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GroupServiceTest extends TestCase
{
    private const ACL = ['mailbox_groups' => '1'];

    private GroupRepository&MockObject $repo;
    private LookupRepository&MockObject $lookup;
    private TemplateService&MockObject $templates;
    private ChangeEffects&MockObject $effects;
    private SharingService&MockObject $sharing;

    protected function setUp(): void
    {
        $this->repo = $this->createMock(GroupRepository::class);
        $this->lookup = $this->createMock(LookupRepository::class);
        $this->templates = $this->createMock(TemplateService::class);
        $this->effects = $this->createMock(ChangeEffects::class);
        $this->effects->method('snapshot')->willReturn([]);
        $this->effects->method('apply')->willReturn([]);
        $this->sharing = $this->createMock(SharingService::class);
        $this->sharing->method('applyImap')->willReturn([]);
        $this->sharing->method('applySogo')->willReturn([]);
        $this->lookup->method('isDomain')->willReturnCallback(fn(string $d): bool => in_array($d, ['example.org', 'other.org'], true));
        $this->lookup->method('isDomainOrAliasDomain')->willReturnCallback(fn(string $d): bool => in_array($d, ['example.org', 'other.org', 'alias.org'], true));
        $this->lookup->method('aliasExists')->willReturnCallback(fn(string $a): bool => str_starts_with($a, 'shared@') || str_starts_with($a, 'a@'));
        $this->lookup->method('regularMailboxes')->willReturnCallback(fn(array $u): array => array_values(array_filter($u, fn(string $x): bool => !str_starts_with($x, 'room'))));
        $this->lookup->method('aliasesLeft')->willReturn(10);
        $this->lookup->method('templateName')->willReturnCallback(fn(int $id): ?string => $id === 3 ? 'Staff' : null);
    }

    private function service(Context $context): GroupService
    {
        return new GroupService($this->repo, $this->lookup, $this->templates, $this->effects, $this->sharing, $context);
    }

    private static function admin(): FakeContext
    {
        return FakeContext::admin(self::ACL);
    }

    /** Domain admin of example.org with its mailboxes a@ and b@ */
    private static function domainAdmin(array $acl = self::ACL): FakeContext
    {
        return FakeContext::domainAdmin(['a@example.org', 'b@example.org'], ['example.org'], $acl);
    }

    private static function stored(array $row = [], array $members = ['a@example.org' => 'manual'], array $sendAs = []): Group
    {
        $group = Group::fromRow($row + ['id' => 7, 'domain' => 'example.org', 'name' => 'Sales', 'address' => 'sales@example.org',
            'sender_allowed' => 1, 'active' => 1, 'priority' => 0]);
        $group->members = $members;
        $group->sendAs = $sendAs;
        return $group;
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

    public static function deniedEditors(): array
    {
        return [
            'user' => [FakeContext::user('a@example.org', self::ACL)],
            'domain admin without ACL' => [self::domainAdmin([])],
            'read-only API key' => [new Context('admin', 'API', self::ACL, false, 'ro')],
            'system' => [Context::system()],
        ];
    }

    #[DataProvider('deniedEditors')]
    public function testOnlyAdminsAndDomainAdminsWithAclMayWrite(Context $context): void
    {
        $this->repo->expects($this->never())->method('insert');

        $this->expectUserError('access_denied', fn() => $this->service($context)->create(['domain' => 'example.org', 'name' => 'X']));
    }

    public function testReadOnlyApiKeyMayRead(): void
    {
        $this->repo->expects($this->once())->method('visibleIds')->willReturn([7]);

        $this->assertSame([7], $this->service(new Context('admin', 'API', self::ACL, false, 'ro'))->visibleIds());
    }

    public function testCreateNormalisesInput(): void
    {
        $this->repo->expects($this->once())->method('insert')->with($this->callback(function (Group $g): bool {
            $this->assertSame('example.org', $g->domain);
            $this->assertSame('Sales', $g->name);
            $this->assertSame('sales@example.org', $g->address);
            $this->assertSame(['a@example.org' => 'manual', 'b@example.org' => 'manual'], $g->members);
            $this->assertSame(['@alias.org', 'shared@example.org'], $g->sendAs);
            $this->assertSame([1, 0, 1, 1], [$g->senderAllowed, $g->sogoVisible, $g->internal, $g->active]);
            $this->assertSame([3, 'Staff', 5], [$g->templateId, $g->template, $g->priority]);
            return true;
        }))->willReturn([]);

        $group = $this->service(self::admin())->create([
            'domain' => ' Example.ORG ', 'name' => ' Sales ', 'local_part' => 'Sales',
            'members' => "B@example.org\na@example.org, a@example.org", 'send_as' => ['Shared@Example.org', '@alias.org'],
            'sender_allowed' => ['0', '1'], 'sogo_visible' => '0', 'internal' => 'true',
            'template_id' => '3', 'priority' => '5',
        ]);

        $this->assertSame([], $group->warnings);
    }

    public static function invalidInput(): array
    {
        return [
            'empty name' => [['name' => ' '], 'group_name_invalid'],
            'long name' => [['name' => str_repeat('x', 101)], 'group_name_invalid'],
            'long description' => [['description' => str_repeat('x', 256)], 'group_description_invalid'],
            'unknown domain' => [['domain' => 'nope.org'], ['domain_not_found', 'nope.org']],
            'address in other domain' => [['address' => 'sales@other.org'], ['group_address_invalid', 'sales@other.org']],
            'invalid address' => [['local_part' => 'a b'], ['group_address_invalid', 'a b@example.org']],
            'member is a resource' => [['members' => ['room1@example.org']], ['group_member_invalid', 'room1@example.org']],
            'unknown send-as address' => [['send_as' => 'nobody@example.org'], ['group_send_as_invalid', 'nobody@example.org']],
            'send-as on own address' => [['local_part' => 'shared', 'send_as' => 'shared@example.org'], ['group_send_as_invalid', 'shared@example.org']],
            'send-as on unknown domain' => [['send_as' => '@nope.org'], ['group_send_as_invalid', '@nope.org']],
            'unknown template' => [['template_id' => '4'], ['template_id_invalid', '4']],
            'invalid priority' => [['priority' => 'high'], 'group_priority_invalid'],
        ];
    }

    #[DataProvider('invalidInput')]
    public function testCreateRejectsInvalidInput(array $input, string|array $msg): void
    {
        $this->repo->expects($this->never())->method('insert');

        $this->expectUserError($msg, fn() => $this->service(self::admin())->create($input + ['domain' => 'example.org', 'name' => 'Sales']));
    }

    public function testAddressMustBeFreeAndWithinQuota(): void
    {
        $lookup = $this->createMock(LookupRepository::class);
        $lookup->method('isDomain')->willReturn(true);
        $lookup->method('addressTaken')->willReturnOnConsecutiveCalls(true, false);
        $lookup->method('aliasesLeft')->willReturn(0);
        $this->lookup = $lookup;
        $this->repo->expects($this->never())->method('insert');
        $service = $this->service(self::admin());

        $this->expectUserError(['is_alias_or_mailbox', 'sales@example.org'], fn() => $service->create(['domain' => 'example.org', 'name' => 'S', 'local_part' => 'sales']));
        $this->expectUserError('max_alias_exceeded', fn() => $service->create(['domain' => 'example.org', 'name' => 'S', 'local_part' => 'sales']));
    }

    public function testNameIsUniquePerDomain(): void
    {
        $this->repo->method('nameExists')->willReturn(true);

        $this->expectUserError(['object_exists', 'Sales'], fn() => $this->service(self::admin())->create(['domain' => 'example.org', 'name' => 'Sales']));
    }

    public function testDomainAdminScope(): void
    {
        $service = $this->service(self::domainAdmin());

        $this->expectUserError('access_denied', fn() => $service->create(['domain' => 'other.org', 'name' => 'X']));
        $this->expectUserError('access_denied', fn() => $service->create(['domain' => 'example.org', 'name' => 'X', 'members' => ['c@other.org']]));
        $this->expectUserError('access_denied', fn() => $service->create(['domain' => 'example.org', 'name' => 'X', 'send_as' => '@other.org']));
    }

    public function testAdminFieldsAreIgnoredForDomainAdmins(): void
    {
        $this->repo->expects($this->once())->method('insert')->with($this->callback(fn(Group $g): bool =>
            $g->templateId === null && $g->priority === 0 && $g->idpRef === null))->willReturn([]);

        $this->service(self::domainAdmin())->create(['domain' => 'example.org', 'name' => 'X', 'template_id' => 3, 'priority' => 9, 'idp_ref' => 'x']);
    }

    public function testReplacingMembersKeepsIdpAndOutOfScopeMembers(): void
    {
        $this->repo->method('findVisible')->willReturn(self::stored([], [
            'a@example.org' => 'manual', 'c@other.org' => 'manual', 'd@example.org' => 'idp',
        ]));
        $this->repo->expects($this->once())->method('update')->with($this->callback(fn(Group $g): bool =>
            $g->members === ['b@example.org' => 'manual', 'c@other.org' => 'manual', 'd@example.org' => 'idp']))->willReturn([]);

        $this->service(self::domainAdmin())->update(7, ['members' => ['b@example.org']]);
    }

    public function testIdpMembersCannotBeRemoved(): void
    {
        $this->repo->method('findVisible')->willReturn(self::stored([], ['a@example.org' => 'idp']));
        $this->repo->expects($this->never())->method('update');

        $this->expectUserError(['group_member_idp', 'a@example.org'], fn() => $this->service(self::admin())->update(7, ['remove_members' => 'a@example.org']));
    }

    public function testUpdateKeepsMissingAttributes(): void
    {
        $stored = self::stored(['description' => 'Team', 'template_id' => 3, 'priority' => 4], ['a@example.org' => 'manual'], ['shared@example.org']);
        $this->repo->method('findVisible')->willReturn($stored);
        $this->repo->expects($this->once())->method('update')->with($this->callback(function (Group $g): bool {
            $this->assertSame(['Team', 'sales@example.org', 0, 3, 4], [$g->description, $g->address, $g->active, $g->templateId, $g->priority]);
            $this->assertSame(['a@example.org' => 'manual'], $g->members);
            $this->assertSame(['shared@example.org'], $g->sendAs);
            return true;
        }))->willReturn([]);

        $this->service(self::admin())->update(7, ['active' => '0']);
    }

    public function testRemovingTheAddressFreesIt(): void
    {
        $this->repo->method('findVisible')->willReturn(self::stored());
        $this->repo->expects($this->once())->method('update')->with($this->callback(fn(Group $g): bool => $g->address === null))->willReturn([]);

        $this->service(self::admin())->update(7, ['address' => '']);
    }

    public function testForeignSendAsMakesAGroupReadOnlyForDomainAdmins(): void
    {
        $group = self::stored([], ['a@example.org' => 'manual'], ['@other.org']);
        $this->repo->method('findVisible')->willReturn($group);
        $this->repo->expects($this->never())->method('update');
        $service = $this->service(self::domainAdmin());

        $this->assertFalse($service->toArray($group)['can_edit']);
        $this->expectUserError('access_denied', fn() => $service->update(7, ['name' => 'X']));
    }

    public function testGrantsAreNormalisedAndApplied(): void
    {
        $this->repo->method('findVisible')->willReturn(self::stored());
        $this->repo->expects($this->once())->method('update')->with($this->callback(function (Group $g): bool {
            $this->assertSame([['owner' => 'a@example.org', 'rights' => 'full', 'subscribe' => 1], ['owner' => 'b@example.org', 'rights' => 'read', 'subscribe' => 0]],
                $g->imapGrants);
            $this->assertSame([['owner' => 'a@example.org', 'type' => 'Calendar', 'folder' => 'personal', 'access' => 'write', 'subscribe' => 1],
                ['owner' => 'a@example.org', 'type' => 'Contacts', 'folder' => 'team-1', 'access' => 'read', 'subscribe' => 0]], $g->sogoGrants);
            return true;
        }))->willReturn([]);
        $this->sharing = $this->createMock(SharingService::class);
        $this->sharing->expects($this->once())->method('applyImap')->with(7)->willReturn([['group_imap_grant_failed', 'b@example.org']]);
        $this->sharing->expects($this->once())->method('applySogo')->with(7)->willReturn([]);

        $group = $this->service(self::admin())->update(7, [
            // a form sends JSON strings, an API client lists
            'imap_grants' => '[{"owner":"B@example.org","rights":"read"},{"owner":"a@example.org","rights":"full","subscribe":1}]',
            'sogo_grants' => [['owner' => 'a@example.org', 'type' => 'Contacts', 'folder' => 'team-1'],
                ['owner' => 'a@example.org', 'access' => 'write', 'subscribe' => '1']],
        ]);

        $this->assertSame([['group_imap_grant_failed', 'b@example.org']], $group->warnings);
    }

    public static function invalidGrants(): array
    {
        return [
            'unknown rights' => [['imap_grants' => [['owner' => 'a@example.org', 'rights' => 'admin']]], ['group_grant_invalid', 'a@example.org']],
            'resource owner' => [['imap_grants' => [['owner' => 'room1@example.org']]], ['group_grant_invalid', 'room1@example.org']],
            'no owner' => [['sogo_grants' => [['type' => 'Calendar']]], ['group_grant_invalid', '']],
            'folder path' => [['sogo_grants' => [['owner' => 'a@example.org', 'folder' => '../x']]], ['group_grant_invalid', 'a@example.org']],
            'unknown type' => [['sogo_grants' => [['owner' => 'a@example.org', 'type' => 'Mail']]], ['group_grant_invalid', 'a@example.org']],
            'no list' => [['imap_grants' => 'a@example.org'], ['group_grant_invalid', '']],
            'foreign owner for a domain admin' => [['imap_grants' => [['owner' => 'c@other.org']]], 'access_denied'],
        ];
    }

    #[DataProvider('invalidGrants')]
    public function testInvalidGrantsAreRejected(array $input, string|array $msg): void
    {
        $this->repo->method('findVisible')->willReturn(self::stored());
        $this->repo->expects($this->never())->method('update');

        $this->expectUserError($msg, fn() => $this->service(self::domainAdmin())->update(7, $input));
    }

    public function testForeignGrantOwnersMakeAGroupReadOnlyForDomainAdmins(): void
    {
        $group = self::stored();
        $group->sogoGrants = [['owner' => 'c@other.org', 'type' => 'Calendar', 'folder' => 'personal', 'access' => 'read']];

        $this->assertFalse($this->service(self::domainAdmin())->canEdit($group));
        $this->assertTrue($this->service(self::admin())->canEdit($group));
    }

    public function testDeleteRemovesTheImapAclsFirst(): void
    {
        $group = self::stored();
        $this->repo->method('findVisible')->willReturn($group);
        $calls = [];
        $this->sharing->expects($this->once())->method('removeImap')->with($group)->willReturnCallback(function () use (&$calls): void {
            $calls[] = 'acl';
        });
        $this->repo->method('delete')->willReturnCallback(function () use (&$calls): array {
            $calls[] = 'delete';
            return [];
        });

        $this->service(self::admin())->delete(7);

        $this->assertSame(['acl', 'delete'], $calls);
    }

    public function testDeleteReportsWarningsOfTheEffects(): void
    {
        $effects = $this->createMock(ChangeEffects::class);
        $effects->expects($this->once())->method('snapshot')->with(['a@example.org'])->willReturn(['a@example.org' => 'Staff']);
        $effects->expects($this->once())->method('apply')->with(['a@example.org'], ['a@example.org' => 'Staff'])->willReturn([['w']]);
        $this->effects = $effects;
        $this->repo->method('findVisible')->willReturn(self::stored());
        $this->repo->expects($this->once())->method('delete')->with(7)->willReturn(['a@example.org']);

        $this->assertSame([['w']], $this->service(self::admin())->delete(7)->warnings);
    }

    public function testLargeDistributionGroupsWarn(): void
    {
        $members = [];
        for ($i = 0; $i < 1001; $i++) {
            $members[] = 'u' . $i . '@example.org';
        }
        $this->repo->method('insert')->willReturn([]);

        $group = $this->service(self::admin())->create(['domain' => 'example.org', 'name' => 'All', 'local_part' => 'all', 'members' => $members]);

        $this->assertSame([['group_too_many_members', 'All', 1000]], $group->warnings);
    }

    public function testMembershipsAreVisibleToTheMailboxAndItsManagers(): void
    {
        $this->lookup->method('membershipsOf')->willReturn([['id' => 7, 'name' => 'Sales']]);
        $this->lookup->method('sendAsOf')->willReturn(['sales@example.org']);
        $this->templates->method('effective')->willReturn(['a@example.org' => 'Staff']);
        $expected = ['groups' => [['id' => 7, 'name' => 'Sales']], 'template' => 'Staff', 'send_as' => ['sales@example.org']];

        $this->assertSame($expected, $this->service(FakeContext::user('a@example.org', []))->membershipsOf('A@example.org'));
        $this->assertSame($expected, $this->service(self::domainAdmin())->membershipsOf('a@example.org'));
        $this->assertSame([], $this->service(FakeContext::user('b@example.org', []))->membershipsOf('a@example.org'));
        $this->assertSame([], $this->service(self::domainAdmin())->membershipsOf('c@other.org'));
    }
}
