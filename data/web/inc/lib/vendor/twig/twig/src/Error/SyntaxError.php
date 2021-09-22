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

namespace Twig\Error;

/**
 * \Exception thrown when a syntax error occurs during lexing or parsing of a template.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class SyntaxError extends Error
{
    /**
     * Tweaks the error message to include suggestions.
     *
     * @param string $name  The original name of the item that does not exist
     * @param array  $items An array of possible items
     */
    public function addSuggestions(string $name, array $items): void
    {
        $alternatives = [];
        foreach ($items as $item) {
            $lev = levenshtein($name, $item);
            if ($lev <= \strlen($name) / 3 || false !== strpos($item, $name)) {
                $alternatives[$item] = $lev;
            }
        }

        if (!$alternatives) {
            return;
        }

        asort($alternatives);

        $this->appendMessage(sprintf(' Did you mean "%s"?', implode('", "', array_keys($alternatives))));
    }
}
