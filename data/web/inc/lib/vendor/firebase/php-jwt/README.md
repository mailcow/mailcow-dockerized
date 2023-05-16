![Build Status](https://github.com/firebase/php-jwt/actions/workflows/tests.yml/badge.svg)
[![Latest Stable Version](https://poser.pugx.org/firebase/php-jwt/v/stable)](https://packagist.org/packages/firebase/php-jwt)
[![Total Downloads](https://poser.pugx.org/firebase/php-jwt/downloads)](https://packagist.org/packages/firebase/php-jwt)
[![License](https://poser.pugx.org/firebase/php-jwt/license)](https://packagist.org/packages/firebase/php-jwt)

PHP-JWT
=======
A simple library to encode and decode JSON Web Tokens (JWT) in PHP, conforming to [RFC 7519](https://tools.ietf.org/html/rfc7519).

Installation
------------

Use composer to manage your dependencies and download PHP-JWT:

```bash
composer require firebase/php-jwt
```

Optionally, install the `paragonie/sodium_compat` package from composer if your
php is < 7.2 or does not have libsodium installed:

```bash
composer require paragonie/sodium_compat
```

Example
-------
```php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$key = 'example_key';
$payload = [
    'iss' => 'http://example.org',
    'aud' => 'http://example.com',
    'iat' => 1356999524,
    'nbf' => 1357000000
];

/**
 * IMPORTANT:
 * You must specify supported algorithms for your application. See
 * https://tools.ietf.org/html/draft-ietf-jose-json-web-algorithms-40
 * for a list of spec-compliant algorithms.
 */
$jwt = JWT::encode($payload, $key, 'HS256');
$decoded = JWT::decode($jwt, new Key($key, 'HS256'));

print_r($decoded);

/*
 NOTE: This will now be an object instead of an associative array. To get
 an associative array, you will need to cast it as such:
*/

$decoded_array = (array) $decoded;

/**
 * You can add a leeway to account for when there is a clock skew times between
 * the signing and verifying servers. It is recommended that this leeway should
 * not be bigger than a few minutes.
 *
 * Source: http://self-issued.info/docs/draft-ietf-oauth-json-web-token.html#nbfDef
 */
JWT::$leeway = 60; // $leeway in seconds
$decoded = JWT::decode($jwt, new Key($key, 'HS256'));
```
Example encode/decode headers
-------
Decoding the JWT headers without verifying the JWT first is NOT recommended, and is not supported by
this library. This is because without verifying the JWT, the header values could have been tampered with.
Any value pulled from an unverified header should be treated as if it could be any string sent in from an
attacker.  If this is something you still want to do in your application for whatever reason, it's possible to 
decode the header values manually simply by calling `json_decode` and `base64_decode` on the JWT 
header part:
```php
use Firebase\JWT\JWT;

$key = 'example_key';
$payload = [
    'iss' => 'http://example.org',
    'aud' => 'http://example.com',
    'iat' => 1356999524,
    'nbf' => 1357000000
];

$headers = [
    'x-forwarded-for' => 'www.google.com'
];

// Encode headers in the JWT string
$jwt = JWT::encode($payload, $key, 'HS256', null, $headers);

// Decode headers from the JWT string WITHOUT validation
// **IMPORTANT**: This operation is vulnerable to attacks, as the JWT has not yet been verified.
// These headers could be any value sent by an attacker.
list($headersB64, $payloadB64, $sig) = explode('.', $jwt);
$decoded = json_decode(base64_decode($headersB64), true);

print_r($decoded);
```
Example with RS256 (openssl)
----------------------------
```php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$privateKey = <<<EOD
-----BEGIN RSA PRIVATE KEY-----
MIIEowIBAAKCAQEAuzWHNM5f+amCjQztc5QTfJfzCC5J4nuW+L/aOxZ4f8J3Frew
M2c/dufrnmedsApb0By7WhaHlcqCh/ScAPyJhzkPYLae7bTVro3hok0zDITR8F6S
JGL42JAEUk+ILkPI+DONM0+3vzk6Kvfe548tu4czCuqU8BGVOlnp6IqBHhAswNMM
78pos/2z0CjPM4tbeXqSTTbNkXRboxjU29vSopcT51koWOgiTf3C7nJUoMWZHZI5
HqnIhPAG9yv8HAgNk6CMk2CadVHDo4IxjxTzTTqo1SCSH2pooJl9O8at6kkRYsrZ
WwsKlOFE2LUce7ObnXsYihStBUDoeBQlGG/BwQIDAQABAoIBAFtGaOqNKGwggn9k
6yzr6GhZ6Wt2rh1Xpq8XUz514UBhPxD7dFRLpbzCrLVpzY80LbmVGJ9+1pJozyWc
VKeCeUdNwbqkr240Oe7GTFmGjDoxU+5/HX/SJYPpC8JZ9oqgEA87iz+WQX9hVoP2
oF6EB4ckDvXmk8FMwVZW2l2/kd5mrEVbDaXKxhvUDf52iVD+sGIlTif7mBgR99/b
c3qiCnxCMmfYUnT2eh7Vv2LhCR/G9S6C3R4lA71rEyiU3KgsGfg0d82/XWXbegJW
h3QbWNtQLxTuIvLq5aAryV3PfaHlPgdgK0ft6ocU2de2FagFka3nfVEyC7IUsNTK
bq6nhAECgYEA7d/0DPOIaItl/8BWKyCuAHMss47j0wlGbBSHdJIiS55akMvnAG0M
39y22Qqfzh1at9kBFeYeFIIU82ZLF3xOcE3z6pJZ4Dyvx4BYdXH77odo9uVK9s1l
3T3BlMcqd1hvZLMS7dviyH79jZo4CXSHiKzc7pQ2YfK5eKxKqONeXuECgYEAyXlG
vonaus/YTb1IBei9HwaccnQ/1HRn6MvfDjb7JJDIBhNClGPt6xRlzBbSZ73c2QEC
6Fu9h36K/HZ2qcLd2bXiNyhIV7b6tVKk+0Psoj0dL9EbhsD1OsmE1nTPyAc9XZbb
OPYxy+dpBCUA8/1U9+uiFoCa7mIbWcSQ+39gHuECgYAz82pQfct30aH4JiBrkNqP
nJfRq05UY70uk5k1u0ikLTRoVS/hJu/d4E1Kv4hBMqYCavFSwAwnvHUo51lVCr/y
xQOVYlsgnwBg2MX4+GjmIkqpSVCC8D7j/73MaWb746OIYZervQ8dbKahi2HbpsiG
8AHcVSA/agxZr38qvWV54QKBgCD5TlDE8x18AuTGQ9FjxAAd7uD0kbXNz2vUYg9L
hFL5tyL3aAAtUrUUw4xhd9IuysRhW/53dU+FsG2dXdJu6CxHjlyEpUJl2iZu/j15
YnMzGWHIEX8+eWRDsw/+Ujtko/B7TinGcWPz3cYl4EAOiCeDUyXnqnO1btCEUU44
DJ1BAoGBAJuPD27ErTSVtId90+M4zFPNibFP50KprVdc8CR37BE7r8vuGgNYXmnI
RLnGP9p3pVgFCktORuYS2J/6t84I3+A17nEoB4xvhTLeAinAW/uTQOUmNicOP4Ek
2MsLL2kHgL8bLTmvXV4FX+PXphrDKg1XxzOYn0otuoqdAQrkK4og
-----END RSA PRIVATE KEY-----
EOD;

$publicKey = <<<EOD
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAuzWHNM5f+amCjQztc5QT
fJfzCC5J4nuW+L/aOxZ4f8J3FrewM2c/dufrnmedsApb0By7WhaHlcqCh/ScAPyJ
hzkPYLae7bTVro3hok0zDITR8F6SJGL42JAEUk+ILkPI+DONM0+3vzk6Kvfe548t
u4czCuqU8BGVOlnp6IqBHhAswNMM78pos/2z0CjPM4tbeXqSTTbNkXRboxjU29vS
opcT51koWOgiTf3C7nJUoMWZHZI5HqnIhPAG9yv8HAgNk6CMk2CadVHDo4IxjxTz
TTqo1SCSH2pooJl9O8at6kkRYsrZWwsKlOFE2LUce7ObnXsYihStBUDoeBQlGG/B
wQIDAQAB
-----END PUBLIC KEY-----
EOD;

$payload = [
    'iss' => 'example.org',
    'aud' => 'example.com',
    'iat' => 1356999524,
    'nbf' => 1357000000
];

$jwt = JWT::encode($payload, $privateKey, 'RS256');
echo "Encode:\n" . print_r($jwt, true) . "\n";

$decoded = JWT::decode($jwt, new Key($publicKey, 'RS256'));

/*
 NOTE: This will now be an object instead of an associative array. To get
 an associative array, you will need to cast it as such:
*/

$decoded_array = (array) $decoded;
echo "Decode:\n" . print_r($decoded_array, true) . "\n";
```

Example with a passphrase
-------------------------

```php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Your passphrase
$passphrase = '[YOUR_PASSPHRASE]';

// Your private key file with passphrase
// Can be generated with "ssh-keygen -t rsa -m pem"
$privateKeyFile = '/path/to/key-with-passphrase.pem';

// Create a private key of type "resource"
$privateKey = openssl_pkey_get_private(
    file_get_contents($privateKeyFile),
    $passphrase
);

$payload = [
    'iss' => 'example.org',
    'aud' => 'example.com',
    'iat' => 1356999524,
    'nbf' => 1357000000
];

$jwt = JWT::encode($payload, $privateKey, 'RS256');
echo "Encode:\n" . print_r($jwt, true) . "\n";

// Get public key from the private key, or pull from from a file.
$publicKey = openssl_pkey_get_details($privateKey)['key'];

$decoded = JWT::decode($jwt, new Key($publicKey, 'RS256'));
echo "Decode:\n" . print_r((array) $decoded, true) . "\n";
```

Example with EdDSA (libsodium and Ed25519 signature)
----------------------------
```php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Public and private keys are expected to be Base64 encoded. The last
// non-empty line is used so that keys can be generated with
// sodium_crypto_sign_keypair(). The secret keys generated by other tools may
// need to be adjusted to match the input expected by libsodium.

$keyPair = sodium_crypto_sign_keypair();

$privateKey = base64_encode(sodium_crypto_sign_secretkey($keyPair));

$publicKey = base64_encode(sodium_crypto_sign_publickey($keyPair));

$payload = [
    'iss' => 'example.org',
    'aud' => 'example.com',
    'iat' => 1356999524,
    'nbf' => 1357000000
];

$jwt = JWT::encode($payload, $privateKey, 'EdDSA');
echo "Encode:\n" . print_r($jwt, true) . "\n";

$decoded = JWT::decode($jwt, new Key($publicKey, 'EdDSA'));
echo "Decode:\n" . print_r((array) $decoded, true) . "\n";
````

Example with multiple keys
--------------------------
```php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Example RSA keys from previous example
// $privateKey1 = '...';
// $publicKey1 = '...';

// Example EdDSA keys from previous example
// $privateKey2 = '...';
// $publicKey2 = '...';

$payload = [
    'iss' => 'example.org',
    'aud' => 'example.com',
    'iat' => 1356999524,
    'nbf' => 1357000000
];

$jwt1 = JWT::encode($payload, $privateKey1, 'RS256', 'kid1');
$jwt2 = JWT::encode($payload, $privateKey2, 'EdDSA', 'kid2');
echo "Encode 1:\n" . print_r($jwt1, true) . "\n";
echo "Encode 2:\n" . print_r($jwt2, true) . "\n";

$keys = [
    'kid1' => new Key($publicKey1, 'RS256'),
    'kid2' => new Key($publicKey2, 'EdDSA'),
];

$decoded1 = JWT::decode($jwt1, $keys);
$decoded2 = JWT::decode($jwt2, $keys);

echo "Decode 1:\n" . print_r((array) $decoded1, true) . "\n";
echo "Decode 2:\n" . print_r((array) $decoded2, true) . "\n";
```

Using JWKs
----------

```php
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;

// Set of keys. The "keys" key is required. For example, the JSON response to
// this endpoint: https://www.gstatic.com/iap/verify/public_key-jwk
$jwks = ['keys' => []];

// JWK::parseKeySet($jwks) returns an associative array of **kid** to Firebase\JWT\Key
// objects. Pass this as the second parameter to JWT::decode.
JWT::decode($payload, JWK::parseKeySet($jwks));
```

Using Cached Key Sets
---------------------

The `CachedKeySet` class can be used to fetch and cache JWKS (JSON Web Key Sets) from a public URI.
This has the following advantages:

1. The results are cached for performance.
2. If an unrecognized key is requested, the cache is refreshed, to accomodate for key rotation.
3. If rate limiting is enabled, the JWKS URI will not make more than 10 requests a second.

```php
use Firebase\JWT\CachedKeySet;
use Firebase\JWT\JWT;

// The URI for the JWKS you wish to cache the results from
$jwksUri = 'https://www.gstatic.com/iap/verify/public_key-jwk';

// Create an HTTP client (can be any PSR-7 compatible HTTP client)
$httpClient = new GuzzleHttp\Client();

// Create an HTTP request factory (can be any PSR-17 compatible HTTP request factory)
$httpFactory = new GuzzleHttp\Psr\HttpFactory();

// Create a cache item pool (can be any PSR-6 compatible cache item pool)
$cacheItemPool = Phpfastcache\CacheManager::getInstance('files');

$keySet = new CachedKeySet(
    $jwksUri,
    $httpClient,
    $httpFactory,
    $cacheItemPool,
    null, // $expiresAfter int seconds to set the JWKS to expire
    true  // $rateLimit    true to enable rate limit of 10 RPS on lookup of invalid keys
);

$jwt = 'eyJhbGci...'; // Some JWT signed by a key from the $jwkUri above
$decoded = JWT::decode($jwt, $keySet);
```

Miscellaneous
-------------

#### Exception Handling

When a call to `JWT::decode` is invalid, it will throw one of the following exceptions:

```php
use Firebase\JWT\JWT;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use DomainException;
use InvalidArgumentException;
use UnexpectedValueException;

try {
    $decoded = JWT::decode($payload, $keys);
} catch (InvalidArgumentException $e) {
    // provided key/key-array is empty or malformed.
} catch (DomainException $e) {
    // provided algorithm is unsupported OR
    // provided key is invalid OR
    // unknown error thrown in openSSL or libsodium OR
    // libsodium is required but not available.
} catch (SignatureInvalidException $e) {
    // provided JWT signature verification failed.
} catch (BeforeValidException $e) {
    // provided JWT is trying to be used before "nbf" claim OR
    // provided JWT is trying to be used before "iat" claim.
} catch (ExpiredException $e) {
    // provided JWT is trying to be used after "exp" claim.
} catch (UnexpectedValueException $e) {
    // provided JWT is malformed OR
    // provided JWT is missing an algorithm / using an unsupported algorithm OR
    // provided JWT algorithm does not match provided key OR
    // provided key ID in key/key-array is empty or invalid.
}
```

All exceptions in the `Firebase\JWT` namespace extend `UnexpectedValueException`, and can be simplified
like this:

```php
try {
    $decoded = JWT::decode($payload, $keys);
} catch (LogicException $e) {
    // errors having to do with environmental setup or malformed JWT Keys
} catch (UnexpectedValueException $e) {
    // errors having to do with JWT signature and claims
}
```

#### Casting to array

The return value of `JWT::decode` is the generic PHP object `stdClass`. If you'd like to handle with arrays
instead, you can do the following:

```php
// return type is stdClass
$decoded = JWT::decode($payload, $keys);

// cast to array
$decoded = json_decode(json_encode($decoded), true);
```

Tests
-----
Run the tests using phpunit:

```bash
$ pear install PHPUnit
$ phpunit --configuration phpunit.xml.dist
PHPUnit 3.7.10 by Sebastian Bergmann.
.....
Time: 0 seconds, Memory: 2.50Mb
OK (5 tests, 5 assertions)
```

New Lines in private keys
-----

If your private key contains `\n` characters, be sure to wrap it in double quotes `""`
and not single quotes `''` in order to properly interpret the escaped characters.

License
-------
[3-Clause BSD](http://opensource.org/licenses/BSD-3-Clause).
