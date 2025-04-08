<?php

namespace Twig\Node\Expression\FunctionNode;

use Twig\Compiler;
use Twig\Error\SyntaxError;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FunctionExpression;

class EnumCasesFunction extends FunctionExpression
{
    public function compile(Compiler $compiler): void
    {
        $arguments = $this->getNode('arguments');
        if ($arguments->hasNode('enum')) {
            $firstArgument = $arguments->getNode('enum');
        } elseif ($arguments->hasNode('0')) {
            $firstArgument = $arguments->getNode('0');
        } else {
            $firstArgument = null;
        }

        if (!$firstArgument instanceof ConstantExpression || 1 !== \count($arguments)) {
            parent::compile($compiler);

            return;
        }

        $value = $firstArgument->getAttribute('value');

        if (!\is_string($value)) {
            throw new SyntaxError('The first argument of the "enum_cases" function must be a string.', $this->getTemplateLine(), $this->getSourceContext());
        }

        if (!enum_exists($value)) {
            throw new SyntaxError(\sprintf('The first argument of the "enum_cases" function must be the name of an enum, "%s" given.', $value), $this->getTemplateLine(), $this->getSourceContext());
        }

        $compiler->raw(\sprintf('%s::cases()', $value));
    }
}
