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

namespace Twig\Node\Expression;

use Twig\Compiler;
use Twig\Node\Expression\Ternary\ConditionalTernary;

class ConditionalExpression extends AbstractExpression implements OperatorEscapeInterface
{
    public function __construct(AbstractExpression $expr1, AbstractExpression $expr2, AbstractExpression $expr3, int $lineno)
    {
        trigger_deprecation('twig/twig', '3.17', \sprintf('"%s" is deprecated; use "%s" instead.', __CLASS__, ConditionalTernary::class));

        parent::__construct(['expr1' => $expr1, 'expr2' => $expr2, 'expr3' => $expr3], [], $lineno);
    }

    public function compile(Compiler $compiler): void
    {
        // Ternary with no then uses Elvis operator
        if ($this->getNode('expr1') === $this->getNode('expr2')) {
            $compiler
                ->raw('((')
                ->subcompile($this->getNode('expr1'))
                ->raw(') ?: (')
                ->subcompile($this->getNode('expr3'))
                ->raw('))');
        } else {
            $compiler
                ->raw('((')
                ->subcompile($this->getNode('expr1'))
                ->raw(') ? (')
                ->subcompile($this->getNode('expr2'))
                ->raw(') : (')
                ->subcompile($this->getNode('expr3'))
                ->raw('))');
        }
    }

    public function getOperandNamesToEscape(): array
    {
        return ['expr2', 'expr3'];
    }
}
