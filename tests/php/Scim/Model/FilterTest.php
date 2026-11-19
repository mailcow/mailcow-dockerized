<?php

declare(strict_types=1);

namespace Mailcow\Tests\Scim\Model;

use Mailcow\Scim\Model\Filter;
use Mailcow\Scim\ScimError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FilterTest extends TestCase
{
    private const USER = [
        'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User', 'urn:mailcow:params:scim:schemas:extension:mailcow:2.0:User'],
        'id' => '2819c223-7f76-453a-919d-413861904646',
        'userName' => 'Bjensen@Example.org',
        'externalId' => 'ext-1',
        'displayName' => 'Barbara Jensen',
        'name' => ['formatted' => 'Barbara Jensen', 'givenName' => 'Barbara', 'familyName' => 'Jensen'],
        'active' => true,
        'emails' => [['value' => 'bjensen@example.org', 'type' => 'work', 'primary' => true]],
        'urn:mailcow:params:scim:schemas:extension:mailcow:2.0:User' => ['template' => 'Staff'],
        'meta' => ['resourceType' => 'User', 'created' => '2026-01-10T08:00:00+00:00', 'lastModified' => '2026-03-01T12:00:00+00:00'],
    ];

    private const GROUP = [
        'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Group'],
        'id' => 'g-1',
        'displayName' => 'Sales Team',
        'members' => [
            ['value' => 'u-1', 'type' => 'User', 'display' => 'a@example.org'],
            ['value' => 'u-2', 'type' => 'User', 'display' => 'b@example.org'],
        ],
    ];

    public static function userFilters(): array
    {
        return [
            'eq is case-insensitive' => ['userName eq "bjensen@example.org"', true],
            'attribute names are case-insensitive' => ['USERNAME Eq "bjensen@example.org"', true],
            'ne' => ['userName ne "bjensen@example.org"', false],
            'co' => ['displayName co "jens"', true],
            'sw' => ['userName sw "bjen"', true],
            'ew' => ['userName ew "@example.org"', true],
            'pr' => ['externalId pr', true],
            'pr of a missing attribute' => ['title pr', null],
            'sub-attribute' => ['name.familyName eq "Jensen"', true],
            'core URN prefix' => ['urn:ietf:params:scim:schemas:core:2.0:User:userName eq "bjensen@example.org"', true],
            'extension attribute' => ['urn:mailcow:params:scim:schemas:extension:mailcow:2.0:User:template eq "staff"', true],
            'boolean' => ['active eq true', true],
            'boolean false' => ['active eq false', false],
            'and' => ['active eq true and userName sw "b"', true],
            'or' => ['active eq false or userName sw "b"', true],
            'not' => ['not (active eq true)', false],
            'precedence: and before or' => ['userName eq "x" and active eq true or externalId eq "ext-1"', true],
            'parentheses' => ['userName eq "x" and (active eq true or externalId eq "ext-1")', false],
            'value filter' => ['emails[type eq "work" and value co "@example.org"]', true],
            'value filter without match' => ['emails[type eq "home"]', false],
            'Entra ID value filter with sub-attribute' => ['emails[type eq "work"].value eq "bjensen@example.org"', true],
            'multi-valued sub-attribute' => ['emails.value eq "bjensen@example.org"', true],
            'dateTime compares as instant' => ['meta.created eq "2026-01-10T08:00:00Z"', true],
            'dateTime gt' => ['meta.lastModified gt "2026-02-01T00:00:00Z"', true],
            'escaped quote in string' => ['displayName eq "Barbara \"B\" Jensen"', false],
            'id is caseExact' => ['id eq "2819C223-7F76-453A-919D-413861904646"', false],
            'externalId is caseExact' => ['externalId eq "EXT-1"', false],
            'externalId sw' => ['externalId sw "ext-"', true],
            'non-ASCII is case-insensitive' => ['displayName ne "ÄRGER" and displayName co "barbara"', true],
            'an unassigned attribute equals null' => ['name.middleName eq null', null],
            'externalId eq null of an assigned one' => ['externalId eq null', false],
        ];
    }

    #[DataProvider('userFilters')]
    public function testUserFilters(string $filter, ?bool $matches): void
    {
        if ($matches === null) {
            $this->expectException(ScimError::class);
        }
        $this->assertSame($matches, Filter::parse($filter, 'User')->matches(self::USER));
    }

    public function testUnassignedAttributes(): void
    {
        $user = self::USER;
        unset($user['externalId']);
        // RFC 7643 2.5: unassigned equals null and differs from every value
        $this->assertTrue(Filter::parse('externalId eq null', 'User')->matches($user));
        $this->assertTrue(Filter::parse('externalId ne "x"', 'User')->matches($user));
        $this->assertFalse(Filter::parse('externalId eq "x"', 'User')->matches($user));
        // lenient: an attribute the type lacks has no value
        $this->assertTrue(Filter::parse('not (userName pr)', 'Group', true)->matches(self::GROUP));
        $this->assertTrue(Filter::parse('not (members[nope eq "x"])', 'User', true)->matches(self::USER));
    }

    public function testGroupFilters(): void
    {
        $this->assertTrue(Filter::parse('displayName eq "sales team"', 'Group')->matches(self::GROUP));
        $this->assertTrue(Filter::parse('members[value eq "u-2"]', 'Group')->matches(self::GROUP));
        $this->assertTrue(Filter::parse('members.display co "b@"', 'Group')->matches(self::GROUP));
        $this->assertFalse(Filter::parse('members[value eq "u-3"]', 'Group')->matches(self::GROUP));
    }

    public static function invalidFilters(): array
    {
        return [
            'empty' => [''],
            'unknown attribute' => ['nickName eq "x"'],
            'unknown operator' => ['userName like "x"'],
            'missing value' => ['userName eq'],
            'unterminated string' => ['userName eq "x'],
            'trailing tokens' => ['userName eq "x" "y"'],
            'unclosed parenthesis' => ['(userName eq "x"'],
            'value filter on a single-valued attribute' => ['name[givenName eq "x"]'],
            'unknown URN' => ['urn:example:User:userName eq "x"'],
            'group attribute on users' => ['members pr'],
            'gt on a boolean attribute' => ['active gt false'],
            'lt on a boolean sub-attribute' => ['emails[primary lt true]'],
            'ge with a boolean value' => ['displayName ge true'],
        ];
    }

    #[DataProvider('invalidFilters')]
    public function testInvalidFiltersAre400InvalidFilter(string $filter): void
    {
        try {
            Filter::parse($filter, 'User');
            $this->fail('ScimError expected');
        } catch (ScimError $e) {
            $this->assertSame([400, 'invalidFilter'], [$e->status, $e->scimType]);
        }
    }

    public function testPaths(): void
    {
        $path = Filter::path('name.givenName', 'User');
        $this->assertSame(['ext' => false, 'parts' => ['name', 'givenname']], $path['attr']);
        $this->assertNull($path['filter']);

        $path = Filter::path('emails[type eq "work"].value', 'User');
        $this->assertSame(['emails'], $path['attr']['parts']);
        $this->assertSame('value', $path['sub']);
        $this->assertTrue($path['filter']->matches(['type' => 'work']));

        $path = Filter::path('members[value eq "u-1"]', 'Group');
        $this->assertTrue($path['filter']->matches(self::GROUP['members'][0]));
        $this->assertFalse($path['filter']->matches(self::GROUP['members'][1]));

        $this->assertSame(['ext' => true, 'parts' => ['template']],
            Filter::path('urn:mailcow:params:scim:schemas:extension:mailcow:2.0:User:template', 'User')['attr']);
        // unstored User attributes are ignored, not rejected
        $this->assertNull(Filter::path('phoneNumbers[type eq "work"].value', 'User'));
        $this->assertNull(Filter::path('title', 'User'));
        $this->assertNull(Filter::path('password', 'User'));
        // unstored sub-attributes of stored attributes are ignored too
        $this->assertNull(Filter::path('name.middleName', 'User'));
        $this->assertNull(Filter::path('urn:ietf:params:scim:schemas:core:2.0:User:name.honorificPrefix', 'User'));
        $this->assertNull(Filter::path('emails[type eq "work"].display', 'User'));
        // Entra ID's default mapping sends department and manager in the enterprise extension
        $this->assertNull(Filter::path('urn:ietf:params:scim:schemas:extension:enterprise:2.0:User:department', 'User'));
    }

    public function testSortValues(): void
    {
        $spec = fn(string $name): array => \Mailcow\Scim\Model\Schema::attribute('User', $name);
        $user = self::USER;
        $user['emails'] = [['value' => 'b@x', 'type' => 'home'], ['value' => 'a@x', 'type' => 'work', 'primary' => true]];
        // a multi-valued attribute sorts by its primary value
        $this->assertSame('a@x', Filter::sortValue($spec('emails'), $user));
        $this->assertSame('a@x', Filter::sortValue($spec('emails.value'), $user));
        $this->assertSame('Jensen', Filter::sortValue($spec('name.familyName'), $user));
        $this->assertNull(Filter::sortValue($spec('groups'), $user));
        $this->assertSame(-1, Filter::sortCompare('alpha', 'Beta', false));
        $this->assertSame(1, Filter::sortCompare('alpha', 'Beta', true));
        // no value sorts last
        $this->assertSame(1, Filter::sortCompare(null, 'a', false));
    }

    public function testInvalidPathsAre400InvalidPath(): void
    {
        foreach (['middleName', 'name[givenName eq "x"]', 'members.value.x', 'emails[type eq "work"].nope'] as $path) {
            try {
                Filter::path($path, ($path === 'members.value.x') ? 'Group' : 'User');
                $this->fail("ScimError expected for $path");
            } catch (ScimError $e) {
                $this->assertSame([400, 'invalidPath'], [$e->status, $e->scimType], $path);
            }
        }
    }
}
