<?php

namespace OAuth2\TokenType;

use OAuth2\RequestInterface;
use OAuth2\ResponseInterface;

/**
*
*/
class Bearer implements TokenTypeInterface
{
    private $config;

    public function __construct(array $config = array())
    {
        $this->config = array_merge(array(
            'token_param_name'         => 'access_token',
            'token_bearer_header_name' => 'Bearer',
        ), $config);
    }

    public function getTokenType()
    {
        return 'Bearer';
    }

    /**
     * Check if the request has supplied token
     *
     * @see https://github.com/bshaffer/oauth2-server-php/issues/349#issuecomment-37993588
     */
    public function requestHasToken(RequestInterface $request)
    {
        $headers = $request->headers('AUTHORIZATION');

        // check the header, then the querystring, then the request body
        return !empty($headers) || (bool) ($request->request($this->config['token_param_name'])) || (bool) ($request->query($this->config['token_param_name']));
    }

    /**
     * This is a convenience function that can be used to get the token, which can then
     * be passed to getAccessTokenData(). The constraints specified by the draft are
     * attempted to be adheared to in this method.
     *
     * As per the Bearer spec (draft 8, section 2) - there are three ways for a client
     * to specify the bearer token, in order of preference: Authorization Header,
     * POST and GET.
     *
     * NB: Resource servers MUST accept tokens via the Authorization scheme
     * (http://tools.ietf.org/html/rfc6750#section-2).
     *
     * @todo Should we enforce TLS/SSL in this function?
     *
     * @see http://tools.ietf.org/html/rfc6750#section-2.1
     * @see http://tools.ietf.org/html/rfc6750#section-2.2
     * @see http://tools.ietf.org/html/rfc6750#section-2.3
     *
     * Old Android version bug (at least with version 2.2)
     * @see http://code.google.com/p/android/issues/detail?id=6684
     *
     */
    public function getAccessTokenParameter(RequestInterface $request, ResponseInterface $response)
    {
        $headers = $request->headers('AUTHORIZATION');

        /**
         * Ensure more than one method is not used for including an
         * access token
         *
         * @see http://tools.ietf.org/html/rfc6750#section-3.1
         */
        $methodsUsed = !empty($headers) + (bool) ($request->query($this->config['token_param_name'])) + (bool) ($request->request($this->config['token_param_name']));
        if ($methodsUsed > 1) {
            $response->setError(400, 'invalid_request', 'Only one method may be used to authenticate at a time (Auth header, GET or POST)');

            return null;
        }

        /**
         * If no authentication is provided, set the status code
         * to 401 and return no other error information
         *
         * @see http://tools.ietf.org/html/rfc6750#section-3.1
         */
        if ($methodsUsed == 0) {
            $response->setStatusCode(401);

            return null;
        }

        // HEADER: Get the access token from the header
        if (!empty($headers)) {
            if (!preg_match('/' . $this->config['token_bearer_header_name'] . '\s(\S+)/i', $headers, $matches)) {
                $response->setError(400, 'invalid_request', 'Malformed auth header');

                return null;
            }

            return $matches[1];
        }

        if ($request->request($this->config['token_param_name'])) {
            // // POST: Get the token from POST data
            if (!in_array(strtolower($request->server('REQUEST_METHOD')), array('post', 'put'))) {
                $response->setError(400, 'invalid_request', 'When putting the token in the body, the method must be POST or PUT', '#section-2.2');

                return null;
            }

            $contentType = $request->server('CONTENT_TYPE');
            if (false !== $pos = strpos($contentType, ';')) {
                $contentType = substr($contentType, 0, $pos);
            }

            if ($contentType !== null && $contentType != 'application/x-www-form-urlencoded') {
                // IETF specifies content-type. NB: Not all webservers populate this _SERVER variable
                // @see http://tools.ietf.org/html/rfc6750#section-2.2
                $response->setError(400, 'invalid_request', 'The content type for POST requests must be "application/x-www-form-urlencoded"');

                return null;
            }

            return $request->request($this->config['token_param_name']);
        }

        // GET method
        return $request->query($this->config['token_param_name']);
    }
}
