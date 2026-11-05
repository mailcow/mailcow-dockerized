<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Twig\Extension;

interface LastModifiedExtensionInterface extends ExtensionInterface
{
    /**
     * Returns the last modification time of the extension for cache invalidation.
     *
     * This timestamp should be the last time the source code of the extension class
     * and all its dependencies were modified (including the Runtime class).
     */
    public function getLastModified(): int;
}
