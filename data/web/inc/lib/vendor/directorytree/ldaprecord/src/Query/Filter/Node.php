<?php

namespace LdapRecord\Query\Filter;

abstract class Node
{
    /**
     * The raw value of the node.
     */
    protected string $raw;

    /**
     * Create a new filter node.
     */
    abstract public function __construct(string $filter);

    /**
     * Get the raw value of the node.
     */
    public function getRaw(): string
    {
        return $this->raw;
    }
}
