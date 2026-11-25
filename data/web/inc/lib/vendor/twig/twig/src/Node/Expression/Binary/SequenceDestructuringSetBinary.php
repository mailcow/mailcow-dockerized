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
use Twig\Error\SyntaxError;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Expression\EmptyExpression;
use Twig\Node\Expression\Variable\AssignContextVariable;
use Twig\Node\Node;

/**
 * @internal
 */
class SequenceDestructuringSetBinary extends AbstractBinary
{
    private array $variables = [];

    /**
     * @param ArrayExpression    $left  The array expression containing variables to assign to
     * @param AbstractExpression $right The expression providing values for assignment
     */
    public function __construct(Node $left, Node $right, int $lineno)
    {
        foreach ($left->getKeyValuePairs() as $pair) {
            if ($pair['value'] instanceof EmptyExpression) {
                $this->variables[] = null;
            } elseif ($pair['value'] instanceof AssignContextVariable) {
                $this->variables[] = $pair['value']->getAttribute('name');
            } else {
                throw new SyntaxError(\sprintf('Cannot assign to "%s", only variables can be assigned in sequence destructuring.', $pair['value']::class), $lineno);
            }
        }

        parent::__construct($left, $right, $lineno);
    }

    public function compile(Compiler $compiler): void
    {
        $compiler->addDebugInfo($this);
        $var = '$'.$compiler->getVarName();
        $compiler
            ->raw('[(('.$var.' = ')
            ->subcompile($this->getNode('right'))
            ->raw(') instanceof \Traversable ? CoreExtension::destructureSequence($context, ')
            ->repr($this->variables)
            ->raw(', '.$var.') : ([')
        ;
        foreach ($this->variables as $i => $name) {
            if ($i) {
                $compiler->raw(', ');
            }
            if (null !== $name) {
                $compiler->raw('$context[')->repr($name)->raw(']');
            }
        }
        $compiler
            ->raw('] = array_pad('.$var.', ')
            ->repr(\count($this->variables))
            ->raw(', null))), '.$var.' = null][0]')
        ;
    }

    public function operator(Compiler $compiler): Compiler
    {
        return $compiler->raw('=');
    }
}
