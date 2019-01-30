<?php

declare(strict_types=1);

namespace Ddeboer\Imap;

use DateTimeInterface;
use Ddeboer\Imap\Exception\InvalidSearchCriteriaException;
use Ddeboer\Imap\Exception\MessageCopyException;
use Ddeboer\Imap\Exception\MessageMoveException;
use Ddeboer\Imap\Search\ConditionInterface;
use Ddeboer\Imap\Search\LogicalOperator\All;

/**
 * An IMAP mailbox (commonly referred to as a 'folder').
 */
final class Mailbox implements MailboxInterface
{
    /**
     * @var ImapResourceInterface
     */
    private $resource;

    /**
     * @var string
     */
    private $name;

    /**
     * @var \stdClass
     */
    private $info;

    /**
     * Constructor.
     *
     * @param ImapResourceInterface $resource IMAP resource
     * @param string                $name     Mailbox decoded name
     * @param \stdClass             $info     Mailbox info
     */
    public function __construct(ImapResourceInterface $resource, string $name, \stdClass $info)
    {
        $this->resource = new ImapResource($resource->getStream(), $this);
        $this->name = $name;
        $this->info = $info;
    }

    /**
     * Get mailbox decoded name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get mailbox encoded path.
     *
     * @return string
     */
    public function getEncodedName(): string
    {
        return \preg_replace('/^{.+}/', '', $this->info->name);
    }

    /**
     * Get mailbox encoded full name.
     *
     * @return string
     */
    public function getFullEncodedName(): string
    {
        return $this->info->name;
    }

    /**
     * Get mailbox attributes.
     *
     * @return int
     */
    public function getAttributes(): int
    {
        return $this->info->attributes;
    }

    /**
     * Get mailbox delimiter.
     *
     * @return string
     */
    public function getDelimiter(): string
    {
        return $this->info->delimiter;
    }

    /**
     * Get number of messages in this mailbox.
     *
     * @return int
     */
    public function count()
    {
        return \imap_num_msg($this->resource->getStream());
    }

    /**
     * Get Mailbox status.
     *
     * @param null|int $flags
     *
     * @return \stdClass
     */
    public function getStatus(int $flags = null): \stdClass
    {
        return \imap_status($this->resource->getStream(), $this->getFullEncodedName(), $flags ?? \SA_ALL);
    }

    /**
     * Bulk Set Flag for Messages.
     *
     * @param string                       $flag    \Seen, \Answered, \Flagged, \Deleted, and \Draft
     * @param array|MessageIterator|string $numbers Message numbers
     *
     * @return bool
     */
    public function setFlag(string $flag, $numbers): bool
    {
        return \imap_setflag_full($this->resource->getStream(), $this->prepareMessageIds($numbers), $flag, \ST_UID);
    }

    /**
     * Bulk Clear Flag for Messages.
     *
     * @param string                       $flag    \Seen, \Answered, \Flagged, \Deleted, and \Draft
     * @param array|MessageIterator|string $numbers Message numbers
     *
     * @return bool
     */
    public function clearFlag(string $flag, $numbers): bool
    {
        return \imap_clearflag_full($this->resource->getStream(), $this->prepareMessageIds($numbers), $flag, \ST_UID);
    }

    /**
     * Get message ids.
     *
     * @param ConditionInterface $search Search expression (optional)
     *
     * @return MessageIteratorInterface
     */
    public function getMessages(ConditionInterface $search = null, int $sortCriteria = null, bool $descending = false): MessageIteratorInterface
    {
        if (null === $search) {
            $search = new All();
        }
        $query = $search->toString();

        // We need to clear the stack to know whether imap_last_error()
        // is related to this imap_search
        \imap_errors();

        if (null !== $sortCriteria) {
            $messageNumbers = \imap_sort($this->resource->getStream(), $sortCriteria, $descending ? 1 : 0, \SE_UID, $query);
        } else {
            $messageNumbers = \imap_search($this->resource->getStream(), $query, \SE_UID);
        }
        if (false === $messageNumbers) {
            if (false !== \imap_last_error()) {
                throw new InvalidSearchCriteriaException(\sprintf('Invalid search criteria [%s]', $query));
            }

            // imap_search can also return false
            $messageNumbers = [];
        }

        return new MessageIterator($this->resource, $messageNumbers);
    }

    /**
     * Get message iterator for a sequence.
     *
     * @param string $sequence Message numbers
     *
     * @return MessageIteratorInterface
     */
    public function getMessageSequence(string $sequence): MessageIteratorInterface
    {
        \imap_errors();

        $overview = \imap_fetch_overview($this->resource->getStream(), $sequence, FT_UID);
        if (empty($overview)) {
            if (false !== \imap_last_error()) {
                throw new InvalidSearchCriteriaException(\sprintf('Invalid sequence [%s]', $sequence));
            }

            $messageNumbers = [];
        } else {
            $messageNumbers = \array_column($overview, 'uid');
        }

        return new MessageIterator($this->resource, $messageNumbers);
    }

    /**
     * Get a message by message number.
     *
     * @param int $number Message number
     *
     * @return MessageInterface
     */
    public function getMessage(int $number): MessageInterface
    {
        return new Message($this->resource, $number);
    }

    /**
     * Get messages in this mailbox.
     *
     * @return MessageIteratorInterface
     */
    public function getIterator(): MessageIteratorInterface
    {
        return $this->getMessages();
    }

    /**
     * Add a message to the mailbox.
     *
     * @param string                 $message
     * @param null|string            $options
     * @param null|DateTimeInterface $internalDate
     *
     * @return bool
     */
    public function addMessage(string $message, string $options = null, DateTimeInterface $internalDate = null): bool
    {
        $arguments = [
            $this->resource->getStream(),
            $this->getFullEncodedName(),
            $message,
        ];
        if (null !== $options) {
            $arguments[] = $options;
            if (null !== $internalDate) {
                $arguments[] = $internalDate->format('d-M-Y H:i:s O');
            }
        }

        return \imap_append(...$arguments);
    }

    /**
     * Returns a tree of threaded message for the current Mailbox.
     *
     * @return array
     */
    public function getThread(): array
    {
        \set_error_handler(function () {});

        $tree = \imap_thread($this->resource->getStream());

        \restore_error_handler();

        return false !== $tree ? $tree : [];
    }

    /**
     * Bulk move messages.
     *
     * @param array|MessageIterator|string $numbers Message numbers
     * @param MailboxInterface             $mailbox Destination Mailbox to move the messages to
     *
     * @throws \Ddeboer\Imap\Exception\MessageMoveException
     */
    public function move($numbers, MailboxInterface $mailbox): void
    {
        if (!\imap_mail_move($this->resource->getStream(), $this->prepareMessageIds($numbers), $mailbox->getEncodedName(), \CP_UID)) {
            throw new MessageMoveException(\sprintf('Messages cannot be moved to "%s"', $mailbox->getName()));
        }
    }

    /**
     * Bulk copy messages.
     *
     * @param array|MessageIterator|string $numbers Message numbers
     * @param MailboxInterface             $mailbox Destination Mailbox to copy the messages to
     *
     * @throws \Ddeboer\Imap\Exception\MessageCopyException
     */
    public function copy($numbers, MailboxInterface $mailbox): void
    {
        if (!\imap_mail_copy($this->resource->getStream(), $this->prepareMessageIds($numbers), $mailbox->getEncodedName(), \CP_UID)) {
            throw new MessageCopyException(\sprintf('Messages cannot be copied to "%s"', $mailbox->getName()));
        }
    }

    /**
     * Prepare message ids for the use with bulk functions.
     *
     * @param array|MessageIterator|string $messageIds Message numbers
     *
     * @return string
     */
    private function prepareMessageIds($messageIds): string
    {
        if ($messageIds instanceof MessageIterator) {
            $messageIds = $messageIds->getArrayCopy();
        }

        if (\is_array($messageIds)) {
            $messageIds = \implode(',', $messageIds);
        }

        return (string) $messageIds;
    }
}
