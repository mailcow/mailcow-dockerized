<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Twig\Node\Expression\Binary;

use Twig\Compiler;
use Twig\Error\SyntaxError;
use Twig\Extension\SandboxExtension;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Expression\Variable\AssignContextVariable;
use Twig\Node\Node;

/**
 * @internal
 */
class ObjectDestructuringSetBinary extends AbstractBinary
{
    /** @var list<array{property: string, variable: string}> */
    private array $mappings = [];

    /**
     * @param ArrayExpression    $left  The array expression containing object/mapping destructuring properties
     * @param AbstractExpression $right The expression providing values for assignment
     */
    public function __construct(Node $left, Node $right, int $lineno)
    {
        if (!$left instanceof ArrayExpression) {
            throw new \LogicException('Left side must be ArrayExpression for object/mapping destructuring.');
        }
        foreach ($left->getKeyValuePairs() as $pair) {
            if (!$pair['value'] instanceof AssignContextVariable) {
                throw new SyntaxError(\sprintf('Cannot assign to "%s", only variables can be assigned in object/mapping destructuring.', $pair['value']::class), $lineno);
            }

            $this->mappings[] = [
                'property' => $pair['key']->getAttribute('value'),
                'variable' => $pair['value']->getAttribute('name'),
            ];
        }

        parent::__construct($left, $right, $lineno);
    }

    public function compile(Compiler $compiler): void
    {
        $compiler->addDebugInfo($this);
        $var = '$'.$compiler->getVarName();
        $compiler->raw('[[');
        foreach ($this->mappings as $i => $mapping) {
            if ($i) {
                $compiler->raw(', ');
            }
            $compiler->raw('$context[')->repr($mapping['variable'])->raw(']');
        }
        $compiler->raw('] = [');
        foreach ($this->mappings as $i => $mapping) {
            if ($i) {
                $compiler->raw(', ');
            }
            $compiler->raw('CoreExtension::getAttribute($this->env, $this->source, ');
            if (0 === $i) {
                $compiler->raw('('.$var.' = ')->subcompile($this->getNode('right'))->raw(')');
            } else {
                $compiler->raw($var);
            }
            $compiler->raw(', ')->repr($mapping['property'])->raw(', [], \\Twig\\Template::ANY_CALL, false, false, ')->repr($compiler->getEnvironment()->hasExtension(SandboxExtension::class))->raw(', ')->repr($this->getNode('right')->getTemplateLine())->raw(')');
        }
        $compiler->raw('], '.$var.' = null][0]');
    }

    public function operator(Compiler $compiler): Compiler
    {
        return $compiler->raw('=');
    }
}
