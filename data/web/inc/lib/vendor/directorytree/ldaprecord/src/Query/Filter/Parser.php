<?php

namespace LdapRecord\Query\Filter;

use LdapRecord\Support\Arr;
use LdapRecord\Support\Str;

class Parser
{
    /**
     * Parse an LDAP filter into nodes.
     *
     * @return (ConditionNode|GroupNode)[]
     *
     * @throws ParserException
     */
    public static function parse(string $string): array
    {
        [$open, $close] = static::countParenthesis($string);

        if ($open !== $close) {
            $errors = [-1 => '"("', 1 => '")"'];

            throw new ParserException(
                sprintf('Unclosed filter group. Missing %s parenthesis', $errors[$open <=> $close])
            );
        }

        return static::buildNodes(
            array_map('trim', static::match($string))
        );
    }

    /**
     * Perform a match for all filters in the string.
     */
    protected static function match(string $string): array
    {
        preg_match_all("/\((((?>[^()]+)|(?R))*)\)/", trim($string), $matches);

        return $matches[1] ?? [];
    }

    /**
     * Assemble the parsed nodes into a single filter.
     *
     * @param  Node|Node[]  $nodes
     */
    public static function assemble(Node|array $nodes = []): string
    {
        return array_reduce(Arr::wrap($nodes), fn ($carry, Node $node) => (
            $carry .= static::compileNode($node)
        ));
    }

    /**
     * Assemble the node into its string based format.
     */
    protected static function compileNode(Node $node): string
    {
        switch (true) {
            case $node instanceof GroupNode:
                return static::wrap($node->getOperator().static::assemble($node->getNodes()));
            case $node instanceof ConditionNode:
                return static::wrap($node->getAttribute().$node->getOperator().$node->getValue());
            default:
                return $node->getRaw();
        }
    }

    /**
     * Build an array of nodes from the given filters.
     *
     * @param  string[]  $filters
     * @return (ConditionNode|GroupNode)[]
     *
     * @throws ParserException
     */
    protected static function buildNodes(array $filters = []): array
    {
        return array_map(function ($filter) {
            if (static::isWrapped($filter)) {
                $filter = static::unwrap($filter);
            }

            if (static::isGroup($filter) && ! Str::endsWith($filter, ')')) {
                throw new ParserException(sprintf('Unclosed filter group [%s]', Str::afterLast($filter, ')')));
            }

            return static::isGroup($filter)
                ? new GroupNode($filter)
                : new ConditionNode($filter);
        }, $filters);
    }

    /**
     * Count the open and close parenthesis of the sting.
     */
    protected static function countParenthesis(string $string): array
    {
        return [Str::substrCount($string, '('), Str::substrCount($string, ')')];
    }

    /**
     * Wrap the value in parentheses.
     */
    protected static function wrap(string $value): string
    {
        return "($value)";
    }

    /**
     * Recursively unwrwap the value from its parentheses.
     */
    protected static function unwrap(string $value): string
    {
        $nodes = static::parse($value);

        $unwrapped = Arr::first($nodes);

        return $unwrapped instanceof Node ? $unwrapped->getRaw() : $value;
    }

    /**
     * Determine if the filter is wrapped.
     */
    protected static function isWrapped(string $filter): bool
    {
        return Str::startsWith($filter, '(') && Str::endsWith($filter, ')');
    }

    /**
     * Determine if the filter is a group.
     */
    protected static function isGroup(string $filter): bool
    {
        return Str::startsWith($filter, ['&', '|', '!']);
    }
}
