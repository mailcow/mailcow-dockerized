<?php

namespace OAuth2;

interface RequestInterface
{
    /**
     * @param string $name
     * @param mixed  $default
     * @return mixed
     */
    public function query($name, $default = null);

    /**
     * @param string $name
     * @param mixed  $default
     * @return mixed
     */
    public function request($name, $default = null);

    /**
     * @param string $name
     * @param mixed  $default
     * @return mixed
     */
    public function server($name, $default = null);

    /**
     * @param string $name
     * @param mixed  $default
     * @return mixed
     */
    public function headers($name, $default = null);

    /**
     * @return mixed
     */
    public function getAllQueryParameters();
}
