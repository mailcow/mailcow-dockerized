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

use Twig\Error\SyntaxError;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\VariadicExpression;
use Twig\Node\Node;
use Twig\TwigCallableInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @internal
 */
final class CallableArgumentsExtractor
{
    private ReflectionCallable $rc;

    public function __construct(
        private Node $node,
        private TwigCallableInterface $twigCallable,
    ) {
        $this->rc = new ReflectionCallable($twigCallable);
    }

    /**
     * @return array<Node>
     */
    public function extractArguments(Node $arguments): array
    {
        $extractedArguments = [];
        $extractedArgumentNameMap = [];
        $named = false;
        foreach ($arguments as $name => $node) {
            if (!\is_int($name)) {
                $named = true;
            } elseif ($named) {
                throw new SyntaxError(\sprintf('Positional arguments cannot be used after named arguments for %s "%s".', $this->twigCallable->getType(), $this->twigCallable->getName()), $this->node->getTemplateLine(), $this->node->getSourceContext());
            }

            $extractedArguments[$normalizedName = $this->normalizeName($name)] = $node;
            $extractedArgumentNameMap[$normalizedName] = $name;
        }

        if (!$named && !$this->twigCallable->isVariadic()) {
            $min = $this->twigCallable->getMinimalNumberOfRequiredArguments();
            if (\count($extractedArguments) < $this->rc->getReflector()->getNumberOfRequiredParameters() - $min) {
                $argName = $this->toSnakeCase($this->rc->getReflector()->getParameters()[$min + \count($extractedArguments)]->getName());

                throw new SyntaxError(\sprintf('Value for argument "%s" is required for %s "%s".', $argName, $this->twigCallable->getType(), $this->twigCallable->getName()), $this->node->getTemplateLine(), $this->node->getSourceContext());
            }

            return $extractedArguments;
        }

        if (!$callable = $this->twigCallable->getCallable()) {
            if ($named) {
                throw new SyntaxError(\sprintf('Named arguments are not supported for %s "%s".', $this->twigCallable->getType(), $this->twigCallable->getName()));
            }

            throw new SyntaxError(\sprintf('Arbitrary positional arguments are not supported for %s "%s".', $this->twigCallable->getType(), $this->twigCallable->getName()));
        }

        [$callableParameters, $isPhpVariadic] = $this->getCallableParameters();
        $arguments = [];
        $callableParameterNames = [];
        $missingArguments = [];
        $optionalArguments = [];
        $pos = 0;
        foreach ($callableParameters as $callableParameter) {
            $callableParameterName = $callableParameter->name;
            if (\PHP_VERSION_ID >= 80000 && 'range' === $callable) {
                if ('start' === $callableParameterName) {
                    $callableParameterName = 'low';
                } elseif ('end' === $callableParameterName) {
                    $callableParameterName = 'high';
                }
            }

            $callableParameterNames[] = $callableParameterName;
            $normalizedCallableParameterName = $this->normalizeName($callableParameterName);

            if (\array_key_exists($normalizedCallableParameterName, $extractedArguments)) {
                if (\array_key_exists($pos, $extractedArguments)) {
                    throw new SyntaxError(\sprintf('Argument "%s" is defined twice for %s "%s".', $callableParameterName, $this->twigCallable->getType(), $this->twigCallable->getName()), $this->node->getTemplateLine(), $this->node->getSourceContext());
                }

                if (\count($missingArguments)) {
                    throw new SyntaxError(\sprintf(
                        'Argument "%s" could not be assigned for %s "%s(%s)" because it is mapped to an internal PHP function which cannot determine default value for optional argument%s "%s".',
                        $callableParameterName, $this->twigCallable->getType(), $this->twigCallable->getName(), implode(', ', array_map([$this, 'toSnakeCase'], $callableParameterNames)), \count($missingArguments) > 1 ? 's' : '', implode('", "', $missingArguments)
                    ), $this->node->getTemplateLine(), $this->node->getSourceContext());
                }

                $arguments = array_merge($arguments, $optionalArguments);
                $arguments[] = $extractedArguments[$normalizedCallableParameterName];
                unset($extractedArguments[$normalizedCallableParameterName]);
                $optionalArguments = [];
            } elseif (\array_key_exists($pos, $extractedArguments)) {
                $arguments = array_merge($arguments, $optionalArguments);
                $arguments[] = $extractedArguments[$pos];
                unset($extractedArguments[$pos]);
                $optionalArguments = [];
                ++$pos;
            } elseif ($callableParameter->isDefaultValueAvailable()) {
                $optionalArguments[] = new ConstantExpression($callableParameter->getDefaultValue(), $this->node->getTemplateLine());
            } elseif ($callableParameter->isOptional()) {
                if (!$extractedArguments) {
                    break;
                }

                $missingArguments[] = $callableParameterName;
            } else {
                throw new SyntaxError(\sprintf('Value for argument "%s" is required for %s "%s".', $this->toSnakeCase($callableParameterName), $this->twigCallable->getType(), $this->twigCallable->getName()), $this->node->getTemplateLine(), $this->node->getSourceContext());
            }
        }

        if ($this->twigCallable->isVariadic()) {
            $arbitraryArguments = $isPhpVariadic ? new VariadicExpression([], $this->node->getTemplateLine()) : new ArrayExpression([], $this->node->getTemplateLine());
            foreach ($extractedArguments as $key => $value) {
                if (\is_int($key)) {
                    $arbitraryArguments->addElement($value);
                } else {
                    $originalKey = $extractedArgumentNameMap[$key];
                    if ($originalKey !== $this->toSnakeCase($originalKey)) {
                        trigger_deprecation('twig/twig', '3.15', \sprintf('Using "snake_case" for variadic arguments is required for a smooth upgrade with Twig 4.0; rename "%s" to "%s" in "%s" at line %d.', $originalKey, $this->toSnakeCase($originalKey), $this->node->getSourceContext()->getName(), $this->node->getTemplateLine()));
                    }
                    $arbitraryArguments->addElement($value, new ConstantExpression($this->toSnakeCase($originalKey), $this->node->getTemplateLine()));
                    // I Twig 4.0, don't convert the key:
                    // $arbitraryArguments->addElement($value, new ConstantExpression($originalKey, $this->node->getTemplateLine()));
                }
                unset($extractedArguments[$key]);
            }

            if ($arbitraryArguments->count()) {
                $arguments = array_merge($arguments, $optionalArguments);
                $arguments[] = $arbitraryArguments;
            }
        }

        if ($extractedArguments) {
            $unknownArgument = null;
            foreach ($extractedArguments as $extractedArgument) {
                if ($extractedArgument instanceof Node) {
                    $unknownArgument = $extractedArgument;
                    break;
                }
            }

            throw new SyntaxError(
                \sprintf(
                    'Unknown argument%s "%s" for %s "%s(%s)".',
                    \count($extractedArguments) > 1 ? 's' : '', implode('", "', array_keys($extractedArguments)), $this->twigCallable->getType(), $this->twigCallable->getName(), implode(', ', array_map([$this, 'toSnakeCase'], $callableParameterNames))
                ),
                $unknownArgument ? $unknownArgument->getTemplateLine() : $this->node->getTemplateLine(),
                $unknownArgument ? $unknownArgument->getSourceContext() : $this->node->getSourceContext()
            );
        }

        return $arguments;
    }

    private function normalizeName(string $name): string
    {
        return strtolower(str_replace('_', '', $name));
    }

    private function toSnakeCase(string $name): string
    {
        return strtolower(preg_replace(['/([A-Z]+)([A-Z][a-z])/', '/([a-z0-9])([A-Z])/'], '\1_\2', $name));
    }

    private function getCallableParameters(): array
    {
        $parameters = $this->rc->getTwigParameters($this->node->hasNode('node'));

        $isPhpVariadic = false;
        if ($this->twigCallable->isVariadic()) {
            $argument = end($parameters);
            $isArray = $argument && $argument->hasType() && $argument->getType() instanceof \ReflectionNamedType && 'array' === $argument->getType()->getName();
            if ($isArray && $argument->isDefaultValueAvailable() && [] === $argument->getDefaultValue()) {
                array_pop($parameters);
            } elseif ($argument && $argument->isVariadic()) {
                array_pop($parameters);
                $isPhpVariadic = true;
            } else {
                throw new SyntaxError(\sprintf('The last parameter of "%s" for %s "%s" must be an array with default value, eg. "array $arg = []".', $this->rc->getName(), $this->twigCallable->getType(), $this->twigCallable->getName()));
            }
        }

        return [$parameters, $isPhpVariadic];
    }
}
