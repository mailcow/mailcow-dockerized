<?php

declare(strict_types=1);

namespace Mailcow\Tests\Scim\Service;

use Mailcow\Scim\Legacy\LegacyMailboxes;
use Mailcow\Scim\Model\Schema;
use Mailcow\Scim\Model\Token;
use Mailcow\Scim\Model\User;
use Mailcow\Scim\Repository\UserRepository;
use Mailcow\Scim\ScimError;
use Mailcow\Scim\Service\GroupService;
use Mailcow\Scim\Service\UserService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class UserServiceTest extends TestCase
{
    private UserRepository&MockObject $users;
    private LegacyMailboxes&MockObject $legacy;
    private GroupService&MockObject $groups;
    private UserService $service;
    private Token $token;

    protected function setUp(): void
    {
        $this->users = $this->createMock(UserRepository::class);
        $this->legacy = $this->createMock(LegacyMailboxes::class);
        $this->groups = $this->createMock(GroupService::class);
        $this->service = new UserService($this->users, $this->legacy, $this->groups);
        $this->users->method('link')->willReturn(true);
        $this->users->method('templateExists')->willReturn(true);
        // the locks just run the work
        $this->users->method('locked')->willReturnCallback(fn(string $id, callable $work): mixed => $work());
        $this->groups->method('withMemberLocks')->willReturnCallback(fn(string $username, callable $work): mixed => $work());
        $this->token = Token::fromRow(['id' => 7, 'default_template' => 'Default', 'mappers' => '["staff"]', 'templates' => '["Staff"]']);
    }

    private static function user(array $row = []): User
    {
        return User::fromRow($row + ['scim_id' => 'u-1', 'username' => 'jane@example.org', 'domain' => 'example.org',
            'name' => 'Jane Doe', 'active' => 1, 'external_id' => 'ext-1', 'token_id' => 7, 'template' => 'Staff', 'disabled' => 0]);
    }

    private static function body(array $attributes = []): array
    {
        return $attributes + ['schemas' => [Schema::USER], 'username' => 'Jane@Example.org', 'name' => ['givenname' => 'Jane', 'familyname' => 'Doe']];
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

    public function testCreateProvisionsAMailboxWithTheMappedTemplate(): void
    {
        $this->users->method('mailbox')->with('jane@example.org')->willReturn(null);
        $this->legacy->expects($this->once())->method('create')->with('jane', 'example.org', 'Jane Doe', 1, 'Staff')->willReturn(null);
        $this->users->expects($this->once())->method('link')->with($this->anything(), 'jane@example.org', 'ext-1', 7, 'Staff', 0);
        $this->users->method('find')->willReturn(self::user());

        $body = self::body(['externalid' => 'ext-1', strtolower(Schema::EXT) => ['template' => 'staff']]);
        $this->assertSame('u-1', $this->service->create($this->token, $body)->scimId);
    }

    public function testCreateWithNullActiveIsActive(): void
    {
        $this->users->method('mailbox')->willReturn(null);
        // null is unassigned (RFC 7643 2.5), so the default applies
        $this->legacy->expects($this->once())->method('create')->with('jane', 'example.org', 'Jane Doe', 1, 'Default')->willReturn(null);
        $this->users->method('find')->willReturn(self::user());
        $this->service->create($this->token, self::body(['active' => null]));
    }

    public function testCreateInactiveDisallowsTheLogin(): void
    {
        $this->users->method('mailbox')->willReturn(null);
        $this->legacy->expects($this->once())->method('create')->with('jane', 'example.org', 'Jane Doe', 2, 'Default')->willReturn(null);
        $this->users->expects($this->once())->method('link')->with($this->anything(), 'jane@example.org', null, 7, 'Default', 1);
        $this->users->method('find')->willReturn(self::user());

        $this->service->create($this->token, self::body(['active' => 'False']));
    }

    public function testCreateAdoptsAScimMailboxWithoutLink(): void
    {
        $this->users->method('mailbox')->willReturn(['authsource' => 'scim', 'linked' => false, 'tombstone' => false, 'active' => 1, 'disabled' => 0, 'external_id' => null, 'token_id' => null]);
        // a mailbox an admin handed over keeps its credentials
        $this->legacy->expects($this->never())->method('create');
        $this->legacy->expects($this->once())->method('applyTemplate')->with('jane@example.org', 'Jane Doe', 'Default')->willReturn(null);
        $this->legacy->expects($this->once())->method('edit')->with('jane@example.org', ['name' => 'Jane Doe', 'active' => 1])->willReturn(null);
        $this->users->expects($this->once())->method('link');
        $this->users->method('find')->willReturn(self::user());

        $this->service->create($this->token, self::body());
    }

    public static function conflicts(): array
    {
        return [
            'linked SCIM user' => [['authsource' => 'scim', 'linked' => true], false],
            'mailbox of another authsource' => [['authsource' => 'mailcow', 'linked' => false], false],
            'externalId of another user' => [null, true],
        ];
    }

    #[DataProvider('conflicts')]
    public function testCreateConflictsAre409(?array $mailbox, bool $externalIdTaken): void
    {
        $this->users->method('mailbox')->willReturn($mailbox);
        $this->users->method('externalIdTaken')->willReturn($externalIdTaken);
        $this->legacy->expects($this->never())->method($this->anything());
        $this->users->expects($this->never())->method('link');

        $this->expectScimError(409, 'uniqueness', fn() => $this->service->create($this->token, self::body(['externalid' => 'ext-1'])));
    }

    public function testCreateRespectsTheDomainRestriction(): void
    {
        $this->token->domainRestriction = 'example.com';
        $this->users->expects($this->never())->method('mailbox');
        $this->expectScimError(403, null, fn() => $this->service->create($this->token, self::body()));
        $this->expectScimError(400, 'invalidValue', fn() => $this->service->create($this->token, self::body(['username' => 'no-address'])));
    }

    public function testMailcowErrorsAre400(): void
    {
        $this->users->method('mailbox')->willReturn(null);
        $this->legacy->method('create')->willReturn('mailbox_quota_exceeded');
        $this->users->expects($this->never())->method('link');
        $this->expectScimError(400, 'invalidValue', fn() => $this->service->create($this->token, self::body()));
    }

    /** active: SCIM disallows logins of active mailboxes and only allows again what it disallowed; admin states win */
    public static function activeChanges(): array
    {
        return [
            'deactivate an active mailbox' => [1, 0, false, ['active' => 2], ['disabled' => 1]],
            'deactivate a mailbox an admin disabled' => [0, 0, false, null, []],
            'deactivate twice' => [2, 1, false, null, []],
            // the flag of SCIM's earlier deactivation is still set: the deactivation must not be skipped
            'deactivate a mailbox an admin enabled again' => [1, 1, false, ['active' => 2], ['disabled' => 1]],
            'activate what SCIM disabled' => [2, 1, true, ['active' => 1], ['disabled' => 0]],
            'activate a mailbox an admin enabled meanwhile' => [1, 1, true, null, ['disabled' => 0]],
            'activate a mailbox an admin disabled' => [2, 0, true, null, []],
        ];
    }

    #[DataProvider('activeChanges')]
    public function testActive(int $active, int $disabled, bool $wanted, ?array $edit, array $map): void
    {
        $this->users->method('find')->willReturn(self::user(['active' => $active, 'disabled' => $disabled]));
        if ($edit === null) {
            $this->legacy->expects($this->never())->method('edit');
        } else {
            $this->legacy->expects($this->once())->method('edit')->with('jane@example.org', $edit)->willReturn(null);
        }
        $this->users->expects($this->once())->method('update')->with('jane@example.org', $map);

        $this->service->patch($this->token, 'u-1', self::patch(['op' => 'replace', 'path' => 'active', 'value' => $wanted]));
    }

    public function testPatchIsAtomic(): void
    {
        $this->users->method('find')->willReturn(self::user());
        $this->users->method('externalIdTaken')->willReturn(true);
        $this->legacy->expects($this->never())->method($this->anything());
        $this->users->expects($this->never())->method('update');

        $this->expectScimError(409, 'uniqueness', fn() => $this->service->patch($this->token, 'u-1', self::patch(
            ['op' => 'replace', 'path' => 'displayName', 'value' => 'Jane Roe'],
            ['op' => 'replace', 'path' => 'externalId', 'value' => 'ext-2'],
        )));
    }

    public function testPatchNames(): void
    {
        $this->users->method('find')->willReturn(self::user());
        $this->legacy->expects($this->once())->method('edit')->with('jane@example.org', ['name' => 'Janet Roe'])->willReturn(null);

        // Entra ID: path-less operation with paths as keys; any order of name operations converges
        $this->service->patch($this->token, 'u-1', self::patch(
            ['op' => 'Replace', 'value' => ['name.familyname' => 'Roe', 'title' => 'ignored',
                strtolower(Schema::ENTERPRISE) => ['department' => 'ignored'], 'meta' => ['resourcetype' => 'User']]],
            ['op' => 'replace', 'path' => 'name.givenName', 'value' => 'Janet'],
            ['op' => 'Add', 'path' => Schema::ENTERPRISE . ':manager', 'value' => 'ignored'],
            ['op' => 'add', 'path' => 'emails[type eq "work"].value', 'value' => 'jane@example.org'],
        ));
    }

    /** @return array<string, array{0: array, 1: string, 2: string, 3: ?string, 4: ?array}> stored row, path, value, new name (null = unchanged), stored parts */
    public static function nameParts(): array
    {
        return [
            // Entra ID sends only the changed part
            'one part changes' => [['name' => 'Mary Ann Smith', 'given_name' => 'Mary Ann', 'family_name' => 'Smith'], 'name.familyName', 'Jones',
                'Mary Ann Jones', ['given_name' => 'Mary Ann', 'family_name' => 'Jones']],
            // the display name was not built from the parts
            'own display name stays' => [['name' => 'Smith, Mary', 'given_name' => 'Mary', 'family_name' => 'Smith'], 'name.familyName', 'Jones', null, null],
            // without stored parts a guessed family name would stick
            'unknown split is not stored' => [['name' => 'Mary Ann Smith'], 'name.givenName', 'Mary Ann', null, []],
        ];
    }

    #[DataProvider('nameParts')]
    public function testNameParts(array $stored, string $path, string $value, ?string $name, ?array $parts): void
    {
        $this->users->method('find')->willReturn(self::user($stored));
        if ($name === null) {
            $this->legacy->expects($this->never())->method('edit');
        } else {
            $this->legacy->expects($this->once())->method('edit')->with('jane@example.org', ['name' => $name])->willReturn(null);
        }
        if ($parts !== null) {
            $this->users->expects($this->once())->method('update')->with('jane@example.org', $parts);
        }
        $this->service->patch($this->token, 'u-1', self::patch(['op' => 'replace', 'path' => $path, 'value' => $value]));
    }

    public function testImmutableAttributes(): void
    {
        $this->users->method('find')->willReturn(self::user());
        $this->legacy->expects($this->never())->method($this->anything());

        $this->expectScimError(400, 'mutability', fn() => $this->service->patch($this->token, 'u-1',
            self::patch(['op' => 'replace', 'path' => 'userName', 'value' => 'john@example.org'])));
        $this->expectScimError(400, 'mutability', fn() => $this->service->patch($this->token, 'u-1',
            self::patch(['op' => 'replace', 'path' => 'emails[type eq "work"].value', 'value' => 'john@example.org'])));
        $this->expectScimError(400, 'mutability', fn() => $this->service->patch($this->token, 'u-1',
            self::patch(['op' => 'replace', 'path' => 'meta.lastModified', 'value' => '2026-01-01T00:00:00Z'])));
        $this->expectScimError(400, 'noTarget', fn() => $this->service->patch($this->token, 'u-1',
            self::patch(['op' => 'replace', 'path' => 'emails[type eq "home"].value', 'value' => 'jane@example.org'])));
        // the applied template sent back unchanged (GET-modify-PUT) is no change
        $this->users->expects($this->once())->method('update')->with('jane@example.org', []);
        $this->service->patch($this->token, 'u-1', self::patch(['op' => 'replace', 'path' => Schema::EXT . ':template', 'value' => 'Staff']));
    }

    public function testPutReplacesAndClearsOmittedAttributes(): void
    {
        $this->users->method('find')->willReturn(self::user(['active' => 2, 'disabled' => 1]));
        // active omitted: unchanged, a PUT without it must not lift an offboarding
        $this->legacy->expects($this->never())->method('edit');
        // externalId omitted: cleared; the name parts of the body are stored
        $this->users->expects($this->once())->method('update')->with('jane@example.org', ['external_id' => null, 'token_id' => 7,
            'given_name' => 'Jane', 'family_name' => 'Doe']);

        $this->service->replace($this->token, 'u-1', self::body());
    }

    public function testDeleteDisallowsTheLoginAndUnlinks(): void
    {
        $this->users->method('find')->willReturn(self::user());
        $this->groups->expects($this->once())->method('removeMember')->with('jane@example.org');
        $this->legacy->expects($this->once())->method('edit')->with('jane@example.org', ['active' => 2])->willReturn(null);
        $this->users->expects($this->once())->method('unlink')->with('jane@example.org');

        $this->service->delete($this->token, 'u-1');
    }

    public function testGroupsAreReadOnly(): void
    {
        $this->users->method('find')->willReturn(self::user());
        $this->groups->method('membershipsOf')->with($this->token, ['jane@example.org'])->willReturn(['jane@example.org' => ['g-1' => 'Sales']]);
        $resource = $this->service->get($this->token, 'u-1')->toResource('https://h/scim/v2');
        $this->assertSame([['value' => 'g-1', '$ref' => 'https://h/scim/v2/Groups/g-1', 'display' => 'Sales', 'type' => 'direct']], $resource['groups']);

        // RFC 7643 4.1.2: membership changes through the Group; an echo in a path-less replace is ignored
        $this->expectScimError(400, 'mutability', fn() => $this->service->patch($this->token, 'u-1',
            self::patch(['op' => 'add', 'path' => 'groups', 'value' => [['value' => 'g-2']]])));
        $this->service->patch($this->token, 'u-1', self::patch(['op' => 'replace', 'value' => ['groups' => [['value' => 'g-1']]]]));
    }

    public function testRename(): void
    {
        $this->users->method('find')->willReturnCallback(fn(): User => self::user());
        $rename = fn(string $userName, array ...$more): array => self::patch(['op' => 'replace', 'path' => 'userName', 'value' => $userName], ...$more);

        // off by default
        $this->expectScimError(400, 'mutability', fn() => $this->service->patch($this->token, 'u-1', $rename('janet@example.org')));
        $this->token->allowRename = 1;
        $this->expectScimError(400, 'mutability', fn() => $this->service->patch($this->token, 'u-1', $rename('jane@example.com')));
        $this->users->method('addressTaken')->willReturnCallback(fn(string $address): bool => $address === 'taken@example.org');
        $this->expectScimError(409, 'uniqueness', fn() => $this->service->patch($this->token, 'u-1', $rename('taken@example.org')));

        $this->token->renameAlias = 0;
        $this->legacy->expects($this->once())->method('rename')->with('jane@example.org', 'janet@example.org')->willReturn(null);
        $this->legacy->expects($this->never())->method('addAlias');
        // the new address in emails of the same request is no error; the case of userName is no rename
        $this->service->patch($this->token, 'u-1', $rename('Janet@Example.org',
            ['op' => 'replace', 'path' => 'emails[type eq "work"].value', 'value' => 'janet@example.org']));
        $this->service->patch($this->token, 'u-1', $rename('JANE@example.org'));
    }

    public function testDeleteDisallowsTheLoginFirstAndKeepsTheCredentials(): void
    {
        $this->users->method('find')->willReturn(self::user());
        $calls = [];
        $this->legacy->method('edit')->willReturnCallback(function () use (&$calls): ?string {
            $calls[] = 'login';
            return null;
        });
        $this->groups->method('removeMember')->willReturnCallback(function () use (&$calls): void {
            $calls[] = 'groups';
        });
        // the tombstone keeps disabled=1 for the template guard and who the user was
        // the mailbox keeps its credentials
        $this->users->expects($this->once())->method('unlink')->with('jane@example.org', 1)
            ->willReturnCallback(function () use (&$calls): void {
                $calls[] = 'unlink';
            });
        $this->service->delete($this->token, 'u-1');
        $this->assertSame(['login', 'groups', 'unlink'], $calls);
    }

    public function testFailedDeleteChangesNothing(): void
    {
        $this->users->method('find')->willReturn(self::user());
        $this->legacy->method('edit')->willReturn('mailbox_invalid');
        $this->groups->expects($this->never())->method('removeMember');
        $this->users->expects($this->never())->method('unlink');
        $this->expectScimError(400, 'invalidValue', fn() => $this->service->delete($this->token, 'u-1'));
    }

    public function testAMailboxHandedBackLeavesScim(): void
    {
        $this->groups->expects($this->once())->method('removeMember')->with('jane@example.org');
        // the admin keeps its login state and credentials
        // released: SCIM row and IdP link go
        $this->users->expects($this->once())->method('release')->with('jane@example.org');
        $this->service->onAuthsourceLeft('jane@example.org');
    }

    public function testRenameKeepsTheOldAddressInAStepOfItsOwn(): void
    {
        $this->token->allowRename = 1;
        $this->users->method('find')->willReturnCallback(fn(): User => self::user());
        // janet@ is an alias kept by an earlier rename of this mailbox: it goes first
        $this->users->method('addressTaken')->with('janet@example.org', 'jane@example.org')->willReturn(false);
        $this->users->method('aliasTo')->with('janet@example.org', 'jane@example.org')->willReturn(42);
        $calls = [];
        $this->legacy->method('deleteAlias')->willReturnCallback(function (int $id) use (&$calls): ?string {
            $calls[] = "delete $id";
            return null;
        });
        $this->legacy->method('rename')->willReturnCallback(function () use (&$calls): ?string {
            $calls[] = 'rename';
            return null;
        });
        // the domain has no aliases left: the rename stands, a notice is logged
        $this->legacy->method('addAlias')->willReturnCallback(function (string $address, string $goto) use (&$calls): ?string {
            $calls[] = "alias $address > $goto";
            return 'max_alias_exceeded';
        });
        $this->service->patch($this->token, 'u-1', self::patch(['op' => 'replace', 'path' => 'userName', 'value' => 'janet@example.org']));
        $this->assertSame(['delete 42', 'rename', 'alias jane@example.org > janet@example.org'], $calls);
        $this->assertStringContainsString('max_alias_exceeded', $this->service->notices()[0]);
        $this->assertSame([], $this->service->notices());
    }



    public function testNullUnassigns(): void
    {
        $this->users->method('find')->willReturn(self::user(['active' => 2, 'disabled' => 1]));
        // active: unassigned keeps the login state; displayName: the name becomes the local part
        $this->legacy->expects($this->once())->method('edit')->with('jane@example.org', ['name' => 'jane'])->willReturn(null);
        $this->service->patch($this->token, 'u-1', self::patch(['op' => 'replace', 'path' => 'active', 'value' => null],
            ['op' => 'replace', 'value' => ['displayname' => null]]));
    }

    public function testExternalIdAndNameLimits(): void
    {
        $this->users->method('mailbox')->willReturn(null);
        $this->users->method('find')->willReturn(self::user());
        $this->expectScimError(400, 'invalidValue', fn() => $this->service->create($this->token, self::body(['externalid' => str_repeat('x', 256)])));
        $this->expectScimError(400, 'invalidValue', fn() => $this->service->create($this->token, self::body(['displayname' => str_repeat('x', 256)])));
        // an empty externalId is none
        $this->legacy->method('create')->willReturn(null);
        $this->users->expects($this->once())->method('link')->with($this->anything(), 'jane@example.org', null);
        $this->users->expects($this->never())->method('externalIdTaken');
        $this->service->create($this->token, self::body(['externalid' => '']));
    }

    public function testAConcurrentCreateIs409(): void
    {
        $this->users->method('mailbox')->willReturn(null);
        $this->legacy->method('create')->willReturn('object_exists');
        $this->expectScimError(409, 'uniqueness', fn() => $this->service->create($this->token, self::body()));
    }

    public function testAConcurrentAdoptionIs409(): void
    {
        // both POSTs saw the tombstone, the other one linked it first
        $this->users = $this->createMock(UserRepository::class);
        $this->users->method('mailbox')->willReturn(['authsource' => 'scim', 'linked' => false, 'tombstone' => true, 'active' => 2, 'disabled' => 1, 'external_id' => 'ext-1', 'token_id' => 7]);
        $this->users->method('link')->willReturn(false);
        $this->legacy->method('applyTemplate')->willReturn(null);
        $this->legacy->method('edit')->willReturn(null);
        $service = new UserService($this->users, $this->legacy, $this->groups);
        $this->expectScimError(409, 'uniqueness', fn() => $service->create($this->token, self::body(['externalid' => 'ext-1'])));
    }

    public function testARetriedDeleteKeepsTheLoginGuard(): void
    {
        $this->users->method('find')->willReturn(self::user());
        $this->legacy->method('edit')->willReturn(null);
        // disabled is recorded right after the login edit, before the steps that can fail
        $this->users->expects($this->once())->method('update')->with('jane@example.org', ['disabled' => 1]);
        $this->groups->method('removeMember')->willThrowException(new \RuntimeException('lock wait timeout'));
        $this->expectException(\RuntimeException::class);
        $this->service->delete($this->token, 'u-1');
    }

    public function testTheSamePersonComesBackWithTheStateOfTheIdp(): void
    {
        $this->users->method('mailbox')->willReturn(['authsource' => 'scim', 'linked' => false, 'tombstone' => true, 'active' => 2, 'disabled' => 1, 'external_id' => 'ext-1', 'token_id' => 7]);
        $this->legacy->method('applyTemplate')->willReturn(null);
        // SCIM disabled it: the new state applies
        $this->legacy->expects($this->once())->method('edit')->with('jane@example.org', ['name' => 'Jane Doe', 'active' => 1])->willReturn(null);
        $this->users->method('find')->willReturn(self::user());
        $this->service->create($this->token, self::body(['externalid' => 'ext-1']));
    }

    public function testADeletedUserOfAnIdpWithoutExternalIdNeedsARelease(): void
    {
        // nobody can be told apart: the mailbox (with the old person's credentials) is not handed over automatically
        $this->users->method('mailbox')->willReturn(['authsource' => 'scim', 'linked' => false, 'tombstone' => true, 'active' => 2, 'disabled' => 1, 'external_id' => null, 'token_id' => 7]);
        $this->legacy->expects($this->never())->method($this->anything());
        $this->expectScimError(409, 'uniqueness', fn() => $this->service->create($this->token, self::body()));
    }

    public function testTheTemplateFollowsTheMappingOfTheIdp(): void
    {
        $templates = $this->createMock(\Mailcow\MailboxGroup\Service\TemplateService::class);
        $service = new UserService($this->users, $this->legacy, $this->groups, $templates);
        $this->users->method('find')->willReturn(self::user());
        // a group template wins over the mapped one, like at IdP logins and syncs
        $templates->method('templateForLogin')->willReturnMap([['jane@example.org', 'Default', 'Group template'], ['jane@example.org', 'Staff', 'Staff']]);
        $applied = [];
        $this->legacy->method('applyTemplate')->willReturnCallback(function (string $u, string $n, string $t) use (&$applied): ?string {
            $applied[] = $t;
            return null;
        });
        $updates = [];
        $this->users->method('update')->willReturnCallback(function (string $u, array $values) use (&$updates): void {
            $updates[] = $values['template'] ?? null;
        });
        // a value without mapping and a removed attribute both map to the default template
        $service->patch($this->token, 'u-1', self::patch(['op' => 'replace', 'path' => Schema::EXT . ':template', 'value' => 'other']));
        $service->patch($this->token, 'u-1', self::patch(['op' => 'remove', 'path' => Schema::EXT . ':template']));
        // the recorded template sent back (GET-modify-PUT) and the mapped value of it are no change
        $service->patch($this->token, 'u-1', self::patch(['op' => 'replace', 'path' => Schema::EXT . ':template', 'value' => 'staff']));
        $this->assertSame(['Group template', 'Group template'], $applied);
        $this->assertSame(['Default', 'Default', null], $updates);
    }

    public function testAdoptionKeepsTheStateAnAdminSet(): void
    {
        // an admin handed over an inactive mailbox
        $this->users->method('mailbox')->willReturn(['authsource' => 'scim', 'linked' => false, 'tombstone' => false, 'active' => 0, 'disabled' => 0, 'external_id' => null, 'token_id' => null]);
        $this->legacy->method('applyTemplate')->willReturn(null);
        // the template's own active must not lift it
        $this->legacy->expects($this->once())->method('edit')->with('jane@example.org', ['name' => 'Jane Doe', 'active' => 0])->willReturn(null);
        $this->users->expects($this->once())->method('link')->with($this->anything(), 'jane@example.org', null, 7, 'Default', 0)->willReturn(true);
        $this->users->method('find')->willReturn(self::user(['active' => 0]));
        $this->service->create($this->token, self::body());
    }

    public function testAFailedRenameBackKeepsTheOldAlias(): void
    {
        $this->token->allowRename = 1;
        $this->users->method('find')->willReturnCallback(fn(): User => self::user());
        $this->users->method('addressTaken')->willReturn(false);
        $this->users->method('aliasTo')->willReturn(42);
        $this->legacy->method('deleteAlias')->willReturn(null);
        $this->legacy->method('rename')->willReturn('mysql_error');
        // the deleted alias janet@ > jane@ comes back
        $this->legacy->expects($this->once())->method('addAlias')->with('janet@example.org', 'jane@example.org')->willReturn(null);
        $this->expectScimError(400, 'invalidValue', fn() => $this->service->patch($this->token, 'u-1',
            self::patch(['op' => 'replace', 'path' => 'userName', 'value' => 'janet@example.org'])));
    }

    public function testAnInactiveMailboxIsNotRenamed(): void
    {
        $this->token->allowRename = 1;
        $this->users->method('find')->willReturn(self::user(['active' => 0]));
        $this->legacy->expects($this->never())->method($this->anything());
        $this->expectScimError(400, 'invalidValue', fn() => $this->service->patch($this->token, 'u-1',
            self::patch(['op' => 'replace', 'path' => 'userName', 'value' => 'janet@example.org'])));
    }

    public function testRequiredAttributesSetToNull(): void
    {
        $this->users->method('find')->willReturn(self::user());
        $this->expectScimError(400, 'mutability', fn() => $this->service->patch($this->token, 'u-1',
            self::patch(['op' => 'replace', 'path' => 'userName', 'value' => null])));
    }

    public function testExternalIdBelongsToTheTokenThatSetIt(): void
    {
        // another IdP (token 8) set ext-1: token 7 neither sees it nor collides with it (RFC 7643 3.1)
        $this->users->method('find')->willReturn(self::user(['token_id' => 8]));
        $this->assertNull($this->service->get($this->token, 'u-1')->externalId);
        $this->users->method('externalIdTaken')->with('ext-7', 7, 'jane@example.org')->willReturn(false);
        // setting one takes it over
        $this->users->expects($this->once())->method('update')->with('jane@example.org', ['external_id' => 'ext-7', 'token_id' => 7]);
        $this->service->patch($this->token, 'u-1', self::patch(['op' => 'replace', 'path' => 'externalId', 'value' => 'ext-7']));
    }

    public function testADeletedUserIsOnlyGivenBackToTheSamePerson(): void
    {
        // deleted through SCIM; it was ext-1 of token 7
        $tombstone = ['authsource' => 'scim', 'linked' => false, 'tombstone' => true, 'active' => 2, 'disabled' => 1, 'external_id' => 'ext-1', 'token_id' => 7];
        $this->users->method('mailbox')->willReturn($tombstone);
        $this->legacy->method('applyTemplate')->willReturn(null);
        $this->legacy->method('edit')->willReturn(null);
        $this->users->method('find')->willReturn(self::user());
        // someone else, or another IdP: the admin has to release the address first
        $this->expectScimError(409, 'uniqueness', fn() => $this->service->create($this->token, self::body(['externalid' => 'ext-2'])));
        $this->expectScimError(409, 'uniqueness', fn() => $this->service->create($this->token, self::body()));
        $other = Token::fromRow(['id' => 8, 'default_template' => 'Default']);
        $this->expectScimError(409, 'uniqueness', fn() => $this->service->create($other, self::body(['externalid' => 'ext-1'])));
        // the same person comes back
        $this->assertSame('u-1', $this->service->create($this->token, self::body(['externalid' => 'ext-1']))->scimId);
    }

    public function testAMissingTemplateDoesNotBlockTheOffboarding(): void
    {
        $users = $this->createMock(\Mailcow\Scim\Repository\UserRepository::class);
        $users->method('find')->willReturn(self::user());
        $users->method('templateExists')->willReturn(false);
        $users->method('locked')->willReturnCallback(fn(string $id, callable $work): mixed => $work());
        $service = new UserService($users, $this->legacy, $this->groups);
        // the template is skipped with a notice, the deactivation goes through
        $this->legacy->expects($this->never())->method('applyTemplate');
        $this->legacy->expects($this->once())->method('edit')->with('jane@example.org', ['active' => 2])->willReturn(null);
        $service->replace($this->token, 'u-1', self::body([strtolower(Schema::EXT) => ['template' => 'other'], 'active' => false]));
        $this->assertStringContainsString('does not exist', $service->notices()[0]);
    }

    public function testATemplateChangeDoesNotSwallowActiveFalse(): void
    {
        // an admin disabled the login (no SCIM flag); the new template sets its own active 1
        $state = new \stdClass();
        $state->active = 2;
        $this->users->method('find')->willReturnCallback(fn(): User => self::user(['active' => $state->active]));
        $this->users->method('mailbox')->willReturnCallback(fn(): array => ['authsource' => 'scim', 'linked' => true, 'tombstone' => false,
            'active' => $state->active, 'disabled' => 0, 'external_id' => 'ext-1', 'token_id' => 7]);
        $this->legacy->method('applyTemplate')->willReturnCallback(function () use ($state): ?string {
            $state->active = 1;
            return null;
        });
        $this->legacy->method('edit')->willReturnCallback(function (string $u, array $fields) use ($state): ?string {
            $state->active = $fields['active'] ?? $state->active;
            return null;
        });
        $this->service->patch($this->token, 'u-1', self::patch(
            ['op' => 'replace', 'path' => Schema::EXT . ':template', 'value' => 'other'],
            ['op' => 'replace', 'path' => 'active', 'value' => false]));
        $this->assertSame(2, $state->active);
    }

    public function testTheLastTemplateOperationDecides(): void
    {
        $this->users->method('find')->willReturn(self::user());
        // removed, then the recorded template sent back: no change
        $this->legacy->expects($this->never())->method('applyTemplate');
        $this->service->patch($this->token, 'u-1', self::patch(
            ['op' => 'remove', 'path' => Schema::EXT . ':template'],
            ['op' => 'add', 'path' => Schema::EXT . ':template', 'value' => 'staff']));
    }

    public function testNullTemplateInAPathlessExtensionIsTheDefault(): void
    {
        $this->users->method('find')->willReturn(self::user());
        $this->legacy->expects($this->once())->method('applyTemplate')->with('jane@example.org', 'Jane Doe', 'Default')->willReturn(null);
        $this->service->patch($this->token, 'u-1', self::patch(['op' => 'replace', 'value' => [strtolower(Schema::EXT) => ['template' => null]]]));
    }


    public function testThePreconditionSeesTheUserReadUnderItsLock(): void
    {
        $locked = false;
        $users = $this->createMock(UserRepository::class);
        $users->method('locked')->willReturnCallback(function (string $id, callable $work) use (&$locked): mixed {
            $this->assertSame('u-1', $id);
            $locked = true;
            return $work();
        });
        $users->method('find')->willReturnCallback(function () use (&$locked): User {
            $this->assertTrue($locked);
            return self::user();
        });
        $this->legacy->expects($this->never())->method('edit');
        $service = new UserService($users, $this->legacy, $this->groups);
        // a failed If-Match stops the write
        $stale = fn(User $user) => throw new ScimError(412, 'changed');
        $this->expectScimError(412, null, fn() => $service->patch($this->token, 'u-1', self::patch(['op' => 'replace', 'path' => 'active', 'value' => false]), $stale));
        $this->expectScimError(412, null, fn() => $service->delete($this->token, 'u-1', $stale));
    }

        public function testUnknownUsersAre404(): void
    {
        $this->users->method('find')->willReturn(null);
        $this->expectScimError(404, null, fn() => $this->service->get($this->token, 'nope'));
        $this->expectScimError(404, null, fn() => $this->service->delete($this->token, 'nope'));
    }
}
