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

use Twig\ExpressionParser\AbstractExpressionParser;
use Twig\ExpressionParser\ExpressionParserDescriptionInterface;
use Twig\ExpressionParser\InfixAssociativity;
use Twig\ExpressionParser\InfixExpressionParserInterface;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\GetAttrExpression;
use Twig\Node\Nodes;
use Twig\Parser;
use Twig\Template;
use Twig\Token;

/**
 * @internal
 */
final class SquareBracketExpressionParser extends AbstractExpressionParser implements InfixExpressionParserInterface, ExpressionParserDescriptionInterface
{
    public function parse(Parser $parser, AbstractExpression $expr, Token $token): AbstractExpression
    {
        $stream = $parser->getStream();
        $lineno = $token->getLine();
        $arguments = new ArrayExpression([], $lineno);

        // slice?
        $slice = false;
        if ($stream->test(Token::PUNCTUATION_TYPE, ':')) {
            $slice = true;
            $attribute = new ConstantExpression(0, $token->getLine());
        } else {
            $attribute = $parser->parseExpression();
        }

        if ($stream->nextIf(Token::PUNCTUATION_TYPE, ':')) {
            $slice = true;
        }

        if ($slice) {
            if ($stream->test(Token::PUNCTUATION_TYPE, ']')) {
                $length = new ConstantExpression(null, $token->getLine());
            } else {
                $length = $parser->parseExpression();
            }

            $filter = $parser->getFilter('slice', $token->getLine());
            $arguments = new Nodes([$attribute, $length]);
            $filter = new ($filter->getNodeClass())($expr, $filter, $arguments, $token->getLine());

            $stream->expect(Token::PUNCTUATION_TYPE, ']');

            return $filter;
        }

        $stream->expect(Token::PUNCTUATION_TYPE, ']');

        return new GetAttrExpression($expr, $attribute, $arguments, Template::ARRAY_CALL, $lineno);
    }

    public function getName(): string
    {
        return '[';
    }

    public function getDescription(): string
    {
        return 'Array access';
    }

    public function getPrecedence(): int
    {
        return 512;
    }

    public function getAssociativity(): InfixAssociativity
    {
        return InfixAssociativity::Left;
    }
}
