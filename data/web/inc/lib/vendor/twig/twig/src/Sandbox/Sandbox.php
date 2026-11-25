<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Twig\Sandbox;

use Twig\Environment;
use Twig\Extension\SandboxExtension;
use Twig\TemplateWrapper;

/**
 * Renders untrusted templates in a dedicated, always-sandboxed environment.
 *
 * The sandbox takes ownership of an environment crafted specifically for it:
 * its loader defines which templates are reachable, its extensions, filters,
 * functions, tests, and globals define which capabilities exist, and the
 * security policy defines what is allowed to execute. Never pass an
 * application environment: the sandbox environment must be dedicated to
 * rendering untrusted templates.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class Sandbox implements SandboxInterface
{
    public function __construct(
        private Environment $env,
        SecurityPolicyInterface $policy,
    ) {
        if ($policy instanceof SecurityPolicy && !$policy->isStrict()) {
            throw new \LogicException('The sandbox requires a strict security policy, call "setStrict(true)" on a dedicated policy for this sandbox.');
        }

        try {
            $env->addExtension(new SandboxExtension($policy, true));
        } catch (\LogicException $e) {
            throw new \LogicException(\sprintf('The environment passed to "%s" must be dedicated to it: pass a freshly built environment that has no "%s" registered and has not been used yet.', self::class, SandboxExtension::class), 0, $e);
        }
    }

    public function render(string $name, array $context = []): string
    {
        return $this->env->render($name, $context);
    }

    public function display(string $name, array $context = []): void
    {
        $this->env->display($name, $context);
    }

    public function stream(string $name, array $context = []): iterable
    {
        yield from $this->env->load($name)->stream($context);
    }

    public function renderBlock(string $name, string $block, array $context = []): string
    {
        return $this->env->load($name)->renderBlock($block, $context);
    }

    public function displayBlock(string $name, string $block, array $context = []): void
    {
        $this->env->load($name)->displayBlock($block, $context);
    }

    public function streamBlock(string $name, string $block, array $context = []): iterable
    {
        yield from $this->env->load($name)->streamBlock($block, $context);
    }

    public function createTemplate(string $template, ?string $name = null): TemplateWrapper
    {
        return $this->env->createTemplate($template, $name);
    }
}
