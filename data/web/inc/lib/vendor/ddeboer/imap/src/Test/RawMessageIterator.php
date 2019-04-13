<?php

declare(strict_types=1);

namespace Ddeboer\Imap\Test;

use Ddeboer\Imap\MessageInterface;
use Ddeboer\Imap\MessageIteratorInterface;

/**
 * A MessageIterator to be used in a mocked environment.
 */
final class RawMessageIterator extends \ArrayIterator implements MessageIteratorInterface
{
    public function current(): MessageInterface
    {
        return parent::current();
    }
}
