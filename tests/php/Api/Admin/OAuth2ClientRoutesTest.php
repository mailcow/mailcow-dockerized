<?php

declare(strict_types=1);

namespace Mailcow\Tests\Api\Admin;

use Mailcow\Tests\Api\ApiTestCase;

/** Routes oauth2-client */
class OAuth2ClientRoutesTest extends ApiTestCase
{
    private const CLIENT = ['id' => 1, 'client_id' => '17c76aaa88c0', 'client_secret' => '73fc668a88147e32a31ff80c',
        'redirect_uri' => 'https://app.example.org/oauth/callback', 'grant_types' => null, 'scope' => 'profile', 'user_id' => null];

    public function testGetAllCollectsTheDetailsOfEveryClient(): void
    {
        $result = $this->get('get/oauth2-client/all', ['script' => [
            self::answer('oauth2', ['get', 'clients'], [1, 2]),
            self::answer('oauth2', ['details', 'client', 1], self::CLIENT),
            self::answer('oauth2', ['details', 'client', 2], false),
        ]]);

        $this->assertSame([self::CLIENT], $result->json());
        $this->assertSame([['get', 'clients'], ['details', 'client', 1], ['details', 'client', 2]], $result->callsTo('oauth2'));
    }

    public function testGetAllWithoutClients(): void
    {
        $this->assertSame('{}', $this->get('get/oauth2-client/all', ['script' => [self::answer('oauth2', ['get', 'clients'], null)]])->body);
    }

    public function testGetOne(): void
    {
        $found = $this->get('get/oauth2-client/1', ['script' => [self::answer('oauth2', ['details', 'client', '1'], self::CLIENT)]]);
        $this->assertSame(self::CLIENT, $found->json());

        $missing = $this->get('get/oauth2-client/9', ['script' => [self::answer('oauth2', ['details'], false)]]);
        $this->assertSame('{}', $missing->body);
        $this->assertSame([['details', 'client', '9']], $missing->callsTo('oauth2'));
    }

    public function testAdd(): void
    {
        $result = $this->post('add/oauth2-client', ['redirect_uri' => 'https://app.example.org/oauth/callback']);

        $this->assertSame([['add', 'client', ['redirect_uri' => 'https://app.example.org/oauth/callback']]], $result->callsTo('oauth2'));
    }

    public function testEdit(): void
    {
        $result = $this->post('edit/oauth2-client', ['items' => [1, 2], 'attr' => ['renew_secret' => 1]]);

        $this->assertSame([['edit', 'client', ['id' => [1, 2], 'renew_secret' => 1]]], $result->callsTo('oauth2'));
    }

    public function testDelete(): void
    {
        $result = $this->post('delete/oauth2-client', [1, 2]);

        $this->assertSame([['delete', 'client', ['id' => [1, 2]]]], $result->callsTo('oauth2'));
    }
}
