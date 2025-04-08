<?php

namespace OAuth2\OpenID\ResponseType;

use OAuth2\ResponseType\AuthorizationCodeInterface as BaseAuthorizationCodeInterface;

/**
 * @author Brent Shaffer <bshafs at gmail dot com>
 */
interface AuthorizationCodeInterface extends BaseAuthorizationCodeInterface
{
    /**
     * Handle the creation of the authorization code.
     *
     * @param mixed  $client_id    - Client identifier related to the authorization code
     * @param mixed  $user_id      - User ID associated with the authorization code
     * @param string $redirect_uri - An absolute URI to which the authorization server will redirect the
     *                               user-agent to when the end-user authorization step is completed.
     * @param string $scope        - OPTIONAL Scopes to be stored in space-separated string.
     * @param string $id_token     - OPTIONAL The OpenID Connect id_token.
     * @return string
     *
     * @see http://tools.ietf.org/html/rfc6749#section-4
     * @ingroup oauth2_section_4
     */
    public function createAuthorizationCode($client_id, $user_id, $redirect_uri, $scope = null, $id_token = null);
}
