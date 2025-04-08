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
use Twig\Node\TypesNode;
use Twig\Token;
use Twig\TokenStream;

/**
 * Declare variable types.
 *
 *  {% types {foo: 'int', bar?: 'string'} %}
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

        $types = $this->parseSimpleMappingExpression($stream);

        $stream->expect(Token::BLOCK_END_TYPE);

        return new TypesNode($types, $token->getLine());
    }

    /**
     * @return array<string, array{type: string, optional: bool}>
     *
     * @throws SyntaxError
     */
    private function parseSimpleMappingExpression(TokenStream $stream): array
    {
        $stream->expect(Token::PUNCTUATION_TYPE, '{', 'A mapping element was expected');

        $types = [];

        $first = true;
        while (!$stream->test(Token::PUNCTUATION_TYPE, '}')) {
            if (!$first) {
                $stream->expect(Token::PUNCTUATION_TYPE, ',', 'A type string must be followed by a comma');

                // trailing ,?
                if ($stream->test(Token::PUNCTUATION_TYPE, '}')) {
                    break;
                }
            }
            $first = false;

            $nameToken = $stream->expect(Token::NAME_TYPE);
            $isOptional = null !== $stream->nextIf(Token::PUNCTUATION_TYPE, '?');

            $stream->expect(Token::PUNCTUATION_TYPE, ':', 'A type name must be followed by a colon (:)');

            $valueToken = $stream->expect(Token::STRING_TYPE);

            $types[$nameToken->getValue()] = [
                'type' => $valueToken->getValue(),
                'optional' => $isOptional,
            ];
        }
        $stream->expect(Token::PUNCTUATION_TYPE, '}', 'An opened mapping is not properly closed');

        return $types;
    }

    public function getTag(): string
    {
        return 'types';
    }
}
