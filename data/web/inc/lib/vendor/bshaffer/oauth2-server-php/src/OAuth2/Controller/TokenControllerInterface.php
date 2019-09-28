<?php

namespace OAuth2\Controller;

use OAuth2\RequestInterface;
use OAuth2\ResponseInterface;

/**
 *  This controller is called when a token is being requested.
 *  it is called to handle all grant types the application supports.
 *  It also validates the client's credentials
 *
 * @code
 *     $tokenController->handleTokenRequest(OAuth2\Request::createFromGlobals(), $response = new OAuth2\Response());
 *     $response->send();
 * @endcode
 */
interface TokenControllerInterface
{
    /**
     * Handle the token request
     *
     * @param RequestInterface $request   - The current http request
     * @param ResponseInterface $response - An instance of OAuth2\ResponseInterface to contain the response data
     */
    public function handleTokenRequest(RequestInterface $request, ResponseInterface $response);

    /**
     * Grant or deny a requested access token.
     * This would be called from the "/token" endpoint as defined in the spec.
     * You can call your endpoint whatever you want.
     *
     * @param RequestInterface  $request  - Request object to grant access token
     * @param ResponseInterface $response - Response object
     *
     * @return mixed
     */
    public function grantAccessToken(RequestInterface $request, ResponseInterface $response);
}
