<?php

namespace LdapRecord\Query\Filter;

class GroupNode extends Node
{
    /**
     * The group's operator.
     *
     * @var string
     */
    protected $operator;

    /**
     * The group's sub-nodes.
     *
     * @var Node[]
     */
    protected $nodes = [];

    /**
     * Constructor.
     *
     * @param  string  $filter
     */
    public function __construct($filter)
    {
        $this->raw = $filter;

        $this->operator = substr($filter, 0, 1);

        $this->nodes = Parser::parse($filter);
    }

    /**
     * Get the group's operator.
     *
     * @return string
     */
    public function getOperator()
    {
        return $this->operator;
    }

    /**
     * Get the group's sub-nodes.
     *
     * @return Node[]
     */
    public function getNodes()
    {
        return $this->nodes;
    }
}
