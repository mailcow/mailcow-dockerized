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

use Twig\Node\Expression\Variable\AssignContextVariable;
use Twig\Node\ForElseNode;
use Twig\Node\ForNode;
use Twig\Node\Node;
use Twig\Token;

/**
 * Loops over each item of a sequence.
 *
 *   <ul>
 *    {% for user in users %}
 *      <li>{{ user.username|e }}</li>
 *    {% endfor %}
 *   </ul>
 *
 * @internal
 */
final class ForTokenParser extends AbstractTokenParser
{
    public function parse(Token $token): Node
    {
        $lineno = $token->getLine();
        $stream = $this->parser->getStream();
        $targets = $this->parseAssignmentExpression();
        $stream->expect(Token::OPERATOR_TYPE, 'in');
        $seq = $this->parser->parseExpression();

        $stream->expect(Token::BLOCK_END_TYPE);
        $body = $this->parser->subparse([$this, 'decideForFork']);
        if ('else' == $stream->next()->getValue()) {
            $elseLineno = $stream->getCurrent()->getLine();
            $stream->expect(Token::BLOCK_END_TYPE);
            $else = new ForElseNode($this->parser->subparse([$this, 'decideForEnd'], true), $elseLineno);
        } else {
            $else = null;
        }
        $stream->expect(Token::BLOCK_END_TYPE);

        if (\count($targets) > 1) {
            $keyTarget = $targets->getNode('0');
            $valueTarget = $targets->getNode('1');
        } else {
            $keyTarget = new AssignContextVariable('_key', $lineno);
            $valueTarget = $targets->getNode('0');
        }

        return new ForNode($keyTarget, $valueTarget, $seq, null, $body, $else, $lineno);
    }

    public function decideForFork(Token $token): bool
    {
        return $token->test(['else', 'endfor']);
    }

    public function decideForEnd(Token $token): bool
    {
        return $token->test('endfor');
    }

    public function getTag(): string
    {
        return 'for';
    }
}
