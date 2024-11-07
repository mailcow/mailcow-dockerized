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

use Twig\Node\Expression\FunctionExpression;
use Twig\Node\Node;

/**
 * Represents a template function.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @see https://twig.symfony.com/doc/templates.html#functions
 */
final class TwigFunction extends AbstractTwigCallable
{
    /**
     * @param callable|array{class-string, string}|null $callable A callable implementing the function. If null, you need to overwrite the "node_class" option to customize compilation.
     */
    public function __construct(string $name, $callable = null, array $options = [])
    {
        parent::__construct($name, $callable, $options);

        $this->options = array_merge([
            'is_safe' => null,
            'is_safe_callback' => null,
            'node_class' => FunctionExpression::class,
            'parser_callable' => null,
        ], $this->options);
    }

    public function getType(): string
    {
        return 'function';
    }

    public function getParserCallable(): ?callable
    {
        return $this->options['parser_callable'];
    }

    public function getSafe(Node $functionArgs): ?array
    {
        if (null !== $this->options['is_safe']) {
            return $this->options['is_safe'];
        }

        if (null !== $this->options['is_safe_callback']) {
            return $this->options['is_safe_callback']($functionArgs);
        }

        return [];
    }
}
