<?php

namespace
{
    $mockFileGetContents = null;
}

namespace Stevenmaguire\OAuth2\Client\Provider
{
    function file_get_contents()
    {
        global $mockFileGetContents;
        if (isset($mockFileGetContents) && ! is_null($mockFileGetContents)) {
            if (is_a($mockFileGetContents, 'Exception')) {
                throw $mockFileGetContents;
            }
            return $mockFileGetContents;
        } else {
            return call_user_func_array('\file_get_contents', func_get_args());
        }
    }
}

namespace Stevenmaguire\OAuth2\Client\Test\Provider
{
    use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
    use League\OAuth2\Client\Tool\QueryBuilderTrait;
    use Mockery as m;
    use PHPUnit\Framework\TestCase;
    use Stevenmaguire\OAuth2\Client\Provider\Exception\EncryptionConfigurationException;
    use Stevenmaguire\OAuth2\Client\Provider\Keycloak;

    class KeycloakTest extends TestCase
    {
        use QueryBuilderTrait;

        protected $provider;

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

        public function testAuthorizationUrl()
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

        public function testEncryptionAlgorithm()
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

        public function testEncryptionKey()
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

        public function testEncryptionKeyPath()
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

        public function testEncryptionKeyPathFails()
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

        public function testScopes()
        {
            $scopeSeparator = ' ';
            $options = ['scope' => [uniqid(), uniqid()]];
            $query = ['scope' => implode($scopeSeparator, $options['scope'])];
            $url = $this->provider->getAuthorizationUrl($options);
            $encodedScope = $this->buildQueryString($query);
            $this->assertStringContainsString($encodedScope, $url);
        }

        public function testGetAuthorizationUrl()
        {
            $url = $this->provider->getAuthorizationUrl();
            $uri = parse_url($url);

            $this->assertEquals('/auth/realms/mock_realm/protocol/openid-connect/auth', $uri['path']);
        }

        public function testGetLogoutUrl()
        {
            $url = $this->provider->getLogoutUrl();
            $uri = parse_url($url);

            $this->assertEquals('/auth/realms/mock_realm/protocol/openid-connect/logout', $uri['path']);
        }

        public function testGetBaseAccessTokenUrl()
        {
            $params = [];

            $url = $this->provider->getBaseAccessTokenUrl($params);
            $uri = parse_url($url);

            $this->assertEquals('/auth/realms/mock_realm/protocol/openid-connect/token', $uri['path']);
        }

        public function testGetAccessToken()
        {
            $response = m::mock('Psr\Http\Message\ResponseInterface');
            $response->shouldReceive('getBody')
                ->andReturn('{"access_token":"mock_access_token", "scope":"email", "token_type":"bearer"}');
            $response->shouldReceive('getHeader')
                ->andReturn(['content-type' => 'json']);

            $client = m::mock('GuzzleHttp\ClientInterface');
            $client->shouldReceive('send')
                ->times(1)
                ->andReturn($response);
            $this->provider->setHttpClient($client);

            $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);

            $this->assertEquals('mock_access_token', $token->getToken());
            $this->assertNull($token->getExpires());
            $this->assertNull($token->getRefreshToken());
            $this->assertNull($token->getResourceOwnerId());
        }

        public function testUserData()
        {
            $userId = rand(1000, 9999);
            $name = uniqid();
            $nickname = uniqid();
            $email = uniqid();

            $postResponse = m::mock('Psr\Http\Message\ResponseInterface');
            $postResponse->shouldReceive('getBody')
                ->andReturn(
                    'access_token=mock_access_token&expires=3600&refresh_token=mock_refresh_token&otherKey={1234}'
                );
            $postResponse->shouldReceive('getHeader')
                ->andReturn(['content-type' => 'application/x-www-form-urlencoded']);

            $userResponse = m::mock('Psr\Http\Message\ResponseInterface');
            $userResponse->shouldReceive('getBody')
                ->andReturn('{"sub": '.$userId.', "name": "'.$name.'", "email": "'.$email.'"}');
            $userResponse->shouldReceive('getHeader')
                ->andReturn(['content-type' => 'json']);

            $client = m::mock('GuzzleHttp\ClientInterface');
            $client->shouldReceive('send')
                ->times(2)
                ->andReturn($postResponse, $userResponse);
            $this->provider->setHttpClient($client);

            $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
            $user = $this->provider->getResourceOwner($token);

            $this->assertEquals($userId, $user->getId());
            $this->assertEquals($userId, $user->toArray()['sub']);
            $this->assertEquals($name, $user->getName());
            $this->assertEquals($name, $user->toArray()['name']);
            $this->assertEquals($email, $user->getEmail());
            $this->assertEquals($email, $user->toArray()['email']);
        }

        public function testUserDataWithEncryption()
        {
            $userId = rand(1000, 9999);
            $name = uniqid();
            $nickname = uniqid();
            $email = uniqid();
            $jwt = uniqid();
            $algorithm = uniqid();
            $key = uniqid();

            $postResponse = m::mock('Psr\Http\Message\ResponseInterface');
            $postResponse->shouldReceive('getBody')
                ->andReturn(
                    'access_token=mock_access_token&expires=3600&refresh_token=mock_refresh_token&otherKey={1234}'
                );
            $postResponse->shouldReceive('getHeader')
                ->andReturn(['content-type' => 'application/x-www-form-urlencoded']);
            $postResponse->shouldReceive('getStatusCode')
                ->andReturn(200);

            $userResponse = m::mock('Psr\Http\Message\ResponseInterface');
            $userResponse->shouldReceive('getBody')
                ->andReturn($jwt);
            $userResponse->shouldReceive('getHeader')
                ->andReturn(['content-type' => 'application/jwt']);
            $userResponse->shouldReceive('getStatusCode')
                ->andReturn(200);

            $decoder = \Mockery::mock('overload:Firebase\JWT\JWT');
            $decoder->shouldReceive('decode')
                ->with($jwt, $key, [$algorithm])
                ->andReturn([
                    'sub' => $userId,
                    'email' => $email,
                    'name' => $name,
                ]);

            $client = m::mock('GuzzleHttp\ClientInterface');
            $client->shouldReceive('send')
                ->times(2)
                ->andReturn($postResponse, $userResponse);
            $this->provider->setHttpClient($client);

            $token = $this->provider->setEncryptionAlgorithm($algorithm)
                ->setEncryptionKey($key)
                ->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
            $user = $this->provider->getResourceOwner($token);

            $this->assertEquals($userId, $user->getId());
            $this->assertEquals($userId, $user->toArray()['sub']);
            $this->assertEquals($name, $user->getName());
            $this->assertEquals($name, $user->toArray()['name']);
            $this->assertEquals($email, $user->getEmail());
            $this->assertEquals($email, $user->toArray()['email']);
        }

        public function testUserDataFailsWhenEncryptionEncounteredAndNotConfigured()
        {
            $this->expectException(EncryptionConfigurationException::class);

            $postResponse = m::mock('Psr\Http\Message\ResponseInterface');
            $postResponse->shouldReceive('getBody')
                ->andReturn(
                    'access_token=mock_access_token&expires=3600&refresh_token=mock_refresh_token&otherKey={1234}'
                );
            $postResponse->shouldReceive('getHeader')
                ->andReturn(['content-type' => 'application/x-www-form-urlencoded']);
            $postResponse->shouldReceive('getStatusCode')
                ->andReturn(200);

            $userResponse = m::mock('Psr\Http\Message\ResponseInterface');
            $userResponse->shouldReceive('getBody')
                ->andReturn(uniqid());
            $userResponse->shouldReceive('getHeader')
                ->andReturn(['content-type' => 'application/jwt']);
            $userResponse->shouldReceive('getStatusCode')
                ->andReturn(200);

            $client = m::mock('GuzzleHttp\ClientInterface');
            $client->shouldReceive('send')
                ->times(2)
                ->andReturn($postResponse, $userResponse);
            $this->provider->setHttpClient($client);

            $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
            $user = $this->provider->getResourceOwner($token);
        }

        public function testErrorResponse()
        {
            $this->expectException(IdentityProviderException::class);

            $response = m::mock('Psr\Http\Message\ResponseInterface');
            $response->shouldReceive('getBody')
                ->andReturn('{"error": "invalid_grant", "error_description": "Code not found"}');
            $response->shouldReceive('getHeader')
                ->andReturn(['content-type' => 'json']);

            $client = m::mock('GuzzleHttp\ClientInterface');
            $client->shouldReceive('send')
                ->times(1)
                ->andReturn($response);
            $this->provider->setHttpClient($client);

            $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
        }
    }
}
