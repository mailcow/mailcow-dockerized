<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Twig\TokenParser;

use Twig\Error\SyntaxError;
use Twig\Node\Node;
use Twig\Node\NodeDocumentation;
use Twig\Node\TypeNode;
use Twig\Node\TypesNode;
use Twig\Token;
use Twig\TokenStream;

/**
 * Declare variable types.
 *
 *  {% types {foo: 'number', bar?: 'string'} %}
 *
 * @author Jeroen Versteeg <jeroen@alisqi.com>
 *
 * @internal
 */
final class TypesTokenParser extends AbstractTokenParser
{
    public function parse(Token $token): Node
    {
        $stream = $this->parser->getStream();
        [$types, $nodes] = $this->parseSimpleMappingExpression($stream);
        $stream->expect(Token::BLOCK_END_TYPE);

        $node = new TypesNode($types, $token->getLine());
        foreach ($nodes as $name => $type) {
            $node->setNode($name, $type);
        }

        return $node;
    }

    /**
     * @return array{array<string, array{type: string, optional: bool}>, array<string, TypeNode>}
     *
     * @throws SyntaxError
     */
    private function parseSimpleMappingExpression(TokenStream $stream): array
    {
        $enclosed = null !== $stream->nextIf(Token::PUNCTUATION_TYPE, '{');
        $types = [];
        $nodes = [];
        $first = true;
        while (!($stream->test(Token::PUNCTUATION_TYPE, '}') || $stream->test(Token::BLOCK_END_TYPE))) {
            if (!$first) {
                $stream->expect(Token::PUNCTUATION_TYPE, ',', 'A type string must be followed by a comma');

                // trailing ,?
                if ($stream->test(Token::PUNCTUATION_TYPE, '}') || $stream->test(Token::BLOCK_END_TYPE)) {
                    break;
                }
            }
            $first = false;

            $nameToken = $stream->expect(Token::NAME_TYPE);

            if ($stream->nextIf(Token::OPERATOR_TYPE, '?:')) {
                $isOptional = true;
            } else {
                $isOptional = null !== $stream->nextIf(Token::OPERATOR_TYPE, '?');
                $stream->expect(Token::PUNCTUATION_TYPE, ':', 'A type name must be followed by a colon (:)');
            }

            $valueToken = $stream->expect(Token::STRING_TYPE);

            $types[$nameToken->getValue()] = [
                'type' => $valueToken->getValue(),
                'optional' => $isOptional,
            ];
            $node = new TypeNode($nameToken->getValue(), $valueToken->getValue(), $isOptional, $nameToken->getLine());
            NodeDocumentation::add($node, $nameToken);
            $nodes[$nameToken->getValue()] = $node;
        }

        if ($enclosed) {
            $stream->expect(Token::PUNCTUATION_TYPE, '}', 'An opened mapping is not properly closed');
        }

        return [$types, $nodes];
    }

    public function getTag(): string
    {
        return 'types';
    }
}
