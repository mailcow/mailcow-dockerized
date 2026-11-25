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

namespace Twig;

use Twig\Error\SyntaxError;
use Twig\ExpressionParser\ExpressionParserInterface;
use Twig\ExpressionParser\ExpressionParsers;
use Twig\ExpressionParser\ExpressionParserType;
use Twig\ExpressionParser\InfixExpressionParserInterface;
use Twig\ExpressionParser\Prefix\LiteralExpressionParser;
use Twig\ExpressionParser\PrefixExpressionParserInterface;
use Twig\Node\BlockNode;
use Twig\Node\BlockReferenceNode;
use Twig\Node\BodyNode;
use Twig\Node\EmptyNode;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Expression\Variable\AssignMacroVariable;
use Twig\Node\Expression\Variable\MacroVariable;
use Twig\Node\MacroNode;
use Twig\Node\MacrosNode;
use Twig\Node\ModuleNode;
use Twig\Node\Node;
use Twig\Node\NodeDocumentation;
use Twig\Node\Nodes;
use Twig\Node\PrintNode;
use Twig\Node\TextNode;
use Twig\TokenParser\TokenParserInterface;
use Twig\Util\ReflectionCallable;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Parser
{
    private $stack = [];
    private ?\WeakMap $expressionRefs = null;
    private $stream;
    private $parent;
    private $visitors;
    private $expressionParser;
    private $blocks;
    private $blockStack;
    /** @var list<Node|null> */
    private array $documentationTargets = [];
    private $macros;
    private $importedSymbols;
    private $traits;
    private $embeddedTemplates = [];
    private int $lastEmbedIndex = 0;
    private $varNameSalt = 0;
    private $ignoreUnknownTwigCallables = false;
    private ExpressionParsers $parsers;

    public function __construct(
        private Environment $env,
    ) {
        $this->parsers = $env->getExpressionParsers();
    }

    public function getEnvironment(): Environment
    {
        return $this->env;
    }

    public function getVarName(): string
    {
        trigger_deprecation('twig/twig', '3.15', 'The "%s()" method is deprecated.', __METHOD__);

        return \sprintf('__internal_parse_%d', $this->varNameSalt++);
    }

    /**
     * @throws SyntaxError
     */
    public function parse(TokenStream $stream, $test = null, bool $dropNeedle = false): ModuleNode
    {
        // reset on root parse() calls only, so the counter spans nested/reentrant parses
        if (!$this->stack) {
            $this->lastEmbedIndex = 0;
        }

        $vars = get_object_vars($this);
        unset($vars['stack'], $vars['env'], $vars['handlers'], $vars['visitors'], $vars['expressionParser'], $vars['reservedMacroNames'], $vars['lastEmbedIndex'], $vars['varNameSalt']);
        $this->stack[] = $vars;

        // node visitors
        if (null === $this->visitors) {
            $this->visitors = $this->env->getNodeVisitors();
        }

        $this->stream = $stream;
        $this->parent = null;
        $this->blocks = [];
        $this->documentationTargets = [];
        $this->macros = [];
        $this->traits = [];
        $this->blockStack = [];
        $this->importedSymbols = [[]];
        $this->embeddedTemplates = [];
        $this->expressionRefs = new \WeakMap();

        try {
            $body = $this->subparse($test, $dropNeedle);
        } catch (SyntaxError $e) {
            if (!$e->getSourceContext()) {
                $e->setSourceContext($this->stream->getSourceContext());
            }

            if (!$e->getTemplateLine()) {
                $e->setTemplateLine($this->getCurrentToken()->getLine());
            }

            throw $e;
        } finally {
            $this->expressionRefs = null;
        }

        if ($this->parent) {
            $body = $this->cleanupBodyForChildTemplates($body);
        }

        $node = new ModuleNode(
            new BodyNode([$body]),
            $this->parent,
            $this->blocks ? new Nodes($this->blocks) : new EmptyNode(),
            new MacrosNode($this->macros),
            $this->traits ? new Nodes($this->traits) : new EmptyNode(),
            $this->embeddedTemplates ? new Nodes($this->embeddedTemplates) : new EmptyNode(),
            $stream->getSourceContext(),
        );

        $traverser = new NodeTraverser($this->env, $this->visitors);

        /**
         * @var ModuleNode $node
         */
        $node = $traverser->traverse($node);

        // restore previous stack so previous parse() call can resume working
        foreach (array_pop($this->stack) as $key => $val) {
            $this->$key = $val;
        }

        return $node;
    }

    public function shouldIgnoreUnknownTwigCallables(): bool
    {
        return $this->ignoreUnknownTwigCallables;
    }

    public function subparseIgnoreUnknownTwigCallables($test, bool $dropNeedle = false): void
    {
        $previous = $this->ignoreUnknownTwigCallables;
        $this->ignoreUnknownTwigCallables = true;
        try {
            $this->subparse($test, $dropNeedle);
        } finally {
            $this->ignoreUnknownTwigCallables = $previous;
        }
    }

    /**
     * @throws SyntaxError
     */
    public function subparse($test, bool $dropNeedle = false): Node
    {
        $lineno = $this->getCurrentToken()->getLine();
        $rv = [];
        while (!$this->stream->isEOF()) {
            switch (true) {
                case $this->stream->getCurrent()->test(Token::TEXT_TYPE):
                    $token = $this->stream->next();
                    $rv[] = new TextNode($token->getValue(), $token->getLine());
                    break;

                case $this->stream->getCurrent()->test(Token::VAR_START_TYPE):
                    $token = $this->stream->next();
                    $expr = $this->parseExpression();
                    $this->stream->expect(Token::VAR_END_TYPE);
                    $node = new PrintNode($expr, $token->getLine());
                    NodeDocumentation::add($node, $token);
                    $rv[] = $node;
                    break;

                case $this->stream->getCurrent()->test(Token::BLOCK_START_TYPE):
                    $startToken = $this->stream->next();
                    $token = $this->getCurrentToken();

                    if (!$token->test(Token::NAME_TYPE)) {
                        throw new SyntaxError('A block must start with a tag name.', $token->getLine(), $this->stream->getSourceContext());
                    }

                    if (null !== $test && $test($token)) {
                        if ($dropNeedle) {
                            $this->stream->next();
                        }

                        if (1 === \count($rv)) {
                            return $rv[0];
                        }

                        return new Nodes($rv, $lineno);
                    }

                    if (!$subparser = $this->env->getTokenParser($token->getValue())) {
                        if (null !== $test) {
                            $e = new SyntaxError(\sprintf('Unexpected "%s" tag', $token->getValue()), $token->getLine(), $this->stream->getSourceContext());

                            $callable = (new ReflectionCallable(new TwigTest('decision', $test)))->getCallable();
                            if (\is_array($callable) && $callable[0] instanceof TokenParserInterface) {
                                $e->appendMessage(\sprintf(' (expecting closing tag for the "%s" tag defined near line %s).', $callable[0]->getTag(), $lineno));
                            }
                        } else {
                            $e = new SyntaxError(\sprintf('Unknown "%s" tag.', $token->getValue()), $token->getLine(), $this->stream->getSourceContext());
                            $e->addSuggestions($token->getValue(), array_keys($this->env->getTokenParsers()));
                        }

                        throw $e;
                    }

                    $this->stream->next();

                    $subparser->setParser($this);
                    $documentationTargetIndex = \count($this->documentationTargets);
                    $this->documentationTargets[] = null;
                    try {
                        $node = $subparser->parse($token);
                        $documentationTarget = $this->documentationTargets[$documentationTargetIndex];
                    } finally {
                        array_pop($this->documentationTargets);
                    }
                    if (!$node) {
                        trigger_deprecation('twig/twig', '3.12', 'Returning "null" from "%s" is deprecated and forbidden by "TokenParserInterface".', $subparser::class);
                    } else {
                        $node->setNodeTag($subparser->getTag());
                        NodeDocumentation::add($node, $startToken);
                        if (null !== $documentationTarget && $node !== $documentationTarget) {
                            NodeDocumentation::move($node, $documentationTarget);
                        }
                        $rv[] = $node;
                    }
                    break;

                default:
                    throw new SyntaxError('The lexer or the parser ended up in an unsupported state.', $this->getCurrentToken()->getLine(), $this->stream->getSourceContext());
            }
        }

        if (1 === \count($rv)) {
            return $rv[0];
        }

        return new Nodes($rv, $lineno);
    }

    public function getBlockStack(): array
    {
        trigger_deprecation('twig/twig', '3.12', 'Method "%s()" is deprecated.', __METHOD__);

        return $this->blockStack;
    }

    /**
     * @return string|null
     */
    public function peekBlockStack()
    {
        return $this->blockStack[\count($this->blockStack) - 1] ?? null;
    }

    public function popBlockStack(): void
    {
        array_pop($this->blockStack);
    }

    public function pushBlockStack($name): void
    {
        $this->blockStack[] = $name;
    }

    public function hasBlock(string $name): bool
    {
        trigger_deprecation('twig/twig', '3.12', 'Method "%s()" is deprecated.', __METHOD__);

        return isset($this->blocks[$name]);
    }

    public function getBlock(string $name): Node
    {
        trigger_deprecation('twig/twig', '3.12', 'Method "%s()" is deprecated.', __METHOD__);

        return $this->blocks[$name];
    }

    public function setBlock(string $name, BlockNode $value): void
    {
        if (isset($this->blocks[$name])) {
            throw new SyntaxError(\sprintf("The block '%s' has already been defined line %d.", $name, $this->blocks[$name]->getTemplateLine()), $this->getCurrentToken()->getLine(), $this->blocks[$name]->getSourceContext());
        }

        $this->blocks[$name] = new BodyNode([$value], [], $value->getTemplateLine());
    }

    public function hasMacro(string $name): bool
    {
        trigger_deprecation('twig/twig', '3.12', 'Method "%s()" is deprecated.', __METHOD__);

        return isset($this->macros[$name]);
    }

    public function setDocumentationTarget(Node $node): void
    {
        if (null === $index = array_key_last($this->documentationTargets)) {
            throw new \LogicException('A documentation target can only be set while parsing a tag.');
        }
        if (null !== $this->documentationTargets[$index]) {
            throw new \LogicException('The documentation target for a tag can only be set once.');
        }

        $this->documentationTargets[$index] = $node;
    }

    public function setMacro(string $name, MacroNode $node): void
    {
        if (isset($this->macros[$name])) {
            trigger_deprecation('twig/twig', '3.29', 'Defining the macro "%s" more than once in "%s" is deprecated and will throw a SyntaxError in Twig 4.0 (previous definition at line %d, new definition at line %d). The last definition is used in Twig 3.', $name, $this->stream->getSourceContext()->getName(), $this->macros[$name]->getTemplateLine(), $node->getTemplateLine());
        }

        $this->macros[$name] = $node;
    }

    public function addTrait($trait): void
    {
        $this->traits[] = $trait;
    }

    public function hasTraits(): bool
    {
        trigger_deprecation('twig/twig', '3.12', 'Method "%s()" is deprecated.', __METHOD__);

        return \count($this->traits) > 0;
    }

    /**
     * @return void
     */
    public function embedTemplate(ModuleNode $template)
    {
        $template->setIndex(++$this->lastEmbedIndex);

        $this->embeddedTemplates[] = $template;
    }

    public function addImportedSymbol(string $type, string $alias, ?string $name = null, AbstractExpression|AssignMacroVariable|null $internalRef = null): void
    {
        if ($internalRef && !$internalRef instanceof AssignMacroVariable) {
            trigger_deprecation('twig/twig', '3.15', 'Not passing a "%s" instance as an internal reference is deprecated ("%s" given).', __METHOD__, AssignMacroVariable::class, $internalRef::class);

            $internalRef = new AssignMacroVariable(new MacroVariable($internalRef->getAttribute('name'), $internalRef->getTemplateLine()), $internalRef->getAttribute('global'));
        }

        $this->importedSymbols[0][$type][$alias] = ['name' => $name, 'node' => $internalRef];
    }

    /**
     * @return array{name: string, node: AssignMacroVariable|null}|null
     */
    public function getImportedSymbol(string $type, string $alias)
    {
        // if the symbol does not exist in the current scope (0), try in the main/global scope (last index)
        return $this->importedSymbols[0][$type][$alias] ?? ($this->importedSymbols[\count($this->importedSymbols) - 1][$type][$alias] ?? null);
    }

    public function isMainScope(): bool
    {
        return 1 === \count($this->importedSymbols);
    }

    public function pushLocalScope(): void
    {
        array_unshift($this->importedSymbols, []);
    }

    public function popLocalScope(): void
    {
        array_shift($this->importedSymbols);
    }

    /**
     * @deprecated since Twig 3.21
     */
    public function getExpressionParser(): ExpressionParser
    {
        trigger_deprecation('twig/twig', '3.21', 'Method "%s()" is deprecated, use "parseExpression()" instead.', __METHOD__);

        if (null === $this->expressionParser) {
            $this->expressionParser = new ExpressionParser($this, $this->env);
        }

        return $this->expressionParser;
    }

    public function parseExpression(int $precedence = 0): AbstractExpression
    {
        $token = $this->getCurrentToken();
        if ($token->test(Token::OPERATOR_TYPE) && $ep = $this->parsers->getByName(PrefixExpressionParserInterface::class, $token->getValue())) {
            $this->getStream()->next();
            $expr = $ep->parse($this, $token);
            $this->checkPrecedenceDeprecations($ep, $expr);
        } else {
            $expr = $this->parsers->getByClass(LiteralExpressionParser::class)->parse($this, $token);
        }

        $token = $this->getCurrentToken();
        while ($token->test(Token::OPERATOR_TYPE) && ($ep = $this->parsers->getByName(InfixExpressionParserInterface::class, $token->getValue())) && $ep->getPrecedence() >= $precedence) {
            $this->getStream()->next();
            $expr = $ep->parse($this, $expr, $token);
            $this->checkPrecedenceDeprecations($ep, $expr);
            $token = $this->getCurrentToken();
        }

        return $expr;
    }

    public function getParent(): ?Node
    {
        trigger_deprecation('twig/twig', '3.12', 'Method "%s()" is deprecated.', __METHOD__);

        return $this->parent;
    }

    /**
     * @return bool
     */
    public function hasInheritance()
    {
        return $this->parent || 0 < \count($this->traits);
    }

    public function setParent(?Node $parent, bool $throwOnMultiple = true): void
    {
        if (null === $parent) {
            trigger_deprecation('twig/twig', '3.12', 'Passing "null" to "%s()" is deprecated.', __METHOD__);
        }

        if (null !== $parent && !$parent instanceof AbstractExpression) {
            trigger_deprecation('twig/twig', '3.24', 'Passing a "%s" instance to "%s()" is deprecated, pass an "AbstractExpression" instance instead.', $parent::class, __METHOD__);
        }

        if (null !== $this->parent) {
            if (!$throwOnMultiple) {
                return;
            }

            throw new SyntaxError('Multiple extends tags are forbidden.', $parent->getTemplateLine(), $parent->getSourceContext());
        }

        $this->parent = $parent;
    }

    public function getStream(): TokenStream
    {
        return $this->stream;
    }

    public function getCurrentToken(): Token
    {
        return $this->stream->getCurrent();
    }

    public function getFunction(string $name, int $line): TwigFunction
    {
        try {
            $function = $this->env->getFunction($name);
        } catch (SyntaxError $e) {
            if (!$this->shouldIgnoreUnknownTwigCallables()) {
                throw $e;
            }

            $function = null;
        }

        if (!$function) {
            if ($this->shouldIgnoreUnknownTwigCallables()) {
                return new TwigFunction($name, static fn () => '');
            }
            $e = new SyntaxError(\sprintf('Unknown "%s" function.', $name), $line, $this->stream->getSourceContext());
            $e->addSuggestions($name, array_keys($this->env->getFunctions()));

            throw $e;
        }

        if ($function->isDeprecated()) {
            $src = $this->stream->getSourceContext();
            $function->triggerDeprecation($src->getPath() ?: $src->getName(), $line);
        }

        return $function;
    }

    public function getFilter(string $name, int $line): TwigFilter
    {
        try {
            $filter = $this->env->getFilter($name);
        } catch (SyntaxError $e) {
            if (!$this->shouldIgnoreUnknownTwigCallables()) {
                throw $e;
            }

            $filter = null;
        }
        if (!$filter) {
            if ($this->shouldIgnoreUnknownTwigCallables()) {
                return new TwigFilter($name, static fn () => '');
            }
            $e = new SyntaxError(\sprintf('Unknown "%s" filter.', $name), $line, $this->stream->getSourceContext());
            $e->addSuggestions($name, array_keys($this->env->getFilters()));

            throw $e;
        }

        if ($filter->isDeprecated()) {
            $src = $this->stream->getSourceContext();
            $filter->triggerDeprecation($src->getPath() ?: $src->getName(), $line);
        }

        return $filter;
    }

    public function getTest(int $line): TwigTest
    {
        $name = $this->stream->expect(Token::NAME_TYPE)->getValue();

        if ($this->stream->test(Token::NAME_TYPE)) {
            // try 2-words tests
            $name = $name.' '.$this->getCurrentToken()->getValue();

            try {
                $test = $this->env->getTest($name);
            } catch (SyntaxError $e) {
                if (!$this->shouldIgnoreUnknownTwigCallables()) {
                    throw $e;
                }

                $test = null;
            }
            $this->stream->next();
        } else {
            try {
                $test = $this->env->getTest($name);
            } catch (SyntaxError $e) {
                if (!$this->shouldIgnoreUnknownTwigCallables()) {
                    throw $e;
                }

                $test = null;
            }
        }

        if (!$test) {
            if ($this->shouldIgnoreUnknownTwigCallables()) {
                return new TwigTest($name, static fn () => '');
            }
            $e = new SyntaxError(\sprintf('Unknown "%s" test.', $name), $line, $this->stream->getSourceContext());
            $e->addSuggestions($name, array_keys($this->env->getTests()));

            throw $e;
        }

        if ($test->isDeprecated()) {
            $src = $this->stream->getSourceContext();
            $test->triggerDeprecation($src->getPath() ?: $src->getName(), $this->stream->getCurrent()->getLine());
        }

        return $test;
    }

    private function cleanupBodyForChildTemplates(Node $body): Node
    {
        if ($body instanceof BlockReferenceNode) {
            return new EmptyNode();
        }
        if ($body instanceof TextNode && $body->isBlank()) {
            return new EmptyNode();
        }

        foreach ($body as $k => $node) {
            if ($node instanceof BlockReferenceNode) {
                // as it has a parent, the block reference won't be used
                $body->removeNode($k);
            } elseif ($node instanceof TextNode && $node->isBlank()) {
                // remove nodes considered as "empty"
                $body->removeNode($k);
            }
        }

        return $body;
    }

    private function checkPrecedenceDeprecations(ExpressionParserInterface $expressionParser, AbstractExpression $expr): void
    {
        $this->expressionRefs[$expr] = $expressionParser;
        $precedenceChanges = $this->parsers->getPrecedenceChanges();

        // Check that the all nodes that are between the 2 precedences have explicit parentheses
        if (!isset($precedenceChanges[$expressionParser])) {
            return;
        }

        if ($expr->hasExplicitParentheses()) {
            return;
        }

        if ($expressionParser instanceof PrefixExpressionParserInterface) {
            /** @var AbstractExpression $node */
            $node = $expr->getNode('node');
            foreach ($precedenceChanges as $ep => $changes) {
                if (!\in_array($expressionParser, $changes, true)) {
                    continue;
                }
                if (isset($this->expressionRefs[$node]) && $ep === $this->expressionRefs[$node]) {
                    $change = $expressionParser->getPrecedenceChange();
                    trigger_deprecation($change->getPackage(), $change->getVersion(), \sprintf('As the "%s" %s operator will change its precedence in the next major version, add explicit parentheses to avoid behavior change in "%s" at line %d.', $expressionParser->getName(), ExpressionParserType::getType($expressionParser)->value, $this->getStream()->getSourceContext()->getName(), $node->getTemplateLine()));
                }
            }
        }

        foreach ($precedenceChanges[$expressionParser] as $ep) {
            foreach ($expr as $node) {
                /** @var AbstractExpression $node */
                if (isset($this->expressionRefs[$node]) && $ep === $this->expressionRefs[$node] && !$node->hasExplicitParentheses()) {
                    $change = $ep->getPrecedenceChange();
                    trigger_deprecation($change->getPackage(), $change->getVersion(), \sprintf('As the "%s" %s operator will change its precedence in the next major version, add explicit parentheses to avoid behavior change in "%s" at line %d.', $ep->getName(), ExpressionParserType::getType($ep)->value, $this->getStream()->getSourceContext()->getName(), $node->getTemplateLine()));
                }
            }
        }
    }
}
