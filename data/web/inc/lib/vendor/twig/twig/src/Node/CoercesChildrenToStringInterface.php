<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Twig\Node;

use Twig\Node\Expression\OperatorEscapeInterface;

/**
 * Implemented by nodes that implicitly coerce one or more of their child
 * nodes to string at runtime (PHP string casts, regex matching, comparisons,
 * range bounds, template-name resolution by the loader, etc.).
 *
 * The sandbox node visitor wraps the listed children with a CheckToStringNode
 * so that an implicit `__toString()` call goes through the sandbox policy
 * check, independently of where this node's result is used.
 *
 * This is distinct from {@see OperatorEscapeInterface}, which describes
 * operands whose value becomes this expression's value (passthrough operators
 * like ternaries) and is consumed by the escaper.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface CoercesChildrenToStringInterface
{
    /**
     * Returns the names of the child nodes that will be coerced to
     * string when this node is evaluated.
     *
     * @return string[]
     */
    public function getStringCoercedChildNames(): array;
}
