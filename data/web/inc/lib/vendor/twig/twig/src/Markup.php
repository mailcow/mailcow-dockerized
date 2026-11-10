<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Twig;

/**
 * Marks a content as safe.
 *
 * Instances of this class (and existing subclasses) are trusted by the Twig
 * sandbox: method calls and property accesses on a Markup instance bypass the
 * SecurityPolicy method/property allowlists. This is by design: Markup
 * represents content that has already been deemed safe to output.
 *
 * This class is considered final as of Twig 3.28 and will be final in Twig
 * 4.0.
 *
 * @final since Twig 3.28
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Markup implements \Countable, \JsonSerializable, \Stringable
{
    private $content;
    private ?string $charset;

    public function __construct($content, $charset)
    {
        $this->content = (string) $content;
        $this->charset = $charset;
    }

    public function __toString(): string
    {
        return $this->content;
    }

    public function getCharset(): string
    {
        return $this->charset;
    }

    /**
     * @return int
     */
    #[\ReturnTypeWillChange]
    public function count()
    {
        return mb_strlen($this->content, $this->charset);
    }

    /**
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->content;
    }
}
