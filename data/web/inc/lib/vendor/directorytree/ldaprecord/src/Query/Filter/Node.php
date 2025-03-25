<?php

namespace LdapRecord\Query\Filter;

abstract class Node
{
    /**
     * The raw value of the node.
     *
     * @var string
     */
    protected $raw;

    /**
     * Create a new filter node.
     *
     * @param  string  $filter
     */
    abstract public function __construct($filter);

    /**
     * Get the raw value of the node.
     *
     * @return string
     */
    public function getRaw()
    {
        return $this->raw;
    }
}
