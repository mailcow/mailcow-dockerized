<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Twig\RuntimeLoader;

use Psr\Container\ContainerInterface;

/**
 * Lazily loads Twig runtime implementations from a PSR-11 container.
 *
 * Note that the runtime services MUST use their class names as identifiers.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
class ContainerRuntimeLoader implements RuntimeLoaderInterface
{
    public function __construct(
        private ContainerInterface $container,
    ) {
    }

    public function load(string $class)
    {
        return $this->container->has($class) ? $this->container->get($class) : null;
    }
}
