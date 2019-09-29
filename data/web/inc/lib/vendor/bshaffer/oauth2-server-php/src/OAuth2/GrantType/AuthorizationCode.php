<?php

namespace OAuth2\GrantType;

use OAuth2\Storage\AuthorizationCodeInterface;
use OAuth2\ResponseType\AccessTokenInterface;
use OAuth2\RequestInterface;
use OAuth2\ResponseInterface;
use Exception;

/**
 * @author Brent Shaffer <bshafs at gmail dot com>
 */
class AuthorizationCode implements GrantTypeInterface
{
    /**
     * @var AuthorizationCodeInterface
     */
    protected $storage;

    /**
     * @var array
     */
    protected $authCode;

    /**
     * @param AuthorizationCodeInterface $storage - REQUIRED Storage class for retrieving authorization code information
     */
    public function __construct(AuthorizationCodeInterface $storage)
    {
        $this->storage = $storage;
    }

    /**
     * @return string
     */
    public function getQueryStringIdentifier()
    {
        return 'authorization_code';
    }

    /**
     * Validate the OAuth request
     *
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     * @return bool
     * @throws Exception
     */
    public function validateRequest(RequestInterface $request, ResponseInterface $response)
    {
        if (!$request->request('code')) {
            $response->setError(400, 'invalid_request', 'Missing parameter: "code" is required');

            return false;
        }

        $code = $request->request('code');
        if (!$authCode = $this->storage->getAuthorizationCode($code)) {
            $response->setError(400, 'invalid_grant', 'Authorization code doesn\'t exist or is invalid for the client');

            return false;
        }

        /*
         * 4.1.3 - ensure that the "redirect_uri" parameter is present if the "redirect_uri" parameter was included in the initial authorization request
         * @uri - http://tools.ietf.org/html/rfc6749#section-4.1.3
         */
        if (isset($authCode['redirect_uri']) && $authCode['redirect_uri']) {
            if (!$request->request('redirect_uri') || urldecode($request->request('redirect_uri')) != urldecode($authCode['redirect_uri'])) {
                $response->setError(400, 'redirect_uri_mismatch', "The redirect URI is missing or do not match", "#section-4.1.3");

                return false;
            }
        }

        if (!isset($authCode['expires'])) {
            throw new \Exception('Storage must return authcode with a value for "expires"');
        }

        if ($authCode["expires"] < time()) {
            $response->setError(400, 'invalid_grant', "The authorization code has expired");

            return false;
        }

        if (!isset($authCode['code'])) {
            $authCode['code'] = $code; // used to expire the code after the access token is granted
        }

        $this->authCode = $authCode;

        return true;
    }

    /**
     * Get the client id
     *
     * @return mixed
     */
    public function getClientId()
    {
        return $this->authCode['client_id'];
    }

    /**
     * Get the scope
     *
     * @return string
     */
    public function getScope()
    {
        return isset($this->authCode['scope']) ? $this->authCode['scope'] : null;
    }

    /**
     * Get the user id
     *
     * @return mixed
     */
    public function getUserId()
    {
        return isset($this->authCode['user_id']) ? $this->authCode['user_id'] : null;
    }

    /**
     * Create access token
     *
     * @param AccessTokenInterface $accessToken
     * @param mixed                $client_id   - client identifier related to the access token.
     * @param mixed                $user_id     - user id associated with the access token
     * @param string               $scope       - scopes to be stored in space-separated string.
     * @return array
     */
    public function createAccessToken(AccessTokenInterface $accessToken, $client_id, $user_id, $scope)
    {
        $token = $accessToken->createAccessToken($client_id, $user_id, $scope);
        $this->storage->expireAuthorizationCode($this->authCode['code']);

        return $token;
    }
}
