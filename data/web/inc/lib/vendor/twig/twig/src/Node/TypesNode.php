<?php

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
        parent::__construct([], ['mapping' => $types], $lineno);
    }

    public function compile(Compiler $compiler)
    {
        // Don't compile anything.
    }
}
