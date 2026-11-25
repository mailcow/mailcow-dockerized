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

abstract class AbstractExtension implements LastModifiedExtensionInterface
{
    public function getTokenParsers()
    {
        return [];
    }

    public function getNodeVisitors()
    {
        return [];
    }

    public function getFilters()
    {
        return [];
    }

    public function getTests()
    {
        return [];
    }

    public function getFunctions()
    {
        return [];
    }

    public function getOperators()
    {
        return [[], []];
    }

    public function getExpressionParsers(): array
    {
        return [];
    }

    public function getLastModified(): int
    {
        $filename = (new \ReflectionClass($this))->getFileName();
        if (!is_file($filename)) {
            return 0;
        }

        $lastModified = filemtime($filename);

        // Track modifications of the runtime class if it exists and follows the naming convention
        if (str_ends_with($filename, 'Extension.php') && is_file($filename = substr($filename, 0, -13).'Runtime.php')) {
            $lastModified = max($lastModified, filemtime($filename));
        }

        return $lastModified;
    }
}
