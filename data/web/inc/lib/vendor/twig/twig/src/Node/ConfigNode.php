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
 * Represents a node that has global side effects but does not generate template code.
 *
 * Such nodes must be at the root level of the body of a template.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
#[YieldReady]
class ConfigNode extends Node
{
    public function __construct(int $lineno)
    {
        parent::__construct([], [], $lineno);
    }
}
