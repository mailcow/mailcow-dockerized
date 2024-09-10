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
use Twig\Node\Expression\NameExpression;

/**
 * Represents an import node.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
#[YieldReady]
class ImportNode extends Node
{
    /**
     * @param bool $global
     */
    public function __construct(AbstractExpression $expr, AbstractExpression $var, int $lineno, $global = true)
    {
        if (null === $global || \is_string($global)) {
            trigger_deprecation('twig/twig', '3.12', 'Passing a tag to %s() is deprecated.', __METHOD__);
            $global = \func_num_args() > 4 ? func_get_arg(4) : true;
        } elseif (!\is_bool($global)) {
            throw new \TypeError(\sprintf('Argument 4 passed to "%s()" must be a boolean, "%s" given.', __METHOD__, get_debug_type($global)));
        }

        parent::__construct(['expr' => $expr, 'var' => $var], ['global' => $global], $lineno);
    }

    public function compile(Compiler $compiler): void
    {
        $compiler
            ->addDebugInfo($this)
            ->write('$macros[')
            ->repr($this->getNode('var')->getAttribute('name'))
            ->raw('] = ')
        ;

        if ($this->getAttribute('global')) {
            $compiler
                ->raw('$this->macros[')
                ->repr($this->getNode('var')->getAttribute('name'))
                ->raw('] = ')
            ;
        }

        if ($this->getNode('expr') instanceof NameExpression && '_self' === $this->getNode('expr')->getAttribute('name')) {
            $compiler->raw('$this');
        } else {
            $compiler
                ->raw('$this->loadTemplate(')
                ->subcompile($this->getNode('expr'))
                ->raw(', ')
                ->repr($this->getTemplateName())
                ->raw(', ')
                ->repr($this->getTemplateLine())
                ->raw(')->unwrap()')
            ;
        }

        $compiler->raw(";\n");
    }
}
