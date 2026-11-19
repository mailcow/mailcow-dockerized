<?php

declare(strict_types=1);

namespace Mailcow\Tests\Scim\Repository;

use Mailcow\Scim\Repository\GroupRepository;
use Mailcow\Scim\Repository\UserRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

/**
 * Query shapes of the repositories against a mocked PDO (the SQL itself runs in the end-to-end tests)
 */
class RepositorySqlTest extends TestCase
{
    /** @var string[] */
    private array $sql = [];

    /**
     * A PDO whose statements return $results in order of preparation. Every statement must bind exactly its
     * placeholders: with native prepares a missing or extra parameter fails (HY093).
     */
    private function pdo(array ...$results): PDO
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturnCallback(function (string $sql) use (&$results): PDOStatement {
            $this->sql[] = $sql;
            $result = array_shift($results) ?? [];
            $rows = $result;
            $stmt = $this->createMock(PDOStatement::class);
            $stmt->method('execute')->willReturnCallback(function (array $params = []) use ($sql): bool {
                preg_match_all('~(?<![:\w]):([a-z_][a-z_0-9]*)~i', $sql, $m);
                $this->assertEqualsCanonicalizing(array_values(array_unique($m[1])), array_keys($params), $sql);
                return true;
            });
            $stmt->method('fetch')->willReturnCallback(function () use (&$rows): array|false {
                return array_shift($rows) ?? false;
            });
            $stmt->method('fetchColumn')->willReturnCallback(fn(): mixed => isset($result[0]) ? reset($result[0]) : false);
            $stmt->method('rowCount')->willReturn(count($result));
            $stmt->method('fetchAll')->willReturnCallback(fn(int $mode = PDO::FETCH_DEFAULT): array => ($mode === PDO::FETCH_KEY_PAIR)
                ? array_column($result, 1, 0) : $result);
            return $stmt;
        });
        return $pdo;
    }

    public function testMembershipsReadTheIdpListOncePerGroup(): void
    {
        $repo = new GroupRepository($this->pdo(
            [['group_id' => 5, 'username' => 'a@example.org'], ['group_id' => 5, 'username' => 'b@example.org']],
            // b@ is a manual member the IdP did not set: not shown
            [['group_id' => 5, 'scim_id' => 'g-1', 'name' => 'Sales', 'members' => '["a@example.org"]']],
        ));
        $this->assertSame(['a@example.org' => ['g-1' => 'Sales']], $repo->membershipsOf(['a@example.org', 'b@example.org'], null));
        // the member rows do not carry the list of the group (one copy per row grows with the group)
        $this->assertStringNotContainsString('sg.`members`', $this->sql[0]);
        $this->assertStringContainsString('gm.`username` IN (:u0, :u1)', $this->sql[0]);
        $this->assertCount(2, $this->sql);
    }

    public function testTheIdpListOfAGroupShowsOnlyCurrentMembers(): void
    {
        $repo = new GroupRepository($this->pdo(
            [['id' => 5, 'domain' => 'example.org', 'name' => 'Sales', 'created' => '2026-11-16 10:00:00', 'modified' => '2026-11-16 10:00:00',
                'scim_id' => 'g-1', 'external_id' => null, 'scim_members' => '["a@example.org","gone@example.org"]']],
            // gone@ was deleted in mailcow: no member row anymore; m@ is a manual member, no SCIM user
            [['group_id' => 5, 'username' => 'a@example.org', 'scim_id' => 'u-1', 'domain' => 'example.org'],
                ['group_id' => 5, 'username' => 'm@example.org', 'scim_id' => null, 'domain' => null]],
        ));
        $group = $repo->find('g-1', null);
        $this->assertSame(['a@example.org'], $group->assigned);
        $this->assertSame(['u-1' => 'a@example.org'], $group->members);
    }

    public function testDeleteKeepsTheIdentityAndTheCredentials(): void
    {
        (new UserRepository($this->pdo()))->unlink('a@example.org', 1);
        $this->assertCount(1, $this->sql);
        $this->assertStringContainsString('`scim_id` = NULL', $this->sql[0]);
        $this->assertStringNotContainsString('external_id', $this->sql[0]);
    }

    public function testAReleaseRemovesTheScimRowAndTheIdpLinkInOneTransaction(): void
    {
        $pdo = $this->pdo();
        $pdo->expects($this->once())->method('beginTransaction');
        $pdo->expects($this->once())->method('commit');
        (new UserRepository($pdo))->release('a@example.org');
        $this->assertStringContainsString('DELETE FROM `scim_maps`', $this->sql[0]);
        $this->assertStringContainsString('DELETE FROM `identity_link`', $this->sql[1]);
    }

    public function testTheGroupLockIsReleasedWhenTheWorkFails(): void
    {
        $repo = new GroupRepository($this->pdo([['group_id' => 5]], [[1]], [[1]]));
        try {
            $repo->locked('g-1', fn() => throw new \RuntimeException('boom'));
            $this->fail('exception expected');
        } catch (\RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }
        // by group id, so writes by SCIM id and by group id exclude each other; no transaction around the work
        $this->assertStringContainsString('GET_LOCK', $this->sql[1]);
        $this->assertStringContainsString('RELEASE_LOCK', $this->sql[2]);
    }

    public function testAllGroupLocksAreTakenInOrderAndReleased(): void
    {
        $repo = new GroupRepository($this->pdo([[1]], [[1]], [[1]], [[1]]));
        $repo->lockedAll([9, 3], fn() => null);
        // id order, so two callers cannot deadlock; released in reverse
        $this->assertStringContainsString('GET_LOCK', $this->sql[0]);
        $this->assertStringContainsString('RELEASE_LOCK', $this->sql[3]);
        $this->assertCount(4, $this->sql);
    }

    public function testABusyGroupReleasesTheLocksAlreadyTaken(): void
    {
        $repo = new GroupRepository($this->pdo([[1]], [[0]], [[1]]));
        try {
            $repo->lockedAll([3, 9], fn() => $this->fail('work without all locks'));
            $this->fail('ScimError expected');
        } catch (\Mailcow\Scim\ScimError $e) {
            $this->assertSame(503, $e->status);
        }
        $this->assertStringContainsString('RELEASE_LOCK', $this->sql[2]);
    }

    public function testABusyGroupIs503(): void
    {
        $repo = new GroupRepository($this->pdo([[0]]));
        try {
            $repo->locked(5, fn() => $this->fail('work without the lock'));
            $this->fail('ScimError expected');
        } catch (\Mailcow\Scim\ScimError $e) {
            $this->assertSame(503, $e->status);
        }
    }

    public function testLinkTakesOverATombstoneInsertsOrLosesTheRace(): void
    {
        $duplicate = new \PDOException('Duplicate entry');
        $duplicate->errorInfo = ['23000', 1062, 'Duplicate entry'];
        foreach ([[1, null, true, 1], [0, null, true, 2], [0, $duplicate, false, 2]] as [$rows, $error, $linked, $count]) {
            $sql = [];
            $pdo = $this->createMock(PDO::class);
            $pdo->method('prepare')->willReturnCallback(function (string $q) use (&$sql, $rows, $error): PDOStatement {
                $sql[] = $q;
                $stmt = $this->createMock(PDOStatement::class);
                $stmt->method('rowCount')->willReturn($rows);
                if ($error !== null && str_starts_with($q, 'INSERT')) {
                    $stmt->method('execute')->willThrowException($error);
                } else {
                    $stmt->method('execute')->willReturn(true);
                }
                return $stmt;
            });
            $this->assertSame($linked, (new UserRepository($pdo))->link('u-1', 'a@example.org', null, 7, null, 0, null, null));
            $this->assertCount($count, $sql);
            // only a tombstone is taken over
            $this->assertStringContainsString('`scim_id` IS NULL', $sql[0]);
        }
    }
}
