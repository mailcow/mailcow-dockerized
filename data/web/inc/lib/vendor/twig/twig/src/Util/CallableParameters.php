<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Twig\Util;

use Twig\Environment;
use Twig\Node\Expression\FilterExpression;
use Twig\Node\Expression\FunctionExpression;
use Twig\Node\Expression\TestExpression;
use Twig\Node\Node;
use Twig\TwigCallableInterface;

/**
 * Reflects the PHP parameters backing a Twig callable expression.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @internal
 */
final class CallableParameters
{
    /**
     * Returns the PHP parameters of a Filter/Function/Test call mapped to its
     * template-level arguments.
     *
     * The parameters Twig injects automatically
     * (`needs_charset/environment/context/is_sandboxed`) and the bound
     * `arguments` are stripped. For a filter, the first returned parameter is
     * the filter's input value. Returns null when reflection fails or the node
     * is not a callable expression.
     *
     * @return list<\ReflectionParameter>|null
     */
    public static function fromNode(Node $node, Environment $env): ?array
    {
        if (!$node instanceof FilterExpression && !$node instanceof FunctionExpression && !$node instanceof TestExpression) {
            return null;
        }

        $callable = self::resolveTwigCallable($node, $env);
        if (null === $callable || null === $callable->getCallable()) {
            return null;
        }

        try {
            return (new ReflectionCallable($callable))->getTwigParameters();
        } catch (\LogicException) {
            return null;
        }
    }

    /**
     * Returns true when a PHP parameter type proves the value reaching it
     * cannot implicitly string-coerce (directly or by iterating it).
     *
     * Safe: `int`, `float`, `bool`, `null`, `false`, `true`, `void`, `never`,
     * enums, and `final` class types that are neither `Stringable` nor
     * `Traversable`.
     *
     * Unsafe: `string`, `array`, `iterable`, `mixed`, `object`,
     * untyped, interfaces, non-final classes, and any type that is
     * `Stringable` or `Traversable`. Interfaces and non-final classes are
     * open: a subtype could add `Stringable`/`Traversable` and reach the host
     * code, bypassing the `__toString` policy, so only a `final` class (enums
     * included) is closed enough.
     *
     * @param \ReflectionClass|null $scope resolves the relative types `self`/`parent`/`static` (which
     *                                     older PHP versions report verbatim instead of the declaring class)
     */
    public static function isStringCoercionSafe(?\ReflectionType $type, ?\ReflectionClass $scope = null): bool
    {
        if (null === $type) {
            return false;
        }

        // A union value is one of its members, so every member must be safe.
        if ($type instanceof \ReflectionUnionType) {
            foreach ($type->getTypes() as $t) {
                if (!self::isStringCoercionSafe($t, $scope)) {
                    return false;
                }
            }

            return true;
        }

        // An intersection value satisfies all its members at once. A safe
        // member is necessarily a final class, which pins the concrete class,
        // so the value is that non-coercible class whatever the other members.
        if ($type instanceof \ReflectionIntersectionType) {
            foreach ($type->getTypes() as $t) {
                if (self::isStringCoercionSafe($t, $scope)) {
                    return true;
                }
            }

            return false;
        }

        if (!$type instanceof \ReflectionNamedType) {
            return false;
        }

        $name = $type->getName();

        if ($type->isBuiltin()) {
            return match ($name) {
                // `null` (e.g. as a union member) cannot have a __toString.
                'null', 'int', 'float', 'bool', 'true', 'false', 'void', 'never' => true,
                default => false, // string, array, iterable, object, mixed, callable
            };
        }

        // Resolve `self`/`parent`/`static` to the concrete class
        // `static` is treated like `self` since a `final` class cannot be subclassed anyway
        $class = match ($name) {
            'self', 'static' => $scope,
            'parent' => $scope ? ($scope->getParentClass() ?: null) : null,
            default => class_exists($name, false) ? new \ReflectionClass($name) : null,
        };

        // Interfaces and non-final classes are open: a subtype could add
        // Stringable/Traversable, so only a final non-coercible class is safe
        if (null === $class) {
            return false;
        }
        if (is_a($class->getName(), \Stringable::class, true) || is_a($class->getName(), \Traversable::class, true)) {
            return false;
        }

        return $class->isFinal();
    }

    private static function resolveTwigCallable(Node $node, Environment $env): ?TwigCallableInterface
    {
        if ($node->hasAttribute('twig_callable')) {
            return $node->getAttribute('twig_callable');
        }
        if (!$node->hasAttribute('name')) {
            return null;
        }
        $name = $node->getAttribute('name');
        try {
            return match (true) {
                $node instanceof FilterExpression => $env->getFilter($name),
                $node instanceof FunctionExpression => $env->getFunction($name),
                $node instanceof TestExpression => $env->getTest($name),
            };
        } catch (\Throwable) {
            return null;
        }
    }
}
