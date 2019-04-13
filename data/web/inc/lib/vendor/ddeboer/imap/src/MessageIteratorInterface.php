<?php

declare(strict_types=1);

namespace Ddeboer\Imap;

interface MessageIteratorInterface extends \Iterator
{
    /**
     * Get current message.
     *
     * @return MessageInterface
     */
    public function current(): MessageInterface;
}
