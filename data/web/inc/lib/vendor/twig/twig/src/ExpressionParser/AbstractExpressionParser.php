<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Twig\ExpressionParser;

abstract class AbstractExpressionParser implements ExpressionParserInterface
{
    public function __toString(): string
    {
        return \sprintf('%s(%s)', ExpressionParserType::getType($this)->value, $this->getName());
    }

    public function getPrecedenceChange(): ?PrecedenceChange
    {
        return null;
    }

    public function getAliases(): array
    {
        return [];
    }

    public function getOperatorTokens(): array
    {
        return [$this->getName(), ...$this->getAliases()];
    }
}
