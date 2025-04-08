<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Twig\Node\Expression\Filter;

use Twig\Attribute\FirstClassTwigCallableReady;
use Twig\Compiler;
use Twig\Extension\CoreExtension;
use Twig\Node\Expression\ConditionalExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FilterExpression;
use Twig\Node\Expression\GetAttrExpression;
use Twig\Node\Expression\NameExpression;
use Twig\Node\Expression\Test\DefinedTest;
use Twig\Node\Node;
use Twig\TwigFilter;
use Twig\TwigTest;

/**
 * Returns the value or the default value when it is undefined or empty.
 *
 *  {{ var.foo|default('foo item on var is not defined') }}
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class DefaultFilter extends FilterExpression
{
    #[FirstClassTwigCallableReady]
    public function __construct(Node $node, TwigFilter|ConstantExpression $filter, Node $arguments, int $lineno)
    {
        if ($filter instanceof TwigFilter) {
            $name = $filter->getName();
            $default = new FilterExpression($node, $filter, $arguments, $node->getTemplateLine());
        } else {
            $name = $filter->getAttribute('value');
            $default = new FilterExpression($node, new TwigFilter('default', [CoreExtension::class, 'default']), $arguments, $node->getTemplateLine());
        }

        if ('default' === $name && ($node instanceof NameExpression || $node instanceof GetAttrExpression)) {
            $test = new DefinedTest(clone $node, new TwigTest('defined'), new Node(), $node->getTemplateLine());
            $false = \count($arguments) ? $arguments->getNode('0') : new ConstantExpression('', $node->getTemplateLine());

            $node = new ConditionalExpression($test, $default, $false, $node->getTemplateLine());
        } else {
            $node = $default;
        }

        parent::__construct($node, $filter, $arguments, $lineno);
    }

    public function compile(Compiler $compiler): void
    {
        $compiler->subcompile($this->getNode('node'));
    }
}
