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

namespace Twig\Node;

use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Expression\Variable\AssignContextVariable;

/**
 * Represents a for node.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
#[YieldReady]
class ForNode extends Node
{
    private $loop;

    public function __construct(AssignContextVariable $keyTarget, AssignContextVariable $valueTarget, AbstractExpression $seq, ?Node $ifexpr, Node $body, ?Node $else, int $lineno)
    {
        $body = new Nodes([$body, $this->loop = new ForLoopNode($lineno)]);

        if (null !== $ifexpr) {
            trigger_deprecation('twig/twig', '3.19', \sprintf('Passing not-null to the "ifexpr" argument of the "%s" constructor is deprecated.', static::class));
        }

        if (null !== $else && !$else instanceof ForElseNode) {
            trigger_deprecation('twig/twig', '3.19', \sprintf('Not passing an instance of "%s" to the "else" argument of the "%s" constructor is deprecated.', ForElseNode::class, static::class));

            $else = new ForElseNode($else, $else->getTemplateLine());
        }

        $nodes = ['key_target' => $keyTarget, 'value_target' => $valueTarget, 'seq' => $seq, 'body' => $body];
        if (null !== $else) {
            $nodes['else'] = $else;
        }

        parent::__construct($nodes, ['with_loop' => true], $lineno);
    }

    public function compile(Compiler $compiler): void
    {
        $compiler
            ->addDebugInfo($this)
            ->write("\$context['_parent'] = \$context;\n")
            ->write("\$context['_seq'] = CoreExtension::ensureTraversable(")
            ->subcompile($this->getNode('seq'))
            ->raw(");\n")
        ;

        if ($this->hasNode('else')) {
            $compiler->write("\$context['_iterated'] = false;\n");
        }

        if ($this->getAttribute('with_loop')) {
            $compiler
                ->write("\$context['loop'] = [\n")
                ->write("  'parent' => \$context['_parent'],\n")
                ->write("  'index0' => 0,\n")
                ->write("  'index'  => 1,\n")
                ->write("  'first'  => true,\n")
                ->write("];\n")
                ->write("if (is_array(\$context['_seq']) || (is_object(\$context['_seq']) && \$context['_seq'] instanceof \Countable)) {\n")
                ->indent()
                ->write("\$length = count(\$context['_seq']);\n")
                ->write("\$context['loop']['revindex0'] = \$length - 1;\n")
                ->write("\$context['loop']['revindex'] = \$length;\n")
                ->write("\$context['loop']['length'] = \$length;\n")
                ->write("\$context['loop']['last'] = 1 === \$length;\n")
                ->outdent()
                ->write("}\n")
            ;
        }

        $this->loop->setAttribute('else', $this->hasNode('else'));
        $this->loop->setAttribute('with_loop', $this->getAttribute('with_loop'));

        $compiler
            ->write("foreach (\$context['_seq'] as ")
            ->subcompile($this->getNode('key_target'))
            ->raw(' => ')
            ->subcompile($this->getNode('value_target'))
            ->raw(") {\n")
            ->indent()
            ->subcompile($this->getNode('body'))
            ->outdent()
            ->write("}\n")
        ;

        if ($this->hasNode('else')) {
            $compiler->subcompile($this->getNode('else'));
        }

        $compiler->write("\$_parent = \$context['_parent'];\n");

        // remove some "private" loop variables (needed for nested loops)
        $compiler->write('unset($context[\'_seq\'], $context[\''.$this->getNode('key_target')->getAttribute('name').'\'], $context[\''.$this->getNode('value_target')->getAttribute('name').'\'], $context[\'_parent\']');
        if ($this->hasNode('else')) {
            $compiler->raw(', $context[\'_iterated\']');
        }
        if ($this->getAttribute('with_loop')) {
            $compiler->raw(', $context[\'loop\']');
        }
        $compiler->raw(");\n");

        // keep the values set in the inner context for variables defined in the outer context
        $compiler
            ->write("\$context = array_intersect_key(\$context, \$_parent);\n")
            ->write("\$context += \$_parent;\n")
        ;
    }
}
