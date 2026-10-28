<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Twig\Extension;

use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;
use Twig\Attribute\AsTwigTest;
use Twig\Environment;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;

/**
 * Define Twig filters, functions, and tests with PHP attributes.
 *
 * @author Jérôme Tamarelle <jerome@tamarelle.net>
 */
final class AttributeExtension extends AbstractExtension
{
    private array $filters;
    private array $functions;
    private array $tests;

    /**
     * Use a runtime class using PHP attributes to define filters, functions, and tests.
     *
     * @param class-string $class
     */
    public function __construct(private string $class)
    {
    }

    /**
     * @return class-string
     */
    public function getClass(): string
    {
        return $this->class;
    }

    public function getFilters(): array
    {
        if (!isset($this->filters)) {
            $this->initFromAttributes();
        }

        return $this->filters;
    }

    public function getFunctions(): array
    {
        if (!isset($this->functions)) {
            $this->initFromAttributes();
        }

        return $this->functions;
    }

    public function getTests(): array
    {
        if (!isset($this->tests)) {
            $this->initFromAttributes();
        }

        return $this->tests;
    }

    public function getLastModified(): int
    {
        return max(
            filemtime(__FILE__),
            is_file($filename = (new \ReflectionClass($this->getClass()))->getFileName()) ? filemtime($filename) : 0,
        );
    }

    private function initFromAttributes(): void
    {
        $filters = $functions = $tests = [];
        $reflectionClass = new \ReflectionClass($this->getClass());
        foreach ($reflectionClass->getMethods() as $method) {
            foreach ($method->getAttributes(AsTwigFilter::class) as $reflectionAttribute) {
                /** @var AsTwigFilter $attribute */
                $attribute = $reflectionAttribute->newInstance();

                $callable = new TwigFilter($attribute->name, [$reflectionClass->name, $method->getName()], [
                    'needs_context' => $attribute->needsContext ?? false,
                    'needs_environment' => $attribute->needsEnvironment ?? $this->needsEnvironment($method),
                    'needs_charset' => $attribute->needsCharset ?? false,
                    'needs_is_sandboxed' => $attribute->needsIsSandboxed ?? false,
                    'is_variadic' => $method->isVariadic(),
                    'is_safe' => $attribute->isSafe,
                    'is_safe_callback' => $attribute->isSafeCallback,
                    'pre_escape' => $attribute->preEscape,
                    'preserves_safety' => $attribute->preservesSafety,
                    'always_allowed_in_sandbox' => $attribute->alwaysAllowedInSandbox ?? false,
                    'deprecation_info' => $attribute->deprecationInfo,
                ]);

                if ($callable->getMinimalNumberOfRequiredArguments() > $method->getNumberOfParameters()) {
                    throw new \LogicException(\sprintf('"%s::%s()" needs at least %d arguments to be used AsTwigFilter, but only %d defined.', $reflectionClass->getName(), $method->getName(), $callable->getMinimalNumberOfRequiredArguments(), $method->getNumberOfParameters()));
                }

                $filters[$attribute->name] = $callable;
            }

            foreach ($method->getAttributes(AsTwigFunction::class) as $reflectionAttribute) {
                /** @var AsTwigFunction $attribute */
                $attribute = $reflectionAttribute->newInstance();

                $callable = new TwigFunction($attribute->name, [$reflectionClass->name, $method->getName()], [
                    'needs_context' => $attribute->needsContext ?? false,
                    'needs_environment' => $attribute->needsEnvironment ?? $this->needsEnvironment($method),
                    'needs_charset' => $attribute->needsCharset ?? false,
                    'needs_is_sandboxed' => $attribute->needsIsSandboxed ?? false,
                    'is_variadic' => $method->isVariadic(),
                    'is_safe' => $attribute->isSafe,
                    'is_safe_callback' => $attribute->isSafeCallback,
                    'always_allowed_in_sandbox' => $attribute->alwaysAllowedInSandbox ?? false,
                    'deprecation_info' => $attribute->deprecationInfo,
                ]);

                if ($callable->getMinimalNumberOfRequiredArguments() > $method->getNumberOfParameters()) {
                    throw new \LogicException(\sprintf('"%s::%s()" needs at least %d arguments to be used AsTwigFunction, but only %d defined.', $reflectionClass->getName(), $method->getName(), $callable->getMinimalNumberOfRequiredArguments(), $method->getNumberOfParameters()));
                }

                $functions[$attribute->name] = $callable;
            }

            foreach ($method->getAttributes(AsTwigTest::class) as $reflectionAttribute) {
                /** @var AsTwigTest $attribute */
                $attribute = $reflectionAttribute->newInstance();

                $callable = new TwigTest($attribute->name, [$reflectionClass->name, $method->getName()], [
                    'needs_context' => $attribute->needsContext ?? false,
                    'needs_environment' => $attribute->needsEnvironment ?? $this->needsEnvironment($method),
                    'needs_charset' => $attribute->needsCharset ?? false,
                    'needs_is_sandboxed' => $attribute->needsIsSandboxed ?? false,
                    'is_variadic' => $method->isVariadic(),
                    'always_allowed_in_sandbox' => $attribute->alwaysAllowedInSandbox ?? false,
                    'deprecation_info' => $attribute->deprecationInfo,
                ]);

                if ($callable->getMinimalNumberOfRequiredArguments() > $method->getNumberOfParameters()) {
                    throw new \LogicException(\sprintf('"%s::%s()" needs at least %d arguments to be used AsTwigTest, but only %d defined.', $reflectionClass->getName(), $method->getName(), $callable->getMinimalNumberOfRequiredArguments(), $method->getNumberOfParameters()));
                }

                $tests[$attribute->name] = $callable;
            }
        }

        // Assign all at the end to avoid inconsistent state in case of exception
        $this->filters = array_values($filters);
        $this->functions = array_values($functions);
        $this->tests = array_values($tests);
    }

    /**
     * Detect if the first argument of the method is the environment.
     */
    private function needsEnvironment(\ReflectionFunctionAbstract $function): bool
    {
        if (!$parameters = $function->getParameters()) {
            return false;
        }

        return $parameters[0]->getType() instanceof \ReflectionNamedType
            && Environment::class === $parameters[0]->getType()->getName()
            && !$parameters[0]->isVariadic();
    }
}
