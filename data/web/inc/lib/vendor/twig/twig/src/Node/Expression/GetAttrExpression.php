<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 * (c) Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Twig\Node\Expression;

use Twig\Compiler;
use Twig\Extension\SandboxExtension;
use Twig\Node\CoercesChildrenToStringInterface;
use Twig\Node\Expression\Variable\ContextVariable;
use Twig\Template;

class GetAttrExpression extends AbstractExpression implements SupportDefinedTestInterface, CoercesChildrenToStringInterface
{
    use SupportDefinedTestDeprecationTrait;
    use SupportDefinedTestTrait;

    /**
     * @param ArrayExpression|NameExpression|null $arguments
     */
    public function __construct(AbstractExpression $node, AbstractExpression $attribute, ?AbstractExpression $arguments, string $type, int $lineno, bool $nullSafe = false)
    {
        $nodes = ['node' => $node, 'attribute' => $attribute];
        if (null !== $arguments) {
            $nodes['arguments'] = $arguments;
        }

        if ($arguments && !$arguments instanceof ArrayExpression && !$arguments instanceof ContextVariable) {
            trigger_deprecation('twig/twig', '3.15', \sprintf('Not passing a "%s" instance as the "arguments" argument of the "%s" constructor is deprecated ("%s" given).', ArrayExpression::class, static::class, $arguments::class));
        }

        parent::__construct($nodes, ['type' => $type, 'ignore_strict_check' => false, 'optimizable' => !$nullSafe, 'null_safe' => $nullSafe, 'is_short_circuited' => false, 'var_name' => null], $lineno);
    }

    public function enableDefinedTest(): void
    {
        $this->definedTest = true;
        $this->changeIgnoreStrictCheck($this);
    }

    public function compile(Compiler $compiler): void
    {
        $env = $compiler->getEnvironment();
        $arrayAccessSandbox = false;
        $nullSafe = $this->getAttribute('null_safe');

        // optimize array calls
        if (
            $this->getAttribute('optimizable')
            && (!$env->isStrictVariables() || $this->getAttribute('ignore_strict_check'))
            && !$this->definedTest
            && Template::ARRAY_CALL === $this->getAttribute('type')
        ) {
            $var = '$'.$compiler->getVarName();
            $compiler
                ->raw('(('.$var.' = ')
                ->subcompile($this->getNode('node'))
                ->raw(') && is_array(')
                ->raw($var)
            ;

            if (!$env->hasExtension(SandboxExtension::class)) {
                $compiler
                    ->raw(') || ')
                    ->raw($var)
                    ->raw(' instanceof ArrayAccess ? (')
                    ->raw($var)
                    ->raw('[')
                ;
                $this->compileArrayKey($compiler, $var);
                $compiler->raw('] ?? null) : null)');

                return;
            }

            $arrayAccessSandbox = true;

            $compiler
                ->raw(') || ')
                ->raw($var)
                ->raw(' instanceof ArrayAccess && in_array(')
                ->raw($var.'::class')
                ->raw(', CoreExtension::ARRAY_LIKE_CLASSES, true) ? (')
                ->raw($var)
                ->raw('[')
            ;
            $this->compileArrayKey($compiler, $var);
            $compiler->raw('] ?? null) : ');
        }

        if ($this->getAttribute('ignore_strict_check')) {
            $this->getNode('node')->setAttribute('ignore_strict_check', true);
        }

        if (null === $nullSafeNode = $nullSafe ? $this : null) {
            $node = $this->getNode('node');
            while ($node instanceof self) {
                if ($node->getAttribute('null_safe')) {
                    $nullSafeNode = $node;
                    break;
                }
                $node = $node->getNode('node');
            }
        }

        $isShortCircuited = false;
        if (null !== $nullSafeNode && !$nullSafeNode->isShortCircuited()) {
            $compiler
                ->raw('((null === ('.$nullSafeNode->getVarName($compiler).' = ')
                ->subcompile($nullSafeNode->getNode('node'))
                ->raw(')) ? null : ');

            $nullSafeNode->markAsShortCircuited();
            $isShortCircuited = true;
        }

        $compiler->raw('CoreExtension::getAttribute($this->env, $this->source, ');

        if ($nullSafe) {
            $compiler->raw($this->getVarName($compiler));
        } else {
            $compiler->subcompile($this->getNode('node'));
        }

        $compiler
            ->raw(', ')
            ->subcompile($this->getNode('attribute'))
        ;

        if ($this->hasNode('arguments')) {
            $compiler->raw(', ')->subcompile($this->getNode('arguments'));
        } else {
            $compiler->raw(', []');
        }

        $compiler->raw(', ')
            ->repr($this->getAttribute('type'))
            ->raw(', ')->repr($this->definedTest)
            ->raw(', ')->repr($this->getAttribute('ignore_strict_check'))
            ->raw(', ')->repr($env->hasExtension(SandboxExtension::class))
            ->raw(', ')->repr($this->getNode('node')->getTemplateLine())
            ->raw(')')
        ;

        if ($arrayAccessSandbox) {
            $compiler->raw(')');
        }

        if ($isShortCircuited) {
            $compiler->raw(')');
        }
    }

    public function getStringCoercedChildNames(): array
    {
        $names = [];

        // the host PHP method may coerce any argument to string
        if ($this->hasNode('arguments')) {
            $names[] = 'arguments';
        }

        // compileArrayKey() may coerce a Stringable key; expose it so the sandbox checks __toString()
        if (Template::ARRAY_CALL === $this->getAttribute('type')) {
            $names[] = 'attribute';
        }

        return $names;
    }

    /**
     * Normalizes a Stringable array key so optimized access matches getAttribute():
     * arrays and known string-keyed ArrayAccess implementations receive a string,
     * while object-key implementations receive the object unchanged.
     */
    private function compileArrayKey(Compiler $compiler, string $var): void
    {
        $attribute = $this->getNode('attribute');

        if ($attribute instanceof ConstantExpression) {
            $compiler->subcompile($attribute);

            return;
        }

        $key = '$'.$compiler->getVarName();
        $compiler
            ->raw('(('.$key.' = ')
            ->subcompile($attribute)
            ->raw(') instanceof \Stringable && (is_array('.$var.') || '.$var.' instanceof \ArrayObject || '.$var.' instanceof \ArrayIterator) ? (string) '.$key.' : '.$key.')')
        ;
    }

    private function changeIgnoreStrictCheck(self $node): void
    {
        $node->setAttribute('optimizable', false);
        $node->setAttribute('ignore_strict_check', true);

        if ($node->getNode('node') instanceof self) {
            $this->changeIgnoreStrictCheck($node->getNode('node'));
        }
    }

    private function markAsShortCircuited(): void
    {
        $this->setAttribute('is_short_circuited', true);
    }

    private function isShortCircuited(): bool
    {
        return $this->getAttribute('is_short_circuited');
    }

    private function getVarName(Compiler $compiler): string
    {
        if (null === $this->getAttribute('var_name')) {
            $this->setAttribute('var_name', $compiler->getVarName());
        }

        return '$'.$this->getAttribute('var_name');
    }
}
