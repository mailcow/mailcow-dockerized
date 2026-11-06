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

use Twig\TemplateWrapper;

/**
 * Renders untrusted templates in a dedicated, always-sandboxed environment.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface SandboxInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function render(string $name, array $context = []): string;

    /**
     * @param array<string, mixed> $context
     */
    public function display(string $name, array $context = []): void;

    /**
     * @param array<string, mixed> $context
     *
     * @return iterable<scalar|\Stringable|null>
     */
    public function stream(string $name, array $context = []): iterable;

    /**
     * @param array<string, mixed> $context
     */
    public function renderBlock(string $name, string $block, array $context = []): string;

    /**
     * @param array<string, mixed> $context
     */
    public function displayBlock(string $name, string $block, array $context = []): void;

    /**
     * @param array<string, mixed> $context
     *
     * @return iterable<scalar|\Stringable|null>
     */
    public function streamBlock(string $name, string $block, array $context = []): iterable;

    /**
     * Creates a sandboxed template from source.
     *
     * The template can only reference templates available in the environment loader.
     */
    public function createTemplate(string $template, ?string $name = null): TemplateWrapper;
}
