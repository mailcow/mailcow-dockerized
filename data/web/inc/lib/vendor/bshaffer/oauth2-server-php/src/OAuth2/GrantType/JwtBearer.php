<?php

namespace OAuth2\GrantType;

use OAuth2\ClientAssertionType\ClientAssertionTypeInterface;
use OAuth2\Storage\JwtBearerInterface;
use OAuth2\Encryption\Jwt;
use OAuth2\Encryption\EncryptionInterface;
use OAuth2\ResponseType\AccessTokenInterface;
use OAuth2\RequestInterface;
use OAuth2\ResponseInterface;

/**
 * The JWT bearer authorization grant implements JWT (JSON Web Tokens) as a grant type per the IETF draft.
 *
 * @see http://tools.ietf.org/html/draft-ietf-oauth-jwt-bearer-04#section-4
 *
 * @author F21
 * @author Brent Shaffer <bshafs at gmail dot com>
 */
class JwtBearer implements GrantTypeInterface, ClientAssertionTypeInterface
{
    private $jwt;

    protected $storage;
    protected $audience;
    protected $jwtUtil;
    protected $allowedAlgorithms;

    /**
     * Creates an instance of the JWT bearer grant type.
     *
     * @param JwtBearerInterface      $storage  - A valid storage interface that implements storage hooks for the JWT
     *                                            bearer grant type.
     * @param string                  $audience - The audience to validate the token against. This is usually the full
     *                                            URI of the OAuth token requests endpoint.
     * @param EncryptionInterface|JWT $jwtUtil  - OPTONAL The class used to decode, encode and verify JWTs.
     * @param array                   $config
     */
    public function __construct(JwtBearerInterface $storage, $audience, EncryptionInterface $jwtUtil = null, array $config = array())
    {
        $this->storage = $storage;
        $this->audience = $audience;

        if (is_null($jwtUtil)) {
            $jwtUtil = new Jwt();
        }

        $this->config = array_merge(array(
            'allowed_algorithms' => array('RS256', 'RS384', 'RS512')
        ), $config);

        $this->jwtUtil = $jwtUtil;

        $this->allowedAlgorithms = $this->config['allowed_algorithms'];
    }

    /**
     * Returns the grant_type get parameter to identify the grant type request as JWT bearer authorization grant.
     *
     * @return string - The string identifier for grant_type.
     *
     * @see GrantTypeInterface::getQueryStringIdentifier()
     */
    public function getQueryStringIdentifier()
    {
        return 'urn:ietf:params:oauth:grant-type:jwt-bearer';
    }

    /**
     * Validates the data from the decoded JWT.
     *
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     * @return bool|mixed|null TRUE if the JWT request is valid and can be decoded. Otherwise, FALSE is returned.@see GrantTypeInterface::getTokenData()
     */
    public function validateRequest(RequestInterface $request, ResponseInterface $response)
    {
        if (!$request->request("assertion")) {
            $response->setError(400, 'invalid_request', 'Missing parameters: "assertion" required');

            return null;
        }

        // Store the undecoded JWT for later use
        $undecodedJWT = $request->request('assertion');

        // Decode the JWT
        $jwt = $this->jwtUtil->decode($request->request('assertion'), null, false);

        if (!$jwt) {
            $response->setError(400, 'invalid_request', "JWT is malformed");

            return null;
        }

        // ensure these properties contain a value
        // @todo: throw malformed error for missing properties
        $jwt = array_merge(array(
            'scope' => null,
            'iss' => null,
            'sub' => null,
            'aud' => null,
            'exp' => null,
            'nbf' => null,
            'iat' => null,
            'jti' => null,
            'typ' => null,
        ), $jwt);

        if (!isset($jwt['iss'])) {
            $response->setError(400, 'invalid_grant', "Invalid issuer (iss) provided");

            return null;
        }

        if (!isset($jwt['sub'])) {
            $response->setError(400, 'invalid_grant', "Invalid subject (sub) provided");

            return null;
        }

        if (!isset($jwt['exp'])) {
            $response->setError(400, 'invalid_grant', "Expiration (exp) time must be present");

            return null;
        }

        // Check expiration
        if (ctype_digit($jwt['exp'])) {
            if ($jwt['exp'] <= time()) {
                $response->setError(400, 'invalid_grant', "JWT has expired");

                return null;
            }
        } else {
            $response->setError(400, 'invalid_grant', "Expiration (exp) time must be a unix time stamp");

            return null;
        }

        // Check the not before time
        if ($notBefore = $jwt['nbf']) {
            if (ctype_digit($notBefore)) {
                if ($notBefore > time()) {
                    $response->setError(400, 'invalid_grant', "JWT cannot be used before the Not Before (nbf) time");

                    return null;
                }
            } else {
                $response->setError(400, 'invalid_grant', "Not Before (nbf) time must be a unix time stamp");

                return null;
            }
        }

        // Check the audience if required to match
        if (!isset($jwt['aud']) || ($jwt['aud'] != $this->audience)) {
            $response->setError(400, 'invalid_grant', "Invalid audience (aud)");

            return null;
        }

        // Check the jti (nonce)
        // @see http://tools.ietf.org/html/draft-ietf-oauth-json-web-token-13#section-4.1.7
        if (isset($jwt['jti'])) {
            $jti = $this->storage->getJti($jwt['iss'], $jwt['sub'], $jwt['aud'], $jwt['exp'], $jwt['jti']);

            //Reject if jti is used and jwt is still valid (exp parameter has not expired).
            if ($jti && $jti['expires'] > time()) {
                $response->setError(400, 'invalid_grant', "JSON Token Identifier (jti) has already been used");

                return null;
            } else {
                $this->storage->setJti($jwt['iss'], $jwt['sub'], $jwt['aud'], $jwt['exp'], $jwt['jti']);
            }
        }

        // Get the iss's public key
        // @see http://tools.ietf.org/html/draft-ietf-oauth-json-web-token-06#section-4.1.1
        if (!$key = $this->storage->getClientKey($jwt['iss'], $jwt['sub'])) {
            $response->setError(400, 'invalid_grant', "Invalid issuer (iss) or subject (sub) provided");

            return null;
        }

        // Verify the JWT
        if (!$this->jwtUtil->decode($undecodedJWT, $key, $this->allowedAlgorithms)) {
            $response->setError(400, 'invalid_grant', "JWT failed signature verification");

            return null;
        }

        $this->jwt = $jwt;

        return true;
    }

    /**
     * Get client id
     *
     * @return mixed
     */
    public function getClientId()
    {
        return $this->jwt['iss'];
    }

    /**
     * Get user id
     *
     * @return mixed
     */
    public function getUserId()
    {
        return $this->jwt['sub'];
    }

    /**
     * Get scope
     *
     * @return null
     */
    public function getScope()
    {
        return null;
    }

    /**
     * Creates an access token that is NOT associated with a refresh token.
     * If a subject (sub) the name of the user/account we are accessing data on behalf of.
     *
     * @see GrantTypeInterface::createAccessToken()
     *
     * @param AccessTokenInterface $accessToken
     * @param mixed                $client_id   - client identifier related to the access token.
     * @param mixed                $user_id     - user id associated with the access token
     * @param string               $scope       - scopes to be stored in space-separated string.
     * @return array
     */
    public function createAccessToken(AccessTokenInterface $accessToken, $client_id, $user_id, $scope)
    {
        $includeRefreshToken = false;

        return $accessToken->createAccessToken($client_id, $user_id, $scope, $includeRefreshToken);
    }
}
