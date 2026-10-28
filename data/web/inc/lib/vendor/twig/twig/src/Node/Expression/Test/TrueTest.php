<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Twig\Node\Expression\Test;

use Twig\Compiler;
use Twig\Node\Expression\ReturnPrimitiveTypeInterface;
use Twig\Node\Expression\TestExpression;
use Twig\Node\Node;
use Twig\TwigTest;

/**
 * Checks that an expression is true.
 *
 *  {{ var is true }}
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TrueTest extends TestExpression
{
    public static function wrap(Node $node): Node
    {
        if ($node instanceof ReturnPrimitiveTypeInterface) {
            return $node;
        }

        return new self($node, new TwigTest('true', null, ['always_allowed_in_sandbox' => true]), null, $node->getTemplateLine());
    }

    public function compile(Compiler $compiler): void
    {
        $compiler
            ->raw('(($tmp = ')
            ->subcompile($this->getNode('node'))
            ->raw(') && $tmp instanceof Markup ? (string) $tmp : $tmp)')
        ;
    }

    public function getStringCoercedChildNames(): array
    {
        // the `(string)` cast only fires for Markup instances, whose __toString is always allowed
        return [];
    }
}
