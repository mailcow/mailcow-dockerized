<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Twig\Node\Expression\Variable;

/**
 * @deprecated since Twig 3.29, use MacroVariable instead
 */
class TemplateVariable extends MacroVariable
{
    public function __construct(string|int|null $name, int $lineno)
    {
        if (self::class === static::class) {
            trigger_deprecation('twig/twig', '3.29', 'The "%s" class is deprecated, use "%s" instead.', self::class, MacroVariable::class);
        }

        parent::__construct($name, $lineno);
    }
}
