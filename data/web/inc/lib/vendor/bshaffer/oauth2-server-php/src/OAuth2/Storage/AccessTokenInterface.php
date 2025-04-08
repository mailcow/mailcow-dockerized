<?php

namespace OAuth2\Storage;

/**
 * Implement this interface to specify where the OAuth2 Server
 * should get/save access tokens
 *
 * @author Brent Shaffer <bshafs at gmail dot com>
 */
interface AccessTokenInterface
{
    /**
     * Look up the supplied oauth_token from storage.
     *
     * We need to retrieve access token data as we create and verify tokens.
     *
     * @param string $oauth_token - oauth_token to be check with.
     *
     * @return array|null - An associative array as below, and return NULL if the supplied oauth_token is invalid:
     * @code
     *     array(
     *         'expires'   => $expires,   // Stored expiration in unix timestamp.
     *         'client_id' => $client_id, // (optional) Stored client identifier.
     *         'user_id'   => $user_id,   // (optional) Stored user identifier.
     *         'scope'     => $scope,     // (optional) Stored scope values in space-separated string.
     *         'id_token'  => $id_token   // (optional) Stored id_token (if "use_openid_connect" is true).
     *     );
     * @endcode
     *
     * @ingroup oauth2_section_7
     */
    public function getAccessToken($oauth_token);

    /**
     * Store the supplied access token values to storage.
     *
     * We need to store access token data as we create and verify tokens.
     *
     * @param string $oauth_token - oauth_token to be stored.
     * @param mixed  $client_id   - client identifier to be stored.
     * @param mixed  $user_id     - user identifier to be stored.
     * @param int    $expires     - expiration to be stored as a Unix timestamp.
     * @param string $scope       - OPTIONAL Scopes to be stored in space-separated string.
     *
     * @ingroup oauth2_section_4
     */
    public function setAccessToken($oauth_token, $client_id, $user_id, $expires, $scope = null);

    /**
     * Expire an access token.
     *
     * This is not explicitly required in the spec, but if defined in a draft RFC for token
     * revoking (RFC 7009) https://tools.ietf.org/html/rfc7009
     *
     * @param $access_token
     * Access token to be expired.
     *
     * @return BOOL true if an access token was unset, false if not
     * @ingroup oauth2_section_6
     *
     * @todo v2.0 include this method in interface. Omitted to maintain BC in v1.x
     */
    //public function unsetAccessToken($access_token);
}