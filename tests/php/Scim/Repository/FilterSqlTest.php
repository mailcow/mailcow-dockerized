<?php

declare(strict_types=1);

namespace Mailcow\Tests\Scim\Repository;

use Mailcow\Scim\Model\Filter;
use Mailcow\Scim\Model\Schema;
use Mailcow\Scim\Repository\FilterSql;
use Mailcow\Scim\Repository\GroupRepository;
use Mailcow\Scim\Repository\UserRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FilterSqlTest extends TestCase
{
    private const COLUMNS = ['id' => 'id', 'username' => 'u', 'displayname' => 'n', 'emails' => 'u', 'emails.value' => 'u', 'active' => 'a'];

    public static function filters(): array
    {
        return [
            'eq' => ['userName eq "a@x"', ' AND u = :f0', ['f0' => 'a@x']],
            'sw escapes LIKE wildcards' => ['displayName sw "50%_"', ' AND n LIKE :f0', ['f0' => '50\\%\\_%']],
            'and keeps what it can' => ['userName eq "a@x" and name.givenName eq "j"', ' AND u = :f0', ['f0' => 'a@x']],
            'or needs both sides' => ['userName eq "a@x" or displayName co "b"', ' AND (u = :f0 OR n LIKE :f1)', ['f0' => 'a@x', 'f1' => '%b%']],
            'or with an unmapped side' => ['userName eq "a@x" or name.givenName eq "j"', '', []],
            'not is not narrowed' => ['not (userName eq "a@x")', '', []],
            'ne is not narrowed' => ['userName ne "a@x"', '', []],
            'boolean' => ['active eq true', ' AND a = 1', []],
            'false is anything but 1' => ['active eq false', ' AND a <> 1', []],
            'Entra ID value filter' => ['emails[type eq "work"].value eq "a@x"', ' AND u = :f0', ['f0' => 'a@x']],
            'value filter' => ['emails[value eq "a@x" and type eq "work"]', ' AND u = :f0', ['f0' => 'a@x']],
            'extension attribute' => ['urn:mailcow:params:scim:schemas:extension:mailcow:2.0:User:template eq "x"', '', []],
            'failed or inside and drops its parameters' => ['(displayName eq "b" or name.givenName eq "j") and userName eq "a@x"', ' AND u = :f0', ['f0' => 'a@x']],
            'and inside or keeps the mapped side' => ['(userName eq "a@x" and name.givenName eq "j") or displayName sw "b"', ' AND (u = :f0 OR n LIKE :f1)', ['f0' => 'a@x', 'f1' => 'b%']],
        ];
    }

    #[DataProvider('filters')]
    public function testWhere(string $filter, string $sql, array $params): void
    {
        $this->assertSame([$sql, $params], FilterSql::where(Filter::parse($filter, 'User'), self::COLUMNS));
    }

    /** Every column the repositories map is a known attribute, so a typo cannot silently disable the narrowing */
    public function testTheRepositoryColumnsAreKnownAttributes(): void
    {
        foreach ([UserRepository::class => 'User', GroupRepository::class => 'Group'] as $class => $type) {
            $columns = (new \ReflectionClassConstant($class, 'COLUMNS'))->getValue();
            foreach (array_keys($columns) as $path) {
                // lowercase, as FilterSql looks the parsed path up; and known, so a filter on it narrows
                $this->assertSame(strtolower($path), $path, "$class: $path");
                $this->assertNotNull(Schema::attribute($type, $path), "$class: $path");
                $this->assertNotSame('', FilterSql::where(Filter::parse($path . ' eq "x"', $type), $columns)[0], "$class: $path");
            }
        }
    }

    public function testNoFilter(): void
    {
        $this->assertSame(['', []], FilterSql::where(null, self::COLUMNS));
    }
}
