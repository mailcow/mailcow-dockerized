<?php

namespace LdapRecord\Query;

use UnexpectedValueException;

class Grammar
{
    /**
     * The query operators and their method names.
     */
    public array $operators = [
        '*' => 'has',
        '!*' => 'notHas',
        '=' => 'equals',
        '!' => 'doesNotEqual',
        '!=' => 'doesNotEqual',
        '>=' => 'greaterThanOrEquals',
        '<=' => 'lessThanOrEquals',
        '~=' => 'approximatelyEquals',
        'starts_with' => 'startsWith',
        'not_starts_with' => 'notStartsWith',
        'ends_with' => 'endsWith',
        'not_ends_with' => 'notEndsWith',
        'contains' => 'contains',
        'not_contains' => 'notContains',
    ];

    /**
     * The query wrapper.
     */
    protected ?string $wrapper = null;

    /**
     * Get all the available operators.
     */
    public function getOperators(): array
    {
        return array_keys($this->operators);
    }

    /**
     * Wraps a query string in brackets.
     *
     * Produces: (query)
     */
    public function wrap(string $query, ?string $prefix = '(', ?string $suffix = ')'): string
    {
        return $prefix.$query.$suffix;
    }

    /**
     * Compiles the Builder instance into an LDAP query string.
     */
    public function compile(Builder $query): string
    {
        if ($this->queryMustBeWrapped($query)) {
            $this->wrapper = 'and';
        }

        $filter = $this->compileRaws($query)
            .$this->compileWheres($query)
            .$this->compileOrWheres($query);

        return match ($this->wrapper) {
            'and' => $this->compileAnd($filter),
            'or' => $this->compileOr($filter),
            default => $filter,
        };
    }

    /**
     * Determine if the query must be wrapped in an encapsulating statement.
     */
    protected function queryMustBeWrapped(Builder $query): bool
    {
        return ! $query->isNested() && $this->hasMultipleFilters($query);
    }

    /**
     * Assembles all the "raw" filters on the query.
     */
    protected function compileRaws(Builder $builder): string
    {
        return $this->concatenate($builder->filters['raw']);
    }

    /**
     * Assembles all where clauses in the current wheres property.
     */
    protected function compileWheres(Builder $builder, string $type = 'and'): string
    {
        $filter = '';

        foreach ($builder->filters[$type] ?? [] as $where) {
            $filter .= $this->compileWhere($where);
        }

        return $filter;
    }

    /**
     * Assembles all or where clauses in the current orWheres property.
     */
    protected function compileOrWheres(Builder $query): string
    {
        $filter = $this->compileWheres($query, 'or');

        if (! $this->hasMultipleFilters($query)) {
            return $filter;
        }

        // Here we will detect whether the entire query can be
        // wrapped inside of an "or" statement by checking
        // how many filter statements exist for each type.
        if ($this->queryCanBeWrappedInSingleOrStatement($query)) {
            $this->wrapper = 'or';
        } else {
            $filter = $this->compileOr($filter);
        }

        return $filter;
    }

    /**
     * Determine if the query can be wrapped in a single or statement.
     */
    protected function queryCanBeWrappedInSingleOrStatement(Builder $query): bool
    {
        return $this->has($query, 'or', '>=', 1)
            && $this->has($query, 'and', '<=', 1)
            && $this->has($query, 'raw', '=', 0);
    }

    /**
     * Concatenates filters into a single string.
     */
    public function concatenate(array $bindings = []): string
    {
        // Filter out empty query segments.
        return implode(
            array_filter($bindings, [$this, 'bindingValueIsNotEmpty'])
        );
    }

    /**
     * Determine if the binding value is not empty.
     */
    protected function bindingValueIsNotEmpty(string $value): bool
    {
        return ! empty($value);
    }

    /**
     * Determine if the query is using multiple filters.
     */
    protected function hasMultipleFilters(Builder $query): bool
    {
        return $this->has($query, ['and', 'or', 'raw'], '>', 1);
    }

    /**
     * Determine if the query contains the given filter statement type.
     */
    protected function has(Builder $query, array|string $type, string $operator = '>=', int $count = 1): bool
    {
        $types = (array) $type;

        $filters = 0;

        foreach ($types as $type) {
            $filters += count($query->filters[$type] ?? []);
        }

        return match ($operator) {
            '>' => $filters > $count,
            '>=' => $filters >= $count,
            '<' => $filters < $count,
            '<=' => $filters <= $count,
            default => $filters == $count,
        };
    }

    /**
     * Returns a query string for equals.
     *
     * Produces: (field=value)
     */
    public function compileEquals(string $field, string $value): string
    {
        return $this->wrap($field.'='.$value);
    }

    /**
     * Returns a query string for does not equal.
     *
     * Produces: (!(field=value))
     */
    public function compileDoesNotEqual(string $field, string $value): string
    {
        return $this->compileNot(
            $this->compileEquals($field, $value)
        );
    }

    /**
     * Alias for does not equal operator (!=) operator.
     *
     * Produces: (!(field=value))
     */
    public function compileDoesNotEqualAlias(string $field, string $value): string
    {
        return $this->compileDoesNotEqual($field, $value);
    }

    /**
     * Returns a query string for greater than or equals.
     *
     * Produces: (field>=value)
     */
    public function compileGreaterThanOrEquals(string $field, string $value): string
    {
        return $this->wrap("$field>=$value");
    }

    /**
     * Returns a query string for less than or equals.
     *
     * Produces: (field<=value)
     */
    public function compileLessThanOrEquals(string $field, string $value): string
    {
        return $this->wrap("$field<=$value");
    }

    /**
     * Returns a query string for approximately equals.
     *
     * Produces: (field~=value)
     */
    public function compileApproximatelyEquals(string $field, string $value): string
    {
        return $this->wrap("$field~=$value");
    }

    /**
     * Returns a query string for starts with.
     *
     * Produces: (field=value*)
     */
    public function compileStartsWith(string $field, string $value): string
    {
        return $this->wrap("$field=$value*");
    }

    /**
     * Returns a query string for does not start with.
     *
     * Produces: (!(field=*value))
     */
    public function compileNotStartsWith(string $field, string $value): string
    {
        return $this->compileNot(
            $this->compileStartsWith($field, $value)
        );
    }

    /**
     * Returns a query string for ends with.
     *
     * Produces: (field=*value)
     */
    public function compileEndsWith(string $field, string $value): string
    {
        return $this->wrap("$field=*$value");
    }

    /**
     * Returns a query string for does not end with.
     *
     * Produces: (!(field=value*))
     */
    public function compileNotEndsWith(string $field, string $value): string
    {
        return $this->compileNot($this->compileEndsWith($field, $value));
    }

    /**
     * Returns a query string for contains.
     *
     * Produces: (field=*value*)
     */
    public function compileContains(string $field, string $value): string
    {
        return $this->wrap("$field=*$value*");
    }

    /**
     * Returns a query string for does not contain.
     *
     * Produces: (!(field=*value*))
     */
    public function compileNotContains(string $field, string $value): string
    {
        return $this->compileNot(
            $this->compileContains($field, $value)
        );
    }

    /**
     * Returns a query string for a where has.
     *
     * Produces: (field=*)
     */
    public function compileHas(string $field): string
    {
        return $this->wrap("$field=*");
    }

    /**
     * Returns a query string for a where does not have.
     *
     * Produces: (!(field=*))
     */
    public function compileNotHas(string $field): string
    {
        return $this->compileNot(
            $this->compileHas($field)
        );
    }

    /**
     * Wraps the inserted query inside an AND operator.
     *
     * Produces: (&query)
     */
    public function compileAnd(string $query): string
    {
        return $query ? $this->wrap($query, '(&') : '';
    }

    /**
     * Wraps the inserted query inside an OR operator.
     *
     * Produces: (|query)
     */
    public function compileOr(string $query): string
    {
        return $query ? $this->wrap($query, '(|') : '';
    }

    /**
     * Wraps the inserted query inside an NOT operator.
     */
    public function compileNot(string $query): string
    {
        return $query ? $this->wrap($query, '(!') : '';
    }

    /**
     * Assembles a single where query.
     *
     * @throws UnexpectedValueException
     */
    protected function compileWhere(array $where): string
    {
        $method = $this->makeCompileMethod($where['operator']);

        return $this->{$method}($where['field'], $where['value']);
    }

    /**
     * Make the compile method name for the operator.
     *
     * @throws UnexpectedValueException
     */
    protected function makeCompileMethod(string $operator): string
    {
        if (! $this->operatorExists($operator)) {
            throw new UnexpectedValueException("Invalid LDAP filter operator ['$operator']");
        }

        return 'compile'.ucfirst($this->operators[$operator]);
    }

    /**
     * Determine if the operator exists.
     */
    protected function operatorExists(string $operator): bool
    {
        return array_key_exists($operator, $this->operators);
    }
}
