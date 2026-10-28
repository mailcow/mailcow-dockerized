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
use Twig\Node\Expression\Variable\AssignContextVariable;

class ListExpression extends AbstractExpression
{
    /**
     * @param array<AssignContextVariable> $items
     */
    public function __construct(array $items, int $lineno)
    {
        parent::__construct($items, [], $lineno);
    }

    public function compile(Compiler $compiler): void
    {
        foreach ($this as $i => $name) {
            if ($i) {
                $compiler->raw(', ');
            }

            $compiler
                ->raw('$__')
                ->raw($name->getAttribute('name'))
                ->raw('__')
            ;
        }
    }
}
