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

/**
 * Represents a nested "with" scope.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
#[YieldReady]
class WithNode extends Node
{
    public function __construct(Node $body, ?Node $variables, bool $only, int $lineno)
    {
        $nodes = ['body' => $body];
        if (null !== $variables) {
            $nodes['variables'] = $variables;
        }

        parent::__construct($nodes, ['only' => $only], $lineno);
    }

    public function compile(Compiler $compiler): void
    {
        $compiler->addDebugInfo($this);

        $parentContextName = $compiler->getVarName();

        $compiler->write(\sprintf("\$%s = \$context;\n", $parentContextName));

        if ($this->hasNode('variables')) {
            $node = $this->getNode('variables');
            $varsName = $compiler->getVarName();
            $compiler
                ->write(\sprintf('$%s = ', $varsName))
                ->subcompile($node)
                ->raw(";\n")
                ->write(\sprintf("if (!is_iterable(\$%s)) {\n", $varsName))
                ->indent()
                ->write("throw new RuntimeError('Variables passed to the \"with\" tag must be a mapping.', ")
                ->repr($node->getTemplateLine())
                ->raw(", \$this->getSourceContext());\n")
                ->outdent()
                ->write("}\n")
                ->write(\sprintf("\$%s = CoreExtension::toArray(\$%s);\n", $varsName, $varsName))
            ;

            if ($this->getAttribute('only')) {
                $compiler->write("\$context = [];\n");
            }

            $compiler->write(\sprintf("\$context = \$%s + \$context + \$this->env->getGlobals();\n", $varsName));
        }

        $compiler
            ->subcompile($this->getNode('body'))
            ->write(\sprintf("\$context = \$%s;\n", $parentContextName))
        ;
    }
}
