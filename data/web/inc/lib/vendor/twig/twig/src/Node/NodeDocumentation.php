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

use Twig\Token;

final class NodeDocumentation
{
    public static function add(Node $node, Token ...$tokens): void
    {
        $documentation = [];
        foreach ($tokens as $token) {
            if (null !== $comment = $token->getDocumentation()) {
                $documentation[] = $comment;
            }
        }
        if (null !== $comment = $node->getDocumentation()) {
            $documentation[] = $comment;
        }

        if ($documentation) {
            $node->setDocumentation(implode("\n", $documentation));
        }
    }

    public static function move(Node $source, Node $target): void
    {
        if (null !== $documentation = $source->getDocumentation()) {
            self::prepend($target, $documentation);
            $source->setDocumentation(null);
        }
    }

    private static function prepend(Node $node, string $documentation): void
    {
        if (null !== $currentDocumentation = $node->getDocumentation()) {
            $documentation .= "\n".$currentDocumentation;
        }

        $node->setDocumentation($documentation);
    }
}
