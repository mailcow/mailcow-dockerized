<?php

declare(strict_types=1);

namespace Mailcow\Tests\Api;

/**
 * The routes json_api.php answers, read from its nested switch statements over $action, $category, $object and
 * $extra: "get/mailbox/all", "add/alias", "*" for a default case ("get/mailbox/*" = get/mailbox/<anything else>).
 * Default cases that answer 404 are no routes. Only the most specific routes are listed (get/mailbox/all, not get/mailbox).
 */
final class RouteMap
{
    private const ROUTE_VARS = ['$action', '$category', '$object', '$extra'];

    /** @return list<string> */
    public static function fromFile(string $file): array
    {
        $tokens = array_values(array_filter(token_get_all((string)file_get_contents($file)),
            fn($t): bool => !is_array($t) || !in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)));
        $stack = [];
        $depth = 0;
        $pending = null;
        $routes = [];
        foreach ($tokens as $i => $token) {
            $id = is_array($token) ? $token[0] : $token;
            if ($id === T_SWITCH) {
                $isVar = ($tokens[$i + 1] ?? null) === '(' && is_array($tokens[$i + 2] ?? null) && $tokens[$i + 2][0] === T_VARIABLE && ($tokens[$i + 3] ?? null) === ')';
                $pending = ($isVar && in_array($tokens[$i + 2][1], self::ROUTE_VARS, true)) ? $tokens[$i + 2][1] : '';
            } elseif ($id === '{' || $id === T_CURLY_OPEN || $id === T_DOLLAR_OPEN_CURLY_BRACES) {
                $depth++;
                if ($pending !== null) {
                    $stack[] = ['var' => $pending, 'depth' => $depth, 'label' => null];
                    $pending = null;
                }
            } elseif ($id === '}') {
                if (!empty($stack) && end($stack)['depth'] === $depth) {
                    array_pop($stack);
                }
                $depth--;
            } elseif (($id === T_CASE || $id === T_DEFAULT) && !empty($stack) && end($stack)['depth'] === $depth) {
                $label = ($id === T_DEFAULT) ? '*' : self::literal($tokens[$i + 1] ?? null);
                $stack[count($stack) - 1]['label'] = $label;
                if (end($stack)['var'] === '' || $label === null || ($id === T_DEFAULT && self::answers404($tokens, $i))) {
                    continue;
                }
                $route = [];
                foreach ($stack as $switch) {
                    if ($switch['var'] !== '') {
                        $route[] = $switch['label'];
                    }
                }
                $routes[implode('/', $route)] = true;
            }
        }
        $routes = array_keys($routes);
        return array_values(array_filter($routes, function (string $route) use ($routes): bool {
            foreach ($routes as $other) {
                if (str_starts_with($other, $route . '/')) {
                    return false;
                }
            }
            return true;
        }));
    }

    /**
     * Whether $documented (an OpenAPI path like /api/v1/get/mailbox/{id}) covers $route: same leading segments, a
     * {parameter} matches anything, trailing "*" of the route are optional (add/mailbox/* = add/mailbox)
     */
    public static function covers(string $documented, string $route): bool
    {
        $doc = explode('/', trim(preg_replace('#^/?api/v1/#', '', $documented), '/'));
        $segments = explode('/', $route);
        while (count($segments) > count($doc) && end($segments) === '*') {
            array_pop($segments);
        }
        if (count($segments) > count($doc)) {
            return false;
        }
        foreach ($segments as $i => $segment) {
            $isParam = (bool)preg_match('/^\{.+\}$/', $doc[$i]);
            if ($segment === '*' ? !$isParam : ($segment !== $doc[$i] && !$isParam)) {
                return false;
            }
        }
        return true;
    }

    private static function literal(mixed $token): ?string
    {
        return (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING) ? substr($token[1], 1, -1) : null;
    }

    /** A default case whose code (up to the next case of its switch) sets the status 404 */
    private static function answers404(array $tokens, int $start): bool
    {
        $level = 0;
        for ($i = $start + 1; $i < count($tokens); $i++) {
            $id = is_array($tokens[$i]) ? $tokens[$i][0] : $tokens[$i];
            if ($id === '{' || $id === T_CURLY_OPEN || $id === T_DOLLAR_OPEN_CURLY_BRACES) {
                $level++;
            } elseif ($id === '}') {
                if ($level-- === 0) {
                    return false;
                }
            } elseif ($level === 0 && ($id === T_CASE || $id === T_DEFAULT)) {
                return false;
            } elseif ($id === T_STRING && $tokens[$i][1] === 'http_response_code' && is_array($tokens[$i + 2] ?? null) && $tokens[$i + 2][1] === '404') {
                return true;
            }
        }
        return false;
    }
}
