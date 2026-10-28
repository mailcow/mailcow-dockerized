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
 * Represents a type declaration node.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
#[YieldReady]
final class TypeNode extends Node
{
    /**
     * @internal
     */
    public function __construct(string $name, string $type, bool $optional, int $lineno)
    {
        parent::__construct([], ['name' => $name, 'type' => $type, 'optional' => $optional], $lineno);
    }
}
