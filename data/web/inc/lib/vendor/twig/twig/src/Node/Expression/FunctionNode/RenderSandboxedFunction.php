<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Twig\Node\Expression\FunctionNode;

use Twig\Compiler;
use Twig\Error\SyntaxError;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FunctionExpression;
use Twig\Node\Node;

/**
 * @internal
 */
final class RenderSandboxedFunction extends FunctionExpression
{
    public function compile(Compiler $compiler): void
    {
        $arguments = $this->getNode('arguments');
        $outputStrategyName = self::getOutputStrategyName($arguments);
        if (null === $outputStrategyName) {
            throw new SyntaxError('Value for argument "output_strategy" is required for function "render_sandboxed".', $this->getTemplateLine(), $this->getSourceContext());
        }

        $outputStrategy = $arguments->getNode($outputStrategyName);
        if (!$outputStrategy instanceof ConstantExpression) {
            throw new SyntaxError('The "output_strategy" argument of the "render_sandboxed" function must be a non-empty literal string other than "all".', $outputStrategy->getTemplateLine(), $outputStrategy->getSourceContext());
        }

        $strategy = $outputStrategy->getAttribute('value');
        if (!\is_string($strategy) || '' === $strategy || 'all' === $strategy) {
            throw new SyntaxError('The "output_strategy" argument of the "render_sandboxed" function must be a non-empty literal string other than "all".', $outputStrategy->getTemplateLine(), $outputStrategy->getSourceContext());
        }

        $runtimeArguments = clone $arguments;
        $runtimeArguments->removeNode($outputStrategyName);
        $this->setNode('arguments', $runtimeArguments);
        try {
            parent::compile($compiler);
        } finally {
            $this->setNode('arguments', $arguments);
        }
    }

    /**
     * @internal
     *
     * @return string[]
     */
    public static function getSafe(Node $arguments): array
    {
        $name = self::getOutputStrategyName($arguments);
        if (null === $name) {
            return [];
        }

        $outputStrategy = $arguments->getNode($name);
        if (!$outputStrategy instanceof ConstantExpression) {
            return [];
        }

        $outputStrategy = $outputStrategy->getAttribute('value');

        return \is_string($outputStrategy) && '' !== $outputStrategy && 'all' !== $outputStrategy ? [$outputStrategy] : [];
    }

    private static function getOutputStrategyName(Node $arguments): string|int|null
    {
        foreach ($arguments as $name => $argument) {
            if (2 === $name || (\is_string($name) && 'outputstrategy' === strtolower(str_replace('_', '', $name)))) {
                return $name;
            }
        }

        return null;
    }
}
