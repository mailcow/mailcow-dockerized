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

    public function render(array $context = []): string
    {
        return $this->template->render($context);
    }

    public function display(array $context = [])
    {
        // using func_get_args() allows to not expose the blocks argument
        // as it should only be used by internal code
        $this->template->display($context, \func_get_args()[1] ?? []);
    }

    public function hasBlock(string $name, array $context = []): bool
    {
        return $this->template->hasBlock($name, $context);
    }

    /**
     * @return string[] An array of defined template block names
     */
    public function getBlockNames(array $context = []): array
    {
        return $this->template->getBlockNames($context);
    }

    public function renderBlock(string $name, array $context = []): string
    {
        return $this->template->renderBlock($name, $context + $this->env->getGlobals());
    }

    public function displayBlock(string $name, array $context = [])
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
     * @internal
     *
     * @return Template
     */
    public function unwrap()
    {
        return $this->template;
    }
}
