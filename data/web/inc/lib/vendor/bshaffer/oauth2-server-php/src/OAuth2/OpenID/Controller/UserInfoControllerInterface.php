<?php

namespace OAuth2\OpenID\Controller;

use OAuth2\RequestInterface;
use OAuth2\ResponseInterface;

/**
 *  This controller is called when the user claims for OpenID Connect's
 *  UserInfo endpoint should be returned.
 *
 * @code
 *     $response = new OAuth2\Response();
 *     $userInfoController->handleUserInfoRequest(
 *         OAuth2\Request::createFromGlobals(),
 *         $response
 *     );
 *     $response->send();
 * @endcode
 */
interface UserInfoControllerInterface
{
    /**
     * Handle user info request
     *
     * @param RequestInterface $request
     * @param ResponseInterface $response
     */
    public function handleUserInfoRequest(RequestInterface $request, ResponseInterface $response);
}
