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

use Twig\Compiler;
use Twig\Node\CoercesChildrenToStringInterface;
use Twig\Node\Expression\Variable\MacroVariable;

/**
 * Represents a macro call node.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class MacroReferenceExpression extends AbstractExpression implements SupportDefinedTestInterface, CoercesChildrenToStringInterface
{
    use SupportDefinedTestDeprecationTrait;
    use SupportDefinedTestTrait;

    private bool $hasCallParentheses = true;

    /**
     * @param string|AbstractExpression $name The bare macro name (a static identifier) or, for a dynamic
     *                                        call, an expression resolving to the macro name
     */
    public function __construct(MacroVariable $template, string|AbstractExpression $name, AbstractExpression $arguments, int $lineno)
    {
        $nodes = ['template' => $template, 'arguments' => $arguments];
        $attributes = ['name' => null];

        if (\is_string($name)) {
            $attributes['name'] = $name;
        } else {
            $nodes['name'] = $name;
        }

        parent::__construct($nodes, $attributes, $lineno);
    }

    /**
     * @internal
     */
    public function setHasCallParentheses(bool $hasCallParentheses): void
    {
        $this->hasCallParentheses = $hasCallParentheses;
    }

    /**
     * @internal
     */
    public function hasCallParentheses(): bool
    {
        return $this->hasCallParentheses;
    }

    public function __clone()
    {
        // The template node must not be deep-cloned because its name is
        // lazily generated during compilation and must stay in sync with
        // the AssignMacroVariable that populates the $macros array.
        $template = $this->nodes['template'];
        parent::__clone();
        $this->nodes['template'] = $template;
    }

    public function compile(Compiler $compiler): void
    {
        $compiler->subcompile($this->getNode('template'));

        if ($this->definedTest) {
            $compiler->raw('->has(');
            $this->compileName($compiler);
            $compiler->raw(', $context)');

            return;
        }

        $compiler->raw('->call(');
        $this->compileName($compiler);
        $compiler
            ->raw(', ')
            ->subcompile($this->getNode('arguments'))
            ->raw(', $context, ')
            ->repr($this->getTemplateLine())
            ->raw(', $this->source)')
        ;
    }

    public function getStringCoercedChildNames(): array
    {
        // Dynamic macro names are string-coerced at runtime.
        return $this->hasNode('name') ? ['name'] : [];
    }

    private function compileName(Compiler $compiler): void
    {
        // A dynamic macro name is resolved at runtime from a context value and
        // string-coerced before the registry lookup.
        if ($this->hasNode('name')) {
            $compiler->raw('(string) ')->subcompile($this->getNode('name'));
        } else {
            $compiler->repr($this->getAttribute('name'));
        }
    }
}
