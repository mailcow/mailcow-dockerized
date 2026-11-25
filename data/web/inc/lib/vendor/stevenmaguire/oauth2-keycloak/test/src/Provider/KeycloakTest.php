<?php

namespace
{
    $mockFileGetContents = null;
}

namespace Stevenmaguire\OAuth2\Client\Provider
{
    function file_get_contents(...$args): string|false
    {
        global $mockFileGetContents;
        if (isset($mockFileGetContents) && ! is_null($mockFileGetContents)) {
            if (is_a($mockFileGetContents, 'Exception')) {
                throw $mockFileGetContents;
            }
            return $mockFileGetContents;
        } else {
            return \file_get_contents(...$args);
        }
    }
}

namespace Stevenmaguire\OAuth2\Client\Test\Provider
{
    use DateInterval;
    use DateTimeImmutable;
    use Firebase\JWT\JWT;
    use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
    use League\OAuth2\Client\Token\AccessToken;
    use League\OAuth2\Client\Tool\QueryBuilderTrait;
    use Mockery as m;
    use PHPUnit\Framework\TestCase;
    use Psr\Http\Message\StreamInterface;
    use Stevenmaguire\OAuth2\Client\Provider\Exception\EncryptionConfigurationException;
    use Stevenmaguire\OAuth2\Client\Provider\Keycloak;

    class KeycloakTest extends TestCase
    {
        use QueryBuilderTrait;

        public const ENCRYPTION_KEY = <<<EOD
-----BEGIN PUBLIC KEY-----
MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQC8kGa1pSjbSYZVebtTRBLxBz5H
4i2p/llLCrEeQhta5kaQu/RnvuER4W8oDH3+3iuIYW4VQAzyqFpwuzjkDI+17t5t
0tyazyZ8JXw+KgXTxldMPEL95+qVhgXvwtihXC1c5oGbRlEDvDF6Sa53rcFVsYJ4
ehde/zUxo6UvS7UrBQIDAQAB
-----END PUBLIC KEY-----
EOD;

        public const ENCRYPTION_ALGORITHM = 'HS256';

        private string $jwtTemplate = <<<EOF
{
  "exp": "%s",
  "iat": "%s",
  "jti": "e11a85c8-aa91-4f75-9088-57db4586f8b9",
  "iss": "https://example.org/auth/realms/test-realm",
  "aud": "account",
  "nbf": "%s",
  "sub": "4332085e-b944-4acc-9eb1-27d8f5405f3e",
  "typ": "Bearer",
  "azp": "test-app",
  "session_state": "c90c8e0d-aabb-4c71-b8a8-e88792cacd96",
  "acr": "1",
  "realm_access": {
    "roles": [
      "default-roles-test-realm",
      "offline_access",
      "uma_authorization"
    ]
  },
  "resource_access": {
    "account": {
      "roles": [
        "manage-account",
        "manage-account-links",
        "view-profile"
      ]
    }
  },
  "scope": "openid email profile",
  "sid": "c90c8e0d-aabb-4c71-b8a8-e88792cacd96",
  "address": {},
  "email_verified": true,
  "name": "Test User",
  "preferred_username": "test-user",
  "given_name": "Test",
  "family_name": "User",
  "email": "test-user@example.org"
}
EOF;

        protected Keycloak $provider;

        protected function setUp(): void
        {
            $this->provider = new Keycloak([
                'authServerUrl' => 'http://mock.url/auth',
                'realm' => 'mock_realm',
                'clientId' => 'mock_client_id',
                'clientSecret' => 'mock_secret',
                'redirectUri' => 'none',
            ]);
        }

        public function tearDown(): void
        {
            m::close();
            parent::tearDown();
        }

        public function testAuthorizationUrl(): void
        {
            $url = $this->provider->getAuthorizationUrl();
            $uri = parse_url($url);
            parse_str($uri['query'], $query);

            $this->assertArrayHasKey('client_id', $query);
            $this->assertArrayHasKey('redirect_uri', $query);
            $this->assertArrayHasKey('state', $query);
            $this->assertArrayHasKey('scope', $query);
            $this->assertArrayHasKey('response_type', $query);
            $this->assertArrayHasKey('approval_prompt', $query);
            $this->assertNotNull($this->provider->getState());
        }

        public function testEncryptionAlgorithm(): void
        {
            $algorithm = uniqid();
            $provider = new Keycloak([
                'encryptionAlgorithm' => $algorithm,
            ]);

            $this->assertEquals($algorithm, $provider->encryptionAlgorithm);

            $algorithm = uniqid();
            $provider->setEncryptionAlgorithm($algorithm);

            $this->assertEquals($algorithm, $provider->encryptionAlgorithm);
        }

        public function testEncryptionKey(): void
        {
            $key = uniqid();
            $provider = new Keycloak([
                'encryptionKey' => $key,
            ]);

            $this->assertEquals($key, $provider->encryptionKey);

            $key = uniqid();
            $provider->setEncryptionKey($key);

            $this->assertEquals($key, $provider->encryptionKey);
        }

        public function testEncryptionKeyPath(): void
        {
            global $mockFileGetContents;
            $path = uniqid();
            $key = uniqid();
            $mockFileGetContents = $key;

            $provider = new Keycloak([
                'encryptionKeyPath' => $path,
            ]);

            $this->assertEquals($key, $provider->encryptionKey);

            $path = uniqid();
            $key = uniqid();
            $mockFileGetContents = $key;

            $provider->setEncryptionKeyPath($path);

            $this->assertEquals($key, $provider->encryptionKey);
        }

        public function testEncryptionKeyPathFails(): void
        {
            $this->markTestIncomplete('Need to assess the test to see what is required to be checked.');

            global $mockFileGetContents;
            $path = uniqid();
            $key = uniqid();
            $mockFileGetContents = new \Exception();

            $provider = new Keycloak([
                'encryptionKeyPath' => $path,
            ]);

            $provider->setEncryptionKeyPath($path);
        }

        public function testScopes(): void
        {
            $scopeSeparator = ' ';
            $options = ['scope' => [uniqid(), uniqid()]];
            $query = ['scope' => implode($scopeSeparator, $options['scope'])];
            $url = $this->provider->getAuthorizationUrl($options);
            $encodedScope = $this->buildQueryString($query);
            $this->assertStringContainsString($encodedScope, $url);
        }

        public function testGetAuthorizationUrl(): void
        {
            $url = $this->provider->getAuthorizationUrl();
            $uri = parse_url($url);

            $this->assertEquals('/auth/realms/mock_realm/protocol/openid-connect/auth', $uri['path']);
        }

        public function testGetLogoutUrl(): void
        {
            $url = $this->provider->getLogoutUrl();
            $uri = parse_url($url);

            $this->assertEquals('/auth/realms/mock_realm/protocol/openid-connect/logout', $uri['path']);
        }

        public function testGetLogoutUrlWithIdTokenHint(): void
        {
            $this->provider->setVersion('18.0.0');

            $options = [
                'access_token' => new AccessToken(
                    [
                        'id_token' => 'the_id_token',
                        'access_token' => 'the_access_token',
                    ]
                ),
            ];
            $url = $this->provider->getLogoutUrl($options);
            $uri = parse_url($url);

            $this->assertEquals('/auth/realms/mock_realm/protocol/openid-connect/logout', $uri['path']);
            $this->assertStringContainsString('id_token_hint=the_id_token', $uri['query']);
        }

        public function testGetBaseAccessTokenUrl(): void
        {
            $params = [];

            $url = $this->provider->getBaseAccessTokenUrl($params);
            $uri = parse_url($url);

            $this->assertEquals('/auth/realms/mock_realm/protocol/openid-connect/token', $uri['path']);
        }

        public function testGetAccessToken(): void
        {
            $stream = $this->createMock(StreamInterface::class);
            $stream
                ->method('__toString')
                ->willReturn('{"access_token":"mock_access_token","scope":"email","token_type":"bearer"}');

            $response = m::mock('Psr\Http\Message\ResponseInterface');
            $response
                ->shouldReceive('getBody')
                ->andReturn($stream);
            $response
                ->shouldReceive('getHeader')
                ->andReturn(['content-type' => 'json']);

            $client = m::mock('GuzzleHttp\ClientInterface');
            $client->shouldReceive('send')
                ->times(1)
                ->andReturn($response);
            $this->provider->setHttpClient($client);

            $token = $this
                ->provider
                ->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);

            $this->assertEquals('mock_access_token', $token->getToken());
            $this->assertNull($token->getExpires());
            $this->assertNull($token->getRefreshToken());
            $this->assertNull($token->getResourceOwnerId());
        }

        public function testUserData(): void
        {
            $userId = rand(1000, 9999);
            $name = uniqid();
            $email = uniqid();
            $username = uniqid();
            $firstName = uniqid();
            $lastName = uniqid();

            $getAccessTokenResponseStream = $this->createMock(StreamInterface::class);
            $getAccessTokenResponseStream
                ->method('__toString')
                ->willReturn(
                    '{"access_token":"mock_access_token","expires":"3600","refresh_token":"mock_refresh_token","otherKey":[1234]}'
                );

            $postResponse = m::mock('Psr\Http\Message\ResponseInterface');
            $postResponse
                ->shouldReceive('getBody')
                ->andReturn($getAccessTokenResponseStream);
            $postResponse
                ->shouldReceive('getHeader')
                ->andReturn(['content-type' => 'json']);

            $getResourceOwnerResponseStream = $this->createMock(StreamInterface::class);
            $getResourceOwnerResponseStream
                ->method('__toString')
                ->willReturn(
                    sprintf(
                        '{"sub": "%s", "name": "%s", "email": "%s", "preferred_username": "%s", "given_name": "%s", "family_name": "%s"}',
                        $userId,
                        $name,
                        $email,
                        $username,
                        $firstName,
                        $lastName
                    )
                );

            $userResponse = m::mock('Psr\Http\Message\ResponseInterface');
            $userResponse
                ->shouldReceive('getBody')
                ->andReturn($getResourceOwnerResponseStream);
            $userResponse
                ->shouldReceive('getHeader')
                ->andReturn(['content-type' => 'json']);

            $client = m::mock('GuzzleHttp\ClientInterface');
            $client
                ->shouldReceive('send')
                ->andReturn($postResponse, $userResponse);
            $this->provider->setHttpClient($client);

            $token = $this->provider->getAccessToken(
                'authorization_code',
                [
                    'code' => 'mock_authorization_code',
                    'access_token' => 'mock_access_token',
                ]
            );
            $user = $this->provider->getResourceOwner($token);

            $this->assertEquals($userId, $user->getId());
            $this->assertEquals($userId, $user->toArray()['sub']);
            $this->assertEquals($name, $user->getName());
            $this->assertEquals($name, $user->toArray()['name']);
            $this->assertEquals($email, $user->getEmail());
            $this->assertEquals($email, $user->toArray()['email']);
            $this->assertEquals($username, $user->getUsername());
            $this->assertEquals($username, $user->toArray()['preferred_username']);
            $this->assertEquals($firstName, $user->getFirstName());
            $this->assertEquals($firstName, $user->toArray()['given_name']);
            $this->assertEquals($lastName, $user->getLastName());
            $this->assertEquals($lastName, $user->toArray()['family_name']);
        }

        public function testUserDataWithEncryption(): void
        {
            $jwt = JWT::encode(
                json_decode(
                    sprintf(
                        $this->jwtTemplate,
                        (new DateTimeImmutable())->add(new DateInterval('PT1H'))->getTimestamp(),
                        (new DateTimeImmutable())->sub(new DateInterval('P1D'))->getTimestamp(),
                        (new DateTimeImmutable())->sub(new DateInterval('P1D'))->getTimestamp()
                    ),
                    true
                ),
                self::ENCRYPTION_KEY,
                self::ENCRYPTION_ALGORITHM
            );

            $getAccessTokenResponseStream = $this->createMock(StreamInterface::class);
            $getAccessTokenResponseStream
                ->method('__toString')
                ->willReturn(
                    sprintf(
                        '{"access_token":"%s","expires":"3600","refresh_token":"mock_refresh_token","otherKey":[1234]}',
                        $jwt
                    )
                );

            $accessTokenResponse = m::mock('Psr\Http\Message\ResponseInterface');
            $accessTokenResponse
                ->shouldReceive('getBody')
                ->andReturn($getAccessTokenResponseStream);
            $accessTokenResponse
                ->shouldReceive('getHeader')
                ->andReturn(['content-type' => 'json']);
            $accessTokenResponse
                ->shouldReceive('getStatusCode')
                ->andReturn(200);

            $getResourceOwnerResponseStream = $this->createMock(StreamInterface::class);
            $getResourceOwnerResponseStream
                ->method('__toString')
                ->willReturn($jwt);

            $resourceOwnerResponse = m::mock('Psr\Http\Message\ResponseInterface');
            $resourceOwnerResponse
                ->shouldReceive('getBody')
                ->andReturn($getResourceOwnerResponseStream);
            $resourceOwnerResponse
                ->shouldReceive('getHeader')
                ->andReturn(['content-type' => 'application/jwt']);
            $resourceOwnerResponse
                ->shouldReceive('getStatusCode')
                ->andReturn(200);

            $client = m::mock('GuzzleHttp\ClientInterface');
            $client
                ->shouldReceive('send')
                ->times(2)
                ->andReturn($accessTokenResponse, $resourceOwnerResponse);
            $this->provider->setHttpClient($client);

            $token = $this
                ->provider
                ->setEncryptionAlgorithm(self::ENCRYPTION_ALGORITHM)
                ->setEncryptionKey(self::ENCRYPTION_KEY)
                ->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
            $user = $this->provider->getResourceOwner($token);

            $email = "test-user@example.org";
            $name = "Test User";
            $userId = "4332085e-b944-4acc-9eb1-27d8f5405f3e";
            $username = "test-user";
            $firstName = "Test";
            $lastName = "User";

            $this->assertEquals($userId, $user->getId());
            $this->assertEquals($userId, $user->toArray()['sub']);
            $this->assertEquals($name, $user->getName());
            $this->assertEquals($name, $user->toArray()['name']);
            $this->assertEquals($email, $user->getEmail());
            $this->assertEquals($email, $user->toArray()['email']);
            $this->assertEquals($username, $user->getUsername());
            $this->assertEquals($username, $user->toArray()['preferred_username']);
            $this->assertEquals($firstName, $user->getFirstName());
            $this->assertEquals($firstName, $user->toArray()['given_name']);
            $this->assertEquals($lastName, $user->getLastName());
            $this->assertEquals($lastName, $user->toArray()['family_name']);
        }

        public function testUserDataFailsWhenEncryptionEncounteredAndNotConfigured(): void
        {
            $this->expectException(EncryptionConfigurationException::class);

            $accessTokenResponseStream = $this->createMock(StreamInterface::class);
            $accessTokenResponseStream
                ->method('__toString')
                ->willReturn(
                    '{"access_token":"mock_access_token","expires":"3600","refresh_token":"mock_refresh_token","otherKey":[1234]}'
                );

            $getAccessTokenResponse = m::mock('Psr\Http\Message\ResponseInterface');
            $getAccessTokenResponse
                ->shouldReceive('getBody')
                ->andReturn($accessTokenResponseStream);
            $getAccessTokenResponse
                ->shouldReceive('getHeader')
                ->andReturn(['content-type' => 'json']);
            $getAccessTokenResponse
                ->shouldReceive('getStatusCode')
                ->andReturn(200);

            $resourceOwnerResponseStream = $this->createMock(StreamInterface::class);
            $resourceOwnerResponseStream
                ->method('__toString')
                ->willReturn(uniqid());

            $getResourceOwnerResponse = m::mock('Psr\Http\Message\ResponseInterface');
            $getResourceOwnerResponse
                ->shouldReceive('getBody')
                ->andReturn($resourceOwnerResponseStream);
            $getResourceOwnerResponse
                ->shouldReceive('getHeader')
                ->andReturn(['content-type' => 'application/jwt']);
            $getResourceOwnerResponse
                ->shouldReceive('getStatusCode')
                ->andReturn(200);

            $client = m::mock('GuzzleHttp\ClientInterface');
            $client
                ->shouldReceive('send')
                ->times(2)
                ->andReturn($getAccessTokenResponse, $getResourceOwnerResponse);
            $this->provider->setHttpClient($client);

            $token = $this->provider->getAccessToken(
                'authorization_code', #
                ['code' => 'mock_authorization_code']
            );
            $user = $this->provider->getResourceOwner($token);
        }

        public function testErrorResponse(): void
        {
            $this->expectException(IdentityProviderException::class);

            $accessTokenResponseStream = $this->createMock(StreamInterface::class);
            $accessTokenResponseStream
                ->method('__toString')
                ->willReturn(
                    '{"error": "invalid_grant", "error_description": "Code not found"}'
                );

            $response = m::mock('Psr\Http\Message\ResponseInterface');
            $response
                ->shouldReceive('getBody')
                ->andReturn($accessTokenResponseStream);
            $response
                ->shouldReceive('getHeader')
                ->andReturn(['content-type' => 'json']);
            $response
                ->shouldReceive('getStatusCode')
                ->andReturn(401);

            $client = m::mock('GuzzleHttp\ClientInterface');
            $client
                ->shouldReceive('send')
                ->times(1)
                ->andReturn($response);
            $this->provider->setHttpClient($client);

            $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
        }

        public function testCanDecryptResponseThrowsExceptionIfResponseIsNotAStringAndEncryptionIsNotUsed(): void
        {
            $this->expectException(EncryptionConfigurationException::class);

            $this->provider->decryptResponse('');

            $this->assertFalse($this->provider->usesEncryption());
        }

        public function testCanDecryptResponseReturnsResponseWhenEncryptionIsUsed(): void
        {
            $jwtPayload = json_decode(
                sprintf(
                    $this->jwtTemplate,
                    (new DateTimeImmutable())->add(new DateInterval('PT1H'))->getTimestamp(),
                    (new DateTimeImmutable())->sub(new DateInterval('P1D'))->getTimestamp(),
                    (new DateTimeImmutable())->sub(new DateInterval('P1D'))->getTimestamp()
                ),
                true
            );
            $jwt = JWT::encode(
                $jwtPayload,
                self::ENCRYPTION_KEY,
                self::ENCRYPTION_ALGORITHM
            );

            $this->provider
                ->setEncryptionAlgorithm(self::ENCRYPTION_ALGORITHM)
                ->setEncryptionKey(self::ENCRYPTION_KEY);

            $response = $this->provider->decryptResponse($jwt);

            $this->assertSame($jwtPayload, $response);
        }
    }
}
