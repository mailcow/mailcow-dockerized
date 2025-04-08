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

use Twig\Attribute\FirstClassTwigCallableReady;
use Twig\Error\SyntaxError;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Expression\ArrowFunctionExpression;
use Twig\Node\Expression\AssignNameExpression;
use Twig\Node\Expression\Binary\AbstractBinary;
use Twig\Node\Expression\Binary\ConcatBinary;
use Twig\Node\Expression\ConditionalExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\GetAttrExpression;
use Twig\Node\Expression\MethodCallExpression;
use Twig\Node\Expression\NameExpression;
use Twig\Node\Expression\TestExpression;
use Twig\Node\Expression\Unary\AbstractUnary;
use Twig\Node\Expression\Unary\NegUnary;
use Twig\Node\Expression\Unary\NotUnary;
use Twig\Node\Expression\Unary\PosUnary;
use Twig\Node\Node;

/**
 * Parses expressions.
 *
 * This parser implements a "Precedence climbing" algorithm.
 *
 * @see https://www.engr.mun.ca/~theo/Misc/exp_parsing.htm
 * @see https://en.wikipedia.org/wiki/Operator-precedence_parser
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ExpressionParser
{
    public const OPERATOR_LEFT = 1;
    public const OPERATOR_RIGHT = 2;

    /** @var array<string, array{precedence: int, class: class-string<AbstractUnary>}> */
    private $unaryOperators;
    /** @var array<string, array{precedence: int, class: class-string<AbstractBinary>, associativity: self::OPERATOR_*}> */
    private $binaryOperators;
    private $readyNodes = [];

    public function __construct(
        private Parser $parser,
        private Environment $env,
    ) {
        $this->unaryOperators = $env->getUnaryOperators();
        $this->binaryOperators = $env->getBinaryOperators();
    }

    public function parseExpression($precedence = 0, $allowArrow = false)
    {
        if ($allowArrow && $arrow = $this->parseArrow()) {
            return $arrow;
        }

        $expr = $this->getPrimary();
        $token = $this->parser->getCurrentToken();
        while ($this->isBinary($token) && $this->binaryOperators[$token->getValue()]['precedence'] >= $precedence) {
            $op = $this->binaryOperators[$token->getValue()];
            $this->parser->getStream()->next();

            if ('is not' === $token->getValue()) {
                $expr = $this->parseNotTestExpression($expr);
            } elseif ('is' === $token->getValue()) {
                $expr = $this->parseTestExpression($expr);
            } elseif (isset($op['callable'])) {
                $expr = $op['callable']($this->parser, $expr);
            } else {
                $expr1 = $this->parseExpression(self::OPERATOR_LEFT === $op['associativity'] ? $op['precedence'] + 1 : $op['precedence'], true);
                $class = $op['class'];
                $expr = new $class($expr, $expr1, $token->getLine());
            }

            $token = $this->parser->getCurrentToken();
        }

        if (0 === $precedence) {
            return $this->parseConditionalExpression($expr);
        }

        return $expr;
    }

    /**
     * @return ArrowFunctionExpression|null
     */
    private function parseArrow()
    {
        $stream = $this->parser->getStream();

        // short array syntax (one argument, no parentheses)?
        if ($stream->look(1)->test(Token::ARROW_TYPE)) {
            $line = $stream->getCurrent()->getLine();
            $token = $stream->expect(Token::NAME_TYPE);
            $names = [new AssignNameExpression($token->getValue(), $token->getLine())];
            $stream->expect(Token::ARROW_TYPE);

            return new ArrowFunctionExpression($this->parseExpression(0), new Node($names), $line);
        }

        // first, determine if we are parsing an arrow function by finding => (long form)
        $i = 0;
        if (!$stream->look($i)->test(Token::PUNCTUATION_TYPE, '(')) {
            return null;
        }
        ++$i;
        while (true) {
            // variable name
            ++$i;
            if (!$stream->look($i)->test(Token::PUNCTUATION_TYPE, ',')) {
                break;
            }
            ++$i;
        }
        if (!$stream->look($i)->test(Token::PUNCTUATION_TYPE, ')')) {
            return null;
        }
        ++$i;
        if (!$stream->look($i)->test(Token::ARROW_TYPE)) {
            return null;
        }

        // yes, let's parse it properly
        $token = $stream->expect(Token::PUNCTUATION_TYPE, '(');
        $line = $token->getLine();

        $names = [];
        while (true) {
            $token = $stream->expect(Token::NAME_TYPE);
            $names[] = new AssignNameExpression($token->getValue(), $token->getLine());

            if (!$stream->nextIf(Token::PUNCTUATION_TYPE, ',')) {
                break;
            }
        }
        $stream->expect(Token::PUNCTUATION_TYPE, ')');
        $stream->expect(Token::ARROW_TYPE);

        return new ArrowFunctionExpression($this->parseExpression(0), new Node($names), $line);
    }

    private function getPrimary(): AbstractExpression
    {
        $token = $this->parser->getCurrentToken();

        if ($this->isUnary($token)) {
            $operator = $this->unaryOperators[$token->getValue()];
            $this->parser->getStream()->next();
            $expr = $this->parseExpression($operator['precedence']);
            $class = $operator['class'];

            return $this->parsePostfixExpression(new $class($expr, $token->getLine()));
        } elseif ($token->test(Token::PUNCTUATION_TYPE, '(')) {
            $this->parser->getStream()->next();
            $expr = $this->parseExpression();
            $this->parser->getStream()->expect(Token::PUNCTUATION_TYPE, ')', 'An opened parenthesis is not properly closed');

            return $this->parsePostfixExpression($expr);
        }

        return $this->parsePrimaryExpression();
    }

    private function parseConditionalExpression($expr): AbstractExpression
    {
        while ($this->parser->getStream()->nextIf(Token::PUNCTUATION_TYPE, '?')) {
            if (!$this->parser->getStream()->nextIf(Token::PUNCTUATION_TYPE, ':')) {
                $expr2 = $this->parseExpression();
                if ($this->parser->getStream()->nextIf(Token::PUNCTUATION_TYPE, ':')) {
                    // Ternary operator (expr ? expr2 : expr3)
                    $expr3 = $this->parseExpression();
                } else {
                    // Ternary without else (expr ? expr2)
                    $expr3 = new ConstantExpression('', $this->parser->getCurrentToken()->getLine());
                }
            } else {
                // Ternary without then (expr ?: expr3)
                $expr2 = $expr;
                $expr3 = $this->parseExpression();
            }

            $expr = new ConditionalExpression($expr, $expr2, $expr3, $this->parser->getCurrentToken()->getLine());
        }

        return $expr;
    }

    private function isUnary(Token $token): bool
    {
        return $token->test(Token::OPERATOR_TYPE) && isset($this->unaryOperators[$token->getValue()]);
    }

    private function isBinary(Token $token): bool
    {
        return $token->test(Token::OPERATOR_TYPE) && isset($this->binaryOperators[$token->getValue()]);
    }

    public function parsePrimaryExpression()
    {
        $token = $this->parser->getCurrentToken();
        switch ($token->getType()) {
            case Token::NAME_TYPE:
                $this->parser->getStream()->next();
                switch ($token->getValue()) {
                    case 'true':
                    case 'TRUE':
                        $node = new ConstantExpression(true, $token->getLine());
                        break;

                    case 'false':
                    case 'FALSE':
                        $node = new ConstantExpression(false, $token->getLine());
                        break;

                    case 'none':
                    case 'NONE':
                    case 'null':
                    case 'NULL':
                        $node = new ConstantExpression(null, $token->getLine());
                        break;

                    default:
                        if ('(' === $this->parser->getCurrentToken()->getValue()) {
                            $node = $this->getFunctionNode($token->getValue(), $token->getLine());
                        } else {
                            $node = new NameExpression($token->getValue(), $token->getLine());
                        }
                }
                break;

            case Token::NUMBER_TYPE:
                $this->parser->getStream()->next();
                $node = new ConstantExpression($token->getValue(), $token->getLine());
                break;

            case Token::STRING_TYPE:
            case Token::INTERPOLATION_START_TYPE:
                $node = $this->parseStringExpression();
                break;

            case Token::OPERATOR_TYPE:
                if (preg_match(Lexer::REGEX_NAME, $token->getValue(), $matches) && $matches[0] == $token->getValue()) {
                    // in this context, string operators are variable names
                    $this->parser->getStream()->next();
                    $node = new NameExpression($token->getValue(), $token->getLine());
                    break;
                }

                if (isset($this->unaryOperators[$token->getValue()])) {
                    $class = $this->unaryOperators[$token->getValue()]['class'];
                    if (!\in_array($class, [NegUnary::class, PosUnary::class])) {
                        throw new SyntaxError(\sprintf('Unexpected unary operator "%s".', $token->getValue()), $token->getLine(), $this->parser->getStream()->getSourceContext());
                    }

                    $this->parser->getStream()->next();
                    $expr = $this->parsePrimaryExpression();

                    $node = new $class($expr, $token->getLine());
                    break;
                }

                // no break
            default:
                if ($token->test(Token::PUNCTUATION_TYPE, '[')) {
                    $node = $this->parseSequenceExpression();
                } elseif ($token->test(Token::PUNCTUATION_TYPE, '{')) {
                    $node = $this->parseMappingExpression();
                } elseif ($token->test(Token::OPERATOR_TYPE, '=') && ('==' === $this->parser->getStream()->look(-1)->getValue() || '!=' === $this->parser->getStream()->look(-1)->getValue())) {
                    throw new SyntaxError(\sprintf('Unexpected operator of value "%s". Did you try to use "===" or "!==" for strict comparison? Use "is same as(value)" instead.', $token->getValue()), $token->getLine(), $this->parser->getStream()->getSourceContext());
                } else {
                    throw new SyntaxError(\sprintf('Unexpected token "%s" of value "%s".', Token::typeToEnglish($token->getType()), $token->getValue()), $token->getLine(), $this->parser->getStream()->getSourceContext());
                }
        }

        return $this->parsePostfixExpression($node);
    }

    public function parseStringExpression()
    {
        $stream = $this->parser->getStream();

        $nodes = [];
        // a string cannot be followed by another string in a single expression
        $nextCanBeString = true;
        while (true) {
            if ($nextCanBeString && $token = $stream->nextIf(Token::STRING_TYPE)) {
                $nodes[] = new ConstantExpression($token->getValue(), $token->getLine());
                $nextCanBeString = false;
            } elseif ($stream->nextIf(Token::INTERPOLATION_START_TYPE)) {
                $nodes[] = $this->parseExpression();
                $stream->expect(Token::INTERPOLATION_END_TYPE);
                $nextCanBeString = true;
            } else {
                break;
            }
        }

        $expr = array_shift($nodes);
        foreach ($nodes as $node) {
            $expr = new ConcatBinary($expr, $node, $node->getTemplateLine());
        }

        return $expr;
    }

    /**
     * @deprecated since 3.11, use parseSequenceExpression() instead
     */
    public function parseArrayExpression()
    {
        trigger_deprecation('twig/twig', '3.11', 'Calling "%s()" is deprecated, use "parseSequenceExpression()" instead.', __METHOD__);

        return $this->parseSequenceExpression();
    }

    public function parseSequenceExpression()
    {
        $stream = $this->parser->getStream();
        $stream->expect(Token::PUNCTUATION_TYPE, '[', 'A sequence element was expected');

        $node = new ArrayExpression([], $stream->getCurrent()->getLine());
        $first = true;
        while (!$stream->test(Token::PUNCTUATION_TYPE, ']')) {
            if (!$first) {
                $stream->expect(Token::PUNCTUATION_TYPE, ',', 'A sequence element must be followed by a comma');

                // trailing ,?
                if ($stream->test(Token::PUNCTUATION_TYPE, ']')) {
                    break;
                }
            }
            $first = false;

            if ($stream->test(Token::SPREAD_TYPE)) {
                $stream->next();
                $expr = $this->parseExpression();
                $expr->setAttribute('spread', true);
                $node->addElement($expr);
            } else {
                $node->addElement($this->parseExpression());
            }
        }
        $stream->expect(Token::PUNCTUATION_TYPE, ']', 'An opened sequence is not properly closed');

        return $node;
    }

    /**
     * @deprecated since 3.11, use parseMappingExpression() instead
     */
    public function parseHashExpression()
    {
        trigger_deprecation('twig/twig', '3.11', 'Calling "%s()" is deprecated, use "parseMappingExpression()" instead.', __METHOD__);

        return $this->parseMappingExpression();
    }

    public function parseMappingExpression()
    {
        $stream = $this->parser->getStream();
        $stream->expect(Token::PUNCTUATION_TYPE, '{', 'A mapping element was expected');

        $node = new ArrayExpression([], $stream->getCurrent()->getLine());
        $first = true;
        while (!$stream->test(Token::PUNCTUATION_TYPE, '}')) {
            if (!$first) {
                $stream->expect(Token::PUNCTUATION_TYPE, ',', 'A mapping value must be followed by a comma');

                // trailing ,?
                if ($stream->test(Token::PUNCTUATION_TYPE, '}')) {
                    break;
                }
            }
            $first = false;

            if ($stream->test(Token::SPREAD_TYPE)) {
                $stream->next();
                $value = $this->parseExpression();
                $value->setAttribute('spread', true);
                $node->addElement($value);
                continue;
            }

            // a mapping key can be:
            //
            //  * a number -- 12
            //  * a string -- 'a'
            //  * a name, which is equivalent to a string -- a
            //  * an expression, which must be enclosed in parentheses -- (1 + 2)
            if ($token = $stream->nextIf(Token::NAME_TYPE)) {
                $key = new ConstantExpression($token->getValue(), $token->getLine());

                // {a} is a shortcut for {a:a}
                if ($stream->test(Token::PUNCTUATION_TYPE, [',', '}'])) {
                    $value = new NameExpression($key->getAttribute('value'), $key->getTemplateLine());
                    $node->addElement($value, $key);
                    continue;
                }
            } elseif (($token = $stream->nextIf(Token::STRING_TYPE)) || $token = $stream->nextIf(Token::NUMBER_TYPE)) {
                $key = new ConstantExpression($token->getValue(), $token->getLine());
            } elseif ($stream->test(Token::PUNCTUATION_TYPE, '(')) {
                $key = $this->parseExpression();
            } else {
                $current = $stream->getCurrent();

                throw new SyntaxError(\sprintf('A mapping key must be a quoted string, a number, a name, or an expression enclosed in parentheses (unexpected token "%s" of value "%s".', Token::typeToEnglish($current->getType()), $current->getValue()), $current->getLine(), $stream->getSourceContext());
            }

            $stream->expect(Token::PUNCTUATION_TYPE, ':', 'A mapping key must be followed by a colon (:)');
            $value = $this->parseExpression();

            $node->addElement($value, $key);
        }
        $stream->expect(Token::PUNCTUATION_TYPE, '}', 'An opened mapping is not properly closed');

        return $node;
    }

    public function parsePostfixExpression($node)
    {
        while (true) {
            $token = $this->parser->getCurrentToken();
            if (Token::PUNCTUATION_TYPE == $token->getType()) {
                if ('.' == $token->getValue() || '[' == $token->getValue()) {
                    $node = $this->parseSubscriptExpression($node);
                } elseif ('|' == $token->getValue()) {
                    $node = $this->parseFilterExpression($node);
                } else {
                    break;
                }
            } else {
                break;
            }
        }

        return $node;
    }

    public function getFunctionNode($name, $line)
    {
        if (null !== $alias = $this->parser->getImportedSymbol('function', $name)) {
            $arguments = new ArrayExpression([], $line);
            foreach ($this->parseArguments() as $n) {
                $arguments->addElement($n);
            }

            $node = new MethodCallExpression($alias['node'], $alias['name'], $arguments, $line);
            $node->setAttribute('safe', true);

            return $node;
        }

        $args = $this->parseArguments(true);
        $function = $this->getFunction($name, $line);

        if ($function->getParserCallable()) {
            $fakeNode = new Node(lineno: $line);
            $fakeNode->setSourceContext($this->parser->getStream()->getSourceContext());

            return ($function->getParserCallable())($this->parser, $fakeNode, $args, $line);
        }

        if (!isset($this->readyNodes[$class = $function->getNodeClass()])) {
            $this->readyNodes[$class] = (bool) (new \ReflectionClass($class))->getConstructor()->getAttributes(FirstClassTwigCallableReady::class);
        }

        if (!$ready = $this->readyNodes[$class]) {
            trigger_deprecation('twig/twig', '3.12', 'Twig node "%s" is not marked as ready for passing a "TwigFunction" in the constructor instead of its name; please update your code and then add #[FirstClassTwigCallableReady] attribute to the constructor.', $class);
        }

        return new $class($ready ? $function : $function->getName(), $args, $line);
    }

    public function parseSubscriptExpression($node)
    {
        $stream = $this->parser->getStream();
        $token = $stream->next();
        $lineno = $token->getLine();
        $arguments = new ArrayExpression([], $lineno);
        $type = Template::ANY_CALL;
        if ('.' == $token->getValue()) {
            $token = $stream->next();
            if (
                Token::NAME_TYPE == $token->getType()
                ||
                Token::NUMBER_TYPE == $token->getType()
                ||
                (Token::OPERATOR_TYPE == $token->getType() && preg_match(Lexer::REGEX_NAME, $token->getValue()))
            ) {
                $arg = new ConstantExpression($token->getValue(), $lineno);

                if ($stream->test(Token::PUNCTUATION_TYPE, '(')) {
                    $type = Template::METHOD_CALL;
                    foreach ($this->parseArguments() as $n) {
                        $arguments->addElement($n);
                    }
                }
            } else {
                throw new SyntaxError(\sprintf('Expected name or number, got value "%s" of type %s.', $token->getValue(), Token::typeToEnglish($token->getType())), $lineno, $stream->getSourceContext());
            }

            if ($node instanceof NameExpression && null !== $this->parser->getImportedSymbol('template', $node->getAttribute('name'))) {
                $name = $arg->getAttribute('value');

                $node = new MethodCallExpression($node, 'macro_'.$name, $arguments, $lineno);
                $node->setAttribute('safe', true);

                return $node;
            }
        } else {
            $type = Template::ARRAY_CALL;

            // slice?
            $slice = false;
            if ($stream->test(Token::PUNCTUATION_TYPE, ':')) {
                $slice = true;
                $arg = new ConstantExpression(0, $token->getLine());
            } else {
                $arg = $this->parseExpression();
            }

            if ($stream->nextIf(Token::PUNCTUATION_TYPE, ':')) {
                $slice = true;
            }

            if ($slice) {
                if ($stream->test(Token::PUNCTUATION_TYPE, ']')) {
                    $length = new ConstantExpression(null, $token->getLine());
                } else {
                    $length = $this->parseExpression();
                }

                $filter = $this->getFilter('slice', $token->getLine());
                $arguments = new Node([$arg, $length]);
                $filter = new ($filter->getNodeClass())($node, $filter, $arguments, $token->getLine());

                $stream->expect(Token::PUNCTUATION_TYPE, ']');

                return $filter;
            }

            $stream->expect(Token::PUNCTUATION_TYPE, ']');
        }

        return new GetAttrExpression($node, $arg, $arguments, $type, $lineno);
    }

    public function parseFilterExpression($node)
    {
        $this->parser->getStream()->next();

        return $this->parseFilterExpressionRaw($node);
    }

    public function parseFilterExpressionRaw($node)
    {
        if (\func_num_args() > 1) {
            trigger_deprecation('twig/twig', '3.12', 'Passing a second argument to "%s()" is deprecated.', __METHOD__);
        }

        while (true) {
            $token = $this->parser->getStream()->expect(Token::NAME_TYPE);

            if (!$this->parser->getStream()->test(Token::PUNCTUATION_TYPE, '(')) {
                $arguments = new Node();
            } else {
                $arguments = $this->parseArguments(true, false, true);
            }

            $filter = $this->getFilter($token->getValue(), $token->getLine());

            $ready = true;
            if (!isset($this->readyNodes[$class = $filter->getNodeClass()])) {
                $this->readyNodes[$class] = (bool) (new \ReflectionClass($class))->getConstructor()->getAttributes(FirstClassTwigCallableReady::class);
            }

            if (!$ready = $this->readyNodes[$class]) {
                trigger_deprecation('twig/twig', '3.12', 'Twig node "%s" is not marked as ready for passing a "TwigFilter" in the constructor instead of its name; please update your code and then add #[FirstClassTwigCallableReady] attribute to the constructor.', $class);
            }

            $node = new $class($node, $ready ? $filter : new ConstantExpression($filter->getName(), $token->getLine()), $arguments, $token->getLine());

            if (!$this->parser->getStream()->test(Token::PUNCTUATION_TYPE, '|')) {
                break;
            }

            $this->parser->getStream()->next();
        }

        return $node;
    }

    /**
     * Parses arguments.
     *
     * @param bool $namedArguments Whether to allow named arguments or not
     * @param bool $definition     Whether we are parsing arguments for a function (or macro) definition
     *
     * @return Node
     *
     * @throws SyntaxError
     */
    public function parseArguments($namedArguments = false, $definition = false, $allowArrow = false)
    {
        $args = [];
        $stream = $this->parser->getStream();

        $stream->expect(Token::PUNCTUATION_TYPE, '(', 'A list of arguments must begin with an opening parenthesis');
        while (!$stream->test(Token::PUNCTUATION_TYPE, ')')) {
            if (!empty($args)) {
                $stream->expect(Token::PUNCTUATION_TYPE, ',', 'Arguments must be separated by a comma');

                // if the comma above was a trailing comma, early exit the argument parse loop
                if ($stream->test(Token::PUNCTUATION_TYPE, ')')) {
                    break;
                }
            }

            if ($definition) {
                $token = $stream->expect(Token::NAME_TYPE, null, 'An argument must be a name');
                $value = new NameExpression($token->getValue(), $this->parser->getCurrentToken()->getLine());
            } else {
                $value = $this->parseExpression(0, $allowArrow);
            }

            $name = null;
            if ($namedArguments && (($token = $stream->nextIf(Token::OPERATOR_TYPE, '=')) || ($token = $stream->nextIf(Token::PUNCTUATION_TYPE, ':')))) {
                if (!$value instanceof NameExpression) {
                    throw new SyntaxError(\sprintf('A parameter name must be a string, "%s" given.', \get_class($value)), $token->getLine(), $stream->getSourceContext());
                }
                $name = $value->getAttribute('name');

                if ($definition) {
                    $value = $this->parsePrimaryExpression();

                    if (!$this->checkConstantExpression($value)) {
                        throw new SyntaxError('A default value for an argument must be a constant (a boolean, a string, a number, a sequence, or a mapping).', $token->getLine(), $stream->getSourceContext());
                    }
                } else {
                    $value = $this->parseExpression(0, $allowArrow);
                }
            }

            if ($definition) {
                if (null === $name) {
                    $name = $value->getAttribute('name');
                    $value = new ConstantExpression(null, $this->parser->getCurrentToken()->getLine());
                    $value->setAttribute('is_implicit', true);
                }
                $args[$name] = $value;
            } else {
                if (null === $name) {
                    $args[] = $value;
                } else {
                    $args[$name] = $value;
                }
            }
        }
        $stream->expect(Token::PUNCTUATION_TYPE, ')', 'A list of arguments must be closed by a parenthesis');

        return new Node($args);
    }

    public function parseAssignmentExpression()
    {
        $stream = $this->parser->getStream();
        $targets = [];
        while (true) {
            $token = $this->parser->getCurrentToken();
            if ($stream->test(Token::OPERATOR_TYPE) && preg_match(Lexer::REGEX_NAME, $token->getValue())) {
                // in this context, string operators are variable names
                $this->parser->getStream()->next();
            } else {
                $stream->expect(Token::NAME_TYPE, null, 'Only variables can be assigned to');
            }
            $value = $token->getValue();
            if (\in_array(strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), ['true', 'false', 'none', 'null'])) {
                throw new SyntaxError(\sprintf('You cannot assign a value to "%s".', $value), $token->getLine(), $stream->getSourceContext());
            }
            $targets[] = new AssignNameExpression($value, $token->getLine());

            if (!$stream->nextIf(Token::PUNCTUATION_TYPE, ',')) {
                break;
            }
        }

        return new Node($targets);
    }

    public function parseMultitargetExpression()
    {
        $targets = [];
        while (true) {
            $targets[] = $this->parseExpression();
            if (!$this->parser->getStream()->nextIf(Token::PUNCTUATION_TYPE, ',')) {
                break;
            }
        }

        return new Node($targets);
    }

    private function parseNotTestExpression(Node $node): NotUnary
    {
        return new NotUnary($this->parseTestExpression($node), $this->parser->getCurrentToken()->getLine());
    }

    private function parseTestExpression(Node $node): TestExpression
    {
        $stream = $this->parser->getStream();
        $test = $this->getTest($node->getTemplateLine());

        $arguments = null;
        if ($stream->test(Token::PUNCTUATION_TYPE, '(')) {
            $arguments = $this->parseArguments(true);
        } elseif ($test->hasOneMandatoryArgument()) {
            $arguments = new Node([0 => $this->parsePrimaryExpression()]);
        }

        if ('defined' === $test->getName() && $node instanceof NameExpression && null !== $alias = $this->parser->getImportedSymbol('function', $node->getAttribute('name'))) {
            $node = new MethodCallExpression($alias['node'], $alias['name'], new ArrayExpression([], $node->getTemplateLine()), $node->getTemplateLine());
            $node->setAttribute('safe', true);
        }

        $ready = $test instanceof TwigTest;
        if (!isset($this->readyNodes[$class = $test->getNodeClass()])) {
            $this->readyNodes[$class] = (bool) (new \ReflectionClass($class))->getConstructor()->getAttributes(FirstClassTwigCallableReady::class);
        }

        if (!$ready = $this->readyNodes[$class]) {
            trigger_deprecation('twig/twig', '3.12', 'Twig node "%s" is not marked as ready for passing a "TwigTest" in the constructor instead of its name; please update your code and then add #[FirstClassTwigCallableReady] attribute to the constructor.', $class);
        }

        return new $class($node, $ready ? $test : $test->getName(), $arguments, $this->parser->getCurrentToken()->getLine());
    }

    private function getTest(int $line): TwigTest
    {
        $stream = $this->parser->getStream();
        $name = $stream->expect(Token::NAME_TYPE)->getValue();

        if ($stream->test(Token::NAME_TYPE)) {
            // try 2-words tests
            $name = $name.' '.$this->parser->getCurrentToken()->getValue();

            if ($test = $this->env->getTest($name)) {
                $stream->next();
            }
        } else {
            $test = $this->env->getTest($name);
        }

        if (!$test) {
            $e = new SyntaxError(\sprintf('Unknown "%s" test.', $name), $line, $stream->getSourceContext());
            $e->addSuggestions($name, array_keys($this->env->getTests()));

            throw $e;
        }

        if ($test->isDeprecated()) {
            $stream = $this->parser->getStream();
            $message = \sprintf('Twig Test "%s" is deprecated', $test->getName());

            if ($test->getAlternative()) {
                $message .= \sprintf('. Use "%s" instead', $test->getAlternative());
            }
            $src = $stream->getSourceContext();
            $message .= \sprintf(' in %s at line %d.', $src->getPath() ?: $src->getName(), $stream->getCurrent()->getLine());

            trigger_deprecation($test->getDeprecatingPackage(), $test->getDeprecatedVersion(), $message);
        }

        return $test;
    }

    private function getFunction(string $name, int $line): TwigFunction
    {
        if (!$function = $this->env->getFunction($name)) {
            $e = new SyntaxError(\sprintf('Unknown "%s" function.', $name), $line, $this->parser->getStream()->getSourceContext());
            $e->addSuggestions($name, array_keys($this->env->getFunctions()));

            throw $e;
        }

        if ($function->isDeprecated()) {
            $message = \sprintf('Twig Function "%s" is deprecated', $function->getName());
            if ($function->getAlternative()) {
                $message .= \sprintf('. Use "%s" instead', $function->getAlternative());
            }
            $src = $this->parser->getStream()->getSourceContext();
            $message .= \sprintf(' in %s at line %d.', $src->getPath() ?: $src->getName(), $line);

            trigger_deprecation($function->getDeprecatingPackage(), $function->getDeprecatedVersion(), $message);
        }

        return $function;
    }

    private function getFilter(string $name, int $line): TwigFilter
    {
        if (!$filter = $this->env->getFilter($name)) {
            $e = new SyntaxError(\sprintf('Unknown "%s" filter.', $name), $line, $this->parser->getStream()->getSourceContext());
            $e->addSuggestions($name, array_keys($this->env->getFilters()));

            throw $e;
        }

        if ($filter->isDeprecated()) {
            $message = \sprintf('Twig Filter "%s" is deprecated', $filter->getName());
            if ($filter->getAlternative()) {
                $message .= \sprintf('. Use "%s" instead', $filter->getAlternative());
            }
            $src = $this->parser->getStream()->getSourceContext();
            $message .= \sprintf(' in %s at line %d.', $src->getPath() ?: $src->getName(), $line);

            trigger_deprecation($filter->getDeprecatingPackage(), $filter->getDeprecatedVersion(), $message);
        }

        return $filter;
    }

    // checks that the node only contains "constant" elements
    private function checkConstantExpression(Node $node): bool
    {
        if (!($node instanceof ConstantExpression || $node instanceof ArrayExpression
            || $node instanceof NegUnary || $node instanceof PosUnary
        )) {
            return false;
        }

        foreach ($node as $n) {
            if (!$this->checkConstantExpression($n)) {
                return false;
            }
        }

        return true;
    }
}
