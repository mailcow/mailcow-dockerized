<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Twig\Cache;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface RemovableCacheInterface
{
    public function remove(string $name, string $cls): void;
}
