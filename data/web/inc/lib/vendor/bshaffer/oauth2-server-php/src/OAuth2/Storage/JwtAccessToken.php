<?php

namespace OAuth2\Storage;

use OAuth2\Encryption\EncryptionInterface;
use OAuth2\Encryption\Jwt;

/**
 * @author Brent Shaffer <bshafs at gmail dot com>
 */
class JwtAccessToken implements JwtAccessTokenInterface
{
    protected $publicKeyStorage;
    protected $tokenStorage;
    protected $encryptionUtil;

    /**
     * @param OAuth2\Encryption\PublicKeyInterface  $publicKeyStorage the public key encryption to use
     * @param OAuth2\Storage\AccessTokenInterface   $tokenStorage     OPTIONAL persist the access token to another storage. This is useful if
     *                                                                you want to retain access token grant information somewhere, but
     *                                                                is not necessary when using this grant type.
     * @param OAuth2\Encryption\EncryptionInterface $encryptionUtil   OPTIONAL class to use for "encode" and "decode" functions.
     */
    public function __construct(PublicKeyInterface $publicKeyStorage, AccessTokenInterface $tokenStorage = null, EncryptionInterface $encryptionUtil = null)
    {
        $this->publicKeyStorage = $publicKeyStorage;
        $this->tokenStorage = $tokenStorage;
        if (is_null($encryptionUtil)) {
            $encryptionUtil = new Jwt;
        }
        $this->encryptionUtil = $encryptionUtil;
    }

    public function getAccessToken($oauth_token)
    {
        // just decode the token, don't verify
        if (!$tokenData = $this->encryptionUtil->decode($oauth_token, null, false)) {
            return false;
        }

        $client_id  = isset($tokenData['aud']) ? $tokenData['aud'] : null;
        $public_key = $this->publicKeyStorage->getPublicKey($client_id);
        $algorithm  = $this->publicKeyStorage->getEncryptionAlgorithm($client_id);

        // now that we have the client_id, verify the token
        if (false === $this->encryptionUtil->decode($oauth_token, $public_key, array($algorithm))) {
            return false;
        }

        // normalize the JWT claims to the format expected by other components in this library
        return $this->convertJwtToOAuth2($tokenData);
    }

    public function setAccessToken($oauth_token, $client_id, $user_id, $expires, $scope = null)
    {
        if ($this->tokenStorage) {
            return $this->tokenStorage->setAccessToken($oauth_token, $client_id, $user_id, $expires, $scope);
        }
    }

    public function unsetAccessToken($access_token)
    {
        if ($this->tokenStorage) {
            return $this->tokenStorage->unsetAccessToken($access_token);
        }
    }


    // converts a JWT access token into an OAuth2-friendly format
    protected function convertJwtToOAuth2($tokenData)
    {
        $keyMapping = array(
            'aud' => 'client_id',
            'exp' => 'expires',
            'sub' => 'user_id'
        );

        foreach ($keyMapping as $jwtKey => $oauth2Key) {
            if (isset($tokenData[$jwtKey])) {
                $tokenData[$oauth2Key] = $tokenData[$jwtKey];
                unset($tokenData[$jwtKey]);
            }
        }

        return $tokenData;
    }
}