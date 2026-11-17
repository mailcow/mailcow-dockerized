<?php

declare(strict_types=1);

namespace Mailcow\Scim\Repository;

use Mailcow\Scim\Model\Filter;

/**
 * Narrows a SCIM filter down to SQL for the repositories, so a lookup like `userName eq "x"` reads one row
 * instead of all resources.
 *
 * The SQL is a pre-filter: it keeps at least every resource the filter matches (the case-insensitive collation
 * matches no less than the filter does), and the caller still evaluates the full filter in memory. Parts it cannot
 * express (not, pr, gt/lt, ne, unmapped attributes) are left out of an `and` and make an `or` untranslatable.
 * A boolean is compared to a column that holds 1 for true (mailbox.active: 0 and 2 are both inactive for SCIM).
 */
class FilterSql
{
    /**
     * @param array<string, string> $columns attribute path (lowercase, e.g. "name.formatted") => SQL column
     * @return array{0: string, 1: array} " AND (...)" or "", and its parameters
     */
    public static function where(?Filter $filter, array $columns): array
    {
        $params = [];
        $sql = ($filter === null) ? null : self::node($filter->ast, $columns, $params, '');
        return [($sql === null) ? '' : " AND $sql", $params];
    }

    private static function node(array $node, array $columns, array &$params, string $prefix): ?string
    {
        switch ($node['op']) {
            case 'and':
                $l = self::node($node['l'], $columns, $params, $prefix);
                $r = self::node($node['r'], $columns, $params, $prefix);
                return ($l === null || $r === null) ? ($l ?? $r) : "($l AND $r)";
            case 'or':
                $saved = $params;
                $l = self::node($node['l'], $columns, $params, $prefix);
                $r = self::node($node['r'], $columns, $params, $prefix);
                if ($l === null || $r === null) {
                    // PDO rejects parameters the statement does not use
                    $params = $saved;
                    return null;
                }
                return "($l OR $r)";
            case 'cmp':
                return $node['attr']['ext'] ? null
                    : self::compare($columns[$prefix . implode('.', $node['attr']['parts'])] ?? null, $node['cmp'], $node['val'], $params);
            case 'valpath':
                // emails[type eq "work"].value eq "x": the value comparison, without the item filter (a superset)
                $attr = $node['attr']['parts'][0];
                if ($node['sub'] !== null) {
                    return $node['pr'] ? null : self::compare($columns[$attr . '.' . $node['sub']] ?? null, $node['cmp'], $node['val'], $params);
                }
                return self::node($node['filter'], $columns, $params, $attr . '.');
            default:
                return null;
        }
    }

    private static function compare(?string $column, string $op, mixed $value, array &$params): ?string
    {
        if ($column !== null && is_bool($value) && $op === 'eq') {
            return $column . ($value ? ' = 1' : ' <> 1');
        }
        if ($column === null || !is_string($value) || !in_array($op, ['eq', 'sw', 'ew', 'co'], true)) {
            return null;
        }
        $key = 'f' . count($params);
        $like = addcslashes($value, '\\%_');
        $params[$key] = match ($op) {
            'eq' => $value,
            'sw' => $like . '%',
            'ew' => '%' . $like,
            'co' => '%' . $like . '%',
        };
        return $column . (($op === 'eq') ? ' = :' : ' LIKE :') . $key;
    }
}
