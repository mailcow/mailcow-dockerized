<?php

namespace LdapRecord\Query\Filter;

class GroupNode extends Node
{
    /**
     * The group's operator.
     */
    protected string $operator;

    /**
     * The group's sub-nodes.
     *
     * @var Node[]
     */
    protected array $nodes = [];

    /**
     * Constructor.
     */
    public function __construct(string $filter)
    {
        $this->raw = $filter;

        $this->operator = substr($filter, 0, 1);

        $this->nodes = Parser::parse($filter);
    }

    /**
     * Get the group's operator.
     */
    public function getOperator(): string
    {
        return $this->operator;
    }

    /**
     * Get the group's sub-nodes.
     *
     * @return Node[]
     */
    public function getNodes(): array
    {
        return $this->nodes;
    }
}
