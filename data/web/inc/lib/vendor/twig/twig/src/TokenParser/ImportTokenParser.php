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

use Twig\Node\Expression\Variable\AssignMacroVariable;
use Twig\Node\Expression\Variable\MacroVariable;
use Twig\Node\ImportNode;
use Twig\Node\Node;
use Twig\Token;

/**
 * Imports macros.
 *
 *   {% import 'forms.html.twig' as forms %}
 *
 * @internal
 */
final class ImportTokenParser extends AbstractTokenParser
{
    public function parse(Token $token): Node
    {
        $macro = $this->parser->parseExpression();
        $this->parser->getStream()->expect(Token::NAME_TYPE, 'as');
        $name = $this->parser->getStream()->expect(Token::NAME_TYPE)->getValue();
        $var = new AssignMacroVariable(new MacroVariable($name, $token->getLine()), $this->parser->isMainScope());
        $this->parser->getStream()->expect(Token::BLOCK_END_TYPE);
        $this->parser->addImportedSymbol('template', $name);

        return new ImportNode($macro, $var, $token->getLine());
    }

    public function getTag(): string
    {
        return 'import';
    }
}
