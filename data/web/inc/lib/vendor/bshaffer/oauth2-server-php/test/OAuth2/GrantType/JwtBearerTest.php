<?php

namespace OAuth2\GrantType;

use OAuth2\Storage\Bootstrap;
use OAuth2\Server;
use OAuth2\Request\TestRequest;
use OAuth2\Response;
use OAuth2\Encryption\Jwt;
use PHPUnit\Framework\TestCase;

class JwtBearerTest extends TestCase
{
    private $privateKey;

    public function setUp(): void
    {
        $this->privateKey = <<<EOD
-----BEGIN RSA PRIVATE KEY-----
MIICXAIBAAKBgQC5/SxVlE8gnpFqCxgl2wjhzY7ucEi00s0kUg3xp7lVEvgLgYcA
nHiWp+gtSjOFfH2zsvpiWm6Lz5f743j/FEzHIO1owR0p4d9pOaJK07d01+RzoQLO
IQAgXrr4T1CCWUesncwwPBVCyy2Mw3Nmhmr9MrF8UlvdRKBxriRnlP3qJQIDAQAB
AoGAVgJJVU4fhYMu1e5JfYAcTGfF+Gf+h3iQm4JCpoUcxMXf5VpB9ztk3K7LRN5y
kwFuFALpnUAarRcUPs0D8FoP4qBluKksbAtgHkO7bMSH9emN+mH4le4qpFlR7+P1
3fLE2Y19IBwPwEfClC+TpJvuog6xqUYGPlg6XLq/MxQUB4ECQQDgovP1v+ONSeGS
R+NgJTR47noTkQT3M2izlce/OG7a+O0yw6BOZjNXqH2wx3DshqMcPUFrTjibIClP
l/tEQ3ShAkEA0/TdBYDtXpNNjqg0R9GVH2pw7Kh68ne6mZTuj0kCgFYpUF6L6iMm
zXamIJ51rTDsTyKTAZ1JuAhAsK/M2BbDBQJAKQ5fXEkIA+i+64dsDUR/hKLBeRYG
PFAPENONQGvGBwt7/s02XV3cgGbxIgAxqWkqIp0neb9AJUoJgtyaNe3GQQJANoL4
QQ0af0NVJAZgg8QEHTNL3aGrFSbzx8IE5Lb7PLRsJa5bP5lQxnDoYuU+EI/Phr62
niisp/b/ZDGidkTMXQJBALeRsH1I+LmICAvWXpLKa9Gv0zGCwkuIJLiUbV9c6CVh
suocCAteQwL5iW2gA4AnYr5OGeHFsEl7NCQcwfPZpJ0=
-----END RSA PRIVATE KEY-----
EOD;
    }

    public function testMalformedJWT()
    {
        $server = $this->getTestServer();
        $request = TestRequest::createPost(array(
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', // valid grant type
        ));

        //Get the jwt and break it
        $jwt = $this->getJWT();
        $jwt = substr_replace($jwt, 'broken', 3, 6);

        $request->request['assertion'] = $jwt;

        $server->grantAccessToken($request, $response = new Response());

        $this->assertEquals($response->getStatusCode(), 400);
        $this->assertEquals($response->getParameter('error'), 'invalid_request');
        $this->assertEquals($response->getParameter('error_description'), 'JWT is malformed');
    }

    public function testBrokenSignature()
    {
        $server = $this->getTestServer();
        $request = TestRequest::createPost(array(
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', // valid grant type
        ));

        //Get the jwt and break signature
        $jwt = $this->getJWT() . 'notSupposeToBeHere';
        $request->request['assertion'] = $jwt;

        $server->grantAccessToken($request, $response = new Response());

        $this->assertEquals($response->getStatusCode(), 400);
        $this->assertEquals($response->getParameter('error'), 'invalid_grant');
        $this->assertEquals($response->getParameter('error_description'), 'JWT failed signature verification');
    }

    public function testExpiredJWT()
    {
        $server = $this->getTestServer();
        $request = TestRequest::createPost(array(
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', // valid grant type
        ));

        //Get an expired JWT
        $jwt = $this->getJWT(1234);
        $request->request['assertion'] = $jwt;

        $server->grantAccessToken($request, $response = new Response());

        $this->assertEquals($response->getStatusCode(), 400);
        $this->assertEquals($response->getParameter('error'), 'invalid_grant');
        $this->assertEquals($response->getParameter('error_description'), 'JWT has expired');
    }

    public function testBadExp()
    {
        $server = $this->getTestServer();
        $request = TestRequest::createPost(array(
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', // valid grant type
        ));

        //Get an expired JWT
        $jwt = $this->getJWT('badtimestamp');
        $request->request['assertion'] = $jwt;

        $server->grantAccessToken($request, $response = new Response());

        $this->assertEquals($response->getStatusCode(), 400);
        $this->assertEquals($response->getParameter('error'), 'invalid_grant');
        $this->assertEquals($response->getParameter('error_description'), 'Expiration (exp) time must be a unix time stamp');
    }

    public function testNoAssert()
    {
        $server = $this->getTestServer();
        $request = TestRequest::createPost(array(
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', // valid grant type
        ));

        //Do not pass the assert (JWT)

        $server->grantAccessToken($request, $response = new Response());

        $this->assertEquals($response->getStatusCode(), 400);
        $this->assertEquals($response->getParameter('error'), 'invalid_request');
        $this->assertEquals($response->getParameter('error_description'), 'Missing parameters: "assertion" required');
    }

    public function testNotBefore()
    {
        $server = $this->getTestServer();
        $request = TestRequest::createPost(array(
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', // valid grant type
        ));

        //Get a future NBF
        $jwt = $this->getJWT(null, time() + 10000);
        $request->request['assertion'] = $jwt;

        $server->grantAccessToken($request, $response = new Response());

        $this->assertEquals($response->getStatusCode(), 400);
        $this->assertEquals($response->getParameter('error'), 'invalid_grant');
        $this->assertEquals($response->getParameter('error_description'), 'JWT cannot be used before the Not Before (nbf) time');
    }

    public function testBadNotBefore()
    {
        $server = $this->getTestServer();
        $request = TestRequest::createPost(array(
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', // valid grant type
        ));

        //Get a non timestamp nbf
        $jwt = $this->getJWT(null, 'notatimestamp');
        $request->request['assertion'] = $jwt;

        $server->grantAccessToken($request, $response = new Response());

        $this->assertEquals($response->getStatusCode(), 400);
        $this->assertEquals($response->getParameter('error'), 'invalid_grant');
        $this->assertEquals($response->getParameter('error_description'), 'Not Before (nbf) time must be a unix time stamp');
    }

    public function testNonMatchingAudience()
    {
        $server = $this->getTestServer('http://google.com/oauth/o/auth');
        $request = TestRequest::createPost(array(
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', // valid grant type
            'assertion' => $this->getJWT(),
        ));

        $server->grantAccessToken($request, $response = new Response());

        $this->assertEquals($response->getStatusCode(), 400);
        $this->assertEquals($response->getParameter('error'), 'invalid_grant');
        $this->assertEquals($response->getParameter('error_description'), 'Invalid audience (aud)');
    }

    public function testBadClientID()
    {
        $server = $this->getTestServer();
        $request = TestRequest::createPost(array(
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',  // valid grant type
            'assertion' => $this->getJWT(null, null, null, 'bad_client_id'),
        ));

        $server->grantAccessToken($request, $response = new Response());

        $this->assertEquals($response->getStatusCode(), 400);
        $this->assertEquals($response->getParameter('error'), 'invalid_grant');
        $this->assertEquals($response->getParameter('error_description'), 'Invalid issuer (iss) or subject (sub) provided');
    }

    public function testBadSubject()
    {
        $server = $this->getTestServer();
        $request = TestRequest::createPost(array(
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',  // valid grant type
            'assertion' => $this->getJWT(null, null, 'anotheruser@ourdomain,com'),
        ));

        $server->grantAccessToken($request, $response = new Response());

        $this->assertEquals($response->getStatusCode(), 400);
        $this->assertEquals($response->getParameter('error'), 'invalid_grant');
        $this->assertEquals($response->getParameter('error_description'), 'Invalid issuer (iss) or subject (sub) provided');
    }

    public function testMissingKey()
    {
        $server = $this->getTestServer();
        $request = TestRequest::createPost(array(
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',  // valid grant type
            'assertion' => $this->getJWT(null, null, null, 'Missing Key Cli,nt'),
        ));

        $server->grantAccessToken($request, $response = new Response());

        $this->assertEquals($response->getStatusCode(), 400);
        $this->assertEquals($response->getParameter('error'), 'invalid_grant');
        $this->assertEquals($response->getParameter('error_description'), 'Invalid issuer (iss) or subject (sub) provided');
    }

    public function testValidJwt()
    {
        $server = $this->getTestServer();
        $request = TestRequest::createPost(array(
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',  // valid grant type
            'assertion' => $this->getJWT(), // valid assertion
        ));

        $token = $server->grantAccessToken($request, new Response());
        $this->assertNotNull($token);
        $this->assertArrayHasKey('access_token', $token);
    }

    public function testValidJwtWithScope()
    {
        $server = $this->getTestServer();
        $request = TestRequest::createPost(array(
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',  // valid grant type
            'assertion'  => $this->getJWT(null, null, null, 'Test Client ID'), // valid assertion
            'scope'      => 'scope1', // valid scope
        ));
        $token = $server->grantAccessToken($request, new Response());

        $this->assertNotNull($token);
        $this->assertArrayHasKey('access_token', $token);
        $this->assertArrayHasKey('scope', $token);
        $this->assertEquals($token['scope'], 'scope1');
    }

    public function testValidJwtInvalidScope()
    {
        $server = $this->getTestServer();
        $request = TestRequest::createPost(array(
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',  // valid grant type
            'assertion'  => $this->getJWT(null, null, null, 'Test Client ID'), // valid assertion
            'scope'      => 'invalid-scope', // invalid scope
        ));
        $token = $server->grantAccessToken($request, $response = new Response());

        $this->assertEquals($response->getStatusCode(), 400);
        $this->assertEquals($response->getParameter('error'), 'invalid_scope');
        $this->assertEquals($response->getParameter('error_description'), 'An unsupported scope was requested');
    }

    public function testValidJti()
    {
        $server = $this->getTestServer();
        $request = TestRequest::createPost(array(
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',  // valid grant type
                'assertion' => $this->getJWT(null, null, 'testuser@ourdomain.com', 'Test Client ID', 'unused_jti'), // valid assertion with invalid scope
        ));
        $token = $server->grantAccessToken($request, $response = new Response());

        $this->assertNotNull($token);
        $this->assertArrayHasKey('access_token', $token);
    }

    public function testInvalidJti()
    {
        $server = $this->getTestServer();
        $request = TestRequest::createPost(array(
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',  // valid grant type
                'assertion' => $this->getJWT(99999999900, null, 'testuser@ourdomain.com', 'Test Client ID', 'used_jti'), // valid assertion with invalid scope
        ));
        $token = $server->grantAccessToken($request, $response = new Response());

        $this->assertEquals($response->getStatusCode(), 400);
        $this->assertEquals($response->getParameter('error'), 'invalid_grant');
        $this->assertEquals($response->getParameter('error_description'), 'JSON Token Identifier (jti) has already been used');
    }

    public function testJtiReplayAttack()
    {
        $server = $this->getTestServer();
        $request = TestRequest::createPost(array(
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',  // valid grant type
                'assertion' => $this->getJWT(99999999900, null, 'testuser@ourdomain.com', 'Test Client ID', 'totally_new_jti'), // valid assertion with invalid scope
        ));
        $token = $server->grantAccessToken($request, $response = new Response());

        $this->assertNotNull($token);
        $this->assertArrayHasKey('access_token', $token);

        //Replay the same request
        $token = $server->grantAccessToken($request, $response = new Response());

        $this->assertEquals($response->getStatusCode(), 400);
        $this->assertEquals($response->getParameter('error'), 'invalid_grant');
        $this->assertEquals($response->getParameter('error_description'), 'JSON Token Identifier (jti) has already been used');
    }

    /**
     * Generates a JWT
     * @param $exp The expiration date. If the current time is greater than the exp, the JWT is invalid.
     * @param $nbf The "not before" time. If the current time is less than the nbf, the JWT is invalid.
     * @param $sub The subject we are acting on behalf of. This could be the email address of the user in the system.
     * @param $iss The issuer, usually the client_id.
     * @return string
     */
    private function getJWT($exp = null, $nbf = null, $sub = null, $iss = 'Test Client ID', $jti = null)
    {
        if (!$exp) {
            $exp = time() + 1000;
        }

        if (!$sub) {
            $sub = "testuser@ourdomain.com";
        }

        $params = array(
            'iss' => $iss,
            'exp' => $exp,
            'iat' => time(),
            'sub' => $sub,
            'aud' => 'http://myapp.com/oauth/auth',
        );

        if ($nbf) {
            $params['nbf'] = $nbf;
        }

        if ($jti) {
            $params['jti'] = $jti;
        }

        $jwtUtil = new Jwt();

        return $jwtUtil->encode($params, $this->privateKey, 'RS256');
    }

    private function getTestServer($audience = 'http://myapp.com/oauth/auth')
    {
        $storage = Bootstrap::getInstance()->getMemoryStorage();
        $server = new Server($storage);
        $server->addGrantType(new JwtBearer($storage, $audience, new Jwt()));

        return $server;
    }
}
