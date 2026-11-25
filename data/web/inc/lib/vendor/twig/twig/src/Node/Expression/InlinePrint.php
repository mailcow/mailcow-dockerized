<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Twig\Node\Expression;

use Twig\Compiler;
use Twig\Node\Node;

/**
 * @internal
 */
final class InlinePrint extends AbstractExpression
{
    /**
     * @param AbstractExpression $node
     */
    public function __construct(Node $node, int $lineno)
    {
        trigger_deprecation('twig/twig', '3.16', \sprintf('The "%s" class is deprecated with no replacement.', static::class));

        parent::__construct(['node' => $node], [], $lineno);
    }

    public function compile(Compiler $compiler): void
    {
        $compiler
            ->raw('yield ')
            ->subcompile($this->getNode('node'))
        ;
    }
}
