<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Twig\Attribute;

use Twig\DeprecatedCallableInfo;
use Twig\TwigFilter;

/**
 * Registers a method as template filter.
 *
 * If the first argument of the method has Twig\Environment type-hint, the filter will receive the current environment.
 * Additional arguments of the method come from the filter call.
 *
 *     #[AsTwigFilter(name: 'foo')]
 *     function fooFilter(Environment $env, $string, $arg1 = null, ...) { ... }
 *
 *    {{ 'string'|foo(arg1) }}
 *
 * @see TwigFilter
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class AsTwigFilter
{
    /**
     * @param non-empty-string            $name                   The name of the filter in Twig
     * @param bool|null                   $needsCharset           Whether the filter needs the charset passed as the first argument
     * @param bool|null                   $needsEnvironment       Whether the filter needs the environment passed as the first argument, or after the charset
     * @param bool|null                   $needsContext           Whether the filter needs the context array passed as the first argument, or after the charset and the environment
     * @param bool|null                   $needsIsSandboxed       Whether the filter needs the current sandbox state (a boolean) passed as the first argument, or after the charset, the environment, and the context
     * @param string[]|null               $isSafe                 List of formats in which you want the raw output to be printed unescaped
     * @param string|array|null           $isSafeCallback         Function called at compilation time to determine if the filter is safe
     * @param string|null                 $preEscape              Some filters may need to work on input that is already escaped or safe
     * @param string[]|null               $preservesSafety        Preserves the safety of the value that the filter is applied to
     * @param DeprecatedCallableInfo|null $deprecationInfo        Information about the deprecation
     * @param bool|null                   $alwaysAllowedInSandbox Whether the filter is always allowed in sandbox mode, even when not explicitly allow-listed
     */
    public function __construct(
        public string $name,
        public ?bool $needsCharset = null,
        public ?bool $needsEnvironment = null,
        public ?bool $needsContext = null,
        public ?bool $needsIsSandboxed = null,
        public ?array $isSafe = null,
        public string|array|null $isSafeCallback = null,
        public ?string $preEscape = null,
        public ?array $preservesSafety = null,
        public ?DeprecatedCallableInfo $deprecationInfo = null,
        public ?bool $alwaysAllowedInSandbox = null,
    ) {
    }
}
