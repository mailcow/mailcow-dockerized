<?php

namespace OAuth2\OpenID\ResponseType;

use OAuth2\ResponseType\AccessTokenInterface;

class IdTokenToken implements IdTokenTokenInterface
{
    /**
     * @var AccessTokenInterface
     */
    protected $accessToken;

    /**
     * @var IdTokenInterface
     */
    protected $idToken;

    /**
     * Constructor
     *
     * @param AccessTokenInterface $accessToken
     * @param IdTokenInterface $idToken
     */
    public function __construct(AccessTokenInterface $accessToken, IdTokenInterface $idToken)
    {
        $this->accessToken = $accessToken;
        $this->idToken = $idToken;
    }

    /**
     * @param array $params
     * @param mixed $user_id
     * @return mixed
     */
    public function getAuthorizeResponse($params, $user_id = null)
    {
        $result = $this->accessToken->getAuthorizeResponse($params, $user_id);
        $access_token = $result[1]['fragment']['access_token'];
        $id_token = $this->idToken->createIdToken($params['client_id'], $user_id, $params['nonce'], null, $access_token);
        $result[1]['fragment']['id_token'] = $id_token;

        return $result;
    }
}
