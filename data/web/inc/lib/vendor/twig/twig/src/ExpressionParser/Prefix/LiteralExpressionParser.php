<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Twig\ExpressionParser\Prefix;

use Twig\Error\SyntaxError;
use Twig\ExpressionParser\AbstractExpressionParser;
use Twig\ExpressionParser\ExpressionParserDescriptionInterface;
use Twig\ExpressionParser\PrefixExpressionParserInterface;
use Twig\Lexer;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Expression\Binary\ConcatBinary;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\EmptyExpression;
use Twig\Node\Expression\Variable\ContextVariable;
use Twig\Parser;
use Twig\Token;

/**
 * @internal
 */
final class LiteralExpressionParser extends AbstractExpressionParser implements PrefixExpressionParserInterface, ExpressionParserDescriptionInterface
{
    public function parse(Parser $parser, Token $token): AbstractExpression
    {
        $stream = $parser->getStream();
        switch (true) {
            case $token->test(Token::NAME_TYPE):
                $stream->next();
                switch ($token->getValue()) {
                    case 'true':
                    case 'TRUE':
                        return new ConstantExpression(true, $token->getLine());

                    case 'false':
                    case 'FALSE':
                        return new ConstantExpression(false, $token->getLine());

                    case 'none':
                    case 'NONE':
                    case 'null':
                    case 'NULL':
                        return new ConstantExpression(null, $token->getLine());

                    default:
                        return new ContextVariable($token->getValue(), $token->getLine());
                }

                // no break
            case $token->test(Token::NUMBER_TYPE):
                $stream->next();

                return new ConstantExpression($token->getValue(), $token->getLine());

            case $token->test(Token::STRING_TYPE):
            case $token->test(Token::INTERPOLATION_START_TYPE):
                return $this->parseStringExpression($parser);

            case $token->test(Token::PUNCTUATION_TYPE):
                // In 4.0, we should always return the node or throw an error for default
                if ($node = match ($token->getValue()) {
                    '{' => $this->parseMappingExpression($parser),
                    default => null,
                }) {
                    return $node;
                }

                // no break
            case $token->test(Token::OPERATOR_TYPE):
                if ('[' === $token->getValue()) {
                    return $this->parseSequenceExpression($parser);
                }

                if (preg_match(Lexer::REGEX_NAME, $token->getValue(), $matches) && $matches[0] == $token->getValue()) {
                    // in this context, string operators are variable names
                    $stream->next();

                    return new ContextVariable($token->getValue(), $token->getLine());
                }

                // no break
            default:
                throw new SyntaxError(\sprintf('Unexpected token "%s" of value "%s".', $token->toEnglish(), $token->getValue()), $token->getLine(), $stream->getSourceContext());
        }
    }

    public function getName(): string
    {
        return 'literal';
    }

    public function getOperatorTokens(): array
    {
        return [];
    }

    public function getDescription(): string
    {
        return 'A literal value (boolean, string, number, sequence, mapping, ...)';
    }

    public function getPrecedence(): int
    {
        // not used
        return 0;
    }

    private function parseStringExpression(Parser $parser)
    {
        $stream = $parser->getStream();

        $nodes = [];
        // a string cannot be followed by another string in a single expression
        $nextCanBeString = true;
        while (true) {
            if ($nextCanBeString && $token = $stream->nextIf(Token::STRING_TYPE)) {
                $nodes[] = new ConstantExpression($token->getValue(), $token->getLine());
                $nextCanBeString = false;
            } elseif ($stream->nextIf(Token::INTERPOLATION_START_TYPE)) {
                $nodes[] = $parser->parseExpression();
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

    private function parseSequenceExpression(Parser $parser)
    {
        $stream = $parser->getStream();
        $stream->expect(Token::OPERATOR_TYPE, '[', 'A sequence element was expected');

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

            // Check for empty slots (comma with no expression)
            if ($stream->test(Token::PUNCTUATION_TYPE, ',')) {
                $node->addElement(new EmptyExpression($stream->getCurrent()->getLine()));
            } else {
                $node->addElement($parser->parseExpression());
            }
        }
        $stream->expect(Token::PUNCTUATION_TYPE, ']', 'An opened sequence is not properly closed');

        return $node;
    }

    private function parseMappingExpression(Parser $parser)
    {
        $stream = $parser->getStream();
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

            if ($stream->test(Token::OPERATOR_TYPE, '...')) {
                $node->addElement($parser->parseExpression());

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
                    $value = new ContextVariable($key->getAttribute('value'), $key->getTemplateLine());
                    $node->addElement($value, $key);
                    continue;
                }
            } elseif (($token = $stream->nextIf(Token::STRING_TYPE)) || $token = $stream->nextIf(Token::NUMBER_TYPE)) {
                $key = new ConstantExpression($token->getValue(), $token->getLine());
            } elseif ($stream->test(Token::OPERATOR_TYPE, '(')) {
                $key = $parser->parseExpression();
            } else {
                $current = $stream->getCurrent();

                throw new SyntaxError(\sprintf('A mapping key must be a quoted string, a number, a name, or an expression enclosed in parentheses (unexpected token "%s" of value "%s".', $current->toEnglish(), $current->getValue()), $current->getLine(), $stream->getSourceContext());
            }

            $stream->expect(Token::PUNCTUATION_TYPE, ':', 'A mapping key must be followed by a colon (:)');
            $value = $parser->parseExpression();

            $node->addElement($value, $key);
        }
        $stream->expect(Token::PUNCTUATION_TYPE, '}', 'An opened mapping is not properly closed');

        return $node;
    }
}
