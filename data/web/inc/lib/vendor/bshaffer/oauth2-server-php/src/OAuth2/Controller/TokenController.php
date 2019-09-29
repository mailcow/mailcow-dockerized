<?php

namespace OAuth2\Controller;

use OAuth2\ResponseType\AccessTokenInterface;
use OAuth2\ClientAssertionType\ClientAssertionTypeInterface;
use OAuth2\GrantType\GrantTypeInterface;
use OAuth2\ScopeInterface;
use OAuth2\Scope;
use OAuth2\Storage\ClientInterface;
use OAuth2\RequestInterface;
use OAuth2\ResponseInterface;
use InvalidArgumentException;
use LogicException;
use RuntimeException;

/**
 * @see TokenControllerInterface
 */
class TokenController implements TokenControllerInterface
{
    /**
     * @var AccessTokenInterface
     */
    protected $accessToken;

    /**
     * @var array<GrantTypeInterface>
     */
    protected $grantTypes;

    /**
     * @var ClientAssertionTypeInterface
     */
    protected $clientAssertionType;

    /**
     * @var ScopeInterface
     */
    protected $scopeUtil;

    /**
     * @var ClientInterface
     */
    protected $clientStorage;

    /**
     * Constructor
     *
     * @param AccessTokenInterface         $accessToken
     * @param ClientInterface              $clientStorage
     * @param array                        $grantTypes
     * @param ClientAssertionTypeInterface $clientAssertionType
     * @param ScopeInterface               $scopeUtil
     * @throws InvalidArgumentException
     */
    public function __construct(AccessTokenInterface $accessToken, ClientInterface $clientStorage, array $grantTypes = array(), ClientAssertionTypeInterface $clientAssertionType = null, ScopeInterface $scopeUtil = null)
    {
        if (is_null($clientAssertionType)) {
            foreach ($grantTypes as $grantType) {
                if (!$grantType instanceof ClientAssertionTypeInterface) {
                    throw new InvalidArgumentException('You must supply an instance of OAuth2\ClientAssertionType\ClientAssertionTypeInterface or only use grant types which implement OAuth2\ClientAssertionType\ClientAssertionTypeInterface');
                }
            }
        }
        $this->clientAssertionType = $clientAssertionType;
        $this->accessToken = $accessToken;
        $this->clientStorage = $clientStorage;
        foreach ($grantTypes as $grantType) {
            $this->addGrantType($grantType);
        }

        if (is_null($scopeUtil)) {
            $scopeUtil = new Scope();
        }
        $this->scopeUtil = $scopeUtil;
    }

    /**
     * Handle the token request.
     *
     * @param RequestInterface  $request  - Request object to grant access token
     * @param ResponseInterface $response - Response object
     */
    public function handleTokenRequest(RequestInterface $request, ResponseInterface $response)
    {
        if ($token = $this->grantAccessToken($request, $response)) {
            // @see http://tools.ietf.org/html/rfc6749#section-5.1
            // server MUST disable caching in headers when tokens are involved
            $response->setStatusCode(200);
            $response->addParameters($token);
            $response->addHttpHeaders(array(
                'Cache-Control' => 'no-store',
                'Pragma' => 'no-cache',
                'Content-Type' => 'application/json'
            ));
        }
    }

    /**
     * Grant or deny a requested access token.
     * This would be called from the "/token" endpoint as defined in the spec.
     * You can call your endpoint whatever you want.
     *
     * @param RequestInterface  $request  - Request object to grant access token
     * @param ResponseInterface $response - Response object
     *
     * @return bool|null|array
     *
     * @throws \InvalidArgumentException
     * @throws \LogicException
     *
     * @see http://tools.ietf.org/html/rfc6749#section-4
     * @see http://tools.ietf.org/html/rfc6749#section-10.6
     * @see http://tools.ietf.org/html/rfc6749#section-4.1.3
     *
     * @ingroup oauth2_section_4
     */
    public function grantAccessToken(RequestInterface $request, ResponseInterface $response)
    {
        if (strtolower($request->server('REQUEST_METHOD')) === 'options') {
            $response->addHttpHeaders(array('Allow' => 'POST, OPTIONS'));

            return null;
        }

        if (strtolower($request->server('REQUEST_METHOD')) !== 'post') {
            $response->setError(405, 'invalid_request', 'The request method must be POST when requesting an access token', '#section-3.2');
            $response->addHttpHeaders(array('Allow' => 'POST, OPTIONS'));

            return null;
        }

        /**
         * Determine grant type from request
         * and validate the request for that grant type
         */
        if (!$grantTypeIdentifier = $request->request('grant_type')) {
            $response->setError(400, 'invalid_request', 'The grant type was not specified in the request');

            return null;
        }

        if (!isset($this->grantTypes[$grantTypeIdentifier])) {
            /* TODO: If this is an OAuth2 supported grant type that we have chosen not to implement, throw a 501 Not Implemented instead */
            $response->setError(400, 'unsupported_grant_type', sprintf('Grant type "%s" not supported', $grantTypeIdentifier));

            return null;
        }

        /** @var GrantTypeInterface $grantType */
        $grantType = $this->grantTypes[$grantTypeIdentifier];

        /**
         * Retrieve the client information from the request
         * ClientAssertionTypes allow for grant types which also assert the client data
         * in which case ClientAssertion is handled in the validateRequest method
         *
         * @see \OAuth2\GrantType\JWTBearer
         * @see \OAuth2\GrantType\ClientCredentials
         */
        if (!$grantType instanceof ClientAssertionTypeInterface) {
            if (!$this->clientAssertionType->validateRequest($request, $response)) {
                return null;
            }
            $clientId = $this->clientAssertionType->getClientId();
        }

        /**
         * Retrieve the grant type information from the request
         * The GrantTypeInterface object handles all validation
         * If the object is an instance of ClientAssertionTypeInterface,
         * That logic is handled here as well
         */
        if (!$grantType->validateRequest($request, $response)) {
            return null;
        }

        if ($grantType instanceof ClientAssertionTypeInterface) {
            $clientId = $grantType->getClientId();
        } else {
            // validate the Client ID (if applicable)
            if (!is_null($storedClientId = $grantType->getClientId()) && $storedClientId != $clientId) {
                $response->setError(400, 'invalid_grant', sprintf('%s doesn\'t exist or is invalid for the client', $grantTypeIdentifier));

                return null;
            }
        }

        /**
         * Validate the client can use the requested grant type
         */
        if (!$this->clientStorage->checkRestrictedGrantType($clientId, $grantTypeIdentifier)) {
            $response->setError(400, 'unauthorized_client', 'The grant type is unauthorized for this client_id');

            return false;
        }

        /**
         * Validate the scope of the token
         *
         * requestedScope - the scope specified in the token request
         * availableScope - the scope associated with the grant type
         *  ex: in the case of the "Authorization Code" grant type,
         *  the scope is specified in the authorize request
         *
         * @see http://tools.ietf.org/html/rfc6749#section-3.3
         */
        $requestedScope = $this->scopeUtil->getScopeFromRequest($request);
        $availableScope = $grantType->getScope();

        if ($requestedScope) {
            // validate the requested scope
            if ($availableScope) {
                if (!$this->scopeUtil->checkScope($requestedScope, $availableScope)) {
                    $response->setError(400, 'invalid_scope', 'The scope requested is invalid for this request');

                    return null;
                }
            } else {
                // validate the client has access to this scope
                if ($clientScope = $this->clientStorage->getClientScope($clientId)) {
                    if (!$this->scopeUtil->checkScope($requestedScope, $clientScope)) {
                        $response->setError(400, 'invalid_scope', 'The scope requested is invalid for this client');

                        return false;
                    }
                } elseif (!$this->scopeUtil->scopeExists($requestedScope)) {
                    $response->setError(400, 'invalid_scope', 'An unsupported scope was requested');

                    return null;
                }
            }
        } elseif ($availableScope) {
            // use the scope associated with this grant type
            $requestedScope = $availableScope;
        } else {
            // use a globally-defined default scope
            $defaultScope = $this->scopeUtil->getDefaultScope($clientId);

            // "false" means default scopes are not allowed
            if (false === $defaultScope) {
                $response->setError(400, 'invalid_scope', 'This application requires you specify a scope parameter');

                return null;
            }

            $requestedScope = $defaultScope;
        }

        return $grantType->createAccessToken($this->accessToken, $clientId, $grantType->getUserId(), $requestedScope);
    }

    /**
     * Add grant type
     *
     * @param GrantTypeInterface $grantType  - the grant type to add for the specified identifier
     * @param string|null        $identifier - a string passed in as "grant_type" in the response that will call this grantType
     */
    public function addGrantType(GrantTypeInterface $grantType, $identifier = null)
    {
        if (is_null($identifier) || is_numeric($identifier)) {
            $identifier = $grantType->getQueryStringIdentifier();
        }

        $this->grantTypes[$identifier] = $grantType;
    }

    /**
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     */
    public function handleRevokeRequest(RequestInterface $request, ResponseInterface $response)
    {
        if ($this->revokeToken($request, $response)) {
            $response->setStatusCode(200);
            $response->addParameters(array('revoked' => true));
        }
    }

    /**
     * Revoke a refresh or access token. Returns true on success and when tokens are invalid
     *
     * Note: invalid tokens do not cause an error response since the client
     * cannot handle such an error in a reasonable way.  Moreover, the
     * purpose of the revocation request, invalidating the particular token,
     * is already achieved.
     *
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @throws RuntimeException
     * @return bool|null
     */
    public function revokeToken(RequestInterface $request, ResponseInterface $response)
    {
        if (strtolower($request->server('REQUEST_METHOD')) === 'options') {
            $response->addHttpHeaders(array('Allow' => 'POST, OPTIONS'));

            return null;
        }

        if (strtolower($request->server('REQUEST_METHOD')) !== 'post') {
            $response->setError(405, 'invalid_request', 'The request method must be POST when revoking an access token', '#section-3.2');
            $response->addHttpHeaders(array('Allow' => 'POST, OPTIONS'));

            return null;
        }

        $token_type_hint = $request->request('token_type_hint');
        if (!in_array($token_type_hint, array(null, 'access_token', 'refresh_token'), true)) {
            $response->setError(400, 'invalid_request', 'Token type hint must be either \'access_token\' or \'refresh_token\'');

            return null;
        }

        $token = $request->request('token');
        if ($token === null) {
            $response->setError(400, 'invalid_request', 'Missing token parameter to revoke');

            return null;
        }

        // @todo remove this check for v2.0
        if (!method_exists($this->accessToken, 'revokeToken')) {
            $class = get_class($this->accessToken);
            throw new RuntimeException("AccessToken {$class} does not implement required revokeToken method");
        }

        $this->accessToken->revokeToken($token, $token_type_hint);

        return true;
    }
}
