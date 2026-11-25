<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Twig;

use Twig\ExpressionParser\PrecedenceChange;

/**
 * Represents a precedence change.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since Twig 1.20 Use Twig\ExpressionParser\PrecedenceChange instead
 */
class OperatorPrecedenceChange extends PrecedenceChange
{
    public function __construct(
        private string $package,
        private string $version,
        private int $newPrecedence,
    ) {
        trigger_deprecation('twig/twig', '3.21', 'The "%s" class is deprecated since Twig 3.21. Use "%s" instead.', self::class, PrecedenceChange::class);

        parent::__construct($package, $version, $newPrecedence);
    }
}
