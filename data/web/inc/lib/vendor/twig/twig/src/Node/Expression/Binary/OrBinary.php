<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 * (c) Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Twig\Node\Expression\Binary;

use Twig\Compiler;
use Twig\Node\Expression\ReturnBoolInterface;
use Twig\Node\Expression\Test\TrueTest;
use Twig\Node\Node;

class OrBinary extends AbstractBinary implements ReturnBoolInterface
{
    public function __construct(Node $left, Node $right, int $lineno)
    {
        parent::__construct(TrueTest::wrap($left), TrueTest::wrap($right), $lineno);
    }

    public function operator(Compiler $compiler): Compiler
    {
        return $compiler->raw('||');
    }
}
