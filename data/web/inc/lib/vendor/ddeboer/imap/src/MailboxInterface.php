<?php

declare(strict_types=1);

namespace Ddeboer\Imap;

use DateTimeInterface;
use Ddeboer\Imap\Search\ConditionInterface;

/**
 * An IMAP mailbox (commonly referred to as a 'folder').
 */
interface MailboxInterface extends \Countable, \IteratorAggregate
{
    /**
     * Get mailbox decoded name.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get mailbox encoded path.
     *
     * @return string
     */
    public function getEncodedName(): string;

    /**
     * Get mailbox encoded full name.
     *
     * @return string
     */
    public function getFullEncodedName(): string;

    /**
     * Get mailbox attributes.
     *
     * @return int
     */
    public function getAttributes(): int;

    /**
     * Get mailbox delimiter.
     *
     * @return string
     */
    public function getDelimiter(): string;

    /**
     * Get Mailbox status.
     *
     * @param null|int $flags
     *
     * @return \stdClass
     */
    public function getStatus(int $flags = null): \stdClass;

    /**
     * Bulk Set Flag for Messages.
     *
     * @param string                       $flag    \Seen, \Answered, \Flagged, \Deleted, and \Draft
     * @param array|MessageIterator|string $numbers Message numbers
     *
     * @return bool
     */
    public function setFlag(string $flag, $numbers): bool;

    /**
     * Bulk Clear Flag for Messages.
     *
     * @param string                       $flag    \Seen, \Answered, \Flagged, \Deleted, and \Draft
     * @param array|MessageIterator|string $numbers Message numbers
     *
     * @return bool
     */
    public function clearFlag(string $flag, $numbers): bool;

    /**
     * Get message ids.
     *
     * @param ConditionInterface $search Search expression (optional)
     *
     * @return MessageIteratorInterface
     */
    public function getMessages(ConditionInterface $search = null, int $sortCriteria = null, bool $descending = false): MessageIteratorInterface;

    /**
     * Get message iterator for a sequence.
     *
     * @param string $sequence Message numbers
     *
     * @return MessageIteratorInterface
     */
    public function getMessageSequence(string $sequence): MessageIteratorInterface;

    /**
     * Get a message by message number.
     *
     * @param int $number Message number
     *
     * @return MessageInterface
     */
    public function getMessage(int $number): MessageInterface;

    /**
     * Get messages in this mailbox.
     *
     * @return MessageIteratorInterface
     */
    public function getIterator(): MessageIteratorInterface;

    /**
     * Add a message to the mailbox.
     *
     * @param string                 $message
     * @param null|string            $options
     * @param null|DateTimeInterface $internalDate
     *
     * @return bool
     */
    public function addMessage(string $message, string $options = null, DateTimeInterface $internalDate = null): bool;

    /**
     * Returns a tree of threaded message for the current Mailbox.
     *
     * @return array
     */
    public function getThread(): array;

    /**
     * Bulk move messages.
     *
     * @param array|MessageIterator|string $numbers Message numbers
     * @param MailboxInterface             $mailbox Destination Mailbox to move the messages to
     *
     * @throws \Ddeboer\Imap\Exception\MessageMoveException
     */
    public function move($numbers, self $mailbox): void;

    /**
     * Bulk copy messages.
     *
     * @param array|MessageIterator|string $numbers Message numbers
     * @param MailboxInterface             $mailbox Destination Mailbox to copy the messages to
     *
     * @throws \Ddeboer\Imap\Exception\MessageCopyException
     */
    public function copy($numbers, self $mailbox): void;
}
