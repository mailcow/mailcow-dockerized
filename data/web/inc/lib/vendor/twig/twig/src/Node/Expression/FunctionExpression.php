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

use Twig\Attribute\FirstClassTwigCallableReady;
use Twig\Compiler;
use Twig\Node\CoercesChildrenToStringInterface;
use Twig\Node\NameDeprecation;
use Twig\Node\Node;
use Twig\TwigFunction;

class FunctionExpression extends CallExpression implements SupportDefinedTestInterface, CoercesChildrenToStringInterface
{
    use SupportDefinedTestDeprecationTrait;
    use SupportDefinedTestTrait;

    #[FirstClassTwigCallableReady]
    public function __construct(TwigFunction|string $function, Node $arguments, int $lineno)
    {
        if ($function instanceof TwigFunction) {
            $name = $function->getName();
        } else {
            $name = $function;
            trigger_deprecation('twig/twig', '3.12', 'Not passing an instance of "TwigFunction" when creating a "%s" function of type "%s" is deprecated.', $name, static::class);
        }

        parent::__construct(['arguments' => $arguments], ['name' => $name, 'type' => 'function'], $lineno);

        if ($function instanceof TwigFunction) {
            $this->setAttribute('twig_callable', $function);
        }

        $this->deprecateAttribute('needs_charset', new NameDeprecation('twig/twig', '3.12'));
        $this->deprecateAttribute('needs_environment', new NameDeprecation('twig/twig', '3.12'));
        $this->deprecateAttribute('needs_context', new NameDeprecation('twig/twig', '3.12'));
        $this->deprecateAttribute('arguments', new NameDeprecation('twig/twig', '3.12'));
        $this->deprecateAttribute('callable', new NameDeprecation('twig/twig', '3.12'));
        $this->deprecateAttribute('is_variadic', new NameDeprecation('twig/twig', '3.12'));
        $this->deprecateAttribute('dynamic_name', new NameDeprecation('twig/twig', '3.12'));
    }

    public function enableDefinedTest(): void
    {
        if ('constant' === $this->getAttribute('name')) {
            $this->definedTest = true;
        }
    }

    /**
     * @return void
     */
    public function compile(Compiler $compiler)
    {
        $name = $this->getAttribute('name');
        if ($this->hasAttribute('twig_callable')) {
            $name = $this->getAttribute('twig_callable')->getName();
            if ($name !== $this->getAttribute('name')) {
                trigger_deprecation('twig/twig', '3.12', 'Changing the value of a "function" node in a NodeVisitor class is not supported anymore.');
                $this->removeAttribute('twig_callable');
            }
        }

        if (!$this->hasAttribute('twig_callable')) {
            $this->setAttribute('twig_callable', $compiler->getEnvironment()->getFunction($name));
        }

        if ('constant' === $name && $this->isDefinedTestEnabled()) {
            $this->getNode('arguments')->setNode('checkDefined', new ConstantExpression(true, $this->getTemplateLine()));
        }

        $this->compileCallable($compiler);
    }

    public function getStringCoercedChildNames(): array
    {
        // a function may coerce its arguments to string (the host PHP code is opaque to Twig)
        return ['arguments'];
    }
}
