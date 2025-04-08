<?php

namespace OAuth2\OpenID\ResponseType;

class CodeIdToken implements CodeIdTokenInterface
{
    /**
     * @var AuthorizationCodeInterface
     */
    protected $authCode;

    /**
     * @var IdTokenInterface
     */
    protected $idToken;

    /**
     * @param AuthorizationCodeInterface $authCode
     * @param IdTokenInterface           $idToken
     */
    public function __construct(AuthorizationCodeInterface $authCode, IdTokenInterface $idToken)
    {
        $this->authCode = $authCode;
        $this->idToken = $idToken;
    }

    /**
     * @param array $params
     * @param mixed $user_id
     * @return mixed
     */
    public function getAuthorizeResponse($params, $user_id = null)
    {
        $result = $this->authCode->getAuthorizeResponse($params, $user_id);
        $resultIdToken = $this->idToken->getAuthorizeResponse($params, $user_id);
        $result[1]['query']['id_token'] = $resultIdToken[1]['fragment']['id_token'];

        return $result;
    }
}
