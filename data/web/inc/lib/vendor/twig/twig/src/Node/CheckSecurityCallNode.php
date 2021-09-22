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
 * @author Fabien Potencier <fabien@symfony.com>
 */
class CheckSecurityCallNode extends Node
{
    public function compile(Compiler $compiler)
    {
        $compiler
            ->write("\$this->sandbox = \$this->env->getExtension('\Twig\Extension\SandboxExtension');\n")
            ->write("\$this->checkSecurity();\n")
        ;
    }
}
