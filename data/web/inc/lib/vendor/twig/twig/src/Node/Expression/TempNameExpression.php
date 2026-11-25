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
use Twig\Error\SyntaxError;

class TempNameExpression extends AbstractExpression
{
    // 4.0: re-evaluate "varargs" here once the implicit macro varargs bucket is removed
    // (see MacroNode::VARARGS_NAME); the other names map to compiled variables ($context,
    // $macros, $blocks, $this) and must stay.
    public const RESERVED_NAMES = ['varargs', 'context', 'macros', 'blocks', 'this'];

    // Prefix applied to reserved names so their compiled PHP variables cannot clash
    // with the internal ones ($varargs, $context, $macros, $blocks, $this)
    public const RESERVED_NAME_PREFIX = "\u{035C}";

    public function __construct(string|int|null $name, int $lineno)
    {
        // All names supported by ExpressionParser::parsePrimaryExpression() should be excluded
        if ($name && \in_array(strtolower($name), ['true', 'false', 'none', 'null'], true)) {
            throw new SyntaxError(\sprintf('You cannot assign a value to "%s".', $name), $lineno);
        }

        if (self::class === static::class) {
            trigger_deprecation('twig/twig', '3.15', 'The "%s" class is deprecated.', self::class);
        }

        if (null !== $name && (\is_int($name) || ctype_digit($name))) {
            $name = (int) $name;
        } elseif (\in_array($name, self::RESERVED_NAMES, true)) {
            $name = self::RESERVED_NAME_PREFIX.$name;
        }

        parent::__construct([], ['name' => $name], $lineno);
    }

    public function compile(Compiler $compiler): void
    {
        if (null === $this->getAttribute('name')) {
            $this->setAttribute('name', $compiler->getVarName());
        }

        $compiler->raw('$'.$this->getAttribute('name'));
    }
}
