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

use Twig\Error\RuntimeError;

/**
 * Exposes a template to userland.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class TemplateWrapper
{
    /**
     * This method is for internal use only and should never be called
     * directly (use Twig\Environment::load() instead).
     *
     * @internal
     */
    public function __construct(
        private Environment $env,
        private Template $template,
    ) {
    }

    /**
     * @return iterable<scalar|\Stringable|null>
     */
    public function stream(array $context = []): iterable
    {
        yield from $this->template->yield($context);
    }

    /**
     * @return iterable<scalar|\Stringable|null>
     */
    public function streamBlock(string $name, array $context = []): iterable
    {
        yield from $this->template->yieldBlock($name, $context + $this->env->getGlobals());
    }

    public function render(array $context = []): string
    {
        return $this->template->render($context);
    }

    public function display(array $context = []): void
    {
        // using func_get_args() allows to not expose the blocks argument
        // as it should only be used by internal code
        $this->template->display($context, \func_get_args()[1] ?? []);
    }

    public function hasBlock(string $name, array $context = []): bool
    {
        return $this->template->hasBlock($name, $context + $this->env->getGlobals());
    }

    /**
     * @return string[] An array of defined template block names
     */
    public function getBlockNames(array $context = []): array
    {
        return $this->template->getBlockNames($context + $this->env->getGlobals());
    }

    public function renderBlock(string $name, array $context = []): string
    {
        return $this->template->renderBlock($name, $context + $this->env->getGlobals());
    }

    public function displayBlock(string $name, array $context = []): void
    {
        $context += $this->env->getGlobals();
        foreach ($this->template->yieldBlock($name, $context) as $data) {
            echo $data;
        }
    }

    public function getSourceContext(): Source
    {
        return $this->template->getSourceContext();
    }

    public function getTemplateName(): string
    {
        return $this->template->getTemplateName();
    }

    /**
     * Returns the escaping strategy the template body was compiled with.
     *
     * This describes the template's own source, not its output: `autoescape`,
     * `escape`, and anything rendered by a parent, embedded, or included
     * template can use another strategy.
     *
     * @return string|false The strategy name or false when the template is not autoescaped
     */
    public function getDefaultEscapeStrategy(): string|false
    {
        return $this->template->getDefaultEscapeStrategy();
    }

    /**
     * @internal
     */
    public function isOwnedBy(Environment $env): bool
    {
        return $this->env === $env && $this->template->isOwnedBy($env);
    }

    /**
     * @internal
     */
    public function unwrap(?Environment $env = null): Template
    {
        if (null === $env) {
            trigger_deprecation('twig/twig', '3.30', 'Calling "%s()" without arguments is deprecated, pass the Twig environment instead.', __METHOD__);
        } elseif (!$this->isOwnedBy($env)) {
            throw new RuntimeError(\sprintf('A "%s" can only be used with the "%s" that created it.', self::class, Environment::class));
        }

        return $this->template;
    }
}
