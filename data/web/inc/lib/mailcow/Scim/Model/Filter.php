<?php

declare(strict_types=1);

namespace Mailcow\Scim\Model;

use Mailcow\Scim\ScimError;

/**
 * A SCIM filter (RFC 7644 3.4.2.2), parsed by recursive descent and evaluated in memory against resources
 * (arrays as returned to the client). Also parses PATCH paths (RFC 7644 3.5.2), which share the grammar.
 *
 * Lenient extension: Entra ID sends `emails[type eq "work"].value eq "x"`, a sub-attribute after a value filter.
 *
 * AST nodes: ['op' => 'or'|'and', 'l', 'r'], ['op' => 'not', 'f'], ['op' => 'pr', 'attr'],
 * ['op' => 'cmp', 'attr', 'cmp', 'val'], ['op' => 'valpath', 'attr', 'filter', 'sub', 'cmp', 'val', 'pr'].
 */
class Filter
{
    private const OPERATORS = ['eq', 'ne', 'co', 'sw', 'ew', 'gt', 'ge', 'lt', 'le'];

    /** The boolean attributes (active, emails.primary): gt/ge/lt/le on them are invalid (RFC 7644 3.4.2.2) */
    private const BOOLEANS = ['active', 'primary'];

    /** Attribute path of an attribute the type does not know (lenient parsing): it has no value */
    private const UNKNOWN = '?';

    private array $tokens = [];
    private int $pos = 0;

    /** @var string[] attributes the type does not know (lenient parsing only) */
    public array $unknown = [];

    private function __construct(private string $type, public readonly array $ast = [], private bool $lenient = false)
    {
    }

    /**
     * @param bool $lenient unknown attributes have no value instead of failing (RFC 7644 3.4.2.1: a search over
     *     several resource types treats an attribute a type lacks as unassigned)
     * @throws ScimError invalidFilter
     */
    public static function parse(string $filter, string $type, bool $lenient = false): self
    {
        $parser = new self($type, [], $lenient);
        try {
            $parser->tokens = self::tokenize($filter);
            if (empty($parser->tokens)) {
                throw new \RuntimeException('Empty filter');
            }
            $ast = $parser->parseOr(null);
            $parser->expectEnd();
        } catch (\RuntimeException $e) {
            throw new ScimError(400, 'Invalid filter: ' . $e->getMessage(), 'invalidFilter');
        }
        $result = new self($type, $ast);
        $result->unknown = array_values(array_unique($parser->unknown));
        return $result;
    }

    /**
     * Parses a PATCH path: attrPath, or valuePath with an optional sub-attribute.
     *
     * @return ?array{attr: array, filter: ?Filter, sub: ?string} null for unstored User attributes (Schema::IGNORED,
     *     the enterprise extension)
     * @throws ScimError invalidPath
     */
    public static function path(string $path, string $type): ?array
    {
        $parser = new self($type);
        try {
            $parser->tokens = self::tokenize($path);
            $word = $parser->tokens[0] ?? null;
            if ($word === null || $word['t'] !== 'word') {
                throw new \RuntimeException('Expected attribute path');
            }
            $dotted = strtolower($word['v']);
            $dotted = str_contains($dotted, ':') ? substr($dotted, strrpos($dotted, ':') + 1) : $dotted;
            $base = explode('.', $dotted, 2)[0];
            if ($type === 'User' && (in_array($base, Schema::IGNORED, true) || in_array($dotted, Schema::IGNORED_SUB, true)
                || str_starts_with(strtolower($word['v']), strtolower(Schema::ENTERPRISE)))) {
                return null;
            }
            $parser->pos = 1;
            $attr = $parser->attribute($word['v'], null);
            $filter = null;
            if ($parser->peek('[')) {
                $filter = new self($type, $parser->valueFilter($attr, $word['v']));
            }
            // emails[type eq "work"].display: an unstored sub-attribute after a value filter
            $next = $parser->tokens[$parser->pos] ?? null;
            if ($type === 'User' && $next !== null && $next['t'] === 'word' && in_array($base . strtolower($next['v']), Schema::IGNORED_SUB, true)) {
                return null;
            }
            $sub = $parser->subAttribute($attr);
            $parser->expectEnd();
        } catch (\RuntimeException $e) {
            throw new ScimError(400, 'Invalid path: ' . $e->getMessage(), 'invalidPath');
        }
        return ['attr' => $attr, 'filter' => $filter, 'sub' => $sub];
    }

    public function matches(array $resource): bool
    {
        return self::evaluate($this->ast, $resource);
    }

    /**
     * Value a resource is sorted by (RFC 7644 3.4.2.3): of a multi-valued attribute the primary item, else the first;
     * a complex value by its "value". Null when the resource has none.
     */
    public static function sortValue(array $spec, array $resource): mixed
    {
        $key = $spec['ext'] ? null : Schema::key($resource, $spec['parts'][0]);
        if ($key !== null && is_array($resource[$key]) && array_is_list($resource[$key])) {
            usort($resource[$key], fn(mixed $a, mixed $b): int => (int)(is_array($b) && Schema::get($b, 'primary') === true)
                - (int)(is_array($a) && Schema::get($a, 'primary') === true));
        }
        $value = self::values($spec, $resource)[0] ?? null;
        return is_array($value) ? Schema::get($value, 'value') : $value;
    }

    /** Ascending order of two sort values; resources without a value come last (descending: first) */
    public static function sortCompare(mixed $a, mixed $b, bool $exact): int
    {
        if ($a === null || $b === null) {
            return ($a === null) <=> ($b === null);
        }
        if (is_bool($a) || is_bool($b) || (is_numeric($a) && is_numeric($b))) {
            return $a <=> $b;
        }
        [$a, $b] = $exact ? [(string)$a, (string)$b] : [mb_strtolower((string)$a), mb_strtolower((string)$b)];
        return strcmp($a, $b) <=> 0;
    }

    /** @throws \RuntimeException on lexical errors */
    private static function tokenize(string $s): array
    {
        $tokens = [];
        $n = strlen($s);
        for ($i = 0; $i < $n;) {
            $c = $s[$i];
            if (ctype_space($c)) {
                $i++;
            } elseif (str_contains('()[]', $c)) {
                $tokens[] = ['t' => $c, 'v' => null];
                $i++;
            } elseif ($c === '"') {
                for ($j = $i + 1; $j < $n && $s[$j] !== '"'; $j += ($s[$j] === '\\') ? 2 : 1);
                $value = ($j < $n) ? json_decode(substr($s, $i, $j - $i + 1)) : null;
                if (!is_string($value)) {
                    throw new \RuntimeException('Invalid string literal');
                }
                $tokens[] = ['t' => 'str', 'v' => $value];
                $i = $j + 1;
            } else {
                $j = $i + strcspn($s, " \t\r\n()[]\"", $i);
                $tokens[] = ['t' => 'word', 'v' => substr($s, $i, $j - $i)];
                $i = $j;
            }
        }
        return $tokens;
    }

    /** $context: name of the multi-valued attribute when inside its value filter */
    private function parseOr(?string $context): array
    {
        $node = $this->parseAnd($context);
        while ($this->keyword('or')) {
            $node = ['op' => 'or', 'l' => $node, 'r' => $this->parseAnd($context)];
        }
        return $node;
    }

    private function parseAnd(?string $context): array
    {
        $node = $this->parseUnary($context);
        while ($this->keyword('and')) {
            $node = ['op' => 'and', 'l' => $node, 'r' => $this->parseUnary($context)];
        }
        return $node;
    }

    private function parseUnary(?string $context): array
    {
        $token = $this->tokens[$this->pos] ?? null;
        $not = $token !== null && $token['t'] === 'word' && strcasecmp($token['v'], 'not') === 0
            && ($this->tokens[$this->pos + 1]['t'] ?? null) === '(';
        if ($not) {
            $this->pos++;
        }
        if ($this->peek('(')) {
            $this->pos++;
            $inner = $this->parseOr($context);
            $this->expect(')');
            return $not ? ['op' => 'not', 'f' => $inner] : $inner;
        }
        return $this->parseAttributeExpression($context);
    }

    private function parseAttributeExpression(?string $context): array
    {
        $token = $this->tokens[$this->pos++] ?? null;
        if ($token === null || $token['t'] !== 'word') {
            throw new \RuntimeException('Expected attribute expression');
        }
        $attr = $this->attribute($token['v'], $context);
        if ($context === null && $this->peek('[')) {
            $node = ['op' => 'valpath', 'attr' => $attr, 'filter' => $this->valueFilter($attr, $token['v']),
                'sub' => null, 'cmp' => null, 'val' => null, 'pr' => false];
            $node['sub'] = $this->subAttribute($attr);
            if ($node['sub'] !== null) {
                [$node['cmp'], $node['val'], $node['pr']] = $this->operator($node['sub']);
            }
            return $node;
        }
        [$cmp, $value, $pr] = $this->operator($attr['parts'][count($attr['parts']) - 1] ?? '');
        $exact = $context === null && Schema::caseExact($attr);
        return $pr ? ['op' => 'pr', 'attr' => $attr] : ['op' => 'cmp', 'attr' => $attr, 'cmp' => $cmp, 'val' => $value, 'exact' => $exact];
    }

    /** Attribute spec; inside a value filter names are sub-attributes and the path is relative to the item */
    private function attribute(string $name, ?string $context): array
    {
        $spec = Schema::attribute($this->type, ($context === null) ? $name : $context . '.' . $name);
        if ($spec === null && ($this->lenient || $context === self::UNKNOWN)) {
            $this->unknown[] = strtolower(($context === null || $context === self::UNKNOWN) ? $name : $context . '.' . $name);
            return ['ext' => false, 'parts' => [self::UNKNOWN]];
        }
        if ($spec === null) {
            throw new \RuntimeException("Unknown attribute '$name'");
        }
        if ($context !== null) {
            $spec['parts'] = array_slice($spec['parts'], 1);
        }
        return $spec;
    }

    /** "[" valFilter "]" after a multi-valued attribute */
    private function valueFilter(array $attr, string $name): array
    {
        $unknown = ($attr['parts'] === [self::UNKNOWN]);
        if (!$unknown && ($attr['ext'] || count($attr['parts']) !== 1 || !in_array($attr['parts'][0], Schema::MULTI_VALUED, true))) {
            throw new \RuntimeException("Attribute '$name' does not support value filters");
        }
        $this->expect('[');
        $filter = $this->parseOr($attr['parts'][0]);
        $this->expect(']');
        return $filter;
    }

    /** Optional ".sub" after a value filter */
    private function subAttribute(array $attr): ?string
    {
        $token = $this->tokens[$this->pos] ?? null;
        if ($token === null || $token['t'] !== 'word' || !str_starts_with($token['v'], '.')) {
            return null;
        }
        $sub = strtolower(substr($token['v'], 1));
        $base = $attr['parts'][count($attr['parts']) - 1] ?? '';
        if ($base !== self::UNKNOWN && Schema::attribute($this->type, $base . '.' . $sub) === null) {
            throw new \RuntimeException("Unknown sub-attribute '$sub'");
        }
        $this->pos++;
        return $sub;
    }

    /**
     * @param string $attribute name of the compared (sub-)attribute
     * @return array{0: string, 1: mixed, 2: bool} "pr" or compareOp + compValue
     */
    private function operator(string $attribute): array
    {
        $token = $this->tokens[$this->pos++] ?? null;
        $op = ($token !== null && $token['t'] === 'word') ? strtolower($token['v']) : '';
        if ($op === 'pr') {
            return ['pr', null, true];
        }
        if (!in_array($op, self::OPERATORS, true)) {
            throw new \RuntimeException("Unknown operator '$op'");
        }
        $value = $this->tokens[$this->pos++] ?? null;
        $word = ($value !== null && $value['t'] === 'word') ? $value['v'] : '';
        $result = match (true) {
            $value !== null && $value['t'] === 'str' => [$op, $value['v'], false],
            strtolower($word) === 'true' => [$op, true, false],
            strtolower($word) === 'false' => [$op, false, false],
            strtolower($word) === 'null' => [$op, null, false],
            is_numeric($word) => [$op, $word + 0, false],
            default => throw new \RuntimeException('Invalid comparison value'),
        };
        if (in_array($op, ['gt', 'ge', 'lt', 'le'], true) && (is_bool($result[1]) || in_array($attribute, self::BOOLEANS, true))) {
            throw new \RuntimeException("'$op' cannot compare booleans");
        }
        return $result;
    }

    private function keyword(string $word): bool
    {
        $token = $this->tokens[$this->pos] ?? null;
        if ($token !== null && $token['t'] === 'word' && strcasecmp($token['v'], $word) === 0) {
            $this->pos++;
            return true;
        }
        return false;
    }

    private function peek(string $type): bool
    {
        return ($this->tokens[$this->pos]['t'] ?? null) === $type;
    }

    private function expect(string $type): void
    {
        if (!$this->peek($type)) {
            throw new \RuntimeException("Expected '$type'");
        }
        $this->pos++;
    }

    private function expectEnd(): void
    {
        if ($this->pos !== count($this->tokens)) {
            throw new \RuntimeException('Unexpected trailing tokens');
        }
    }

    private static function evaluate(array $node, array $resource): bool
    {
        switch ($node['op']) {
            case 'or':
                return self::evaluate($node['l'], $resource) || self::evaluate($node['r'], $resource);
            case 'and':
                return self::evaluate($node['l'], $resource) && self::evaluate($node['r'], $resource);
            case 'not':
                return !self::evaluate($node['f'], $resource);
            case 'pr':
                foreach (self::values($node['attr'], $resource) as $value) {
                    if ($value !== null && $value !== '' && $value !== []) {
                        return true;
                    }
                }
                return false;
            case 'cmp':
                $values = self::values($node['attr'], $resource);
                if ($values === []) {
                    // an unassigned attribute equals null and differs from every value (RFC 7643 2.5)
                    return ($node['cmp'] === 'eq' && $node['val'] === null) || ($node['cmp'] === 'ne' && $node['val'] !== null);
                }
                foreach ($values as $value) {
                    if (self::compare($node['cmp'], $value, $node['val'], $node['exact'])) {
                        return true;
                    }
                }
                return false;
            default:
                foreach (self::values($node['attr'], $resource) as $item) {
                    if (!is_array($item) || !self::evaluate($node['filter'], $item)) {
                        continue;
                    }
                    if ($node['sub'] === null) {
                        return true;
                    }
                    $value = Schema::get($item, $node['sub']);
                    if ($node['pr'] ? ($value !== null && $value !== '') : self::compare($node['cmp'], $value, $node['val'])) {
                        return true;
                    }
                }
                return false;
        }
    }

    /** Flat list of the values an attribute path resolves to (multi-valued attributes give several) */
    private static function values(array $spec, array $resource): array
    {
        $node = $spec['ext'] ? Schema::get($resource, Schema::EXT) : $resource;
        if (!is_array($node)) {
            return [];
        }
        foreach ($spec['parts'] as $part) {
            if (array_is_list($node)) {
                $node = array_values(array_filter(array_map(
                    fn(mixed $item): mixed => is_array($item) ? Schema::get($item, $part) : null, $node),
                    fn(mixed $value): bool => $value !== null));
                continue;
            }
            $node = Schema::get($node, $part);
            if (!is_array($node)) {
                return ($node === null) ? [] : [$node];
            }
        }
        return array_is_list($node) ? $node : [$node];
    }

    private static function compare(string $op, mixed $actual, mixed $expected, bool $exact = false): bool
    {
        // A complex value compares by its "value" sub-attribute (RFC 7644 3.4.2.2)
        if (is_array($actual)) {
            $actual = Schema::get($actual, 'value');
            if ($actual === null) {
                return false;
            }
        }
        if ($expected === null) {
            return match ($op) {
                'eq' => $actual === null,
                'ne' => $actual !== null,
                default => false,
            };
        }
        if (is_bool($expected) || is_bool($actual)) {
            // only true/false (and their strings) match a boolean, (bool)"false" would invert the filter
            $toBool = fn(mixed $v): ?bool => is_bool($v) ? $v
                : (is_string($v) ? (['true' => true, 'false' => false][strtolower($v)] ?? null) : null);
            [$a, $e] = [$toBool($actual), $toBool($expected)];
            return $a !== null && $e !== null && match ($op) {
                'eq' => $a === $e,
                'ne' => $a !== $e,
                default => false,
            };
        }
        if (is_int($expected) || is_float($expected)) {
            return is_numeric($actual) && self::order($op, (float)$actual <=> (float)$expected);
        }
        [$a, $e] = [(string)$actual, (string)$expected];
        $date = '/^\d{4}-\d{2}-\d{2}T/';
        if (!in_array($op, ['co', 'sw', 'ew'], true) && preg_match($date, $a) && preg_match($date, $e)) {
            // dateTime values compare as instants: the server writes +00:00, clients send Z
            [$ta, $te] = [strtotime($a), strtotime($e)];
            if ($ta !== false && $te !== false) {
                return self::order($op, $ta <=> $te);
            }
        }
        if (!$exact) {
            [$a, $e] = [mb_strtolower($a), mb_strtolower($e)];
        }
        return match ($op) {
            'co' => str_contains($a, $e),
            'sw' => str_starts_with($a, $e),
            'ew' => str_ends_with($a, $e),
            default => self::order($op, strcmp($a, $e) <=> 0),
        };
    }

    /** Result of an ordering operator for a comparison result (-1, 0, 1) */
    private static function order(string $op, int $cmp): bool
    {
        return match ($op) {
            'eq' => $cmp === 0,
            'ne' => $cmp !== 0,
            'gt' => $cmp > 0,
            'ge' => $cmp >= 0,
            'lt' => $cmp < 0,
            'le' => $cmp <= 0,
            default => false,
        };
    }
}
