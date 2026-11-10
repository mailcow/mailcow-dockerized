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
use Twig\ExpressionParser\AbstractExpressionParser;
use Twig\ExpressionParser\ExpressionParserDescriptionInterface;
use Twig\ExpressionParser\InfixAssociativity;
use Twig\ExpressionParser\InfixExpressionParserInterface;
use Twig\Lexer;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\GetAttrExpression;
use Twig\Node\Expression\MacroReferenceExpression;
use Twig\Node\Expression\NameExpression;
use Twig\Node\Expression\Variable\MacroVariable;
use Twig\Parser;
use Twig\Template;
use Twig\Token;

/**
 * @internal
 */
final class DotExpressionParser extends AbstractExpressionParser implements InfixExpressionParserInterface, ExpressionParserDescriptionInterface
{
    use ArgumentsTrait;

    public function parse(Parser $parser, AbstractExpression $expr, Token $token): AbstractExpression
    {
        $nullSafe = '?.' === $token->getValue();
        $stream = $parser->getStream();
        $token = $stream->getCurrent();
        $lineno = $token->getLine();
        $arguments = new ArrayExpression([], $lineno);
        $type = Template::ANY_CALL;

        if ($stream->nextIf(Token::OPERATOR_TYPE, '(')) {
            $attribute = $parser->parseExpression();
            $stream->expect(Token::PUNCTUATION_TYPE, ')');
        } else {
            $token = $stream->next();
            if (
                $token->test(Token::NAME_TYPE)
                || $token->test(Token::NUMBER_TYPE)
                || ($token->test(Token::OPERATOR_TYPE) && preg_match(Lexer::REGEX_NAME, $token->getValue()))
            ) {
                $attribute = new ConstantExpression($token->getValue(), $token->getLine());
            } else {
                throw new SyntaxError(\sprintf('Expected name or number, got value "%s" of type "%s".', $token->getValue(), $token->toEnglish()), $token->getLine(), $stream->getSourceContext());
            }
        }

        $isMacroTarget = $expr instanceof NameExpression
            && (
                null !== $parser->getImportedSymbol('template', $expr->getAttribute('name'))
                || '_self' === $expr->getAttribute('name')
            );

        if ($stream->test(Token::OPERATOR_TYPE, '(')) {
            $type = Template::METHOD_CALL;
            $arguments = $this->parseCallableArguments($parser, $token->getLine(), preserveNames: $isMacroTarget);
        }

        if ($isMacroTarget) {
            $name = $attribute instanceof ConstantExpression ? (string) $attribute->getAttribute('value') : $attribute;
            $node = new MacroReferenceExpression(new MacroVariable($expr->getAttribute('name'), $expr->getTemplateLine()), $name, $arguments, $expr->getTemplateLine());
            $node->setHasCallParentheses(Template::METHOD_CALL === $type);

            return $node;
        }

        return new GetAttrExpression($expr, $attribute, $arguments, $type, $lineno, $nullSafe);
    }

    public function getName(): string
    {
        return '.';
    }

    public function getAliases(): array
    {
        return ['?.'];
    }

    public function getDescription(): string
    {
        return 'Get an attribute on a variable';
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
