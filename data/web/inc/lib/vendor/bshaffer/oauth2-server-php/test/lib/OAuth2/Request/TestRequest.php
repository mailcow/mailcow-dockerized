<?php

namespace OAuth2\Request;

use OAuth2\Request;
use OAuth2\RequestInterface;

/**
*
*/
class TestRequest extends Request implements RequestInterface
{
    public $query, $request, $server, $headers;

    public function __construct()
    {
        $this->query = $_GET;
        $this->request = $_POST;
        $this->server  = $_SERVER;
        $this->headers = array();
    }

    public function query($name, $default = null)
    {
        return isset($this->query[$name]) ? $this->query[$name] : $default;
    }

    public function request($name, $default = null)
    {
        return isset($this->request[$name]) ? $this->request[$name] : $default;
    }

    public function server($name, $default = null)
    {
        return isset($this->server[$name]) ? $this->server[$name] : $default;
    }

    public function getAllQueryParameters()
    {
        return $this->query;
    }

    public function setQuery(array $query)
    {
        $this->query = $query;
    }

    public function setMethod($method)
    {
        $this->server['REQUEST_METHOD'] = $method;
    }

    public function setPost(array $params)
    {
        $this->setMethod('POST');
        $this->request = $params;
    }

    public static function createPost(array $params = array())
    {
        $request = new self();
        $request->setPost($params);

        return $request;
    }
}
