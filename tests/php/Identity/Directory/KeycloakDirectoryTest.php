<?php

declare(strict_types=1);

namespace Mailcow\Tests\Identity\Directory;

use Mailcow\Identity\Directory\DirectoryException;
use Mailcow\Identity\Directory\KeycloakDirectory;
use Mailcow\Identity\Http\KeycloakClient;
use Mailcow\Identity\Model\Settings;
use PHPUnit\Framework\TestCase;

class KeycloakDirectoryTest extends TestCase
{
    public function testListsAllPagesOfUsers(): void
    {
        $users = [];
        for ($i = 1; $i <= 150; $i++) {
            $users[] = ['id' => "u$i", 'email' => "User$i@Example.org", 'firstName' => 'User', 'lastName' => (string)$i, 'enabled' => $i !== 2,
                'attributes' => ['mailcow_template' => ['staff']]];
        }
        $client = new FakeKeycloakClient(['/users' => $users]);

        $listing = (new KeycloakDirectory($client, Settings::fromArray([])))->listAll(false);

        $this->assertCount(150, $listing->users);
        $this->assertNull($listing->groups);
        $this->assertSame('user1@example.org', $listing->users['u1']->email);
        $this->assertSame('User 1', $listing->users['u1']->name);
        $this->assertSame(['staff'], $listing->users['u1']->templateValues);
        $this->assertFalse($listing->users['u2']->enabled);
        $this->assertSame(['/users?briefRepresentation=false&first=0&max=100', '/users?briefRepresentation=false&first=100&max=100'],
            $client->paths());
    }

    public function testSelectsGroupsByPathWithChildrenAndListedMembers(): void
    {
        $client = new FakeKeycloakClient([
            '/users' => [['id' => 'u1', 'email' => 'a@example.org', 'enabled' => true], ['id' => 'u2', 'email' => 'b@example.org', 'enabled' => true]],
            '/groups' => [
                ['id' => 'g1', 'name' => 'mailcow', 'path' => '/mailcow', 'subGroupCount' => 2, 'subGroups' => []],
                ['id' => 'g4', 'name' => 'Other', 'path' => '/Other', 'subGroups' => []],
            ],
            '/groups/g1/children' => [
                ['id' => 'g2', 'name' => 'Sales', 'path' => '/mailcow/Sales'],
                ['id' => 'g3', 'name' => 'Support', 'path' => '/mailcow/Support', 'attributes' => ['mail' => ['Support@Example.org']]],
            ],
            '/groups/g2/members' => [['id' => 'u1'], ['id' => 'u2'], ['id' => 'not-listed']],
            '/groups/g3/members' => [],
            '/groups/g4/members' => [],
        ]);

        $listing = (new KeycloakDirectory($client, Settings::fromArray(['group_paths' => '/mailcow/*, /Other'])))->listAll(true);

        $this->assertSame(['g2', 'g3', 'g4'], array_keys($listing->groups));
        $this->assertSame(['u1', 'u2'], $listing->groups['g2']->memberIds);
        $this->assertSame('support@example.org', $listing->groups['g3']->mail);
        $this->assertSame('Other', $listing->groups['g4']->name);
    }

    public function testRejectedTokenIsRenewedOnce(): void
    {
        $client = new FakeKeycloakClient(['/users' => []], 1);

        (new KeycloakDirectory($client, Settings::fromArray([])))->listAll(false);

        $this->assertSame(2, $client->tokens);
    }

    public function testHttpErrorsAbortTheListing(): void
    {
        $client = new FakeKeycloakClient(['/users' => 500]);

        $this->expectException(DirectoryException::class);
        $this->expectExceptionMessage('Keycloak returned HTTP 500 for /users');
        (new KeycloakDirectory($client, Settings::fromArray([])))->listAll(false);
    }
}

/**
 * Keycloak admin API in memory: routes are paths below the realm with a list (paged by first/max) or an HTTP status.
 */
class FakeKeycloakClient extends KeycloakClient
{
    public int $tokens = 0;
    private array $requests = [];

    public function __construct(private array $routes, private int $rejectTokens = 0)
    {
        parent::__construct('https://kc.example.org', 'mailcow', 'mailcow', 'secret');
    }

    /** @return string[] requested paths with query, token requests excluded */
    public function paths(): array
    {
        return $this->requests;
    }

    protected function send(string $method, string $url, array $headers, ?string $body): array
    {
        if (str_ends_with($url, '/protocol/openid-connect/token')) {
            $this->tokens++;
            return [200, json_encode(['access_token' => 'token' . $this->tokens])];
        }
        if ($this->rejectTokens > 0 && in_array('Authorization: Bearer token1', $headers, true)) {
            return [401, ''];
        }
        $path = substr((string)parse_url($url, PHP_URL_PATH), strlen('/admin/realms/mailcow'));
        parse_str((string)parse_url($url, PHP_URL_QUERY), $query);
        $this->requests[] = $path . '?' . http_build_query($query);
        $route = $this->routes[$path] ?? 404;
        if (is_int($route)) {
            return [$route, ''];
        }
        return [200, json_encode(array_slice($route, (int)($query['first'] ?? 0), (int)($query['max'] ?? count($route))))];
    }
}
