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
 * Represents a types node.
 *
 * @author Jeroen Versteeg <jeroen@alisqi.com>
 */
#[YieldReady]
class TypesNode extends Node
{
    /**
     * @param array<string, array{type: string, optional: bool}> $types
     */
    public function __construct(array $types, int $lineno)
    {
        $nodes = [];
        foreach ($types as $name => $type) {
            $nodes[$name] = new TypeNode($name, $type['type'], $type['optional'], $lineno);
        }

        parent::__construct($nodes, ['mapping' => $types], $lineno);
    }

    /**
     * @return void
     */
    public function compile(Compiler $compiler)
    {
        // Don't compile anything.
    }
}
