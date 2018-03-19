<?php

namespace OAuth2\OpenID\ResponseType;

use OAuth2\ResponseType\AuthorizationCode as BaseAuthorizationCode;
use OAuth2\OpenID\Storage\AuthorizationCodeInterface as AuthorizationCodeStorageInterface;

/**
 * @author Brent Shaffer <bshafs at gmail dot com>
 */
class AuthorizationCode extends BaseAuthorizationCode implements AuthorizationCodeInterface
{
    /**
     * Constructor
     *
     * @param AuthorizationCodeStorageInterface $storage
     * @param array $config
     */
    public function __construct(AuthorizationCodeStorageInterface $storage, array $config = array())
    {
        parent::__construct($storage, $config);
    }

    /**
     * @param $params
     * @param null $user_id
     * @return array
     */
    public function getAuthorizeResponse($params, $user_id = null)
    {
        // build the URL to redirect to
        $result = array('query' => array());

        $params += array('scope' => null, 'state' => null, 'id_token' => null);

        $result['query']['code'] = $this->createAuthorizationCode($params['client_id'], $user_id, $params['redirect_uri'], $params['scope'], $params['id_token']);

        if (isset($params['state'])) {
            $result['query']['state'] = $params['state'];
        }

        return array($params['redirect_uri'], $result);
    }

    /**
     * Handle the creation of the authorization code.
     *
     * @param mixed $client_id - Client identifier related to the authorization code
     * @param mixed $user_id - User ID associated with the authorization code
     * @param string $redirect_uri - An absolute URI to which the authorization server will redirect the
     *                               user-agent to when the end-user authorization step is completed.
     * @param string $scope - OPTIONAL Scopes to be stored in space-separated string.
     * @param string $id_token - OPTIONAL The OpenID Connect id_token.
     *
     * @return string
     * @see http://tools.ietf.org/html/rfc6749#section-4
     * @ingroup oauth2_section_4
     */
    public function createAuthorizationCode($client_id, $user_id, $redirect_uri, $scope = null, $id_token = null)
    {
        $code = $this->generateAuthorizationCode();
        $this->storage->setAuthorizationCode($code, $client_id, $user_id, $redirect_uri, time() + $this->config['auth_code_lifetime'], $scope, $id_token);

        return $code;
    }
}
