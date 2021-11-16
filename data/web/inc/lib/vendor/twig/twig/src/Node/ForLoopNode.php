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

use Twig\Compiler;

/**
 * Internal node used by the for node.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ForLoopNode extends Node
{
    public function __construct(int $lineno, string $tag = null)
    {
        parent::__construct([], ['with_loop' => false, 'ifexpr' => false, 'else' => false], $lineno, $tag);
    }

    public function compile(Compiler $compiler): void
    {
        if ($this->getAttribute('else')) {
            $compiler->write("\$context['_iterated'] = true;\n");
        }

        if ($this->getAttribute('with_loop')) {
            $compiler
                ->write("++\$context['loop']['index0'];\n")
                ->write("++\$context['loop']['index'];\n")
                ->write("\$context['loop']['first'] = false;\n")
                ->write("if (isset(\$context['loop']['length'])) {\n")
                ->indent()
                ->write("--\$context['loop']['revindex0'];\n")
                ->write("--\$context['loop']['revindex'];\n")
                ->write("\$context['loop']['last'] = 0 === \$context['loop']['revindex0'];\n")
                ->outdent()
                ->write("}\n")
            ;
        }
    }
}
