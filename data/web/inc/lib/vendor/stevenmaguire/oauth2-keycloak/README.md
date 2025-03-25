# Keycloak Provider for OAuth 2.0 Client
[![Latest Version](https://img.shields.io/github/release/stevenmaguire/oauth2-keycloak.svg?style=flat-square)](https://github.com/stevenmaguire/oauth2-keycloak/releases)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Build Status](https://img.shields.io/travis/stevenmaguire/oauth2-keycloak/master.svg?style=flat-square)](https://travis-ci.org/stevenmaguire/oauth2-keycloak)
[![Coverage Status](https://img.shields.io/scrutinizer/coverage/g/stevenmaguire/oauth2-keycloak.svg?style=flat-square)](https://scrutinizer-ci.com/g/stevenmaguire/oauth2-keycloak/code-structure)
[![Quality Score](https://img.shields.io/scrutinizer/g/stevenmaguire/oauth2-keycloak.svg?style=flat-square)](https://scrutinizer-ci.com/g/stevenmaguire/oauth2-keycloak)
[![Total Downloads](https://img.shields.io/packagist/dt/stevenmaguire/oauth2-keycloak.svg?style=flat-square)](https://packagist.org/packages/stevenmaguire/oauth2-keycloak)

This package provides Keycloak OAuth 2.0 support for the PHP League's [OAuth 2.0 Client](https://github.com/thephpleague/oauth2-client).

## Installation

To install, use composer:

```
composer require stevenmaguire/oauth2-keycloak
```

## Usage

Usage is the same as The League's OAuth client, using `\Stevenmaguire\OAuth2\Client\Provider\Keycloak` as the provider.

Use `authServerUrl` to specify the Keycloak server URL. You can lookup the correct value from the Keycloak client installer JSON under `auth-server-url`, eg. `http://localhost:8080/auth`.

Use `realm` to specify the Keycloak realm name. You can lookup the correct value from the Keycloak client installer JSON under `resource`, eg. `master`.

### Authorization Code Flow

```php
$provider = new Stevenmaguire\OAuth2\Client\Provider\Keycloak([
    'authServerUrl'         => '{keycloak-server-url}',
    'realm'                 => '{keycloak-realm}',
    'clientId'              => '{keycloak-client-id}',
    'clientSecret'          => '{keycloak-client-secret}',
    'redirectUri'           => 'https://example.com/callback-url',
    'encryptionAlgorithm'   => 'RS256',                             // optional
    'encryptionKeyPath'     => '../key.pem'                         // optional
    'encryptionKey'         => 'contents_of_key_or_certificate'     // optional
    'version'               => '20.0.1',                            // optional
]);

if (!isset($_GET['code'])) {

    // If we don't have an authorization code then get one
    $authUrl = $provider->getAuthorizationUrl();
    $_SESSION['oauth2state'] = $provider->getState();
    header('Location: '.$authUrl);
    exit;

// Check given state against previously stored one to mitigate CSRF attack
} elseif (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {

    unset($_SESSION['oauth2state']);
    exit('Invalid state, make sure HTTP sessions are enabled.');

} else {

    // Try to get an access token (using the authorization coe grant)
    try {
        $token = $provider->getAccessToken('authorization_code', [
            'code' => $_GET['code']
        ]);
    } catch (Exception $e) {
        exit('Failed to get access token: '.$e->getMessage());
    }

    // Optional: Now you have a token you can look up a users profile data
    try {

        // We got an access token, let's now get the user's details
        $user = $provider->getResourceOwner($token);

        // Use these details to create a new profile
        printf('Hello %s!', $user->getName());

    } catch (Exception $e) {
        exit('Failed to get resource owner: '.$e->getMessage());
    }

    // Use this to interact with an API on the users behalf
    echo $token->getToken();
}
```

### Refreshing a Token

```php
$provider = new Stevenmaguire\OAuth2\Client\Provider\Keycloak([
    'authServerUrl'     => '{keycloak-server-url}',
    'realm'             => '{keycloak-realm}',
    'clientId'          => '{keycloak-client-id}',
    'clientSecret'      => '{keycloak-client-secret}',
    'redirectUri'       => 'https://example.com/callback-url',
]);

$token = $provider->getAccessToken('refresh_token', ['refresh_token' => $token->getRefreshToken()]);
```

### Handling encryption

If you've configured your Keycloak instance to use encryption, there are some advanced options available to you.

#### Configure the provider to use the same encryption algorithm

```php
$provider = new Stevenmaguire\OAuth2\Client\Provider\Keycloak([
    // ...
    'encryptionAlgorithm'   => 'RS256',
]);
```

or

```php
$provider->setEncryptionAlgorithm('RS256');
```

#### Configure the provider to use the expected decryption public key or certificate

##### By key value

```php
$key = "-----BEGIN PUBLIC KEY-----\n....\n-----END PUBLIC KEY-----";
// or
// $key = "-----BEGIN CERTIFICATE-----\n....\n-----END CERTIFICATE-----";

$provider = new Stevenmaguire\OAuth2\Client\Provider\Keycloak([
    // ...
    'encryptionKey'   => $key,
]);
```

or

```php
$provider->setEncryptionKey($key);
```

##### By key path

```php
$keyPath = '../key.pem';

$provider = new Stevenmaguire\OAuth2\Client\Provider\Keycloak([
    // ...
    'encryptionKeyPath'   => $keyPath,
]);
```

or

```php
$provider->setEncryptionKeyPath($keyPath);
```

## Testing

``` bash
$ ./vendor/bin/phpunit
```

## Contributing

Please see [CONTRIBUTING](https://github.com/stevenmaguire/oauth2-keycloak/blob/master/CONTRIBUTING.md) for details.


## Credits

- [Steven Maguire](https://github.com/stevenmaguire)
- [Martin Stefan](https://github.com/mstefan21)
- [All Contributors](https://github.com/stevenmaguire/oauth2-keycloak/contributors)


## License

The MIT License (MIT). Please see [License File](https://github.com/stevenmaguire/oauth2-keycloak/blob/master/LICENSE) for more information.
