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
use Twig\Node\Expression\MacroReferenceExpression;

/**
 * @internal
 */
final class MacroNamespace
{
    /**
     * @param array<string, TwigMacro> $macros
     */
    public function __construct(
        private Template $template,
        private array $macros,
    ) {
    }

    public function has(string $name, array $context): bool
    {
        if (null !== $declaration = $this->findDeclaredName($name, $context)) {
            [$declaredName, $templateName] = $declaration;
            if ($declaredName !== $name) {
                trigger_deprecation('twig/twig', '3.29', 'Testing whether the macro "%s" (defined in template "%s") is defined as "%s" is deprecated; macro names will be case-sensitive in Twig 4.0 and this test will return false.', $declaredName, $templateName, $name);
            }

            return true;
        }

        if (!str_starts_with($name, 'macro_') || null === $this->findDeclaredName($bareName = substr($name, \strlen('macro_')), $context)) {
            return false;
        }

        trigger_deprecation('twig/twig', '3.29', 'Testing whether the macro "%s" is defined via the "macro_"-prefixed name "%s" is deprecated; pass the bare macro name to "%s" instead.', $bareName, $name, MacroReferenceExpression::class);

        return true;
    }

    /**
     * @param array<int|string, mixed> $arguments
     */
    public function call(string $name, array $arguments, array $context, int $line, Source $source): string|Markup
    {
        if (null !== $macro = $this->getDeclared($name)) {
            return $macro->callLegacy($arguments, $source, $line);
        }

        if (null === $macro = $this->resolve($name, $context)) {
            if (!str_starts_with($name, 'macro_') || null === $macro = $this->resolve($bareName = substr($name, \strlen('macro_')), $context)) {
                throw new RuntimeError(\sprintf('Macro "%s" is not defined in template "%s".', $name, $this->template->getTemplateName()), $line, $source);
            }

            trigger_deprecation('twig/twig', '3.29', 'Calling the macro "%s" via the "macro_"-prefixed name "%s" is deprecated; pass the bare macro name to "%s" instead.', $bareName, $name, MacroReferenceExpression::class);
        }

        return $macro->callLegacy($arguments, $source, $line);
    }

    /**
     * @return array{string, string}|null
     */
    private function findDeclaredName(string $name, array $context): ?array
    {
        $namespace = $this;
        while (true) {
            if (isset($namespace->macros[$name])) {
                return [$name, $namespace->template->getTemplateName()];
            }
            foreach ($namespace->macros as $declaredName => $macro) {
                if (0 === strcasecmp($declaredName, $name)) {
                    return [$declaredName, $namespace->template->getTemplateName()];
                }
            }

            if (null === $namespace = $namespace->getParent($context)) {
                return null;
            }
        }
    }

    private function getDeclared(string $name): ?TwigMacro
    {
        if (isset($this->macros[$name])) {
            $this->template->ensureSecurityChecked();

            return $this->macros[$name];
        }

        foreach ($this->macros as $declaredName => $macro) {
            if (0 === strcasecmp($declaredName, $name)) {
                trigger_deprecation('twig/twig', '3.29', 'Calling the macro "%s" (defined in template "%s") as "%s" is deprecated; macro names will be case-sensitive in Twig 4.0.', $declaredName, $this->template->getTemplateName(), $name);

                $this->template->ensureSecurityChecked();

                return $macro;
            }
        }

        return null;
    }

    private function resolve(string $name, array $context): ?TwigMacro
    {
        $namespace = $this;
        while (true) {
            if (null !== $macro = $namespace->getDeclared($name)) {
                return $macro;
            }

            if (null === $namespace = $namespace->getParent($context)) {
                return null;
            }
        }
    }

    private function getParent(array $context): ?self
    {
        if (!$parent = $this->template->getParent($context)) {
            return null;
        }

        return $parent->unwrap()->getMacroNamespace();
    }
}
