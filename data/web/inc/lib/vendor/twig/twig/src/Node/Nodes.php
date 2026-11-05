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

/**
 * Represents a list of nodes.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
#[YieldReady]
final class Nodes extends Node
{
    public function __construct(array $nodes = [], int $lineno = 0)
    {
        parent::__construct($nodes, [], $lineno);
    }
}
