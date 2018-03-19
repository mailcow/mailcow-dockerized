<?php

namespace OAuth2\ResponseType;

use OAuth2\Encryption\EncryptionInterface;
use OAuth2\Encryption\Jwt;
use OAuth2\Storage\AccessTokenInterface as AccessTokenStorageInterface;
use OAuth2\Storage\RefreshTokenInterface;
use OAuth2\Storage\PublicKeyInterface;
use OAuth2\Storage\Memory;

/**
 * @author Brent Shaffer <bshafs at gmail dot com>
 */
class JwtAccessToken extends AccessToken
{
    protected $publicKeyStorage;
    protected $encryptionUtil;

    /**
     * @param PublicKeyInterface          $publicKeyStorage -
     * @param AccessTokenStorageInterface $tokenStorage     -
     * @param RefreshTokenInterface       $refreshStorage   -
     * @param array                       $config           - array with key store_encrypted_token_string (bool true)
     *                                                        whether the entire encrypted string is stored,
     *                                                        or just the token ID is stored
     * @param EncryptionInterface         $encryptionUtil   -
     */
    public function __construct(PublicKeyInterface $publicKeyStorage = null, AccessTokenStorageInterface $tokenStorage = null, RefreshTokenInterface $refreshStorage = null, array $config = array(), EncryptionInterface $encryptionUtil = null)
    {
        $this->publicKeyStorage = $publicKeyStorage;
        $config = array_merge(array(
            'store_encrypted_token_string' => true,
            'issuer' => ''
        ), $config);
        if (is_null($tokenStorage)) {
            // a pass-thru, so we can call the parent constructor
            $tokenStorage = new Memory();
        }
        if (is_null($encryptionUtil)) {
            $encryptionUtil = new Jwt();
        }
        $this->encryptionUtil = $encryptionUtil;
        parent::__construct($tokenStorage, $refreshStorage, $config);
    }

    /**
     * Handle the creation of access token, also issue refresh token if supported / desirable.
     *
     * @param mixed  $client_id           - Client identifier related to the access token.
     * @param mixed  $user_id             - User ID associated with the access token
     * @param string $scope               - (optional) Scopes to be stored in space-separated string.
     * @param bool   $includeRefreshToken - If true, a new refresh_token will be added to the response
     * @return array                      - The access token
     *
     * @see http://tools.ietf.org/html/rfc6749#section-5
     * @ingroup oauth2_section_5
     */
    public function createAccessToken($client_id, $user_id, $scope = null, $includeRefreshToken = true)
    {
        // payload to encrypt
        $payload = $this->createPayload($client_id, $user_id, $scope);

        /*
         * Encode the payload data into a single JWT access_token string
         */
        $access_token = $this->encodeToken($payload, $client_id);

        /*
         * Save the token to a secondary storage.  This is implemented on the
         * OAuth2\Storage\JwtAccessToken side, and will not actually store anything,
         * if no secondary storage has been supplied
         */
        $token_to_store = $this->config['store_encrypted_token_string'] ? $access_token : $payload['id'];
        $this->tokenStorage->setAccessToken($token_to_store, $client_id, $user_id, $this->config['access_lifetime'] ? time() + $this->config['access_lifetime'] : null, $scope);

        // token to return to the client
        $token = array(
            'access_token' => $access_token,
            'expires_in' => $this->config['access_lifetime'],
            'token_type' => $this->config['token_type'],
            'scope' => $scope
        );

        /*
         * Issue a refresh token also, if we support them
         *
         * Refresh Tokens are considered supported if an instance of OAuth2\Storage\RefreshTokenInterface
         * is supplied in the constructor
         */
        if ($includeRefreshToken && $this->refreshStorage) {
            $refresh_token = $this->generateRefreshToken();
            $expires = 0;
            if ($this->config['refresh_token_lifetime'] > 0) {
                $expires = time() + $this->config['refresh_token_lifetime'];
            }
            $this->refreshStorage->setRefreshToken($refresh_token, $client_id, $user_id, $expires, $scope);
            $token['refresh_token'] = $refresh_token;
        }

        return $token;
    }

    /**
     * @param array $token
     * @param mixed $client_id
     * @return mixed
     */
    protected function encodeToken(array $token, $client_id = null)
    {
        $private_key = $this->publicKeyStorage->getPrivateKey($client_id);
        $algorithm   = $this->publicKeyStorage->getEncryptionAlgorithm($client_id);

        return $this->encryptionUtil->encode($token, $private_key, $algorithm);
    }

    /**
     * This function can be used to create custom JWT payloads
     *
     * @param mixed  $client_id           - Client identifier related to the access token.
     * @param mixed  $user_id             - User ID associated with the access token
     * @param string $scope               - (optional) Scopes to be stored in space-separated string.
     * @return array                      - The access token
     */
    protected function createPayload($client_id, $user_id, $scope = null)
    {
        // token to encrypt
        $expires = time() + $this->config['access_lifetime'];
        $id = $this->generateAccessToken();

        return array(
            'id'         => $id, // for BC (see #591)
            'jti'        => $id,
            'iss'        => $this->config['issuer'],
            'aud'        => $client_id,
            'sub'        => $user_id,
            'exp'        => $expires,
            'iat'        => time(),
            'token_type' => $this->config['token_type'],
            'scope'      => $scope
        );
    }
}
