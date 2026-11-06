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
 * Represents the occurrence of a macro declaration in a template body.
 *
 * The corresponding MacroNode is stored in the module macro registry.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @internal
 */
#[YieldReady]
final class MacroDeclarationNode extends ConfigNode
{
    public function __construct(string $name, int $lineno)
    {
        parent::__construct($lineno);

        $this->setAttribute('name', $name);
    }
}
