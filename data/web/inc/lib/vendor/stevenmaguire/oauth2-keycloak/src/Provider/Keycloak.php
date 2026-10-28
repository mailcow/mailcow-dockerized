<?php

namespace Stevenmaguire\OAuth2\Client\Provider;

use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use Psr\Http\Message\ResponseInterface;
use Stevenmaguire\OAuth2\Client\Provider\Exception\EncryptionConfigurationException;
use UnexpectedValueException;

class Keycloak extends AbstractProvider
{
    use BearerAuthorizationTrait;

    /**
     * Keycloak URL, eg. http://localhost:8080/auth.
     *
     * @var string
     */
    public $authServerUrl = null;

    /**
     * Realm name, eg. demo.
     *
     * @var string
     */
    public $realm = null;

    /**
     * Encryption algorithm.
     *
     * You must specify supported algorithms for your application. See
     * https://tools.ietf.org/html/draft-ietf-jose-json-web-algorithms-40
     * for a list of spec-compliant algorithms.
     *
     * @var string|null
     */
    public $encryptionAlgorithm = null;

    /**
     * Encryption key.
     *
     * @var string|null
     */
    public $encryptionKey = null;

    /**
     * Keycloak version.
     *
     * @var string|null
     */
    public $version = null;

    /**
     * Constructs an OAuth 2.0 service provider.
     *
     * @param array<string, mixed> $options An array of options to set on this provider.
     *     Options include `clientId`, `clientSecret`, `redirectUri`, and `state`.
     *     Individual providers may introduce more options, as needed.
     * @param array<string, mixed> $collaborators An array of collaborators that may be used to
     *     override this provider's default behavior. Collaborators include
     *     `grantFactory`, `requestFactory`, `httpClient`, and `randomFactory`.
     *     Individual providers may introduce more collaborators, as needed.
     */
    public function __construct(array $options = [], array $collaborators = [])
    {
        if (isset($options['encryptionKeyPath']) && is_string($options['encryptionKeyPath'])) {
            $this->setEncryptionKeyPath($options['encryptionKeyPath']);
            unset($options['encryptionKeyPath']);
        }

        if (isset($options['version']) && is_string($options['version'])) {
            $this->setVersion($options['version']);
        }

        parent::__construct($options, $collaborators);
    }

    /**
     * Attempts to decrypt the given response.
     *
     * @param string|array<string, mixed>|null $response
     *
     * @return string|array<string, mixed>|null
     * @throws EncryptionConfigurationException
     */
    public function decryptResponse($response): array|string|null
    {
        if (!is_string($response)) {
            return $response;
        }

        if ($this->usesEncryption()) {
            if (!is_string($this->encryptionKey) || !is_string($this->encryptionAlgorithm)) {
                throw EncryptionConfigurationException::undeterminedEncryption();
            }

            $tokenData = JWT::decode(
                $response,
                new Key(
                    $this->encryptionKey,
                    $this->encryptionAlgorithm
                )
            );
            $tokenJson = json_encode($tokenData);
            if (!is_string($tokenJson)) {
                throw EncryptionConfigurationException::undeterminedEncryption();
            }
            $decodedToken = json_decode($tokenJson, true);
            if (!is_array($decodedToken)) {
                throw EncryptionConfigurationException::undeterminedEncryption();
            }

            return $decodedToken;
        }

        throw EncryptionConfigurationException::undeterminedEncryption();
    }

    /**
     * Get authorization url to begin OAuth flow
     *
     * @return string
     */
    public function getBaseAuthorizationUrl(): string
    {
        return $this->getBaseUrlWithRealm().'/protocol/openid-connect/auth';
    }

    /**
     * Get access token url to retrieve token
     *
     * @param  array<string, mixed> $params
     *
     * @return string
     */
    public function getBaseAccessTokenUrl(array $params): string
    {
        return $this->getBaseUrlWithRealm().'/protocol/openid-connect/token';
    }

    /**
     * Get provider url to fetch user details
     *
     * @param  AccessToken $token
     *
     * @return string
     */
    public function getResourceOwnerDetailsUrl(AccessToken $token): string
    {
        return $this->getBaseUrlWithRealm().'/protocol/openid-connect/userinfo';
    }

    /**
     * Builds the logout URL.
     *
     * @param array<string, mixed> $options
     * @return string Authorization URL
     */
    public function getLogoutUrl(array $options = []): string
    {
        $base = $this->getBaseLogoutUrl();
        $params = $this->getAuthorizationParameters($options);

        // Starting with keycloak 18.0.0, the parameter redirect_uri is no longer supported on logout.
        // As of this version the parameter is called post_logout_redirect_uri. In addition to this
        // a parameter id_token_hint has to be provided.
        if ($this->validateGteVersion('18.0.0')) {
            if (isset($options['access_token']) === true && $options['access_token'] instanceof AccessToken) {
                $accessToken = $options['access_token'];
                $values = $accessToken->getValues();

                if (isset($values['id_token']) && is_string($values['id_token'])) {
                    $params['id_token_hint'] = $values['id_token'];
                }
                $params['post_logout_redirect_uri'] = $params['redirect_uri'];
            }

            unset($params['redirect_uri']);
        }

        $query = $this->getAuthorizationQuery($params);
        return $this->appendQuery($base, $query);
    }

    /**
     * Get logout url to logout of session token
     *
     * @return string
     */
    private function getBaseLogoutUrl(): string
    {
        return $this->getBaseUrlWithRealm() . '/protocol/openid-connect/logout';
    }

    /**
     * Creates base url from provider configuration.
     *
     * @return string
     */
    protected function getBaseUrlWithRealm(): string
    {
        return $this->authServerUrl.'/realms/'.$this->realm;
    }

    /**
     * Get the default scopes used by this provider.
     *
     * This should not be a complete list of all scopes, but the minimum
     * required for the provider user interface!
     *
     * @return string[]
     */
    protected function getDefaultScopes(): array
    {
        $scopes = [
            'profile',
            'email'
        ];
        if ($this->validateGteVersion('20.0.0')) {
            $scopes[] = 'openid';
        }
        return $scopes;
    }

    /**
     * Returns the string that should be used to separate scopes when building
     * the URL for requesting an access token.
     *
     * @return string Scope separator, defaults to ','
     */
    protected function getScopeSeparator(): string
    {
        return ' ';
    }


    /**
     * Check a provider response for errors.
     *
     * @throws IdentityProviderException
     * @param  ResponseInterface $response
     * @param  mixed $data Parsed response data
     * @return void
     */
    protected function checkResponse(ResponseInterface $response, $data): void
    {
        if (!is_array($data)) {
            return;
        }

        if (!empty($data['error'])) {
            $error = $data['error'];
            if (isset($data['error_description'])) {
                $error .= ': '.$data['error_description'];
            }
            throw new IdentityProviderException($error, $response->getStatusCode(), $data);
        }
    }

    /**
     * Generate a user object from a successful user details request.
     *
     * @param array<string, mixed> $response
     * @param AccessToken $token
     * @return KeycloakResourceOwner
     */
    protected function createResourceOwner(array $response, AccessToken $token): KeycloakResourceOwner
    {
        return new KeycloakResourceOwner($response);
    }

    /**
     * Requests and returns the resource owner of given access token.
     *
     * @param  AccessToken $token
     * @return KeycloakResourceOwner
     * @throws EncryptionConfigurationException
     */
    public function getResourceOwner(AccessToken $token): KeycloakResourceOwner
    {
        $response = $this->fetchResourceOwnerDetails($token);
        if (!is_array($response)) {
            throw new UnexpectedValueException('Expected resource owner details to be an array.');
        }

        // We are always getting an array. We have to check if it is
        // the array we created
        if (array_key_exists('jwt', $response)) {
            $response = $response['jwt'];
        }

        $response = $this->decryptResponse($response);
        if (!is_array($response)) {
            throw new UnexpectedValueException('Expected decrypted resource owner details to be an array.');
        }

        return $this->createResourceOwner($response, $token);
    }

    /**
     * Updates expected encryption algorithm of Keycloak instance.
     *
     * @param string  $encryptionAlgorithm
     *
     * @return Keycloak
     */
    public function setEncryptionAlgorithm(string $encryptionAlgorithm): self
    {
        $this->encryptionAlgorithm = $encryptionAlgorithm;

        return $this;
    }

    /**
     * Updates expected encryption key of Keycloak instance.
     *
     * @param string  $encryptionKey
     *
     * @return Keycloak
     */
    public function setEncryptionKey(string $encryptionKey): self
    {
        $this->encryptionKey = $encryptionKey;

        return $this;
    }

    /**
     * Updates expected encryption key of Keycloak instance to content of given
     * file path.
     *
     * @param string  $encryptionKeyPath
     *
     * @return Keycloak
     */
    public function setEncryptionKeyPath(string $encryptionKeyPath): self
    {
        try {
            $encryptionKey = file_get_contents($encryptionKeyPath);
            if (is_string($encryptionKey)) {
                $this->encryptionKey = $encryptionKey;
            }
        } catch (Exception $e) {
            // Not sure how to handle this yet.
        }

        return $this;
    }

     /**
      * Updates the keycloak version.
      *
      * @param string  $version
      *
      * @return Keycloak
      */
    public function setVersion(string $version): self
    {
        $this->version = $version;

        return $this;
    }

    /**
     * Checks if provider is configured to use encryption.
     *
     * @return bool
     */
    public function usesEncryption(): bool
    {
        return (bool) $this->encryptionAlgorithm && $this->encryptionKey;
    }

    /**
     * Parses the response according to its content-type header.
     *
     * @throws UnexpectedValueException
     * @param  ResponseInterface $response
     * @return string|array<string, mixed>
     */
    protected function parseResponse(ResponseInterface $response): string|array
    {
        // We have a problem with keycloak when the userinfo responses
        // with a jwt token
        // Because it just return a jwt as string with the header
        // application/jwt
        // This can't be parsed to a array
        $content = (string) $response->getBody();
        $type = $this->getContentType($response);

        if (strpos($type, 'jwt') !== false) {
            // Here we make the temporary array
            return ['jwt' => $content];
        }

        return parent::parseResponse($response);
    }

    /**
     * Validate if version is greater or equal
     *
     * @param string $version
     * @return bool
     */
    private function validateGteVersion(string $version): bool
    {
        return is_string($this->version) && version_compare($this->version, $version, '>=');
    }
}
