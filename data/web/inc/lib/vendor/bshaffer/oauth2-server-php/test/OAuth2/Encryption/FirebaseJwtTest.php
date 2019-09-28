<?php

namespace OAuth2\Encryption;

use OAuth2\Storage\Bootstrap;
use PHPUnit\Framework\TestCase;

class FirebaseJwtTest extends TestCase
{
    private $privateKey;

    public function setUp()
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

    /** @dataProvider provideClientCredentials */
    public function testJwtUtil($client_id, $client_key)
    {
        $jwtUtil = new FirebaseJwt();

        $params = array(
            'iss' => $client_id,
            'exp' => time() + 1000,
            'iat' => time(),
            'sub' => 'testuser@ourdomain.com',
            'aud' => 'http://myapp.com/oauth/auth',
            'scope' => null,
        );

        $encoded = $jwtUtil->encode($params, $this->privateKey, 'RS256');

        // test BC behaviour of trusting the algorithm in the header
        $payload = $jwtUtil->decode($encoded, $client_key, array('RS256'));
        $this->assertEquals($params, $payload);

        // test BC behaviour of not verifying by passing false
        $payload = $jwtUtil->decode($encoded, $client_key, false);
        $this->assertEquals($params, $payload);

        // test the new restricted algorithms header
        $payload = $jwtUtil->decode($encoded, $client_key, array('RS256'));
        $this->assertEquals($params, $payload);
    }

    public function testInvalidJwt()
    {
        $jwtUtil = new FirebaseJwt();

        $this->assertFalse($jwtUtil->decode('goob'));
        $this->assertFalse($jwtUtil->decode('go.o.b'));
    }

    /** @dataProvider provideClientCredentials */
    public function testInvalidJwtHeader($client_id, $client_key)
    {
        $jwtUtil = new FirebaseJwt();

        $params = array(
            'iss' => $client_id,
            'exp' => time() + 1000,
            'iat' => time(),
            'sub' => 'testuser@ourdomain.com',
            'aud' => 'http://myapp.com/oauth/auth',
            'scope' => null,
        );

        // testing for algorithm tampering when only RSA256 signing is allowed
        // @see https://auth0.com/blog/2015/03/31/critical-vulnerabilities-in-json-web-token-libraries/
        $tampered = $jwtUtil->encode($params, $client_key, 'HS256');

        $payload = $jwtUtil->decode($tampered, $client_key, array('RS256'));

        $this->assertFalse($payload);
    }

    public function provideClientCredentials()
    {
        $storage = Bootstrap::getInstance()->getMemoryStorage();
        $client_id  = 'Test Client ID';
        $client_key = $storage->getClientKey($client_id, "testuser@ourdomain.com");

        return array(
            array($client_id, $client_key),
        );
    }
}
