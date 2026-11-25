<?php

namespace OAuth2;

use OAuth2\Request\TestRequest;
use OAuth2\ResponseType\AuthorizationCode;
use OAuth2\Storage\Bootstrap;
use PHPUnit\Framework\TestCase;
use Yoast\PHPUnitPolyfills\Polyfills\ExpectPHPException;

class ServerTest extends TestCase
{
    use ExpectPHPException;

    public function testGetAuthorizeControllerWithNoClientStorageThrowsException()
    {
        $this->expectExceptionMessage('OAuth2\Storage\ClientInterface');
        // must set Client Storage
        $server = new Server();
        $server->getAuthorizeController();
    }

    public function testGetAuthorizeControllerWithNoAccessTokenStorageThrowsException()
    {
        $this->expectExceptionMessage('OAuth2\Storage\AccessTokenInterface');
        // must set AccessToken or AuthorizationCode
        $server = new Server();
        $server->addStorage($this->createMock('OAuth2\Storage\ClientInterface'));
        $server->getAuthorizeController();
    }

    public function testGetAuthorizeControllerWithClientStorageAndAccessTokenResponseType()
    {
        // must set AccessToken or AuthorizationCode
        $server = new Server();
        $server->addStorage($this->createMock('OAuth2\Storage\ClientInterface'));
        $server->addResponseType($this->createMock('OAuth2\ResponseType\AccessTokenInterface'));

        $this->assertNotNull($server->getAuthorizeController());
    }

    public function testGetAuthorizeControllerWithClientStorageAndAuthorizationCodeResponseType()
    {
        // must set AccessToken or AuthorizationCode
        $server = new Server();
        $server->addStorage($this->createMock('OAuth2\Storage\ClientInterface'));
        $server->addResponseType($this->createMock('OAuth2\ResponseType\AuthorizationCodeInterface'));

        $this->assertNotNull($server->getAuthorizeController());
    }

    public function testGetAuthorizeControllerWithClientStorageAndAccessTokenStorageThrowsException()
    {
        // must set AuthorizationCode or AccessToken / implicit
        $this->expectExceptionMessage('allow_implicit');
        $server = new Server();
        $server->addStorage($this->createMock('OAuth2\Storage\ClientInterface'));
        $server->addStorage($this->createMock('OAuth2\Storage\AccessTokenInterface'));

        $this->assertNotNull($server->getAuthorizeController());
    }

    public function testGetAuthorizeControllerWithClientStorageAndAccessTokenStorage()
    {
        // must set AuthorizationCode or AccessToken / implicit
        $server = new Server(array(), array('allow_implicit' => true));
        $server->addStorage($this->createMock('OAuth2\Storage\ClientInterface'));
        $server->addStorage($this->createMock('OAuth2\Storage\AccessTokenInterface'));

        $this->assertNotNull($server->getAuthorizeController());
    }

    public function testGetAuthorizeControllerWithClientStorageAndAuthorizationCodeStorage()
    {
        // must set AccessToken or AuthorizationCode
        $server = new Server();
        $server->addStorage($this->createMock('OAuth2\Storage\ClientInterface'));
        $server->addStorage($this->createMock('OAuth2\Storage\AuthorizationCodeInterface'));

        $this->assertNotNull($server->getAuthorizeController());
    }

    public function testGetTokenControllerWithGrantTypeStorageThrowsException()
    {
        $this->expectExceptionMessage('grant_types');
        $server = new Server();
        $server->getTokenController();
    }

    public function testGetTokenControllerWithNoClientCredentialsStorageThrowsException()
    {
        $this->expectExceptionMessage('OAuth2\Storage\ClientCredentialsInterface');
        $server = new Server();
        $server->addStorage($this->createMock('OAuth2\Storage\UserCredentialsInterface'));
        $server->getTokenController();
    }

    public function testGetTokenControllerWithNoAccessTokenStorageThrowsException()
    {
        $this->expectExceptionMessage('OAuth2\Storage\AccessTokenInterface');
        $server = new Server();
        $server->addStorage($this->createMock('OAuth2\Storage\ClientCredentialsInterface'));
        $server->getTokenController();
    }

    public function testGetTokenControllerWithAccessTokenAndClientCredentialsStorage()
    {
        $server = new Server();
        $server->addStorage($this->createMock('OAuth2\Storage\AccessTokenInterface'));
        $server->addStorage($this->createMock('OAuth2\Storage\ClientCredentialsInterface'));
        $server->getTokenController();
    }

    public function testGetTokenControllerAccessTokenStorageAndClientCredentialsStorageAndGrantTypes()
    {
        $server = new Server();
        $server->addStorage($this->createMock('OAuth2\Storage\AccessTokenInterface'));
        $server->addStorage($this->createMock('OAuth2\Storage\ClientCredentialsInterface'));
        $server->addGrantType($this->createMock('OAuth2\GrantType\AuthorizationCode'));
        $server->getTokenController();
    }

    public function testGetResourceControllerWithNoAccessTokenStorageThrowsException()
    {
        $this->expectExceptionMessage('OAuth2\Storage\AccessTokenInterface');
        $server = new Server();
        $server->getResourceController();
    }

    public function testGetResourceControllerWithAccessTokenStorage()
    {
        $server = new Server();
        $server->addStorage($this->createMock('OAuth2\Storage\AccessTokenInterface'));
        $server->getResourceController();
    }

    public function testAddingStorageWithInvalidClass()
    {
        $this->expectExceptionMessage('OAuth2\Storage\AccessTokenInterface');
        $server = new Server();
        $server->addStorage(new \StdClass());
    }

    public function testAddingStorageWithInvalidKey()
    {
        $this->expectExceptionMessage('access_token');
        $server = new Server();
        $server->addStorage($this->createMock('OAuth2\Storage\AccessTokenInterface'), 'nonexistant_storage');
    }

    public function testAddingStorageWithInvalidKeyStorageCombination()
    {
        $this->expectExceptionMessage('OAuth2\Storage\AuthorizationCodeInterface');
        $server = new Server();
        $server->addStorage($this->createMock('OAuth2\Storage\AccessTokenInterface'), 'authorization_code');
    }

    public function testAddingStorageWithValidKeyOnlySetsThatKey()
    {
        $server = new Server();
        $server->addStorage($this->createMock('OAuth2\Storage\Memory'), 'access_token');

        $reflection = new \ReflectionClass($server);
        $prop = $reflection->getProperty('storages');
        $prop->setAccessible(true);

        $storages = $prop->getValue($server); // get the private "storages" property

        $this->assertEquals(1, count($storages));
        $this->assertTrue(isset($storages['access_token']));
        $this->assertFalse(isset($storages['authorization_code']));
    }

    public function testAddingClientStorageSetsClientCredentialsStorageByDefault()
    {
        $server = new Server();
        $memory = $this->createMock('OAuth2\Storage\Memory');
        $server->addStorage($memory, 'client');

        $client_credentials = $server->getStorage('client_credentials');

        $this->assertNotNull($client_credentials);
        $this->assertEquals($client_credentials, $memory);
    }

    public function testAddStorageWithNullValue()
    {
        $memory = $this->createMock('OAuth2\Storage\Memory');
        $server = new Server($memory);
        $server->addStorage(null, 'refresh_token');

        $client_credentials = $server->getStorage('client_credentials');

        $this->assertNotNull($client_credentials);
        $this->assertEquals($client_credentials, $memory);

        $refresh_token = $server->getStorage('refresh_token');

        $this->assertNull($refresh_token);
    }

    public function testNewServerWithNullStorageValue()
    {
        $memory = $this->createMock('OAuth2\Storage\Memory');
        $server = new Server(array(
            'client_credentials' => $memory,
            'refresh_token'      => null,
        ));

        $client_credentials = $server->getStorage('client_credentials');

        $this->assertNotNull($client_credentials);
        $this->assertEquals($client_credentials, $memory);

        $refresh_token = $server->getStorage('refresh_token');

        $this->assertNull($refresh_token);
    }

    public function testAddingClientCredentialsStorageSetsClientStorageByDefault()
    {
        $server = new Server();
        $memory = $this->createMock('OAuth2\Storage\Memory');
        $server->addStorage($memory, 'client_credentials');

        $client = $server->getStorage('client');

        $this->assertNotNull($client);
        $this->assertEquals($client, $memory);
    }

    public function testSettingClientStorageByDefaultDoesNotOverrideSetStorage()
    {
        $server = new Server();
        $pdo = $this->createMock('OAuth2\Storage\Pdo');

        $memory = $this->createMock('OAuth2\Storage\Memory');

        $server->addStorage($pdo, 'client');
        $server->addStorage($memory, 'client_credentials');

        $client = $server->getStorage('client');
        $client_credentials = $server->getStorage('client_credentials');

        $this->assertEquals($client, $pdo);
        $this->assertEquals($client_credentials, $memory);
    }

    public function testAddingResponseType()
    {
        $storage = $this->createMock('OAuth2\Storage\Memory');
        $storage
          ->expects($this->any())
          ->method('getClientDetails')
          ->will($this->returnValue(array('client_id' => 'some_client')));
        $storage
          ->expects($this->any())
          ->method('checkRestrictedGrantType')
          ->will($this->returnValue(true));

        // add with the "code" key explicitly set
        $codeType = new AuthorizationCode($storage);
        $server = new Server();
        $server->addStorage($storage);
        $server->addResponseType($codeType);
        $request = new Request(array(
            'response_type' => 'code',
            'client_id' => 'some_client',
            'redirect_uri' => 'http://example.com',
            'state' => 'xyx',
        ));
        $server->handleAuthorizeRequest($request, $response = new Response(), true);

        // the response is successful
        $this->assertEquals($response->getStatusCode(), 302);
        $parts = parse_url($response->getHttpHeader('Location'));
        parse_str($parts['query'], $query);
        $this->assertTrue(isset($query['code']));
        $this->assertFalse(isset($query['error']));

        // add with the "code" key not set
        $codeType = new AuthorizationCode($storage);
        $server = new Server(array($storage), array(), array(), array($codeType));
        $request = new Request(array(
            'response_type' => 'code',
            'client_id' => 'some_client',
            'redirect_uri' => 'http://example.com',
            'state' => 'xyx',
        ));
        $server->handleAuthorizeRequest($request, $response = new Response(), true);

        // the response is successful
        $this->assertEquals($response->getStatusCode(), 302);
        $parts = parse_url($response->getHttpHeader('Location'));
        parse_str($parts['query'], $query);
        $this->assertTrue(isset($query['code']));
        $this->assertFalse(isset($query['error']));
    }

    public function testCustomClientAssertionType()
    {
        $request = TestRequest::createPost(array(
            'grant_type' => 'authorization_code',
            'client_id' =>'Test Client ID',
            'code' => 'testcode',
        ));
        // verify the mock clientAssertionType was called as expected
        $clientAssertionType = $this->createMock('OAuth2\ClientAssertionType\ClientAssertionTypeInterface');
        $clientAssertionType
            ->expects($this->once())
            ->method('validateRequest')
            ->will($this->returnValue(true));
        $clientAssertionType
            ->expects($this->once())
            ->method('getClientId')
            ->will($this->returnValue('Test Client ID'));

        // create mock storage
        $storage = Bootstrap::getInstance()->getMemoryStorage();
        $server = new Server(array($storage), array(), array(), array(), null, null, $clientAssertionType);
        $server->handleTokenRequest($request, $response = new Response());
    }

    public function testHttpBasicConfig()
    {
        // create mock storage
        $storage = Bootstrap::getInstance()->getMemoryStorage();
        $server = new Server(array($storage), array(
            'allow_credentials_in_request_body' => false,
            'allow_public_clients' => false
        ));
        $server->getTokenController();
        $httpBasic = $server->getClientAssertionType();

        $reflection = new \ReflectionClass($httpBasic);
        $prop = $reflection->getProperty('config');
        $prop->setAccessible(true);

        $config = $prop->getValue($httpBasic); // get the private "config" property

        $this->assertEquals($config['allow_credentials_in_request_body'], false);
        $this->assertEquals($config['allow_public_clients'], false);
    }

    public function testRefreshTokenConfig()
    {
        // create mock storage
        $storage = Bootstrap::getInstance()->getMemoryStorage();
        $server1 = new Server(array($storage));
        $server2 = new Server(array($storage), array('always_issue_new_refresh_token' => true, 'unset_refresh_token_after_use' => false));

        $server1->getTokenController();
        $refreshToken1 = $server1->getGrantType('refresh_token');

        $server2->getTokenController();
        $refreshToken2 = $server2->getGrantType('refresh_token');

        $reflection1 = new \ReflectionClass($refreshToken1);
        $prop1 = $reflection1->getProperty('config');
        $prop1->setAccessible(true);

        $reflection2 = new \ReflectionClass($refreshToken2);
        $prop2 = $reflection2->getProperty('config');
        $prop2->setAccessible(true);

        // get the private "config" property
        $config1 = $prop1->getValue($refreshToken1);
        $config2 = $prop2->getValue($refreshToken2);

        $this->assertEquals($config1['always_issue_new_refresh_token'], false);
        $this->assertEquals($config2['always_issue_new_refresh_token'], true);

        $this->assertEquals($config1['unset_refresh_token_after_use'], true);
        $this->assertEquals($config2['unset_refresh_token_after_use'], false);
    }

    /**
     * Test setting "always_issue_new_refresh_token" on a server level
     *
     * @see test/OAuth2/GrantType/RefreshTokenTest::testValidRefreshTokenWithNewRefreshTokenInResponse
     **/
    public function testValidRefreshTokenWithNewRefreshTokenInResponse()
    {
        $storage = Bootstrap::getInstance()->getMemoryStorage();
        $server = new Server($storage, array('always_issue_new_refresh_token' => true));

        $request = TestRequest::createPost(array(
            'grant_type' => 'refresh_token', // valid grant type
            'client_id' => 'Test Client ID', // valid client id
            'client_secret' => 'TestSecret', // valid client secret
            'refresh_token' => 'test-refreshtoken', // valid refresh token
        ));
        $token = $server->grantAccessToken($request, new Response());
        $this->assertTrue(isset($token['refresh_token']), 'refresh token should always refresh');

        $refresh_token = $storage->getRefreshToken($token['refresh_token']);
        $this->assertNotNull($refresh_token);
        $this->assertEquals($refresh_token['refresh_token'], $token['refresh_token']);
        $this->assertEquals($refresh_token['client_id'], $request->request('client_id'));
        $this->assertTrue($token['refresh_token'] != 'test-refreshtoken', 'the refresh token returned is not the one used');
        $used_token = $storage->getRefreshToken('test-refreshtoken');
        $this->assertFalse($used_token, 'the refresh token used is no longer valid');
    }

    public function testAddingUnknownResponseTypeThrowsException()
    {
        $this->expectExceptionMessage('OAuth2\ResponseType\AuthorizationCodeInterface');
        $server = new Server();
        $server->addResponseType($this->createMock('OAuth2\ResponseType\ResponseTypeInterface'));
    }

    public function testUsingJwtAccessTokensWithoutPublicKeyStorageThrowsException()
    {
        $this->expectExceptionMessage('OAuth2\Storage\PublicKeyInterface');
        $server = new Server(array(), array('use_jwt_access_tokens' => true));
        $server->addGrantType($this->createMock('OAuth2\GrantType\GrantTypeInterface'));
        $server->addStorage($this->createMock('OAuth2\Storage\ClientCredentialsInterface'));
        $server->addStorage($this->createMock('OAuth2\Storage\ClientCredentialsInterface'));

        $server->getTokenController();
    }

    public function testUsingJustJwtAccessTokenStorageWithResourceControllerIsOkay()
    {
        $pubkey = $this->createMock('OAuth2\Storage\PublicKeyInterface');
        $server = new Server(array($pubkey), array('use_jwt_access_tokens' => true));

        $this->assertNotNull($server->getResourceController());
        $this->assertInstanceOf('OAuth2\Storage\PublicKeyInterface', $server->getStorage('public_key'));
    }

    public function testUsingJustJwtAccessTokenStorageWithAuthorizeControllerThrowsException()
    {
        $this->expectExceptionMessage('OAuth2\Storage\ClientInterface');
        $pubkey = $this->createMock('OAuth2\Storage\PublicKeyInterface');
        $server = new Server(array($pubkey), array('use_jwt_access_tokens' => true));
        $this->assertNotNull($server->getAuthorizeController());
    }

    public function testUsingJustJwtAccessTokenStorageWithTokenControllerThrowsException()
    {
        $this->expectExceptionMessage('grant_types');
        $pubkey = $this->createMock('OAuth2\Storage\PublicKeyInterface');
        $server = new Server(array($pubkey), array('use_jwt_access_tokens' => true));
        $server->getTokenController();
    }

    public function testUsingJwtAccessTokenAndClientStorageWithAuthorizeControllerIsOkay()
    {
        $pubkey = $this->createMock('OAuth2\Storage\PublicKeyInterface');
        $client = $this->createMock('OAuth2\Storage\ClientInterface');
        $server = new Server(array($pubkey, $client), array('use_jwt_access_tokens' => true, 'allow_implicit' => true));
        $this->assertNotNull($server->getAuthorizeController());

        $this->assertInstanceOf('OAuth2\ResponseType\JwtAccessToken', $server->getResponseType('token'));
    }

    public function testUsingOpenIDConnectWithoutUserClaimsThrowsException()
    {
        $this->expectExceptionMessage('UserClaims');
        $client = $this->createMock('OAuth2\Storage\ClientInterface');
        $server = new Server($client, array('use_openid_connect' => true));

        $server->getAuthorizeController();
    }

    public function testUsingOpenIDConnectWithoutPublicKeyThrowsException()
    {
        $this->expectExceptionMessage('PublicKeyInterface');
        $client = $this->createMock('OAuth2\Storage\ClientInterface');
        $userclaims = $this->createMock('OAuth2\OPenID\Storage\UserClaimsInterface');
        $server = new Server(array($client, $userclaims), array('use_openid_connect' => true));

        $server->getAuthorizeController();
    }

    public function testUsingOpenIDConnectWithoutIssuerThrowsException()
    {
        $this->expectExceptionMessage('issuer');
        $client = $this->createMock('OAuth2\Storage\ClientInterface');
        $userclaims = $this->createMock('OAuth2\OpenID\Storage\UserClaimsInterface');
        $pubkey = $this->createMock('OAuth2\Storage\PublicKeyInterface');
        $server = new Server(array($client, $userclaims, $pubkey), array('use_openid_connect' => true));

        $server->getAuthorizeController();
    }

    public function testUsingOpenIDConnectWithIssuerPublicKeyAndUserClaimsIsOkay()
    {
        $client = $this->createMock('OAuth2\Storage\ClientInterface');
        $userclaims = $this->createMock('OAuth2\OpenID\Storage\UserClaimsInterface');
        $pubkey = $this->createMock('OAuth2\Storage\PublicKeyInterface');
        $server = new Server(array($client, $userclaims, $pubkey), array(
            'use_openid_connect' => true,
            'issuer' => 'someguy',
        ));

        $server->getAuthorizeController();

        $this->assertInstanceOf('OAuth2\OpenID\ResponseType\IdTokenInterface', $server->getResponseType('id_token'));
        $this->assertNull($server->getResponseType('id_token token'));
    }

    public function testUsingOpenIDConnectWithAllowImplicitWithoutTokenStorageThrowsException()
    {
        $this->expectErrorMessage('OAuth2\ResponseType\AccessTokenInterface');
        $client = $this->createMock('OAuth2\Storage\ClientInterface');
        $userclaims = $this->createMock('OAuth2\OpenID\Storage\UserClaimsInterface');
        $pubkey = $this->createMock('OAuth2\Storage\PublicKeyInterface');
        $server = new Server(array($client, $userclaims, $pubkey), array(
            'use_openid_connect' => true,
            'issuer' => 'someguy',
            'allow_implicit' => true,
        ));

        $server->getAuthorizeController();
    }

    public function testUsingOpenIDConnectWithAllowImplicitAndUseJwtAccessTokensIsOkay()
    {
        $client = $this->createMock('OAuth2\Storage\ClientInterface');
        $userclaims = $this->createMock('OAuth2\OpenID\Storage\UserClaimsInterface');
        $pubkey = $this->createMock('OAuth2\Storage\PublicKeyInterface');
        $server = new Server(array($client, $userclaims, $pubkey), array(
            'use_openid_connect' => true,
            'issuer' => 'someguy',
            'allow_implicit' => true,
            'use_jwt_access_tokens' => true,
        ));

        $server->getAuthorizeController();

        $this->assertInstanceOf('OAuth2\OpenID\ResponseType\IdTokenInterface', $server->getResponseType('id_token'));
        $this->assertInstanceOf('OAuth2\OpenID\ResponseType\IdTokenTokenInterface', $server->getResponseType('id_token token'));
    }

    public function testUsingOpenIDConnectWithAllowImplicitAndAccessTokenStorageIsOkay()
    {
        $client = $this->createMock('OAuth2\Storage\ClientInterface');
        $userclaims = $this->createMock('OAuth2\OpenID\Storage\UserClaimsInterface');
        $pubkey = $this->createMock('OAuth2\Storage\PublicKeyInterface');
        $token = $this->createMock('OAuth2\Storage\AccessTokenInterface');
        $server = new Server(array($client, $userclaims, $pubkey, $token), array(
            'use_openid_connect' => true,
            'issuer' => 'someguy',
            'allow_implicit' => true,
        ));

        $server->getAuthorizeController();

        $this->assertInstanceOf('OAuth2\OpenID\ResponseType\IdTokenInterface', $server->getResponseType('id_token'));
        $this->assertInstanceOf('OAuth2\OpenID\ResponseType\IdTokenTokenInterface', $server->getResponseType('id_token token'));
    }

    public function testUsingOpenIDConnectWithAllowImplicitAndAccessTokenResponseTypeIsOkay()
    {
        $client = $this->createMock('OAuth2\Storage\ClientInterface');
        $userclaims = $this->createMock('OAuth2\OpenID\Storage\UserClaimsInterface');
        $pubkey = $this->createMock('OAuth2\Storage\PublicKeyInterface');
        // $token = $this->createMock('OAuth2\Storage\AccessTokenInterface');
        $server = new Server(array($client, $userclaims, $pubkey), array(
            'use_openid_connect' => true,
            'issuer' => 'someguy',
            'allow_implicit' => true,
        ));

        $token = $this->createMock('OAuth2\ResponseType\AccessTokenInterface');
        $server->addResponseType($token, 'token');

        $server->getAuthorizeController();

        $this->assertInstanceOf('OAuth2\OpenID\ResponseType\IdTokenInterface', $server->getResponseType('id_token'));
        $this->assertInstanceOf('OAuth2\OpenID\ResponseType\IdTokenTokenInterface', $server->getResponseType('id_token token'));
    }

    public function testUsingOpenIDConnectWithAuthorizationCodeStorageThrowsException()
    {
        $client = $this->createMock('OAuth2\Storage\ClientCredentialsInterface');
        $userclaims = $this->createMock('OAuth2\OpenID\Storage\UserClaimsInterface');
        $pubkey = $this->createMock('OAuth2\Storage\PublicKeyInterface');
        $token = $this->createMock('OAuth2\Storage\AccessTokenInterface');
        $authcode = $this->createMock('OAuth2\Storage\AuthorizationCodeInterface');

        $this->expectErrorMessage('OAuth2\OpenID\Storage\AuthorizationCodeInterface');
        $server = new Server(array($client, $userclaims, $pubkey, $token, $authcode), array(
            'use_openid_connect' => true,
            'issuer' => 'someguy'
        ));

        $server->getTokenController();

        $this->assertInstanceOf('OAuth2\OpenID\GrantType\AuthorizationCode', $server->getGrantType('authorization_code'));
    }

    public function testUsingOpenIDConnectWithOpenIDAuthorizationCodeStorageCreatesOpenIDAuthorizationCodeGrantType()
    {
        $client = $this->createMock('OAuth2\Storage\ClientCredentialsInterface');
        $userclaims = $this->createMock('OAuth2\OpenID\Storage\UserClaimsInterface');
        $pubkey = $this->createMock('OAuth2\Storage\PublicKeyInterface');
        $token = $this->createMock('OAuth2\Storage\AccessTokenInterface');
        $authcode = $this->createMock('OAuth2\OpenID\Storage\AuthorizationCodeInterface');

        $server = new Server(array($client, $userclaims, $pubkey, $token, $authcode), array(
            'use_openid_connect' => true,
            'issuer' => 'someguy'
        ));

        $server->getTokenController();

        $this->assertInstanceOf('OAuth2\OpenID\GrantType\AuthorizationCode', $server->getGrantType('authorization_code'));
    }

    public function testMultipleValuedResponseTypeOrderDoesntMatter()
    {
        $responseType = $this->createMock('OAuth2\OpenID\ResponseType\IdTokenTokenInterface');
        $server = new Server(array(), array(), array(), array(
            'token id_token' => $responseType,
        ));

        $this->assertEquals($responseType, $server->getResponseType('id_token token'));
    }

    public function testAddGrantTypeWithoutKey()
    {
        $server = new Server();
        $server->addGrantType(new \OAuth2\GrantType\AuthorizationCode($this->createMock('OAuth2\Storage\AuthorizationCodeInterface')));

        $grantTypes = $server->getGrantTypes();
        $this->assertEquals('authorization_code', key($grantTypes));
    }

    public function testAddGrantTypeWithKey()
    {
        $server = new Server();
        $server->addGrantType(new \OAuth2\GrantType\AuthorizationCode($this->createMock('OAuth2\Storage\AuthorizationCodeInterface')), 'ac');

        $grantTypes = $server->getGrantTypes();
        $this->assertEquals('ac', key($grantTypes));
    }

    public function testAddGrantTypeWithKeyNotString()
    {
        $server = new Server();
        $server->addGrantType(new \OAuth2\GrantType\AuthorizationCode($this->createMock('OAuth2\Storage\AuthorizationCodeInterface')), 42);

        $grantTypes = $server->getGrantTypes();
        $this->assertEquals('authorization_code', key($grantTypes));
    }
}
