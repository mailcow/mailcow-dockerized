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
        $named = false;
        foreach ($arguments as $name => $node) {
            if (!\is_int($name)) {
                $named = true;
                $name = $this->normalizeName($name);
            } elseif ($named) {
                throw new SyntaxError(\sprintf('Positional arguments cannot be used after named arguments for %s "%s".', $this->twigCallable->getType(), $this->twigCallable->getName()), $this->node->getTemplateLine(), $this->node->getSourceContext());
            }

            $extractedArguments[$name] = $node;
        }

        if (!$named && !$this->twigCallable->isVariadic()) {
            $min = $this->twigCallable->getMinimalNumberOfRequiredArguments();
            if (\count($extractedArguments) < $this->rc->getReflector()->getNumberOfRequiredParameters() - $min) {
                throw new SyntaxError(\sprintf('Value for argument "%s" is required for %s "%s".', $this->rc->getReflector()->getParameters()[$min + \count($extractedArguments)]->getName(), $this->twigCallable->getType(), $this->twigCallable->getName()), $this->node->getTemplateLine(), $this->node->getSourceContext());
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
        $names = [];
        $missingArguments = [];
        $optionalArguments = [];
        $pos = 0;
        foreach ($callableParameters as $callableParameter) {
            $name = $this->normalizeName($callableParameter->name);
            if (\PHP_VERSION_ID >= 80000 && 'range' === $callable) {
                if ('start' === $name) {
                    $name = 'low';
                } elseif ('end' === $name) {
                    $name = 'high';
                }
            }

            $names[] = $name;

            if (\array_key_exists($name, $extractedArguments)) {
                if (\array_key_exists($pos, $extractedArguments)) {
                    throw new SyntaxError(\sprintf('Argument "%s" is defined twice for %s "%s".', $name, $this->twigCallable->getType(), $this->twigCallable->getName()), $this->node->getTemplateLine(), $this->node->getSourceContext());
                }

                if (\count($missingArguments)) {
                    throw new SyntaxError(\sprintf(
                        'Argument "%s" could not be assigned for %s "%s(%s)" because it is mapped to an internal PHP function which cannot determine default value for optional argument%s "%s".',
                        $name, $this->twigCallable->getType(), $this->twigCallable->getName(), implode(', ', $names), \count($missingArguments) > 1 ? 's' : '', implode('", "', $missingArguments)
                    ), $this->node->getTemplateLine(), $this->node->getSourceContext());
                }

                $arguments = array_merge($arguments, $optionalArguments);
                $arguments[] = $extractedArguments[$name];
                unset($extractedArguments[$name]);
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

                $missingArguments[] = $name;
            } else {
                throw new SyntaxError(\sprintf('Value for argument "%s" is required for %s "%s".', $name, $this->twigCallable->getType(), $this->twigCallable->getName()), $this->node->getTemplateLine(), $this->node->getSourceContext());
            }
        }

        if ($this->twigCallable->isVariadic()) {
            $arbitraryArguments = $isPhpVariadic ? new VariadicExpression([], $this->node->getTemplateLine()) : new ArrayExpression([], $this->node->getTemplateLine());
            foreach ($extractedArguments as $key => $value) {
                if (\is_int($key)) {
                    $arbitraryArguments->addElement($value);
                } else {
                    $arbitraryArguments->addElement($value, new ConstantExpression($key, $this->node->getTemplateLine()));
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
                    \count($extractedArguments) > 1 ? 's' : '', implode('", "', array_keys($extractedArguments)), $this->twigCallable->getType(), $this->twigCallable->getName(), implode(', ', $names)
                ),
                $unknownArgument ? $unknownArgument->getTemplateLine() : $this->node->getTemplateLine(),
                $unknownArgument ? $unknownArgument->getSourceContext() : $this->node->getSourceContext()
            );
        }

        return $arguments;
    }

    private function normalizeName(string $name): string
    {
        return strtolower(preg_replace(['/([A-Z]+)([A-Z][a-z])/', '/([a-z\d])([A-Z])/'], ['\\1_\\2', '\\1_\\2'], $name));
    }

    private function getCallableParameters(): array
    {
        $parameters = $this->rc->getReflector()->getParameters();
        if ($this->node->hasNode('node')) {
            array_shift($parameters);
        }
        if ($this->twigCallable->needsCharset()) {
            array_shift($parameters);
        }
        if ($this->twigCallable->needsEnvironment()) {
            array_shift($parameters);
        }
        if ($this->twigCallable->needsContext()) {
            array_shift($parameters);
        }
        foreach ($this->twigCallable->getArguments() as $argument) {
            array_shift($parameters);
        }

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
