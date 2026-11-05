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

use Twig\Node\Expression\FunctionNode\RenderSandboxedFunction;
use Twig\Runtime\SandboxBridgeRuntime;
use Twig\TwigFunction;

/**
 * Exposes a sandbox to trusted templates.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class SandboxBridgeExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('render_sandboxed', [SandboxBridgeRuntime::class, 'render'], ['is_safe_callback' => [RenderSandboxedFunction::class, 'getSafe'], 'node_class' => RenderSandboxedFunction::class]),
        ];
    }

    public function getLastModified(): int
    {
        return max(
            parent::getLastModified(),
            filemtime((new \ReflectionClass(SandboxBridgeRuntime::class))->getFileName()),
        );
    }
}
