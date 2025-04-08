<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Twig\NodeVisitor;

use Twig\Environment;
use Twig\Node\BlockReferenceNode;
use Twig\Node\Expression\BlockReferenceExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FunctionExpression;
use Twig\Node\Expression\GetAttrExpression;
use Twig\Node\Expression\NameExpression;
use Twig\Node\Expression\ParentExpression;
use Twig\Node\ForNode;
use Twig\Node\IncludeNode;
use Twig\Node\Node;
use Twig\Node\PrintNode;
use Twig\Node\TextNode;

/**
 * Tries to optimize the AST.
 *
 * This visitor is always the last registered one.
 *
 * You can configure which optimizations you want to activate via the
 * optimizer mode.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @internal
 */
final class OptimizerNodeVisitor implements NodeVisitorInterface
{
    public const OPTIMIZE_ALL = -1;
    public const OPTIMIZE_NONE = 0;
    public const OPTIMIZE_FOR = 2;
    public const OPTIMIZE_RAW_FILTER = 4;
    public const OPTIMIZE_TEXT_NODES = 8;

    private $loops = [];
    private $loopsTargets = [];

    /**
     * @param int $optimizers The optimizer mode
     */
    public function __construct(
        private int $optimizers = -1,
    ) {
        if ($optimizers > (self::OPTIMIZE_FOR | self::OPTIMIZE_RAW_FILTER | self::OPTIMIZE_TEXT_NODES)) {
            throw new \InvalidArgumentException(\sprintf('Optimizer mode "%s" is not valid.', $optimizers));
        }

        if (-1 !== $optimizers && self::OPTIMIZE_RAW_FILTER === (self::OPTIMIZE_RAW_FILTER & $optimizers)) {
            trigger_deprecation('twig/twig', '3.11', 'The "Twig\NodeVisitor\OptimizerNodeVisitor::OPTIMIZE_RAW_FILTER" option is deprecated and does nothing.');
        }

        if (-1 !== $optimizers && self::OPTIMIZE_TEXT_NODES === (self::OPTIMIZE_TEXT_NODES & $optimizers)) {
            trigger_deprecation('twig/twig', '3.12', 'The "Twig\NodeVisitor\OptimizerNodeVisitor::OPTIMIZE_TEXT_NODES" option is deprecated and does nothing.');
        }
    }

    public function enterNode(Node $node, Environment $env): Node
    {
        if (self::OPTIMIZE_FOR === (self::OPTIMIZE_FOR & $this->optimizers)) {
            $this->enterOptimizeFor($node);
        }

        return $node;
    }

    public function leaveNode(Node $node, Environment $env): ?Node
    {
        if (self::OPTIMIZE_FOR === (self::OPTIMIZE_FOR & $this->optimizers)) {
            $this->leaveOptimizeFor($node);
        }

        $node = $this->optimizePrintNode($node);

        return $node;
    }

    /**
     * Optimizes print nodes.
     *
     * It replaces:
     *
     *   * "echo $this->render(Parent)Block()" with "$this->display(Parent)Block()"
     */
    private function optimizePrintNode(Node $node): Node
    {
        if (!$node instanceof PrintNode) {
            return $node;
        }

        $exprNode = $node->getNode('expr');

        if ($exprNode instanceof ConstantExpression && \is_string($exprNode->getAttribute('value'))) {
            return new TextNode($exprNode->getAttribute('value'), $exprNode->getTemplateLine());
        }

        if (
            $exprNode instanceof BlockReferenceExpression
            || $exprNode instanceof ParentExpression
        ) {
            $exprNode->setAttribute('output', true);

            return $exprNode;
        }

        return $node;
    }

    /**
     * Optimizes "for" tag by removing the "loop" variable creation whenever possible.
     */
    private function enterOptimizeFor(Node $node): void
    {
        if ($node instanceof ForNode) {
            // disable the loop variable by default
            $node->setAttribute('with_loop', false);
            array_unshift($this->loops, $node);
            array_unshift($this->loopsTargets, $node->getNode('value_target')->getAttribute('name'));
            array_unshift($this->loopsTargets, $node->getNode('key_target')->getAttribute('name'));
        } elseif (!$this->loops) {
            // we are outside a loop
            return;
        }

        // when do we need to add the loop variable back?

        // the loop variable is referenced for the current loop
        elseif ($node instanceof NameExpression && 'loop' === $node->getAttribute('name')) {
            $node->setAttribute('always_defined', true);
            $this->addLoopToCurrent();
        }

        // optimize access to loop targets
        elseif ($node instanceof NameExpression && \in_array($node->getAttribute('name'), $this->loopsTargets)) {
            $node->setAttribute('always_defined', true);
        }

        // block reference
        elseif ($node instanceof BlockReferenceNode || $node instanceof BlockReferenceExpression) {
            $this->addLoopToCurrent();
        }

        // include without the only attribute
        elseif ($node instanceof IncludeNode && !$node->getAttribute('only')) {
            $this->addLoopToAll();
        }

        // include function without the with_context=false parameter
        elseif ($node instanceof FunctionExpression
            && 'include' === $node->getAttribute('name')
            && (!$node->getNode('arguments')->hasNode('with_context')
                 || false !== $node->getNode('arguments')->getNode('with_context')->getAttribute('value')
            )
        ) {
            $this->addLoopToAll();
        }

        // the loop variable is referenced via an attribute
        elseif ($node instanceof GetAttrExpression
            && (!$node->getNode('attribute') instanceof ConstantExpression
                || 'parent' === $node->getNode('attribute')->getAttribute('value')
            )
            && (true === $this->loops[0]->getAttribute('with_loop')
             || ($node->getNode('node') instanceof NameExpression
                 && 'loop' === $node->getNode('node')->getAttribute('name')
             )
            )
        ) {
            $this->addLoopToAll();
        }
    }

    /**
     * Optimizes "for" tag by removing the "loop" variable creation whenever possible.
     */
    private function leaveOptimizeFor(Node $node): void
    {
        if ($node instanceof ForNode) {
            array_shift($this->loops);
            array_shift($this->loopsTargets);
            array_shift($this->loopsTargets);
        }
    }

    private function addLoopToCurrent(): void
    {
        $this->loops[0]->setAttribute('with_loop', true);
    }

    private function addLoopToAll(): void
    {
        foreach ($this->loops as $loop) {
            $loop->setAttribute('with_loop', true);
        }
    }

    public function getPriority(): int
    {
        return 255;
    }
}
