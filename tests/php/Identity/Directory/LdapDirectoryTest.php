<?php

declare(strict_types=1);

namespace Mailcow\Tests\Identity\Directory;

use Closure;
use Mailcow\Identity\Directory\DirectoryException;
use Mailcow\Identity\Directory\LdapDirectory;
use Mailcow\Identity\Model\Settings;
use PHPUnit\Framework\TestCase;

class LdapDirectoryTest extends TestCase
{
    private const BASE = 'dc=example,dc=org';

    public function testUsersWithIdsAndDisabledStates(): void
    {
        $directory = new FakeLdapDirectory(['basedn' => self::BASE, 'filter' => 'objectClass=person', 'disabled_filter' => '(employeeType=left)'],
            function (string $base, string $filter): array {
                if (str_contains($filter, 'employeeType=left')) {
                    return [self::entry('cn=c,ou=people,dc=example,dc=org', [])];
                }
                return [
                    self::entry('CN=A,ou=people,dc=example,dc=org', ['mail' => 'A@example.org', 'displayname' => 'A User',
                        'objectguid' => hex2bin('0123456789abcdef0123456789abcdef'), 'useraccountcontrol' => '512']),
                    self::entry('cn=b,ou=people,dc=example,dc=org', ['mail' => 'b@example.org', 'cn' => 'B',
                        'entryuuid' => 'F81D4FAE-7DEC-11D0-A765-00A0C91E6BF6', 'useraccountcontrol' => '514']),
                    self::entry('cn=c,ou=people,dc=example,dc=org', ['mail' => 'c@example.org']),
                ];
            });

        $users = $directory->listAll(false)->users;

        $this->assertSame(['67452301-ab89-efcd-0123-456789abcdef', 'f81d4fae-7dec-11d0-a765-00a0c91e6bf6', 'dn:cn=c,ou=people,dc=example,dc=org'],
            array_keys($users));
        $user = $users['67452301-ab89-efcd-0123-456789abcdef'];
        $this->assertSame(['a@example.org', 'A User', true], [$user->email, $user->name, $user->enabled]);
        $this->assertFalse($users['f81d4fae-7dec-11d0-a765-00a0c91e6bf6']->enabled);
        $this->assertFalse($users['dn:cn=c,ou=people,dc=example,dc=org']->enabled);
        $this->assertSame('(&(&(objectClass=person)(mail=*))(employeeType=left))', $directory->searches[0][1]);
        $this->assertSame('(&(objectClass=person)(mail=*))', $directory->searches[1][1]);
    }

    public function testConfiguredIdAttributeWins(): void
    {
        $entry = self::entry('cn=a,dc=example,dc=org', ['employeenumber' => 'E-17', 'entryuuid' => 'x']);

        $this->assertSame('e-17', LdapDirectory::entryId($entry, 'employeenumber'));
        $this->assertSame('x', LdapDirectory::entryId($entry));
    }

    public function testMembersFromMemberOf(): void
    {
        $directory = new FakeLdapDirectory(['basedn' => self::BASE, 'group_filter' => '(objectClass=groupOfNames)'],
            function (string $base, string $filter, array $attributes): array {
                if ($filter === '(objectClass=groupOfNames)') {
                    $this->assertContains('mail', $attributes);
                    return [self::entry('CN=Sales,ou=groups,dc=example,dc=org', ['cn' => 'Sales', 'entryuuid' => 'g1', 'mail' => 'Sales@example.org'])];
                }
                $this->assertContains('memberof', $attributes);
                return [
                    self::entry('cn=a,dc=example,dc=org', ['mail' => 'a@example.org', 'entryuuid' => 'u1', 'memberof' => ['cn=sales,ou=groups,dc=example,dc=org']]),
                    self::entry('cn=b,dc=example,dc=org', ['mail' => 'b@example.org', 'entryuuid' => 'u2', 'memberof' => ['cn=other,dc=example,dc=org']]),
                ];
            });

        $groups = $directory->listAll(true)->groups;

        $this->assertSame(['g1'], array_keys($groups));
        $this->assertSame(['Sales', 'sales@example.org', ['u1']], [$groups['g1']->name, $groups['g1']->mail, $groups['g1']->memberIds]);
    }

    public function testMembersFromTheMemberAttribute(): void
    {
        $directory = new FakeLdapDirectory(['basedn' => self::BASE, 'group_filter' => 'objectClass=groupOfNames', 'group_member_mode' => 'member'],
            fn(string $base, string $filter): array => ($filter === '(objectClass=groupOfNames)')
                ? [self::entry('cn=sales,dc=example,dc=org', ['cn' => 'Sales', 'entryuuid' => 'g1',
                    'member' => ['CN=A,dc=example,dc=org', 'cn=unknown,dc=example,dc=org']])]
                : [self::entry('cn=a,dc=example,dc=org', ['mail' => 'a@example.org', 'entryuuid' => 'u1'])]);

        $this->assertSame(['u1'], $directory->listAll(true)->groups['g1']->memberIds);
    }

    public function testRangedMemberListsAbort(): void
    {
        $directory = new FakeLdapDirectory(['basedn' => self::BASE, 'group_filter' => '(objectClass=group)', 'group_member_mode' => 'member'],
            fn(string $base, string $filter): array => ($filter === '(objectClass=group)')
                ? [self::entry('cn=big,dc=example,dc=org', ['cn' => 'Big', 'member;range=0-1499' => ['cn=a,dc=example,dc=org']])]
                : []);

        $this->expectException(DirectoryException::class);
        $directory->listAll(true);
    }

    public function testNestedMembersFromTheAdInChainRule(): void
    {
        $directory = new FakeLdapDirectory(['basedn' => self::BASE, 'group_basedn' => 'ou=groups,dc=example,dc=org',
            'group_filter' => '(objectClass=group)', 'group_member_mode' => 'ad_chain'],
            function (string $base, string $filter): array {
                if ($filter === '(objectClass=group)') {
                    $this->assertSame('ou=groups,dc=example,dc=org', $base);
                    return [self::entry('cn=sales (de),ou=groups,dc=example,dc=org', ['cn' => 'Sales', 'objectguid' => 'g1'])];
                }
                if (str_contains($filter, '1.2.840.113556.1.4.1941')) {
                    $this->assertSame('(&(&(objectClass=*)(mail=*))(memberOf:1.2.840.113556.1.4.1941:=cn=sales \\28de\\29,ou=groups,dc=example,dc=org))', $filter);
                    return [self::entry('cn=a,dc=example,dc=org', [])];
                }
                return [self::entry('cn=a,dc=example,dc=org', ['mail' => 'a@example.org', 'entryuuid' => 'u1'])];
            });

        $this->assertSame(['u1'], $directory->listAll(true)->groups['g1']->memberIds);
    }

    public function testGroupsNeedAFilter(): void
    {
        $directory = new FakeLdapDirectory(['basedn' => self::BASE], fn(): array => []);

        $this->expectException(DirectoryException::class);
        $directory->listAll(true);
    }

    /** An entry as ldap_get_entries() returns it */
    private static function entry(string $dn, array $attributes): array
    {
        $entry = ['dn' => $dn];
        foreach ($attributes as $name => $values) {
            $values = (array)$values;
            $entry[$name] = $values + ['count' => count($values)];
        }
        return $entry + ['count' => count($attributes)];
    }
}

/**
 * LdapDirectory with canned search results instead of an LDAP server.
 */
class FakeLdapDirectory extends LdapDirectory
{
    /** @var array[] [base, filter, attributes] of every search */
    public array $searches = [];

    public function __construct(array $settings, private Closure $results)
    {
        parent::__construct(null, Settings::fromArray($settings + ['authsource' => 'ldap']));
    }

    protected function search(string $base, string $filter, array $attributes): array
    {
        $this->searches[] = [$base, $filter, $attributes];
        return ($this->results)($base, $filter, $attributes);
    }
}
