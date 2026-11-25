<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Twig\Node;

use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Expression\Variable\AssignMacroVariable;
use Twig\Node\Expression\Variable\ContextVariable;
use Twig\Node\Expression\Variable\MacroVariable;

/**
 * Represents an import node.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
#[YieldReady]
class ImportNode extends Node implements CoercesChildrenToStringInterface
{
    public function __construct(AbstractExpression $expr, AbstractExpression|AssignMacroVariable $var, int $lineno)
    {
        if (\func_num_args() > 3) {
            trigger_deprecation('twig/twig', '3.15', \sprintf('Passing more than 3 arguments to "%s()" is deprecated.', __METHOD__));
        }

        if (!$var instanceof AssignMacroVariable) {
            trigger_deprecation('twig/twig', '3.15', \sprintf('Passing a "%s" instance as the second argument of "%s" is deprecated, pass a "%s" instead.', $var::class, __CLASS__, AssignMacroVariable::class));

            $var = new AssignMacroVariable(new MacroVariable($var->getAttribute('name'), $lineno));
        }

        parent::__construct(['expr' => $expr, 'var' => $var], [], $lineno);
    }

    public function compile(Compiler $compiler): void
    {
        $compiler->subcompile($this->getNode('var'));

        if ($this->getNode('expr') instanceof ContextVariable && '_self' === $this->getNode('expr')->getAttribute('name')) {
            $compiler->raw('$this->getMacroNamespace()');
        } else {
            $compiler
                ->raw('$this->load(')
                ->subcompile($this->getNode('expr'))
                ->raw(', ')
                ->repr($this->getTemplateLine())
                ->raw(')->unwrap()->getMacroNamespace()')
            ;
        }

        $compiler->raw(";\n");
    }

    public function getStringCoercedChildNames(): array
    {
        // the loader resolves the template-name expression by coercing it to a string
        return ['expr'];
    }
}
