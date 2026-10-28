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
use Twig\ExpressionParser\Infix\DotExpressionParser;
use Twig\ExpressionParser\Infix\FilterExpressionParser;
use Twig\ExpressionParser\Infix\SquareBracketExpressionParser;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\Unary\NegUnary;
use Twig\Node\Expression\Unary\PosUnary;
use Twig\Node\Expression\Unary\SpreadUnary;
use Twig\Node\Expression\Variable\AssignContextVariable;
use Twig\Node\Expression\Variable\ContextVariable;
use Twig\Node\Node;
use Twig\Node\Nodes;

/**
 * Parses expressions.
 *
 * This parser implements a "Precedence climbing" algorithm.
 *
 * @see https://www.engr.mun.ca/~theo/Misc/exp_parsing.htm
 * @see https://en.wikipedia.org/wiki/Operator-precedence_parser
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since Twig 3.21
 */
class ExpressionParser
{
    /**
     * @deprecated since Twig 3.21
     */
    public const OPERATOR_LEFT = 1;
    /**
     * @deprecated since Twig 3.21
     */
    public const OPERATOR_RIGHT = 2;

    public function __construct(
        private Parser $parser,
        private Environment $env,
    ) {
        trigger_deprecation('twig/twig', '3.21', 'Class "%s" is deprecated, use "Parser::parseExpression()" instead.', __CLASS__);
    }

    public function parseExpression($precedence = 0)
    {
        if (\func_num_args() > 1) {
            trigger_deprecation('twig/twig', '3.15', 'Passing a second argument ($allowArrow) to "%s()" is deprecated.', __METHOD__);
        }

        trigger_deprecation('twig/twig', '3.21', 'The "%s()" method is deprecated, use "Parser::parseExpression()" instead.', __METHOD__);

        return $this->parser->parseExpression((int) $precedence);
    }

    /**
     * @deprecated since Twig 3.21
     */
    public function parsePrimaryExpression()
    {
        trigger_deprecation('twig/twig', '3.21', 'The "%s()" method is deprecated.', __METHOD__);

        return $this->parseExpression();
    }

    /**
     * @deprecated since Twig 3.21
     */
    public function parseStringExpression()
    {
        trigger_deprecation('twig/twig', '3.21', 'The "%s()" method is deprecated.', __METHOD__);

        return $this->parseExpression();
    }

    /**
     * @deprecated since Twig 3.11, use parseExpression() instead
     */
    public function parseArrayExpression()
    {
        trigger_deprecation('twig/twig', '3.11', 'Calling "%s()" is deprecated, use "parseExpression()" instead.', __METHOD__);

        return $this->parseExpression();
    }

    /**
     * @deprecated since Twig 3.21
     */
    public function parseSequenceExpression()
    {
        trigger_deprecation('twig/twig', '3.21', 'The "%s()" method is deprecated.', __METHOD__);

        return $this->parseExpression();
    }

    /**
     * @deprecated since Twig 3.11, use parseExpression() instead
     */
    public function parseHashExpression()
    {
        trigger_deprecation('twig/twig', '3.11', 'Calling "%s()" is deprecated, use "parseExpression()" instead.', __METHOD__);

        return $this->parseExpression();
    }

    /**
     * @deprecated since Twig 3.21
     */
    public function parseMappingExpression()
    {
        trigger_deprecation('twig/twig', '3.21', 'The "%s()" method is deprecated.', __METHOD__);

        return $this->parseExpression();
    }

    /**
     * @deprecated since Twig 3.21
     */
    public function parsePostfixExpression($node)
    {
        trigger_deprecation('twig/twig', '3.21', 'The "%s()" method is deprecated.', __METHOD__);

        while (true) {
            $token = $this->parser->getCurrentToken();
            if ($token->test(Token::PUNCTUATION_TYPE)) {
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

    /**
     * @deprecated since Twig 3.21
     */
    public function parseSubscriptExpression($node)
    {
        trigger_deprecation('twig/twig', '3.21', 'The "%s()" method is deprecated.', __METHOD__);

        $parsers = new \ReflectionProperty($this->parser, 'parsers');

        if ('.' === $this->parser->getStream()->next()->getValue()) {
            return $parsers->getValue($this->parser)->getByClass(DotExpressionParser::class)->parse($this->parser, $node, $this->parser->getCurrentToken());
        }

        return $parsers->getValue($this->parser)->getByClass(SquareBracketExpressionParser::class)->parse($this->parser, $node, $this->parser->getCurrentToken());
    }

    /**
     * @deprecated since Twig 3.21
     */
    public function parseFilterExpression($node)
    {
        trigger_deprecation('twig/twig', '3.21', 'The "%s()" method is deprecated.', __METHOD__);

        $this->parser->getStream()->next();

        return $this->parseFilterExpressionRaw($node);
    }

    /**
     * @deprecated since Twig 3.21
     */
    public function parseFilterExpressionRaw($node)
    {
        trigger_deprecation('twig/twig', '3.21', 'The "%s()" method is deprecated.', __METHOD__);

        $parsers = new \ReflectionProperty($this->parser, 'parsers');

        $op = $parsers->getValue($this->parser)->getByClass(FilterExpressionParser::class);
        while (true) {
            $node = $op->parse($this->parser, $node, $this->parser->getCurrentToken());
            if (!$this->parser->getStream()->test(Token::OPERATOR_TYPE, '|')) {
                break;
            }
            $this->parser->getStream()->next();
        }

        return $node;
    }

    /**
     * Parses arguments.
     *
     * @return Node
     *
     * @throws SyntaxError
     *
     * @deprecated since Twig 3.19 Use Twig\ExpressionParser\Infix\ArgumentsTrait::parseNamedArguments() instead
     */
    public function parseArguments()
    {
        trigger_deprecation('twig/twig', '3.19', \sprintf('The "%s()" method is deprecated, use "Twig\ExpressionParser\Infix\ArgumentsTrait::parseNamedArguments()" instead.', __METHOD__));

        $parsePrimaryExpression = new \ReflectionMethod($this->parser, 'parsePrimaryExpression');

        $namedArguments = false;
        $definition = false;
        if (\func_num_args() > 1) {
            $definition = func_get_arg(1);
        }
        if (\func_num_args() > 0) {
            trigger_deprecation('twig/twig', '3.15', 'Passing arguments to "%s()" is deprecated.', __METHOD__);
            $namedArguments = func_get_arg(0);
        }

        $args = [];
        $stream = $this->parser->getStream();

        $stream->expect(Token::OPERATOR_TYPE, '(', 'A list of arguments must begin with an opening parenthesis');
        $hasSpread = false;
        while (!$stream->test(Token::PUNCTUATION_TYPE, ')')) {
            if ($args) {
                $stream->expect(Token::PUNCTUATION_TYPE, ',', 'Arguments must be separated by a comma');

                // if the comma above was a trailing comma, early exit the argument parse loop
                if ($stream->test(Token::PUNCTUATION_TYPE, ')')) {
                    break;
                }
            }

            if ($definition) {
                $token = $stream->expect(Token::NAME_TYPE, null, 'An argument must be a name');
                $value = new ContextVariable($token->getValue(), $this->parser->getCurrentToken()->getLine());
            } else {
                if ($stream->nextIf(Token::SPREAD_TYPE)) {
                    $hasSpread = true;
                    $value = new SpreadUnary($this->parseExpression(), $stream->getCurrent()->getLine());
                } elseif ($hasSpread) {
                    throw new SyntaxError('Normal arguments must be placed before argument unpacking.', $stream->getCurrent()->getLine(), $stream->getSourceContext());
                } else {
                    $value = $this->parseExpression();
                }
            }

            $name = null;
            if ($namedArguments && (($token = $stream->nextIf(Token::OPERATOR_TYPE, '=')) || (!$definition && $token = $stream->nextIf(Token::PUNCTUATION_TYPE, ':')))) {
                if (!$value instanceof ContextVariable) {
                    throw new SyntaxError(\sprintf('A parameter name must be a string, "%s" given.', $value::class), $token->getLine(), $stream->getSourceContext());
                }
                $name = $value->getAttribute('name');

                if ($definition) {
                    $value = $parsePrimaryExpression->invoke($this->parser);

                    if (!$this->checkConstantExpression($value)) {
                        throw new SyntaxError('A default value for an argument must be a constant (a boolean, a string, a number, a sequence, or a mapping).', $token->getLine(), $stream->getSourceContext());
                    }
                } else {
                    $value = $this->parseExpression();
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

        return new Nodes($args);
    }

    /**
     * @deprecated since Twig 3.21, use "AbstractTokenParser::parseAssignmentExpression()" instead
     */
    public function parseAssignmentExpression()
    {
        trigger_deprecation('twig/twig', '3.21', 'The "%s()" method is deprecated, use "AbstractTokenParser::parseAssignmentExpression()" instead.', __METHOD__);

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
            $targets[] = new AssignContextVariable($token->getValue(), $token->getLine());

            if (!$stream->nextIf(Token::PUNCTUATION_TYPE, ',')) {
                break;
            }
        }

        return new Nodes($targets);
    }

    /**
     * @deprecated since Twig 3.21
     */
    public function parseMultitargetExpression()
    {
        trigger_deprecation('twig/twig', '3.21', 'The "%s()" method is deprecated.', __METHOD__);

        $targets = [];
        while (true) {
            $targets[] = $this->parseExpression();
            if (!$this->parser->getStream()->nextIf(Token::PUNCTUATION_TYPE, ',')) {
                break;
            }
        }

        return new Nodes($targets);
    }

    // checks that the node only contains "constant" elements
    // to be removed in 4.0
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

    /**
     * @deprecated since Twig 3.19 Use Twig\ExpressionParser\Infix\ArgumentsTrait::parseNamedArguments() instead
     */
    public function parseOnlyArguments()
    {
        trigger_deprecation('twig/twig', '3.19', \sprintf('The "%s()" method is deprecated, use "Twig\ExpressionParser\Infix\ArgumentsTrait::parseNamedArguments()" instead.', __METHOD__));

        return $this->parseArguments();
    }
}
