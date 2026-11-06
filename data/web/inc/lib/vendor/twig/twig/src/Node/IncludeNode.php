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

/**
 * Represents an include node.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
#[YieldReady]
class IncludeNode extends Node implements NodeOutputInterface, CoercesChildrenToStringInterface
{
    public function __construct(AbstractExpression $expr, ?AbstractExpression $variables, bool $only, bool $ignoreMissing, int $lineno)
    {
        $nodes = ['expr' => $expr];
        if (null !== $variables) {
            $nodes['variables'] = $variables;
        }

        parent::__construct($nodes, ['only' => $only, 'ignore_missing' => $ignoreMissing], $lineno);
    }

    public function compile(Compiler $compiler): void
    {
        $compiler->addDebugInfo($this);

        if ($this->getAttribute('ignore_missing')) {
            $template = $compiler->getVarName();

            $compiler
                ->write("try {\n")
                ->indent()
                ->write(\sprintf('$%s = ', $template))
            ;

            $this->addGetTemplate($compiler, $template);

            $compiler
                ->raw(";\n")
                ->outdent()
                ->write("} catch (LoaderError \$e) {\n")
                ->indent()
                ->write("// ignore missing template\n")
                ->write(\sprintf("\$$template = null;\n", $template))
                ->outdent()
                ->write("}\n")
                ->write(\sprintf("if ($%s) {\n", $template))
                ->indent()
            ;

            $compiler->write(\sprintf('yield from $%s->unwrap()->yield(', $template));

            $this->addTemplateArguments($compiler);
            $compiler
                ->raw(");\n")
                ->outdent()
                ->write("}\n")
            ;
        } else {
            $compiler->write('yield from ');
            $this->addGetTemplate($compiler);
            $compiler->raw('->unwrap()->yield(');
            $this->addTemplateArguments($compiler);
            $compiler->raw(");\n");
        }
    }

    /**
     * @return void
     */
    protected function addGetTemplate(Compiler $compiler/* , string $template = '' */)
    {
        $compiler
            ->raw('$this->load(')
            ->subcompile($this->getNode('expr'))
            ->raw(', ')
            ->repr($this->getTemplateLine())
            ->raw(')')
        ;
    }

    /**
     * @return void
     */
    protected function addTemplateArguments(Compiler $compiler)
    {
        if (!$this->hasNode('variables')) {
            $compiler->raw(false === $this->getAttribute('only') ? '$context' : '[]');
        } elseif (false === $this->getAttribute('only')) {
            $compiler
                ->raw('CoreExtension::merge($context, ')
                ->subcompile($this->getNode('variables'))
                ->raw(')')
            ;
        } else {
            $compiler->raw('CoreExtension::toArray(');
            $compiler->subcompile($this->getNode('variables'));
            $compiler->raw(')');
        }
    }

    public function getStringCoercedChildNames(): array
    {
        // the loader resolves the template-name expression by coercing it to a string
        return ['expr'];
    }
}
