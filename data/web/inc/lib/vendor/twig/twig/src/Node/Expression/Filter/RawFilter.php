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
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FilterExpression;
use Twig\Node\Node;
use Twig\TwigFilter;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class RawFilter extends FilterExpression
{
    #[FirstClassTwigCallableReady]
    public function __construct(Node $node, TwigFilter|ConstantExpression|null $filter = null, ?Node $arguments = null, int $lineno = 0)
    {
        parent::__construct($node, $filter ?: new TwigFilter('raw', null, ['is_safe' => ['all']]), $arguments ?: new Node(), $lineno ?: $node->getTemplateLine());
    }

    public function compile(Compiler $compiler): void
    {
        $compiler->subcompile($this->getNode('node'));
    }
}
