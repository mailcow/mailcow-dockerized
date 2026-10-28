<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Twig\ExpressionParser\Infix;

use Twig\Error\SyntaxError;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Expression\Binary\SetBinary;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\Unary\SpreadUnary;
use Twig\Node\Expression\Variable\ContextVariable;
use Twig\Node\Expression\Variable\LocalVariable;
use Twig\Node\Nodes;
use Twig\Parser;
use Twig\Token;

trait ArgumentsTrait
{
    private function parseCallableArguments(Parser $parser, int $line, bool $parseOpenParenthesis = true, bool $preserveNames = false): ArrayExpression
    {
        $arguments = new ArrayExpression([], $line);
        foreach ($this->parseNamedArguments($parser, $parseOpenParenthesis) as $k => $n) {
            $arguments->addElement($n, \is_int($k) || !$preserveNames ? new LocalVariable($k, $line) : new ConstantExpression($k, $line));
        }

        return $arguments;
    }

    private function parseNamedArguments(Parser $parser, bool $parseOpenParenthesis = true): Nodes
    {
        $args = [];
        $stream = $parser->getStream();
        if ($parseOpenParenthesis) {
            $stream->expect(Token::OPERATOR_TYPE, '(', 'A list of arguments must begin with an opening parenthesis');
        }
        $hasSpread = false;
        while (!$stream->test(Token::PUNCTUATION_TYPE, ')')) {
            if ($args) {
                $stream->expect(Token::PUNCTUATION_TYPE, ',', 'Arguments must be separated by a comma');

                // if the comma above was a trailing comma, early exit the argument parse loop
                if ($stream->test(Token::PUNCTUATION_TYPE, ')')) {
                    break;
                }
            }

            $value = $parser->parseExpression();
            if ($value instanceof SpreadUnary) {
                $hasSpread = true;
            } elseif ($hasSpread) {
                throw new SyntaxError('Normal arguments must be placed before argument unpacking.', $stream->getCurrent()->getLine(), $stream->getSourceContext());
            }

            $name = null;
            if ($value instanceof SetBinary) {
                $name = $value->getNode('left')->getAttribute('name');
                $value = $value->getNode('right');
            } elseif (($token = $stream->nextIf(Token::OPERATOR_TYPE, '=')) || ($token = $stream->nextIf(Token::PUNCTUATION_TYPE, ':'))) {
                if (!$value instanceof ContextVariable) {
                    throw new SyntaxError(\sprintf('A parameter name must be a string, "%s" given.', $value::class), $token->getLine(), $stream->getSourceContext());
                }
                $name = $value->getAttribute('name');
                $value = $parser->parseExpression();
            }

            if (null === $name) {
                $args[] = $value;
            } else {
                $args[$name] = $value;
            }
        }
        $stream->expect(Token::PUNCTUATION_TYPE, ')', 'A list of arguments must be closed by a parenthesis');

        return new Nodes($args);
    }
}
