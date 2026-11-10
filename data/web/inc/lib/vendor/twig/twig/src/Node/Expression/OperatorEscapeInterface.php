<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Twig\Node\Expression;

/**
 * Interface implemented by n-ary operators for n > 1.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface OperatorEscapeInterface
{
    /**
     * @return string[]
     */
    public function getOperandNamesToEscape(): array;
}
