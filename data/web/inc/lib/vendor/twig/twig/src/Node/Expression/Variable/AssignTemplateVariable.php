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
 * @deprecated since Twig 3.29, use AssignMacroVariable instead
 */
final class AssignTemplateVariable extends AssignMacroVariable
{
    public function __construct(TemplateVariable $var, bool $global = true)
    {
        trigger_deprecation('twig/twig', '3.29', 'The "%s" class is deprecated, use "%s" instead.', self::class, AssignMacroVariable::class);

        parent::__construct($var, $global);
    }
}
