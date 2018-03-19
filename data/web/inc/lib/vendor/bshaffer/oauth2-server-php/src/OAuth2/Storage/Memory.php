<?php

namespace OAuth2\Storage;

use OAuth2\OpenID\Storage\UserClaimsInterface;
use OAuth2\OpenID\Storage\AuthorizationCodeInterface as OpenIDAuthorizationCodeInterface;

/**
 * Simple in-memory storage for all storage types
 *
 * NOTE: This class should never be used in production, and is
 * a stub class for example use only
 *
 * @author Brent Shaffer <bshafs at gmail dot com>
 */
class Memory implements AuthorizationCodeInterface,
    UserCredentialsInterface,
    UserClaimsInterface,
    AccessTokenInterface,
    ClientCredentialsInterface,
    RefreshTokenInterface,
    JwtBearerInterface,
    ScopeInterface,
    PublicKeyInterface,
    OpenIDAuthorizationCodeInterface
{
    public $authorizationCodes;
    public $userCredentials;
    public $clientCredentials;
    public $refreshTokens;
    public $accessTokens;
    public $jwt;
    public $jti;
    public $supportedScopes;
    public $defaultScope;
    public $keys;

    public function __construct($params = array())
    {
        $params = array_merge(array(
            'authorization_codes' => array(),
            'user_credentials' => array(),
            'client_credentials' => array(),
            'refresh_tokens' => array(),
            'access_tokens' => array(),
            'jwt' => array(),
            'jti' => array(),
            'default_scope' => null,
            'supported_scopes' => array(),
            'keys' => array(),
        ), $params);

        $this->authorizationCodes = $params['authorization_codes'];
        $this->userCredentials = $params['user_credentials'];
        $this->clientCredentials = $params['client_credentials'];
        $this->refreshTokens = $params['refresh_tokens'];
        $this->accessTokens = $params['access_tokens'];
        $this->jwt = $params['jwt'];
        $this->jti = $params['jti'];
        $this->supportedScopes = $params['supported_scopes'];
        $this->defaultScope = $params['default_scope'];
        $this->keys = $params['keys'];
    }

    /* AuthorizationCodeInterface */
    public function getAuthorizationCode($code)
    {
        if (!isset($this->authorizationCodes[$code])) {
            return false;
        }

        return array_merge(array(
            'authorization_code' => $code,
        ), $this->authorizationCodes[$code]);
    }

    public function setAuthorizationCode($code, $client_id, $user_id, $redirect_uri, $expires, $scope = null, $id_token = null)
    {
        $this->authorizationCodes[$code] = compact('code', 'client_id', 'user_id', 'redirect_uri', 'expires', 'scope', 'id_token');

        return true;
    }

    public function setAuthorizationCodes($authorization_codes)
    {
        $this->authorizationCodes = $authorization_codes;
    }

    public function expireAuthorizationCode($code)
    {
        unset($this->authorizationCodes[$code]);
    }

    /* UserCredentialsInterface */
    public function checkUserCredentials($username, $password)
    {
        $userDetails = $this->getUserDetails($username);

        return $userDetails && $userDetails['password'] && $userDetails['password'] === $password;
    }

    public function setUser($username, $password, $firstName = null, $lastName = null)
    {
        $this->userCredentials[$username] = array(
            'password'   => $password,
            'first_name' => $firstName,
            'last_name'  => $lastName,
        );

        return true;
    }

    public function getUserDetails($username)
    {
        if (!isset($this->userCredentials[$username])) {
            return false;
        }

        return array_merge(array(
            'user_id'    => $username,
            'password'   => null,
            'first_name' => null,
            'last_name'  => null,
        ), $this->userCredentials[$username]);
    }

    /* UserClaimsInterface */
    public function getUserClaims($user_id, $claims)
    {
        if (!$userDetails = $this->getUserDetails($user_id)) {
            return false;
        }

        $claims = explode(' ', trim($claims));
        $userClaims = array();

        // for each requested claim, if the user has the claim, set it in the response
        $validClaims = explode(' ', self::VALID_CLAIMS);
        foreach ($validClaims as $validClaim) {
            if (in_array($validClaim, $claims)) {
                if ($validClaim == 'address') {
                    // address is an object with subfields
                    $userClaims['address'] = $this->getUserClaim($validClaim, $userDetails['address'] ?: $userDetails);
                } else {
                    $userClaims = array_merge($userClaims, $this->getUserClaim($validClaim, $userDetails));
                }
            }
        }

        return $userClaims;
    }

    protected function getUserClaim($claim, $userDetails)
    {
        $userClaims = array();
        $claimValuesString = constant(sprintf('self::%s_CLAIM_VALUES', strtoupper($claim)));
        $claimValues = explode(' ', $claimValuesString);

        foreach ($claimValues as $value) {
            $userClaims[$value] = isset($userDetails[$value]) ? $userDetails[$value] : null;
        }

        return $userClaims;
    }

    /* ClientCredentialsInterface */
    public function checkClientCredentials($client_id, $client_secret = null)
    {
        return isset($this->clientCredentials[$client_id]['client_secret']) && $this->clientCredentials[$client_id]['client_secret'] === $client_secret;
    }

    public function isPublicClient($client_id)
    {
        if (!isset($this->clientCredentials[$client_id])) {
            return false;
        }

        return empty($this->clientCredentials[$client_id]['client_secret']);
    }

    /* ClientInterface */
    public function getClientDetails($client_id)
    {
        if (!isset($this->clientCredentials[$client_id])) {
            return false;
        }

        $clientDetails = array_merge(array(
            'client_id'     => $client_id,
            'client_secret' => null,
            'redirect_uri'  => null,
            'scope'         => null,
        ), $this->clientCredentials[$client_id]);

        return $clientDetails;
    }

    public function checkRestrictedGrantType($client_id, $grant_type)
    {
        if (isset($this->clientCredentials[$client_id]['grant_types'])) {
            $grant_types = explode(' ', $this->clientCredentials[$client_id]['grant_types']);

            return in_array($grant_type, $grant_types);
        }

        // if grant_types are not defined, then none are restricted
        return true;
    }

    public function setClientDetails($client_id, $client_secret = null, $redirect_uri = null, $grant_types = null, $scope = null, $user_id = null)
    {
        $this->clientCredentials[$client_id] = array(
            'client_id'     => $client_id,
            'client_secret' => $client_secret,
            'redirect_uri'  => $redirect_uri,
            'grant_types'   => $grant_types,
            'scope'         => $scope,
            'user_id'       => $user_id,
        );

        return true;
    }

    /* RefreshTokenInterface */
    public function getRefreshToken($refresh_token)
    {
        return isset($this->refreshTokens[$refresh_token]) ? $this->refreshTokens[$refresh_token] : false;
    }

    public function setRefreshToken($refresh_token, $client_id, $user_id, $expires, $scope = null)
    {
        $this->refreshTokens[$refresh_token] = compact('refresh_token', 'client_id', 'user_id', 'expires', 'scope');

        return true;
    }

    public function unsetRefreshToken($refresh_token)
    {
        if (isset($this->refreshTokens[$refresh_token])) {
            unset($this->refreshTokens[$refresh_token]);

            return true;
        }

        return false;
    }

    public function setRefreshTokens($refresh_tokens)
    {
        $this->refreshTokens = $refresh_tokens;
    }

    /* AccessTokenInterface */
    public function getAccessToken($access_token)
    {
        return isset($this->accessTokens[$access_token]) ? $this->accessTokens[$access_token] : false;
    }

    public function setAccessToken($access_token, $client_id, $user_id, $expires, $scope = null, $id_token = null)
    {
        $this->accessTokens[$access_token] = compact('access_token', 'client_id', 'user_id', 'expires', 'scope', 'id_token');

        return true;
    }

    public function unsetAccessToken($access_token)
    {
        if (isset($this->accessTokens[$access_token])) {
            unset($this->accessTokens[$access_token]);

            return true;
        }

        return false;
    }

    public function scopeExists($scope)
    {
        $scope = explode(' ', trim($scope));

        return (count(array_diff($scope, $this->supportedScopes)) == 0);
    }

    public function getDefaultScope($client_id = null)
    {
        return $this->defaultScope;
    }

    /*JWTBearerInterface */
    public function getClientKey($client_id, $subject)
    {
        if (isset($this->jwt[$client_id])) {
            $jwt = $this->jwt[$client_id];
            if ($jwt) {
                if ($jwt["subject"] == $subject) {
                    return $jwt["key"];
                }
            }
        }

        return false;
    }

    public function getClientScope($client_id)
    {
        if (!$clientDetails = $this->getClientDetails($client_id)) {
            return false;
        }

        if (isset($clientDetails['scope'])) {
            return $clientDetails['scope'];
        }

        return null;
    }

    public function getJti($client_id, $subject, $audience, $expires, $jti)
    {
        foreach ($this->jti as $storedJti) {
            if ($storedJti['issuer'] == $client_id && $storedJti['subject'] == $subject && $storedJti['audience'] == $audience && $storedJti['expires'] == $expires && $storedJti['jti'] == $jti) {
                return array(
                    'issuer' => $storedJti['issuer'],
                    'subject' => $storedJti['subject'],
                    'audience' => $storedJti['audience'],
                    'expires' => $storedJti['expires'],
                    'jti' => $storedJti['jti']
                );
            }
        }

        return null;
    }

    public function setJti($client_id, $subject, $audience, $expires, $jti)
    {
        $this->jti[] = array('issuer' => $client_id, 'subject' => $subject, 'audience' => $audience, 'expires' => $expires, 'jti' => $jti);
    }

    /*PublicKeyInterface */
    public function getPublicKey($client_id = null)
    {
        if (isset($this->keys[$client_id])) {
            return $this->keys[$client_id]['public_key'];
        }

        // use a global encryption pair
        if (isset($this->keys['public_key'])) {
            return $this->keys['public_key'];
        }

        return false;
    }

    public function getPrivateKey($client_id = null)
    {
        if (isset($this->keys[$client_id])) {
            return $this->keys[$client_id]['private_key'];
        }

        // use a global encryption pair
        if (isset($this->keys['private_key'])) {
            return $this->keys['private_key'];
        }

        return false;
    }

    public function getEncryptionAlgorithm($client_id = null)
    {
        if (isset($this->keys[$client_id]['encryption_algorithm'])) {
            return $this->keys[$client_id]['encryption_algorithm'];
        }

        // use a global encryption algorithm
        if (isset($this->keys['encryption_algorithm'])) {
            return $this->keys['encryption_algorithm'];
        }

        return 'RS256';
    }
}