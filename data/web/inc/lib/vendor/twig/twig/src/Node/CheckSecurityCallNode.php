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
 * Wires the security checker at the very top of the template constructor, as
 * the constructor resolves `use` traits before the sandbox could check them.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
#[YieldReady]
class CheckSecurityCallNode extends Node
{
    /**
     * @return void
     */
    public function compile(Compiler $compiler)
    {
        $compiler
            ->write("\$this->sandbox = \$env->getExtension(SandboxExtension::class)->getChecker();\n")
        ;
    }
}
