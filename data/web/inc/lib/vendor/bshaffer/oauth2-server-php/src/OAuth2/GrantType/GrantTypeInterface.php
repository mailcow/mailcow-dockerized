<?php

namespace OAuth2\GrantType;

use OAuth2\ResponseType\AccessTokenInterface;
use OAuth2\RequestInterface;
use OAuth2\ResponseInterface;

/**
 * Interface for all OAuth2 Grant Types
 */
interface GrantTypeInterface
{
    /**
     * Get query string identifier
     *
     * @return string
     */
    public function getQueryStringIdentifier();

    /**
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @return mixed
     */
    public function validateRequest(RequestInterface $request, ResponseInterface $response);

    /**
     * Get client id
     *
     * @return mixed
     */
    public function getClientId();

    /**
     * Get user id
     *
     * @return mixed
     */
    public function getUserId();

    /**
     * Get scope
     *
     * @return string|null
     */
    public function getScope();

    /**
     * Create access token
     *
     * @param AccessTokenInterface $accessToken
     * @param mixed                $client_id   - client identifier related to the access token.
     * @param mixed                $user_id     - user id associated with the access token
     * @param string               $scope       - scopes to be stored in space-separated string.
     * @return array
     */
    public function createAccessToken(AccessTokenInterface $accessToken, $client_id, $user_id, $scope);
}
