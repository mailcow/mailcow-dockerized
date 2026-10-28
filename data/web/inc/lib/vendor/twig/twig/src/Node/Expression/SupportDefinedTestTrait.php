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

trait SupportDefinedTestTrait
{
    private bool $definedTest = false;

    public function enableDefinedTest(): void
    {
        $this->definedTest = true;
    }

    public function isDefinedTestEnabled(): bool
    {
        return $this->definedTest;
    }
}
