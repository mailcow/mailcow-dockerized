<?php

declare(strict_types=1);

namespace Ddeboer\Imap;

use Ddeboer\Imap\Message\PartInterface;

/**
 * @extends \ArrayIterator<int, MessageInterface>
 */
final class MessageIterator extends \ArrayIterator implements MessageIteratorInterface
{
    private ImapResourceInterface $resource;

    /**
     * Constructor.
     *
     * @param ImapResourceInterface $resource       IMAP resource
     * @param int[]                 $messageNumbers Array of message numbers
     */
    public function __construct(ImapResourceInterface $resource, array $messageNumbers)
    {
        $this->resource = $resource;

        parent::__construct($messageNumbers);
    }

    /**
     * Get current message.
     *
     * @return MessageInterface<PartInterface>
     */
    public function current(): MessageInterface
    {
        $current = parent::current();
        if (!\is_int($current)) {
            throw new Exception\OutOfBoundsException(\sprintf(
                'The current value "%s" isn\'t an integer and doesn\'t represent a message;'
                . ' try to cycle this "%s" with a native php function like foreach or with the method getArrayCopy(),'
                . ' or check it by calling the methods valid().',
                \is_object($current) ? \get_class($current) : \gettype($current),
                static::class
            ));
        }

        return new Message($this->resource, $current);
    }
}
