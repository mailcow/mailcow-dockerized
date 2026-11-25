<?php

declare(strict_types=1);

namespace Mailcow\Tests\MailboxGroup\Service;

use Mailcow\MailboxGroup\Model\Group;
use Mailcow\MailboxGroup\Repository\GroupRepository;
use Mailcow\MailboxGroup\Repository\LookupRepository;
use Mailcow\MailboxGroup\Service\ChangeEffects;
use Mailcow\MailboxGroup\Service\IdpSyncService;
use PHPUnit\Framework\TestCase;

class IdpSyncServiceTest extends TestCase
{
    public function testOnlyIdpMembershipsAreReplaced(): void
    {
        $group = Group::fromRow(['id' => 7, 'domain' => 'example.org', 'name' => 'Sales']);
        $group->members = ['a@example.org' => 'manual', 'b@example.org' => 'idp', 'c@example.org' => 'idp'];
        $repo = $this->createMock(GroupRepository::class);
        $repo->method('find')->with(7)->willReturn($group);
        $repo->expects($this->once())->method('update')->with($this->callback(fn(Group $g): bool =>
            $g->members === ['a@example.org' => 'manual', 'c@example.org' => 'idp', 'd@example.org' => 'idp']))->willReturn([]);
        $lookup = $this->createMock(LookupRepository::class);
        $lookup->method('regularMailboxes')->willReturnCallback(fn(array $u): array => array_values(array_diff($u, ['room@example.org'])));
        $effects = $this->createMock(ChangeEffects::class);
        $effects->method('apply')->willReturn([]);

        $result = (new IdpSyncService($repo, $lookup, $effects))
            ->syncGroupMembers(7, ['A@example.org', 'c@example.org', 'd@example.org', 'room@example.org']);

        $this->assertSame(['d@example.org'], $result['added']);
        $this->assertSame(['b@example.org'], $result['removed']);
        $this->assertSame(['room@example.org'], $result['skipped']);
    }

    public function testNothingIsWrittenWithoutChanges(): void
    {
        $group = Group::fromRow(['id' => 7]);
        $group->members = ['a@example.org' => 'idp'];
        $repo = $this->createMock(GroupRepository::class);
        $repo->method('find')->willReturn($group);
        $repo->expects($this->never())->method('update');
        $lookup = $this->createMock(LookupRepository::class);
        $lookup->method('regularMailboxes')->willReturn(['a@example.org']);

        (new IdpSyncService($repo, $lookup, $this->createMock(ChangeEffects::class)))->syncGroupMembers(7, ['a@example.org']);
    }
}
