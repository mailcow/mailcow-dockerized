<?php

namespace LdapRecord\Query\Filter;

use LdapRecord\Support\Str;

class ConditionNode extends Node
{
    /**
     * The condition's attribute.
     */
    protected string $attribute;

    /**
     * The condition's operator.
     */
    protected string $operator;

    /**
     * The condition's value.
     */
    protected string $value;

    /**
     * The available condition operators.
     */
    protected array $operators = ['>=', '<=', '~=', '='];

    /**
     * Constructor.
     */
    public function __construct(string $filter)
    {
        $this->raw = $filter;

        [$this->attribute, $this->value] = $this->extractComponents($filter);
    }

    /**
     * Get the condition's attribute.
     */
    public function getAttribute(): string
    {
        return $this->attribute;
    }

    /**
     * Get the condition's operator.
     */
    public function getOperator(): string
    {
        return $this->operator;
    }

    /**
     * Get the condition's value.
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Extract the condition components from the filter.
     */
    protected function extractComponents(string $filter): array
    {
        $components = Str::whenContains(
            $filter,
            $this->operators,
            fn ($operator, $filter) => explode($this->operator = $operator, $filter, 2),
            fn ($filter) => throw new ParserException("Invalid query condition. No operator found in [$filter]"),
        );

        return $components;
    }
}
