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

/**
 * Represents a text node.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
#[YieldReady]
class TextNode extends Node implements NodeOutputInterface
{
    public function __construct(string $data, int $lineno)
    {
        parent::__construct([], ['data' => $data], $lineno);
    }

    public function compile(Compiler $compiler): void
    {
        $compiler->addDebugInfo($this);

        $compiler
            ->write('yield ')
            ->string($this->getAttribute('data'))
            ->raw(";\n")
        ;
    }

    public function isBlank(): bool
    {
        if (ctype_space($this->getAttribute('data'))) {
            return true;
        }

        if (str_contains((string) $this, \chr(0xEF).\chr(0xBB).\chr(0xBF))) {
            $t = substr($this->getAttribute('data'), 3);
            if ('' === $t || ctype_space($t)) {
                return true;
            }
        }

        return false;
    }
}
