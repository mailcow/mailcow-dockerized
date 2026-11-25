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

namespace Twig\TokenParser;

use Twig\Node\Expression\AbstractExpression;
use Twig\Node\IncludeNode;
use Twig\Node\Node;
use Twig\Token;

/**
 * Includes a template.
 *
 *   {% include 'header.html.twig' %}
 *     Body
 *   {% include 'footer.html.twig' %}
 *
 * @internal
 */
class IncludeTokenParser extends AbstractTokenParser
{
    public function parse(Token $token): Node
    {
        $expr = $this->parser->parseExpression();

        [$variables, $only, $ignoreMissing] = $this->parseArguments();

        return new IncludeNode($expr, $variables, $only, $ignoreMissing, $token->getLine());
    }

    /**
     * @return array{0: ?AbstractExpression, 1: bool, 2: bool}
     */
    protected function parseArguments()
    {
        $stream = $this->parser->getStream();

        $ignoreMissing = false;
        if ($stream->nextIf(Token::NAME_TYPE, 'ignore')) {
            $stream->expect(Token::NAME_TYPE, 'missing');

            $ignoreMissing = true;
        }

        $variables = null;
        if ($stream->nextIf(Token::NAME_TYPE, 'with')) {
            $variables = $this->parser->parseExpression();
        }

        $only = false;
        if ($stream->nextIf(Token::NAME_TYPE, 'only')) {
            $only = true;
        }

        $stream->expect(Token::BLOCK_END_TYPE);

        return [$variables, $only, $ignoreMissing];
    }

    public function getTag(): string
    {
        return 'include';
    }
}
