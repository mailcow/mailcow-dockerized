<?php

declare(strict_types=1);

namespace Mailcow\Tests\Identity\Service;

use Mailcow\Identity\Model\Account;
use Mailcow\Identity\Model\DirectoryGroup;
use Mailcow\Identity\Model\DirectoryUser;
use Mailcow\Identity\Model\Listing;
use Mailcow\Identity\Model\Settings;
use Mailcow\Identity\Model\State;
use Mailcow\Identity\Service\SyncPlanner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SyncPlannerTest extends TestCase
{
    private const SINCE = '2026-10-25 10:00:00';

    public function testUnlinkedMailboxIsAdoptedByEmail(): void
    {
        $plan = $this->plan([self::user('u1', 'a@example.org')], [new Account('a@example.org', 'A', 1)]);

        $this->assertSame(['a@example.org' => 'u1'], $plan->links);
        $this->assertSame([], $plan->conflicts);
    }

    public function testLinkedUserWithNewEmailIsAConflictAndNotCreated(): void
    {
        $plan = $this->plan([self::user('u1', 'new@example.org')], [new Account('a@example.org', 'A', 1, 'u1')]);

        $this->assertSame([], $plan->creates);
        $this->assertSame([], $plan->missing);
        $this->assertSame(['a@example.org is linked to an IdP user whose email is now new@example.org'], $plan->conflicts);
    }

    public function testMailboxLinkedToAnotherUserIsNotTakenOver(): void
    {
        $plan = $this->plan([self::user('u2', 'a@example.org')], [new Account('a@example.org', 'A', 1, 'u1', false, self::SINCE)]);

        $this->assertSame([], $plan->links);
        $this->assertSame(['a@example.org is linked to another IdP user'], $plan->conflicts);
        // u1 is gone for the second run: login disabled
        $this->assertSame(['a@example.org'], $plan->deactivate);
    }

    public function testCreatesOnlyEnabledUsersOfMailcowDomainsWithTemplate(): void
    {
        $settings = ['mappers' => ['staff'], 'templates' => ['Staff']];
        $users = [
            self::user('u1', 'new@example.org', true, ['staff'], 'New User'),
            self::user('u2', 'off@example.org', false, ['staff']),
            self::user('u3', 'x@foreign.org', true, ['staff']),
            self::user('u4', 'local@example.org', true, ['staff']),
            self::user('u5', 'none@example.org'),
            self::user('u6', null),
        ];
        $plan = $this->plan($users, [], $settings, ['mailboxes' => ['local@example.org' => 'mailcow']]);

        $this->assertSame([['email' => 'new@example.org', 'name' => 'New User', 'template' => 'Staff', 'id' => 'u1']], $plan->creates);
        $this->assertSame(['No template matches none@example.org'], $plan->conflicts);
        $this->assertSame(4, $plan->skipped);
    }

    public function testNothingIsCreatedWithoutImport(): void
    {
        $plan = $this->plan([self::user('u1', 'new@example.org')], [], ['import_users' => 0, 'default_template' => 'Default']);

        $this->assertSame([], $plan->creates);
    }

    public function testDuplicateEmailsAreAConflict(): void
    {
        $plan = $this->plan([self::user('u1', 'a@example.org'), self::user('u2', 'a@example.org')], [], ['default_template' => 'Default']);

        $this->assertSame([], $plan->creates);
        $this->assertSame(['Several IdP users have the email a@example.org'], $plan->conflicts);
    }

    public function testNewMailboxGetsTheTemplateOfItsHighestPriorityGroup(): void
    {
        $groups = [self::group(1, 'g1', 'Sales', [], 'Sales', 5), self::group(2, 'g2', 'Board', [], 'Board', 10)];
        $listing = [new DirectoryGroup('g1', 'Sales', null, ['u1']), new DirectoryGroup('g2', 'Board', null, ['u1'])];
        $plan = $this->plan([self::user('u1', 'new@example.org', true, ['staff'])], [],
            ['group_sync' => 1, 'mappers' => ['staff'], 'templates' => ['Staff']], ['groups' => $groups], $listing);

        $this->assertSame('Board', $plan->creates[0]['template']);
        $this->assertSame(['g1' => ['new@example.org'], 'g2' => ['new@example.org']], $plan->memberships);
    }

    /**
     * @param ?bool $listed null = not listed, else enabled in the IdP
     */
    #[DataProvider('offboardingCases')]
    public function testOffboardingStates(int $active, bool $idpDisabled, ?string $missingSince, ?bool $listed, bool $offboarding, array $expected): void
    {
        $users = ($listed === null) ? [] : [self::user('u1', 'a@example.org', $listed)];
        $plan = $this->plan($users, [new Account('a@example.org', 'A', $active, 'u1', $idpDisabled, $missingSince)],
            ['offboarding' => $offboarding ? 1 : 0]);

        foreach (['deactivate', 'restore', 'release', 'missing', 'found'] as $kind) {
            $this->assertSame(in_array($kind, $expected, true) ? ['a@example.org'] : [], $plan->$kind, $kind);
        }
    }

    public static function offboardingCases(): array
    {
        return [
            'disabled in the IdP' => [1, false, null, false, true, ['deactivate']],
            'activated by an admin after the sync disabled it' => [1, true, null, false, true, []],
            'disabled by an admin' => [2, false, null, false, true, []],
            'inactive' => [0, false, null, false, true, []],
            'enabled again' => [2, true, null, true, true, ['restore']],
            'enabled again, active changed by an admin' => [0, true, null, true, true, ['release']],
            'not listed for the first time' => [1, false, null, null, true, ['missing']],
            'not listed for the second time' => [1, false, self::SINCE, null, true, ['deactivate']],
            'listed again' => [1, false, self::SINCE, true, true, ['found']],
            'listed again after removal' => [2, true, self::SINCE, true, true, ['found', 'restore']],
            'offboarding off' => [1, false, null, false, false, []],
            'offboarding off tracks missing users' => [1, false, null, null, false, ['missing']],
        ];
    }

    public function testGuardBlocksMassDeactivationUnlessForced(): void
    {
        $accounts = [];
        $users = [];
        for ($i = 1; $i <= 6; $i++) {
            $accounts[] = new Account("u$i@example.org", '', 1, "u$i");
            $users[] = self::user("u$i", "u$i@example.org", $i > 5);
        }
        $this->assertFalse($this->plan($users, $accounts)->blocked);

        $users[5] = self::user('u6', 'u6@example.org', false);
        $this->assertTrue($this->plan($users, $accounts)->blocked);
        $this->assertFalse($this->plan($users, $accounts, [], [], null, true)->blocked);
    }

    public function testGuardCountsMembershipsOfOrphanedGroups(): void
    {
        $members = [];
        for ($i = 1; $i <= 6; $i++) {
            $members["u$i@example.org"] = 'idp';
        }
        $plan = $this->plan([], [], ['group_sync' => 1], ['groups' => [self::group(3, 'gone', 'Old', $members)]], []);

        $this->assertSame([3], $plan->orphanedGroups);
        $this->assertSame(6, $plan->membershipRemovals);
        $this->assertTrue($plan->blocked);
    }

    public function testGroupMembershipsFollowTheIdp(): void
    {
        $accounts = [new Account('a@example.org', '', 1, 'u1'), new Account('b@example.org', '', 1, 'u2'),
            new Account('c@example.org', '', 1, 'u3'), new Account('gone@example.org', '', 1, 'u4')];
        $members = ['a@example.org' => 'manual', 'b@example.org' => 'idp', 'gone@example.org' => 'idp'];
        $plan = $this->plan([self::user('u1', 'a@example.org'), self::user('u2', 'b@example.org'), self::user('u3', 'c@example.org')],
            $accounts, ['group_sync' => 1], ['groups' => [self::group(1, 'g1', 'Sales', $members)]],
            [new DirectoryGroup('g1', 'Sales', null, ['u1', 'u3'])]);

        // b leaves, c joins, a stays manual, gone@ is not listed and keeps its membership
        $this->assertSame(['g1' => ['a@example.org', 'c@example.org', 'gone@example.org']], $plan->memberships);
        $this->assertSame(1, $plan->membershipRemovals);
    }

    public function testUnchangedMembershipsArePlannedAsNothing(): void
    {
        $plan = $this->plan([self::user('u1', 'a@example.org')], [new Account('a@example.org', '', 1, 'u1')], ['group_sync' => 1],
            ['groups' => [self::group(1, 'g1', 'Sales', ['a@example.org' => 'manual'])]], [new DirectoryGroup('g1', 'Sales', null, ['u1'])]);

        $this->assertSame([], $plan->memberships);
    }

    public function testNewGroupsGoToTheDomainOfTheirMailOrTheDefaultDomain(): void
    {
        $groups = [
            new DirectoryGroup('g1', 'Sales', 'sales@sub.example.org'),
            new DirectoryGroup('g2', 'Staff', 'staff@foreign.org'),
            new DirectoryGroup('g3', 'Board'),
            new DirectoryGroup('g4', 'Support'),
        ];
        $state = ['domains' => ['example.org', 'sub.example.org'], 'groups' => [self::group(9, null, 'support', [])]];
        $plan = $this->plan([], [], ['group_sync' => 1, 'group_autocreate' => 1, 'group_default_domain' => 'example.org'], $state, $groups);

        $this->assertSame([
            ['ref' => 'g1', 'name' => 'Sales', 'domain' => 'sub.example.org', 'mail' => 'sales@sub.example.org'],
            ['ref' => 'g2', 'name' => 'Staff', 'domain' => 'example.org', 'mail' => 'staff@foreign.org'],
            ['ref' => 'g3', 'name' => 'Board', 'domain' => 'example.org', 'mail' => null],
        ], $plan->groupCreates);
        $this->assertSame(['A group Support exists in example.org and is not linked to the IdP group'], $plan->conflicts);

        $plan = $this->plan([], [], ['group_sync' => 1, 'group_autocreate' => 1], $state, [new DirectoryGroup('g3', 'Board')]);
        $this->assertSame(['IdP group Board has no mailcow domain'], $plan->conflicts);

        $plan = $this->plan([], [], ['group_sync' => 1, 'group_autocreate' => 0, 'group_default_domain' => 'example.org'], $state, $groups);
        $this->assertSame([], $plan->groupCreates);
    }

    public function testRenamedIdpGroupsAreRenamedUnlessTheNameIsTaken(): void
    {
        $state = ['groups' => [self::group(1, 'g1', 'Sales', []), self::group(2, 'g2', 'Support', []), self::group(3, null, 'Board', [])]];
        $plan = $this->plan([], [], ['group_sync' => 1], $state,
            [new DirectoryGroup('g1', 'Sales DE'), new DirectoryGroup('g2', 'board')]);

        $this->assertSame([1 => 'Sales DE'], $plan->groupRenames);
        $this->assertSame(['IdP group Support was renamed to board, which exists in example.org'], $plan->conflicts);
    }

    public function testUpdatesNameAndTemplateWhenTheyDiffer(): void
    {
        $settings = ['periodic_sync' => 1, 'mappers' => ['staff'], 'templates' => ['Staff']];
        $accounts = [new Account('a@example.org', 'A', 1, 'u1', false, null, 'h-staff'),
            new Account('b@example.org', 'B', 1, 'u2', false, null, 'h-old'),
            new Account('c@example.org', 'C', 1, 'u3', false, null, 'h-staff'),
            new Account('d@example.org', 'D', 1, 'u4', false, null, 'h-staff')];
        $users = [self::user('u1', 'a@example.org', true, ['staff'], 'A'), self::user('u2', 'b@example.org', true, ['staff'], 'B'),
            self::user('u3', 'c@example.org', true, ['staff'], 'C New'), self::user('u4', 'd@example.org', true, ['staff'])];
        $state = ['hashes' => ['Staff' => 'h-staff', 'Sales' => 'h-sales'], 'effective' => ['a@example.org' => 'Sales']];
        $plan = $this->plan($users, $accounts, $settings, $state);

        $this->assertSame([
            ['username' => 'a@example.org', 'name' => 'A', 'template' => 'Staff'],
            ['username' => 'b@example.org', 'name' => 'B', 'template' => 'Staff'],
            ['username' => 'c@example.org', 'name' => 'C New', 'template' => 'Staff'],
        ], $plan->updates);
    }

    private function plan(array $users, array $accounts, array $settings = [], array $state = [], ?array $groups = null, bool $force = false)
    {
        $byId = [];
        foreach ($users as $user) {
            $byId[$user->id] = $user;
        }
        $groupsById = null;
        if ($groups !== null) {
            $groupsById = [];
            foreach ($groups as $group) {
                $groupsById[$group->id] = $group;
            }
        }
        $byName = [];
        foreach ($accounts as $account) {
            $byName[$account->username] = $account;
        }
        return (new SyncPlanner())->plan(
            Settings::fromArray($settings + ['authsource' => 'keycloak', 'offboarding' => 1, 'import_users' => 1]),
            new Listing($byId, $groupsById),
            new State($byName, array_fill_keys(array_keys($byName), 'keycloak') + ($state['mailboxes'] ?? []),
                $state['domains'] ?? ['example.org'], $state['groups'] ?? [], $state['effective'] ?? [], $state['hashes'] ?? []),
            $force);
    }

    private static function user(string $id, ?string $email, bool $enabled = true, array $templates = [], string $name = ''): DirectoryUser
    {
        return new DirectoryUser($id, $email, $name, $enabled, $templates);
    }

    private static function group(int $id, ?string $ref, string $name, array $members, ?string $template = null, int $priority = 0): array
    {
        return ['id' => $id, 'domain' => 'example.org', 'name' => $name, 'idp_ref' => $ref, 'template' => $template,
            'priority' => $priority, 'active' => 1, 'members' => $members];
    }
}
