<?php

namespace LdapRecord\Query\Filter;

use LdapRecord\Support\Str;

class ConditionNode extends Node
{
    /**
     * The condition's attribute.
     *
     * @var string
     */
    protected $attribute;

    /**
     * The condition's operator.
     *
     * @var string
     */
    protected $operator;

    /**
     * The condition's value.
     *
     * @var string
     */
    protected $value;

    /**
     * The available condition operators.
     *
     * @var array
     */
    protected $operators = ['>=', '<=', '~=', '='];

    /**
     * Constructor.
     *
     * @param  string  $filter
     */
    public function __construct($filter)
    {
        $this->raw = $filter;

        [$this->attribute, $this->value] = $this->extractComponents($filter);
    }

    /**
     * Get the condition's attribute.
     *
     * @return string
     */
    public function getAttribute()
    {
        return $this->attribute;
    }

    /**
     * Get the condition's operator.
     *
     * @return string
     */
    public function getOperator()
    {
        return $this->operator;
    }

    /**
     * Get the condition's value.
     *
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Extract the condition components from the filter.
     *
     * @param  string  $filter
     * @return array
     */
    protected function extractComponents($filter)
    {
        $components = Str::whenContains(
            $filter,
            $this->operators,
            function ($operator, $filter) {
                return explode($this->operator = $operator, $filter, 2);
            },
            function ($filter) {
                throw new ParserException("Invalid query condition. No operator found in [$filter]");
            },
        );

        return $components;
    }
}
