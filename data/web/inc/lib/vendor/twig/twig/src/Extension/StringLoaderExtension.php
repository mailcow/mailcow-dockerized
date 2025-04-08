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

use Twig\Environment;
use Twig\TemplateWrapper;
use Twig\TwigFunction;

final class StringLoaderExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('template_from_string', [self::class, 'templateFromString'], ['needs_environment' => true]),
        ];
    }

    /**
     * Loads a template from a string.
     *
     *     {{ include(template_from_string("Hello {{ name }}")) }}
     *
     * @param string|null $name An optional name of the template to be used in error messages
     *
     * @internal
     */
    public static function templateFromString(Environment $env, string|\Stringable $template, ?string $name = null): TemplateWrapper
    {
        return $env->createTemplate((string) $template, $name);
    }
}
