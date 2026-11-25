<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Twig;

use Twig\Error\RuntimeError;
use Twig\Node\Expression\TempNameExpression;

/**
 * Represents a compiled macro: its body compiled into a closure and the
 * signature declared in the template.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @internal This class is an implementation detail of the per-template macro
 *           registry: compiled templates instantiate it, but it is not part of
 *           the public API, neither on 3.x nor in 4.0.
 */
final class TwigMacro
{
    /**
     * @var array<string, int>
     */
    private array $argumentIndexes = [];

    /**
     * @var array<string, true>
     */
    private array $requiredNames = [];

    /**
     * Twig argument names that do not match their compiled closure parameter name
     * (reserved names get prefixed by the compiler). Only needed by callLegacy()'s
     * native binding; to be removed in 4.0 together with it.
     *
     * @var array<string, string>
     */
    private array $renamedArguments = [];

    private int $requiredCount = 0;

    /**
     * @param \Closure(mixed...): (string|Markup) $body      The compiled macro body, invoked with the bound arguments in definition order
     * @param array<string, bool>                 $arguments The declared argument names mapped to whether they have a default value, in definition order
     */
    public function __construct(
        private string $name,
        private \Closure $body,
        private array $arguments = [],
        private bool $variadic = false,
    ) {
        $i = 0;
        foreach ($arguments as $argName => $hasDefault) {
            $this->argumentIndexes[$argName] = $i;
            if (\in_array($argName, TempNameExpression::RESERVED_NAMES, true)) {
                $this->renamedArguments[$argName] = TempNameExpression::RESERVED_NAME_PREFIX.$argName;
            }
            if (!$hasDefault) {
                $this->requiredCount = $i + 1;
                $this->requiredNames[$argName] = true;
            }
            ++$i;
        }
    }

    /**
     * Invokes the macro the way Twig 3.x always has (lenient argument handling),
     * while reporting the cases that will become errors in Twig 4.0.
     *
     * In 4.0, this becomes the strict call() method: the scan below already detects
     * every deprecated case, so triggerLegacyDeprecations() turns into upfront
     * throws and the rest stays as is.
     *
     * @param array<int|string, mixed> $arguments Positional arguments keyed by their integer position
     *                                            and named arguments keyed by their name
     * @param Source                   $source    The source of the template making the call, used to enrich errors
     * @param int                      $lineno    The line of the call in that template, used to enrich errors
     */
    public function callLegacy(array $arguments, Source $source, int $lineno): string|Markup
    {
        // Spreading the arguments as-is is equivalent to resolving them: PHP binds
        // them natively, maps the named ones onto their parameter, fills the
        // defaults, and collects the extra ones into the variadic bucket. Only the
        // cases where PHP would report its own error (with closure-centric wording)
        // are detected upfront and turned into Twig errors.
        if (array_is_list($arguments)) {
            $count = \count($arguments);
            if ($count < $this->requiredCount || (!$this->variadic && $count > \count($this->arguments))) {
                $this->triggerLegacyDeprecations($arguments, $count, $source, $lineno);
            }

            return ($this->body)(...$arguments);
        }

        // Every declared argument is named: none can be missing, unknown, duplicated, or misordered.
        if (\count($arguments) === \count($this->arguments) && !array_diff_key($arguments, $this->argumentIndexes)) {
            return ($this->body)(...$this->renameArguments($arguments));
        }

        $positionalCount = 0;
        $namedRequired = 0;
        $sawNamed = false;
        $misordered = false;
        $duplicate = null;
        $hasUnknownNamed = false;
        foreach ($arguments as $key => $value) {
            if (\is_int($key)) {
                $misordered = $misordered || $sawNamed;
                ++$positionalCount;
            } else {
                $sawNamed = true;
                if (null === $i = $this->argumentIndexes[$key] ?? null) {
                    $hasUnknownNamed = true;
                } else {
                    if (null === $duplicate && $i < $positionalCount) {
                        $duplicate = $key;
                    }
                    if (isset($this->requiredNames[$key])) {
                        ++$namedRequired;
                    }
                }
            }
        }

        if ($misordered) {
            throw new RuntimeError(\sprintf('Positional arguments cannot be used after named arguments for macro "%s".', $this->name), $lineno, $source);
        }
        if (null !== $duplicate) {
            throw new RuntimeError(\sprintf('Argument "%s" is defined twice for macro "%s".', $duplicate, $this->name), $lineno, $source);
        }

        // For a fully named call, the coverage of the required arguments is exact; a
        // mixed call falls back to the precise (and slower) per-argument check.
        $mayMissRequired = 0 === $positionalCount
            ? $namedRequired < \count($this->requiredNames)
            : $positionalCount < $this->requiredCount;

        if ($mayMissRequired || (!$this->variadic && ($hasUnknownNamed || $positionalCount > \count($this->arguments)))) {
            $this->triggerLegacyDeprecations($arguments, $positionalCount, $source, $lineno);
        }

        return ($this->body)(...$this->renameArguments($arguments));
    }

    /**
     * @param array<int|string, mixed> $arguments
     *
     * @return array<int|string, mixed>
     */
    private function renameArguments(array $arguments): array
    {
        foreach ($this->renamedArguments as $name => $parameterName) {
            if (\array_key_exists($name, $arguments)) {
                $arguments[$parameterName] = $arguments[$name];
                unset($arguments[$name]);
            }
        }

        return $arguments;
    }

    /**
     * To be removed in 4.0 (these deprecated cases become hard errors).
     *
     * @param array<int|string, mixed> $arguments The full argument array; string keys are the named arguments
     */
    private function triggerLegacyDeprecations(array $arguments, int $positionalCount, Source $source, int $lineno): void
    {
        if ($positionalCount < $this->requiredCount) {
            foreach ($this->requiredNames as $argName => $required) {
                if ($this->argumentIndexes[$argName] < $positionalCount || \array_key_exists($argName, $arguments)) {
                    continue;
                }

                trigger_deprecation('twig/twig', '3.29', 'Not passing a value for the "%s" argument of macro "%s" is deprecated and the argument will be required in Twig 4.0; give it a default value in the macro definition or pass a value when calling it (in "%s" at line %d).', $argName, $this->name, $source->getName(), $lineno);
            }
        }

        if ($this->variadic) {
            return;
        }

        if ($positionalCount > \count($this->arguments)) {
            trigger_deprecation('twig/twig', '3.29', 'Passing more arguments than the macro "%s" accepts is deprecated and will throw in Twig 4.0; declare a variadic argument ("...name") in the macro definition to accept extra arguments (in "%s" at line %d).', $this->name, $source->getName(), $lineno);
        }

        foreach ($arguments as $name => $value) {
            if (\is_string($name) && !isset($this->argumentIndexes[$name])) {
                trigger_deprecation('twig/twig', '3.29', 'Passing the unknown named argument "%s" to the macro "%s" is deprecated and will throw in Twig 4.0; declare a variadic argument ("...name") in the macro definition to accept it (in "%s" at line %d).', $name, $this->name, $source->getName(), $lineno);
            }
        }
    }
}
