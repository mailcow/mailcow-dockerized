<?php

declare(strict_types=1);

namespace Mailcow\Tests\Scim\Controller;

use Mailcow\Scim\Controller\ScimController;
use Mailcow\Scim\Model\Filter;
use Mailcow\Scim\Model\Group;
use Mailcow\Scim\Model\Schema;
use Mailcow\Scim\Model\Token;
use Mailcow\Scim\Model\User;
use Mailcow\Scim\ScimError;
use Mailcow\Scim\Service\GroupService;
use Mailcow\Scim\Service\TokenService;
use Mailcow\Scim\Service\UserService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ScimControllerTest extends TestCase
{
    private const BASE = 'https://mail.example.org/scim/v2';

    private TokenService&MockObject $tokens;
    private UserService&MockObject $users;
    private GroupService&MockObject $groups;
    private ScimController $controller;

    protected function setUp(): void
    {
        $this->tokens = $this->createMock(TokenService::class);
        $this->tokens->method('authenticate')->willReturnCallback(fn(string $header): Token => ($header === 'Bearer t')
            ? Token::fromRow(['id' => 1]) : throw new ScimError(401, 'Invalid or inactive token'));
        $this->users = $this->createMock(UserService::class);
        $this->groups = $this->createMock(GroupService::class);
        $this->controller = new ScimController($this->tokens, $this->users, $this->groups, self::BASE, null);
    }

    private function call(string $method, string $path, array $query = [], string $body = '', string $auth = 'Bearer t', array $conditions = []): array
    {
        $response = $this->controller->handle($method, $path, $query, $body, 'application/scim+json', $auth, '192.0.2.1', $conditions);
        return [$response->status, $response->body, $response->headers];
    }

    private static function bulk(array $operations, array $extra = []): string
    {
        return json_encode(['schemas' => [Schema::BULK_REQUEST], 'Operations' => $operations] + $extra);
    }

    private static function user(string $id, string $username, int $active = 1): User
    {
        return User::fromRow(['scim_id' => $id, 'username' => $username, 'domain' => 'example.org', 'name' => 'X', 'active' => $active]);
    }

    public function testAuthentication(): void
    {
        [$status, $body, $headers] = $this->call('GET', 'Users', [], '', 'Bearer wrong');
        $this->assertSame([401, '401', Schema::ERROR], [$status, $body['status'], $body['schemas'][0]]);
        $this->assertSame(['WWW-Authenticate: Bearer realm="mailcow SCIM"'], $headers);
        // CORS preflight without credentials
        $this->assertSame(204, $this->call('OPTIONS', 'Users', [], '', '')[0]);
    }

    public function testListFilterAndPagination(): void
    {
        $this->users->method('list')->willReturn([self::user('u-1', 'a@example.org'), self::user('u-2', 'b@example.org'), self::user('u-3', 'c@example.org')]);
        // without filter and sortBy the page comes from SQL
        $this->users->expects($this->once())->method('page')->with($this->anything(), 1, 1)->willReturn([[self::user('u-2', 'b@example.org')], 3]);

        [$status, $body] = $this->call('GET', 'Users', ['startIndex' => '2', 'count' => '1', 'excludedAttributes' => 'emails']);
        $this->assertSame([200, 3, 2, 1], [$status, $body['totalResults'], $body['startIndex'], $body['itemsPerPage']]);
        $this->assertSame('b@example.org', $body['Resources'][0]['userName']);
        $this->assertArrayNotHasKey('emails', $body['Resources'][0]);

        $body = $this->call('GET', 'Users', ['filter' => 'userName eq "C@example.org"'])[1];
        $this->assertSame(['u-3'], array_column($body['Resources'], 'id'));

        $this->assertSame(['invalidFilter'], [$this->call('GET', 'Users', ['filter' => 'nope eq 1'])[1]['scimType']]);
    }

    public function testAFilteredListNarrowsByTheFilter(): void
    {
        $this->users->expects($this->never())->method('page');
        $this->users->expects($this->once())->method('list')
            ->with($this->anything(), $this->callback(fn(?Filter $f): bool => $f !== null && $f->matches(['userName' => 'c@example.org'])
                && !$f->matches(['userName' => 'a@example.org'])))
            ->willReturn([self::user('u-3', 'c@example.org')]);
        $this->assertSame(['u-3'], array_column($this->call('GET', 'Users', ['filter' => 'userName eq "c@example.org"'])[1]['Resources'], 'id'));
    }

    public function testNothingIsWrittenForAnInvalidProjection(): void
    {
        $this->users->expects($this->never())->method('create');
        $this->users->expects($this->never())->method('patch');
        $query = ['attributes' => 'userName', 'excludedAttributes' => 'emails'];
        $this->assertSame(400, $this->call('POST', 'Users', $query, json_encode(['schemas' => [Schema::USER], 'userName' => 'a@example.org']))[0]);
        $this->assertSame(400, $this->call('PATCH', 'Users/u-1', $query, json_encode(['schemas' => [Schema::PATCH],
            'Operations' => [['op' => 'replace', 'path' => 'active', 'value' => false]]]))[0]);
    }

    public function testIfNoneMatchOnAWrite(): void
    {
        $user = self::user('u-1', 'a@example.org');
        $this->users->method('get')->willReturn($user);
        // the service runs the check on the user it read under its lock
        $this->users->method('delete')->willReturnCallback(fn(Token $token, string $id, callable $precondition) => $precondition($user));
        $version = $this->call('GET', 'Users/u-1')[1]['meta']['version'];
        // RFC 7232 3.2: a write whose If-None-Match matches fails
        $this->assertSame(412, $this->call('DELETE', 'Users/u-1', [], '', 'Bearer t', ['if-none-match' => '*'])[0]);
        $this->assertSame(412, $this->call('DELETE', 'Users/u-1', [], '', 'Bearer t', ['if-none-match' => $version])[0]);
    }

    public function testRootSearchTreatsAnAttributeATypeLacksAsUnassigned(): void
    {
        $this->users->method('list')->willReturn([self::user('u-1', 'a@example.org')]);
        $this->groups->method('list')->willReturn([Group::fromRow(['id' => 5, 'scim_id' => 'g-1', 'domain' => 'example.org', 'name' => 'Sales'])]);
        // groups have no userName: all of them match its negation
        $body = $this->call('POST', '.search', [], json_encode(['schemas' => [Schema::SEARCH], 'filter' => 'not (userName pr)']))[1];
        $this->assertSame(['g-1'], array_column($body['Resources'], 'id'));
        // unknown to every type stays an error
        $this->assertSame(400, $this->call('POST', '.search', [], json_encode(['schemas' => [Schema::SEARCH], 'filter' => 'nope pr']))[0]);
    }

    public function testBodyLimit(): void
    {
        $this->assertSame(413, $this->call('PUT', 'Groups/g-1', [], str_repeat('x', Schema::MAX_PAYLOAD + 1))[0]);
    }

    public function testListsAreReadInBatches(): void
    {
        $controller = new ScimController($this->tokens, $this->users, $this->groups, self::BASE, null, 1000, 2);
        $all = array_map(fn(int $i): User => self::user('u-' . $i, chr(ord('a') + $i) . '@example.org', ($i % 2) ? 1 : 2), range(0, 6));
        $this->users->method('list')->willReturnCallback(fn(Token $token, ?Filter $filter, int $offset, int $limit): array => array_slice($all, $offset, $limit));
        $this->users->method('get')->willReturnCallback(fn(Token $token, string $id): User => $all[(int)substr($id, 2)]);
        $list = fn(array $query): array => $controller->handle('GET', 'Users', $query, '', 'application/scim+json', 'Bearer t', '192.0.2.1')->body;

        // the active users are u-1, u-3, u-5: the page crosses a batch boundary
        $body = $list(['filter' => 'active eq true', 'startIndex' => '2', 'count' => '2']);
        $this->assertSame([3, ['u-3', 'u-5']], [$body['totalResults'], array_column($body['Resources'], 'id')]);
        $body = $list(['filter' => 'active eq true', 'sortBy' => 'userName', 'sortOrder' => 'descending', 'count' => '2']);
        $this->assertSame([3, ['u-5', 'u-3']], [$body['totalResults'], array_column($body['Resources'], 'id')]);
    }

    public function testSorting(): void
    {
        $all = ['u-1' => self::user('u-1', 'b@example.org'), 'u-2' => self::user('u-2', 'C@example.org'), 'u-3' => self::user('u-3', 'a@example.org')];
        $this->users->method('list')->willReturn(array_values($all));
        // the sorted page is read again by id
        $this->users->method('get')->willReturnCallback(fn(Token $token, string $id): User => $all[$id]);

        $ids = fn(array $query): array => array_column($this->call('GET', 'Users', $query)[1]['Resources'], 'id');
        $this->assertSame(['u-3', 'u-1', 'u-2'], $ids(['sortBy' => 'userName']));
        $this->assertSame(['u-2', 'u-1', 'u-3'], $ids(['sortBy' => 'emails', 'sortOrder' => 'descending']));
        // sorting comes before pagination
        $this->assertSame(['u-1'], $ids(['sortBy' => 'userName', 'startIndex' => '2', 'count' => '1']));
        $this->assertSame([400, 'invalidValue'], [$this->call('GET', 'Users', ['sortBy' => 'nope'])[0], $this->call('GET', 'Users', ['sortBy' => 'nope'])[1]['scimType']]);
        $this->assertSame(400, $this->call('GET', 'Users', ['sortBy' => 'userName', 'sortOrder' => 'up'])[0]);
    }

    public function testVersionsAndConditionalRequests(): void
    {
        $user = self::user('u-1', 'a@example.org');
        $this->users->method('get')->willReturn($user);
        [$status, $body, $headers] = $this->call('GET', 'Users/u-1');
        $version = $body['meta']['version'];
        $this->assertSame([200, ['ETag: ' . $version]], [$status, $headers]);
        $this->assertMatchesRegularExpression('~^W/"[0-9a-f]{32}"$~', $version);
        // list resources carry the same version
        $this->users->method('page')->willReturn([[$user], 1]);
        $this->assertSame($version, $this->call('GET', 'Users')[1]['Resources'][0]['meta']['version']);

        $this->assertSame([304, null], array_slice($this->call('GET', 'Users/u-1', [], '', 'Bearer t', ['if-none-match' => $version]), 0, 2));
        $this->assertSame(200, $this->call('GET', 'Users/u-1', [], '', 'Bearer t', ['if-none-match' => 'W/"old"'])[0]);

        $put = json_encode(['schemas' => [Schema::USER], 'userName' => 'a@example.org']);
        $this->users->method('replace')->willReturnCallback(function (Token $token, string $id, array $body, callable $precondition) use ($user): User {
            $precondition($user);
            return $user;
        });
        $this->assertSame(412, $this->call('PUT', 'Users/u-1', [], $put, 'Bearer t', ['if-match' => 'W/"old"'])[0]);
        $this->assertSame(200, $this->call('PUT', 'Users/u-1', [], $put, 'Bearer t', ['if-match' => $version])[0]);
    }

    public function testBulk(): void
    {
        $this->users->method('create')->willReturnCallback(fn(Token $token, array $body): User => self::user('u-9', $body['username']));
        // the group references the user created by a later operation
        $this->groups->expects($this->once())->method('create')->with($this->anything(), $this->callback(
            fn(array $body): bool => $body['members'] === [['value' => 'u-9']]))
            ->willReturn(Group::fromRow(['id' => 5, 'scim_id' => 'g-9', 'domain' => 'example.org', 'name' => 'Sales']));
        $this->users->method('delete')->willThrowException(ScimError::notFound('User not found'));

        [$status, $body] = $this->call('POST', 'Bulk', [], self::bulk([
            ['method' => 'POST', 'path' => '/Groups', 'bulkId' => 'g', 'data' => ['schemas' => [Schema::GROUP], 'displayName' => 'Sales',
                'members' => [['value' => 'bulkId:u']]]],
            ['method' => 'POST', 'path' => '/Users', 'bulkId' => 'u', 'data' => ['schemas' => [Schema::USER], 'userName' => 'z@example.org']],
            ['method' => 'DELETE', 'path' => '/Users/u-404'],
        ]));
        $this->assertSame([200, Schema::BULK_RESPONSE], [$status, $body['schemas'][0]]);
        // the group waited for the user
        $this->assertSame(['u', null, 'g'], array_map(fn(array $r): ?string => $r['bulkId'] ?? null, $body['Operations']));
        $this->assertSame(['201', '404', '201'], array_column($body['Operations'], 'status'));
        $this->assertSame(self::BASE . '/Users/u-9', $body['Operations'][0]['location']);
        $this->assertStringStartsWith('W/"', $body['Operations'][0]['version']);
        $this->assertSame([self::BASE . '/Users/u-404', '404'], [$body['Operations'][1]['location'], $body['Operations'][1]['response']['status']]);
    }

    public function testBulkErrors(): void
    {
        $this->users->method('delete')->willThrowException(ScimError::notFound('User not found'));
        $delete = ['method' => 'DELETE', 'path' => '/Users/u-404'];
        // failOnErrors stops after that many errors
        $this->assertCount(1, $this->call('POST', 'Bulk', [], self::bulk([$delete, $delete], ['failOnErrors' => 1]))[1]['Operations']);
        // circular and unknown references are 409
        [, $body] = $this->call('POST', 'Bulk', [], self::bulk([
            ['method' => 'POST', 'path' => '/Groups', 'bulkId' => 'a', 'data' => ['schemas' => [Schema::GROUP], 'displayName' => 'A', 'members' => [['value' => 'bulkId:b']]]],
            ['method' => 'POST', 'path' => '/Groups', 'bulkId' => 'b', 'data' => ['schemas' => [Schema::GROUP], 'displayName' => 'B', 'members' => [['value' => 'bulkId:a']]]],
            ['method' => 'PATCH', 'path' => '/Groups/bulkId:nope', 'data' => ['schemas' => [Schema::PATCH], 'Operations' => []]],
            ['method' => 'POST', 'path' => '/Users', 'data' => []],
            ['method' => 'GET', 'path' => '/Users/u-1'],
        ]));
        // unknown reference, missing bulkId, GET, then the cycle
        $this->assertSame(['409', '400', '400', '409', '409'], array_column($body['Operations'], 'status'));
        $this->assertArrayNotHasKey('location', $body['Operations'][3]);
        // limits (RFC 7644 3.7.4)
        $this->assertSame(413, $this->call('POST', 'Bulk', [], self::bulk(array_fill(0, Schema::BULK_MAX_OPERATIONS + 1, $delete)))[0]);
        $this->assertSame(413, $this->call('POST', 'Bulk', [], self::bulk([$delete], ['pad' => str_repeat('x', Schema::BULK_MAX_PAYLOAD)]))[0]);
    }

    public function testRootSearchCoversUsersAndGroups(): void
    {
        $this->users->method('list')->willReturn([self::user('u-1', 'sales@example.org')]);
        $group = Group::fromRow(['id' => 5, 'scim_id' => 'g-1', 'domain' => 'example.org', 'name' => 'Sales']);
        $this->groups->method('list')->willReturn([$group]);

        // displayName is known to both types, userName only to users
        $body = $this->call('POST', '.search', [], json_encode(['schemas' => [Schema::SEARCH], 'filter' => 'displayName eq "Sales"']))[1];
        $this->assertSame(['g-1'], array_column($body['Resources'], 'id'));
        $body = $this->call('POST', '.search', [], json_encode(['schemas' => [Schema::SEARCH], 'filter' => 'userName sw "sales"']))[1];
        $this->assertSame(['u-1'], array_column($body['Resources'], 'id'));
    }

    public function testCreateAnswers201WithLocation(): void
    {
        $this->users->method('create')->willReturn(self::user('u-1', 'a@example.org'));
        [$status, $body, $headers] = $this->call('POST', 'Users', [], json_encode(['schemas' => [Schema::USER], 'userName' => 'a@example.org']));
        $this->assertSame(201, $status);
        $this->assertSame(['Location: ' . self::BASE . '/Users/u-1', 'ETag: ' . $body['meta']['version']], $headers);
        $this->assertSame('User', $body['meta']['resourceType']);
    }

    public function testGroupPatchAnswers204UnlessAttributesAreRequested(): void
    {
        $this->groups->method('patch')->willReturn(Group::fromRow(['id' => 5, 'scim_id' => 'g-1', 'domain' => 'example.org', 'name' => 'Sales']));
        $patch = json_encode(['schemas' => [Schema::PATCH], 'Operations' => [['op' => 'replace', 'path' => 'displayName', 'value' => 'Sales']]]);
        $this->assertSame(204, $this->call('PATCH', 'Groups/g-1', [], $patch)[0]);
        [$status, $body] = $this->call('PATCH', 'Groups/g-1', ['attributes' => 'displayName'], $patch);
        $this->assertSame([200, 'Sales'], [$status, $body['displayName']]);
    }

    public function testDeleteAnswers204(): void
    {
        $this->groups->method('get')->willReturn(Group::fromRow(['id' => 5, 'scim_id' => 'g-1', 'domain' => 'example.org', 'name' => 'Sales']));
        $this->groups->expects($this->once())->method('delete')->with($this->anything(), 'g-1');
        $this->assertSame([204, null], array_slice($this->call('DELETE', 'Groups/g-1'), 0, 2));
    }

    public function testProtocolErrors(): void
    {
        [$status, , $headers] = $this->call('DELETE', 'Users');
        $this->assertSame([405, ['Allow: GET, POST, OPTIONS']], [$status, $headers]);
        $this->assertSame(415, $this->controller->handle('POST', 'Users', [], '{}', 'text/plain', 'Bearer t', '192.0.2.1')->status);
        $this->assertSame([400, 'invalidSyntax'], [$this->call('POST', 'Users', [], '{nope')[0], $this->call('POST', 'Users', [], '{nope')[1]['scimType']]);
        $this->assertSame(403, $this->call('GET', 'Schemas', ['filter' => 'id pr'])[0]);
        $this->assertSame(404, $this->call('GET', 'Nope')[0]);
        $this->assertSame(501, $this->call('GET', 'Me')[0]);
        $this->assertSame(405, $this->call('GET', 'Bulk')[0]);
        $this->assertSame(Schema::GROUP, $this->call('GET', 'Schemas/' . Schema::GROUP)[1]['id']);
        $this->assertSame(['User', 'Group'], array_column($this->call('GET', 'ResourceTypes')[1]['Resources'], 'id'));
        $this->users->method('get')->willThrowException(new \RuntimeException('boom'));
        $this->assertSame([500, 'Internal server error'], [$this->call('GET', 'Users/u-1')[0], $this->call('GET', 'Users/u-1')[1]['detail']]);
    }
}
