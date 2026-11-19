<?php

declare(strict_types=1);

namespace Mailcow\Tests\Scim\Model;

use Mailcow\Scim\Model\PatchOp;
use Mailcow\Scim\Model\Schema;
use Mailcow\Scim\ScimError;
use PHPUnit\Framework\TestCase;

class SchemaTest extends TestCase
{
    private const GROUP = [
        'schemas' => [Schema::GROUP],
        'id' => 'g-1',
        'displayName' => 'Sales',
        'members' => [['value' => 'u-1', 'type' => 'User', 'display' => 'a@example.org']],
        'meta' => ['resourceType' => 'Group'],
    ];

    public function testAttributes(): void
    {
        $this->assertSame(['ext' => false, 'parts' => ['members', 'value']], Schema::attribute('Group', 'members.value'));
        $this->assertSame(['ext' => false, 'parts' => ['displayname']], Schema::attribute('Group', Schema::GROUP . ':displayName'));
        $this->assertSame(['ext' => true, 'parts' => []], Schema::attribute('User', Schema::EXT));
        $this->assertNull(Schema::attribute('Group', Schema::EXT . ':template'));
        $this->assertNull(Schema::attribute('User', 'members'));
        $this->assertNull(Schema::attribute('User', 'name.middleName'));
    }

    public function testProjection(): void
    {
        // excludedAttributes=members, sent by Entra ID for every group lookup
        $this->assertArrayNotHasKey('members', Schema::project(self::GROUP, 'Group', null, 'members'));
        // schemas and id are always returned, unknown attributes ignored
        $this->assertSame(['schemas' => [Schema::GROUP], 'id' => 'g-1', 'displayName' => 'Sales'],
            Schema::project(self::GROUP, 'Group', 'displayName,nope', null));
        $this->assertSame([['value' => 'u-1']], Schema::project(self::GROUP, 'Group', ['members.value'], null)['members']);
        $this->assertSame(self::GROUP, Schema::project(self::GROUP, 'Group', null, 'id'));
        $this->expectException(ScimError::class);
        Schema::project(self::GROUP, 'Group', 'displayName', 'members');
    }

    public function testProjectionIsCheckedUpFront(): void
    {
        Schema::checkProjection(['attributes' => 'userName']);
        Schema::checkProjection(['attributes' => '', 'excludedattributes' => 'emails']);
        $this->expectException(ScimError::class);
        Schema::checkProjection(['attributes' => 'userName', 'excludedattributes' => 'emails']);
    }

    public function testBooleansAcceptTheStringsOfEntraId(): void
    {
        $this->assertFalse(Schema::bool('False'));
        $this->assertTrue(Schema::bool('TRUE'));
        $this->assertFalse(Schema::bool(0));
        $this->expectException(ScimError::class);
        Schema::bool('no');
    }

    public function testDiscoveryAdvertisesUsersAndGroups(): void
    {
        $this->assertSame(['User', 'Group'], array_column(Schema::resourceTypes('https://h/scim/v2'), 'id'));
        $this->assertSame([Schema::USER, Schema::GROUP, Schema::EXT], array_column(Schema::schemas('https://h/scim/v2'), 'id'));
        $config = Schema::serviceProviderConfig('https://h/scim/v2');
        $this->assertSame([true, true, true, true], [$config['patch']['supported'], $config['bulk']['supported'], $config['sort']['supported'], $config['etag']['supported']]);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', Schema::uuid());
    }

    public function testPatchOperations(): void
    {
        $ops = PatchOp::parse(['schemas' => [Schema::PATCH], 'operations' => [
            ['op' => 'Replace', 'value' => ['displayname' => 'x']],
            ['op' => 'remove', 'path' => 'members[value eq "u-1"]'],
        ]], 'Group');
        $this->assertSame(['replace', 'remove'], [$ops[0]->op, $ops[1]->op]);
        $this->assertNull($ops[0]->path);
        $this->assertFalse($ops[1]->hasValue);

        $invalid = [
            [['op' => 'move', 'path' => 'displayName', 'value' => 'x'], 'invalidSyntax'],
            [['op' => 'add', 'path' => 'displayName'], 'invalidValue'],
            [['op' => 'remove'], 'noTarget'],
            [['op' => 'add', 'value' => ['a', 'b']], 'invalidValue'],
        ];
        foreach ($invalid as [$op, $scimType]) {
            try {
                PatchOp::parse(['schemas' => [Schema::PATCH], 'operations' => [$op]], 'Group');
                $this->fail('ScimError expected');
            } catch (ScimError $e) {
                $this->assertSame($scimType, $e->scimType);
            }
        }
    }
}
