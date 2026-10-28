<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Twig\Node\Expression\Binary;

use Twig\Compiler;
use Twig\Node\CoercesChildrenToStringInterface;
use Twig\Node\Expression\ReturnArrayInterface;

class RangeBinary extends AbstractBinary implements ReturnArrayInterface, CoercesChildrenToStringInterface
{
    public function compile(Compiler $compiler): void
    {
        $compiler
            ->raw('range(')
            ->subcompile($this->getNode('left'))
            ->raw(', ')
            ->subcompile($this->getNode('right'))
            ->raw(')')
        ;
    }

    public function operator(Compiler $compiler): Compiler
    {
        return $compiler->raw('..');
    }

    public function getStringCoercedChildNames(): array
    {
        return ['left', 'right'];
    }
}
