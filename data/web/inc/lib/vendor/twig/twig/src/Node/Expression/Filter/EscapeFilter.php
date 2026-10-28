<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Twig\Node\Expression\Filter;

use Twig\Attribute\FirstClassTwigCallableReady;
use Twig\Compiler;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FilterExpression;
use Twig\Node\Node;
use Twig\TwigFilter;

/**
 * Uses the escaper runtime fetched by the template constructor when the escaper node visitor flagged it.
 *
 * @internal
 */
final class EscapeFilter extends FilterExpression
{
    /**
     * @param AbstractExpression $node
     */
    #[FirstClassTwigCallableReady]
    public function __construct(Node $node, TwigFilter|ConstantExpression $filter, Node $arguments, int $lineno)
    {
        parent::__construct($node, $filter, $arguments, $lineno);

        $this->setAttribute('template_escaper', false);
    }

    protected function compileCallable(Compiler $compiler): void
    {
        if (!$this->getAttribute('template_escaper')) {
            parent::compileCallable($compiler);

            return;
        }

        $compiler->raw('$this->escaper->escape');
        $this->compileArguments($compiler);
    }
}
