<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Twig\Node;

use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Error\SyntaxError;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Expression\TempNameExpression;
use Twig\Node\Expression\Variable\LocalVariable;
use Twig\TwigMacro;

/**
 * Represents a macro node.
 *
 * This class is considered final as of Twig 3.29 and will be final in Twig
 * 4.0.
 *
 * @final since Twig 3.29
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
#[YieldReady]
class MacroNode extends Node
{
    // 4.0: "varargs" is only the implicit, deprecated extra-arguments bucket. Once the
    // implicit bucket is removed, this constant and the reserved-name guard in the
    // constructor go away (a regular argument may then be named "varargs"); only an
    // explicit "...name" variadic remains.
    public const VARARGS_NAME = 'varargs';

    /**
     * @param BodyNode        $body
     * @param ArrayExpression $arguments
     */
    public function __construct(string $name, Node $body, Node $arguments, int $lineno, ?string $variadicName = null)
    {
        if (!$body instanceof BodyNode) {
            trigger_deprecation('twig/twig', '3.12', \sprintf('Not passing a "%s" instance as the "body" argument of the "%s" constructor is deprecated ("%s" given).', BodyNode::class, static::class, $body::class));
        }

        if (!$arguments instanceof ArrayExpression) {
            trigger_deprecation('twig/twig', '3.15', \sprintf('Not passing a "%s" instance as the "arguments" argument of the "%s" constructor is deprecated ("%s" given).', ArrayExpression::class, static::class, $arguments::class));

            $args = new ArrayExpression([], $arguments->getTemplateLine());
            foreach ($arguments as $n => $default) {
                $args->addElement($default, new LocalVariable($n, $default->getTemplateLine()));
            }
            $arguments = $args;
        }

        $seen = [];
        foreach ($arguments->getKeyValuePairs() as $pair) {
            $argName = $pair['key']->getAttribute('name');
            if (TempNameExpression::RESERVED_NAME_PREFIX.self::VARARGS_NAME === $argName) {
                throw new SyntaxError(\sprintf('The argument "%s" in macro "%s" cannot be defined because the variable "%s" is reserved for arbitrary arguments.', self::VARARGS_NAME, $name, self::VARARGS_NAME), $pair['value']->getTemplateLine(), $pair['value']->getSourceContext());
            }
            if (null !== $variadicName && $variadicName === $this->stripReservedPrefix($argName)) {
                throw new SyntaxError(\sprintf('The variadic argument "%s" in macro "%s" cannot have the same name as another argument.', $variadicName, $name), $pair['value']->getTemplateLine(), $pair['value']->getSourceContext());
            }
            if (isset($seen[$argName])) {
                throw new SyntaxError(\sprintf('Argument "%s" is defined twice for macro "%s".', $this->stripReservedPrefix($argName), $name), $pair['value']->getTemplateLine(), $pair['value']->getSourceContext());
            }
            $seen[$argName] = true;
        }

        parent::__construct(['body' => $body, 'arguments' => $arguments], ['name' => $name, 'variadic_name' => $variadicName], $lineno);
    }

    public function compile(Compiler $compiler): void
    {
        // 4.0 cleanup: only an explicitly declared variadic ("...name") gets a trailing
        // "...$bucket" parameter and a context entry; a non-variadic macro must NOT emit
        // "...$varargs" anymore (so extra arguments raise an error), and the implicit
        // "varargs" context entry and VARARGS_NAME handling below go away.
        $variadicName = $this->getAttribute('variadic_name');
        if (null === $variadicName) {
            // Legacy implicit "varargs" bucket: to be removed in 4.0.
            $bucketName = self::VARARGS_NAME;
            $bucketVar = self::VARARGS_NAME;
        } else {
            $bucketName = $variadicName;
            $bucketVar = \in_array($variadicName, TempNameExpression::RESERVED_NAMES, true) ? TempNameExpression::RESERVED_NAME_PREFIX.$variadicName : $variadicName;
        }

        /** @var ArrayExpression $arguments */
        $arguments = $this->getNode('arguments');

        $compiler
            ->raw('new \\'.TwigMacro::class.'(')
            ->string($this->getAttribute('name'))
            ->raw(', function (')
        ;

        foreach ($arguments->getKeyValuePairs() as $pair) {
            $compiler
                ->subcompile($pair['key'])
                ->raw(' = ')
                ->subcompile($pair['value'])
                ->raw(', ')
            ;
        }

        $compiler
            ->raw('...$'.$bucketVar)
            ->raw("): string|Markup {\n")
            ->indent()
            ->addDebugInfo($this)
            ->write("\$macros = \$this->macros;\n")
            ->write("\$context = [\n")
            ->indent()
        ;

        foreach ($arguments->getKeyValuePairs() as $pair) {
            $var = $this->stripReservedPrefix($pair['key']->getAttribute('name'));
            $compiler
                ->write('')
                ->string($var)
                ->raw(' => ')
                ->subcompile($pair['key'])
                ->raw(",\n")
            ;
        }

        $node = new CaptureNode($this->getNode('body'), $this->getNode('body')->lineno);

        $compiler
            ->write('')
            ->string($bucketName)
            ->raw(' => ')
            ->raw('$'.$bucketVar.",\n")
            ->outdent()
            ->write("] + \$this->env->getGlobals();\n\n")
            ->write("\$blocks = [];\n\n")
            ->write('return ')
            ->subcompile($node)
            ->raw("\n")
            ->outdent()
            ->write('}, ')
        ;

        $signature = [];
        foreach ($arguments->getKeyValuePairs() as $pair) {
            $default = $pair['value'];
            $signature[$this->stripReservedPrefix($pair['key']->getAttribute('name'))] = !($default->hasAttribute('is_implicit') && $default->getAttribute('is_implicit'));
        }

        $compiler
            ->repr($signature)
            ->raw(', '.(null !== $variadicName ? 'true' : 'false').')')
        ;
    }

    private function stripReservedPrefix(string $name): string
    {
        return str_starts_with($name, TempNameExpression::RESERVED_NAME_PREFIX) ? substr($name, \strlen(TempNameExpression::RESERVED_NAME_PREFIX)) : $name;
    }
}
