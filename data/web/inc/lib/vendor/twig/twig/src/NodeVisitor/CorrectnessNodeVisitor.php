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
use Twig\Error\SyntaxError;
use Twig\Node\BlockNode;
use Twig\Node\BlockReferenceNode;
use Twig\Node\ConfigNode;
use Twig\Node\Expression\MacroReferenceExpression;
use Twig\Node\MacroNode;
use Twig\Node\ModuleNode;
use Twig\Node\Node;
use Twig\Node\NodeCaptureInterface;
use Twig\Node\NodeOutputInterface;
use Twig\Node\Nodes;
use Twig\Node\TextNode;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @internal
 */
final class CorrectnessNodeVisitor implements NodeVisitorInterface
{
    private ?\WeakMap $rootNodes = null;
    /**
     * @var array<string, true>
     */
    private array $reportedMacroCallSites = [];
    /**
     * Stack of the output-wrapping tags ("if", "for", "set", ...) currently open;
     * the top one is the nearest tag a "block" definition would be nested under.
     *
     * @var list<Node>
     */
    private array $tagStack = [];
    private bool $hasParent = false;
    private int $blockDepth = 0;
    private int $macroDepth = 0;
    private int $capturingNodeDepth = 0;
    private bool $hasExtends = false;

    public function enterNode(Node $node, Environment $env): Node
    {
        if ($node instanceof ModuleNode) {
            $this->enterModule($node);

            return $node;
        }

        $this->enterScope($node);

        if ($node instanceof ConfigNode) {
            $this->checkConfigTag($node);
        }

        if ($node instanceof MacroReferenceExpression) {
            $this->checkMacroCallParentheses($node);
        }

        if ($node instanceof BlockReferenceNode) {
            $this->checkBlockDefinitionNesting($node);
        }

        return $node;
    }

    public function leaveNode(Node $node, Environment $env): Node
    {
        if ($node instanceof ModuleNode) {
            $this->resetState();

            return $node;
        }

        $this->leaveScope($node);

        return $node;
    }

    public function getPriority(): int
    {
        return -255;
    }

    private function enterModule(ModuleNode $node): void
    {
        $this->resetState();
        $this->rootNodes = new \WeakMap();
        $this->hasParent = $node->hasNode('parent');

        foreach ($this->getRootNodes($node) as $n) {
            if ($this->hasParent && !$this->isEmptyOutputNode($n)) {
                throw new SyntaxError('A template that extends another one cannot include content outside Twig blocks. Did you forget to put the content inside a {% block %} tag?', $n->getTemplateLine(), $n->getSourceContext());
            }
            $this->rootNodes[$n] = true;
        }
    }

    private function resetState(): void
    {
        $this->rootNodes = null;
        $this->reportedMacroCallSites = [];
        $this->tagStack = [];
        $this->hasParent = false;
        $this->blockDepth = 0;
        $this->macroDepth = 0;
        $this->capturingNodeDepth = 0;
        $this->hasExtends = false;
    }

    /**
     * @return iterable<Node>
     */
    private function getRootNodes(ModuleNode $node): iterable
    {
        $body = $node->getNode('body')->getNode('0');

        // Parser::subparse() does not wrap the parsed nodes when there is only one,
        // so $body can be a single "real" node instead of a Nodes container; in that
        // case it is the only root node and must not be iterated over its children.
        return $body instanceof Nodes || Node::class === $body::class ? $body : [$body];
    }

    private function enterScope(Node $node): void
    {
        if ($node instanceof NodeCaptureInterface) {
            ++$this->capturingNodeDepth;
        }

        if ($node instanceof BlockNode) {
            ++$this->blockDepth;
        } elseif ($node instanceof MacroNode) {
            ++$this->macroDepth;
        } elseif ($node->getNodeTag() && !$node instanceof BlockReferenceNode) {
            $this->tagStack[] = $node;
        }
    }

    private function leaveScope(Node $node): void
    {
        if ($node instanceof NodeCaptureInterface) {
            --$this->capturingNodeDepth;
        }

        if ($node instanceof BlockNode) {
            --$this->blockDepth;
        } elseif ($node instanceof MacroNode) {
            --$this->macroDepth;
        } elseif ($node->getNodeTag() && !$node instanceof BlockReferenceNode) {
            array_pop($this->tagStack);
        }
    }

    private function checkMacroCallParentheses(MacroReferenceExpression $node): void
    {
        if ($node->hasCallParentheses() || $node->isDefinedTestEnabled()) {
            return;
        }

        $sourceName = $node->getSourceContext()->getName();
        $line = $node->getTemplateLine();
        // A dynamic macro name is only known at runtime.
        $name = $node->getAttribute('name');

        // A single call site can be visited more than once: "??" reuses its left node,
        // while "?:" and the "default" filter put a clone of it in the compiled tree.
        $callSite = $sourceName."\0".$line."\0".$name;
        if (isset($this->reportedMacroCallSites[$callSite])) {
            return;
        }
        $this->reportedMacroCallSites[$callSite] = true;

        if (null === $name) {
            trigger_deprecation('twig/twig', '3.29', 'Omitting parentheses when calling a macro is deprecated and will throw a SyntaxError in Twig 4.0; add parentheses after the macro name in "%s" at line %d.', $sourceName, $line);
        } else {
            trigger_deprecation('twig/twig', '3.29', 'Omitting parentheses when calling the macro "%s" is deprecated and will throw a SyntaxError in Twig 4.0; add parentheses after the macro name in "%s" at line %d.', $name, $sourceName, $line);
        }
    }

    private function checkConfigTag(ConfigNode $node): void
    {
        if ('extends' === $node->getNodeTag()) {
            $this->checkExtendsTag($node);
        }

        if (!isset($this->rootNodes[$node])) {
            trigger_deprecation('twig/twig', '3.28', 'Using the "%s" tag outside the root of a template is deprecated in %s at line %d.', $node->getNodeTag(), $node->getSourceContext()->getName(), $node->getTemplateLine());
        }
    }

    private function checkExtendsTag(ConfigNode $node): void
    {
        // "extends" inside a "block" or a "macro" has always been a hard error; keep it
        if ($this->blockDepth) {
            throw new SyntaxError('Cannot use "extend" in a block.', $node->getTemplateLine(), $node->getSourceContext());
        }
        if ($this->macroDepth) {
            throw new SyntaxError('Cannot use "extend" in a macro.', $node->getTemplateLine(), $node->getSourceContext());
        }
        if ($this->hasExtends) {
            throw new SyntaxError('Multiple extends tags are forbidden.', $node->getTemplateLine(), $node->getSourceContext());
        }

        $this->hasExtends = true;
    }

    private function checkBlockDefinitionNesting(BlockReferenceNode $node): void
    {
        // A "block" definition nested under an output-wrapping tag is registered globally
        // regardless of that tag, so the nesting is misleading. This only matters at the root
        // of a child template's body: once inside a block, a macro, an output capture, or in
        // a standalone template, the block is rendered in place and behaves like any other.
        if (!$this->hasParent || $this->blockDepth || $this->macroDepth || $this->capturingNodeDepth || !$this->tagStack) {
            return;
        }

        $tag = $this->tagStack[array_key_last($this->tagStack)];
        throw new SyntaxError(\sprintf('A "block" tag cannot be under a "%s" tag (line %d).', $tag->getNodeTag(), $tag->getTemplateLine()), $node->getTemplateLine(), $node->getSourceContext());
    }

    /**
     * Returns true if the node never outputs anything or if the output is empty.
     */
    private function isEmptyOutputNode(Node $node): bool
    {
        if ($node instanceof NodeCaptureInterface) {
            // a "block" tag in such a node will serve as a block definition AND be displayed in place as well
            return true;
        }

        // Can the text be considered "empty" (only whitespace)?
        if ($node instanceof TextNode) {
            return $node->isBlank();
        }

        if (!$node instanceof BlockReferenceNode && $node instanceof NodeOutputInterface) {
            return false;
        }

        foreach ($node as $n) {
            if (!$this->isEmptyOutputNode($n)) {
                return false;
            }
        }

        return true;
    }
}
