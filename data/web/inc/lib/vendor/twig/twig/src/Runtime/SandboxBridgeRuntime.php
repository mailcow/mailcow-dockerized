<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Twig\Runtime;

use Twig\Extension\RuntimeExtensionInterface;
use Twig\Sandbox\SandboxInterface;

final class SandboxBridgeRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private SandboxInterface $sandbox,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function render(string $name, array $context): string
    {
        return $this->sandbox->render($name, $context);
    }
}
