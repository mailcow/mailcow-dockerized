<?php

namespace OAuth2;

/**
 * Interface which represents an object response.  Meant to handle and display the proper OAuth2 Responses
 * for errors and successes
 *
 * @see \OAuth2\Response
 */
interface ResponseInterface
{
    /**
     * @param array $parameters
     */
    public function addParameters(array $parameters);

    /**
     * @param array $httpHeaders
     */
    public function addHttpHeaders(array $httpHeaders);

    /**
     * @param int $statusCode
     */
    public function setStatusCode($statusCode);

    /**
     * @param int    $statusCode
     * @param string $name
     * @param string $description
     * @param string $uri
     * @return mixed
     */
    public function setError($statusCode, $name, $description = null, $uri = null);

    /**
     * @param int    $statusCode
     * @param string $url
     * @param string $state
     * @param string $error
     * @param string $errorDescription
     * @param string $errorUri
     * @return mixed
     */
    public function setRedirect($statusCode, $url, $state = null, $error = null, $errorDescription = null, $errorUri = null);

    /**
     * @param string $name
     * @return mixed
     */
    public function getParameter($name);
}
